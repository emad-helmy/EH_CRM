<?php
/*
=============================================================================
 CRM — boudy-elhegaz  |  crm_csv.php
-----------------------------------------------------------------------------
 مكتبة استيراد CSV (الخطوة 3):
   - تحليل الملف المرفوع + التحقق من كل صف
   - إنشاء دفعة استيراد (eh_crm_import_batches) + صفوف المراجعة (eh_crm_import_staging)
   - تأكيد الدفعة: نقل صفوف "ok" إلى eh_crm_leads + توزيع تلقائي على المبيعات
=============================================================================
*/

/* يسمح لو محمّل من crm_boot (الأندبوينتس) أو من صفحة واجهة حمّلت crm_func قبلك */
if (!defined('CRM_BOOT_LOADED') && !function_exists('crm_db')) {
    die('Direct access not allowed.');
}

/* ----------------------------- القالب ----------------------------- */
function crm_csv_template_columns()
{
    return [
        'phone'   => crm_t('CRM_COMMON_PHONE_CSV'),
        'name'    => crm_t('CRM_COMMON_NAME'),
        'city'    => crm_t('CRM_COMMON_CITY'),
        'project' => crm_t('CRM_COMMON_PROJECT'),
        'source'  => crm_t('CRM_COMMON_SOURCE'),
        'message' => crm_t('CRM_COMMON_MESSAGE'),
        'notes'   => crm_t('CRM_COMMON_NOTES'),
    ];
}

/* تحليل ملف CSV مرفوع إلى مصفوفة صفوف (مفتاح = عنوان العمود) */
function crm_csv_parse_uploaded($tmpName)
{
    $rows = [];
    if (($handle = fopen($tmpName, 'r')) === false) {
        return $rows;
    }
    $header = fgetcsv($handle, 0, ',');
    if ($header === false) {
        fclose($handle);
        return $rows;
    }
    $header = array_map(function ($h) {
        $h = trim((string) $h);
        /* Excel "CSV UTF-8" بينزّل الملف بـBOM في أول عنوان — نشيله عشان العنوان يتطابق */
        if (strncmp($h, "\xEF\xBB\xBF", 3) === 0) {
            $h = substr($h, 3);
        }
        return $h;
    }, $header);

    /* خريطة العناوين العربية -> مفاتيح النظام الداخلية (ويدعم أعمدة إنجليزية بأي حالة) */
    $columns = crm_csv_template_columns();
    $alias   = array_flip($columns); // ['رقم التليفون'=>'phone', 'اسم العميل'=>'name', ...]
    $keys    = array_keys($columns);
    foreach ($header as $i => $col) {
        if (isset($alias[$col])) {
            $header[$i] = $alias[$col];
        } elseif (in_array(strtolower($col), $keys, true)) {
            $header[$i] = strtolower($col); // PHONE / Phone / phone
        }
    }

    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        // skip fully blank lines
        if (count($row) === 1 && trim($row[0]) === '') {
            continue;
        }
        $item = [];
        foreach ($header as $i => $col) {
            $item[$col] = isset($row[$i]) ? $row[$i] : '';
        }
        $rows[] = $item;
    }
    fclose($handle);
    return $rows;
}

/* إنشاء دفعة استيراد + صفوف المراجعة */
function crm_csv_create_batch($userId, $fileName, array $rows)
{
    $db  = crm_db();
    $now = crm_now();

    /* أي دفعات معلّقة قديمة بتتلغي — الملف الجديد بياخد مكانها */
    $db->query(
        "UPDATE eh_crm_import_batches SET status = 'cancelled'
          WHERE user_id = ? AND status = 'pending'",
        [(int) $userId]
    );

    $db->insert('eh_crm_import_batches', [
        'user_id'    => (int) $userId,
        'method'     => 'csv',
        'file_name'  => $fileName,
        'total_rows' => count($rows),
        'inserted'   => 0,
        'dup_db'     => 0,
        'dup_file'   => 0,
        'invalid'    => 0,
        'status'     => 'pending',
        'created_at' => $now,
    ]);
    $batchId = (int) $db->query("SELECT LAST_INSERT_ID() AS id")->first()->id;

    /* أرقام موجودة بالفعل في الداتابيز (تفحص فردي لكل رقم لتجنّب لبس الربط في IN clause) */
    $existingPhones = [];
    $allNorms = [];
    foreach ($rows as $row) {
        $norm = crm_norm_phone($row['phone'] ?? '');
        if ($norm !== '') {
            $allNorms[$norm] = true;
        }
    }
    foreach (array_keys($allNorms) as $norm) {
        $exists = crm_db()->query("SELECT id FROM eh_crm_leads WHERE phone = ? LIMIT 1", [$norm])->first();
        if ($exists) {
            $existingPhones[$norm] = true;
        }
    }

    $seenInFile = [];
    foreach ($rows as $idx => $row) {
        $rowNo    = $idx + 2; // header + 1-based
        $rawPhone = trim((string) ($row['phone'] ?? ''));
        $norm     = crm_norm_phone($rawPhone);
        $status   = 'ok';
        $msg      = '';

        if ($rawPhone === '') {
            $status = 'invalid';
            $msg    = crm_t('CRM_CSV_PHONE_EMPTY');
        } elseif (!crm_phone_valid($norm)) {
            $status = 'invalid';
            $msg    = crm_t('CRM_CSV_PHONE_INVALID');
        } elseif (isset($existingPhones[$norm])) {
            $status = 'dup_db';
            $msg    = crm_t('CRM_CSV_PHONE_IN_DB');
        } elseif (isset($seenInFile[$norm])) {
            $status = 'dup_file';
            $msg    = crm_t('CRM_CSV_PHONE_IN_FILE2');
        } else {
            $seenInFile[$norm] = true;
        }

        $db->insert('eh_crm_import_staging', [
            'batch_id'   => $batchId,
            'row_no'     => $rowNo,
            'phone_raw'  => $rawPhone,
            'phone_norm' => $norm,
            'name'       => trim((string) ($row['name'] ?? '')),
            'city'       => trim((string) ($row['city'] ?? '')),
            'project'    => trim((string) ($row['project'] ?? '')),
            'source'     => trim((string) ($row['source'] ?? '')),
            'message'    => trim((string) ($row['message'] ?? '')),
            'notes'      => trim((string) ($row['notes'] ?? '')),
            'row_status' => $status,
            'row_msg'    => $msg,
            'created_at' => $now,
        ]);
    }

    /* ملخص الحالات */
    $agg = $db->query(
        "SELECT row_status, COUNT(*) AS c FROM eh_crm_import_staging WHERE batch_id = ? GROUP BY row_status",
        [$batchId]
    )->results();
    $counts = ['total' => count($rows), 'ok' => 0, 'invalid' => 0, 'dup_db' => 0, 'dup_file' => 0];
    foreach ($agg as $r) {
        $k = (string) $r->row_status;
        if (array_key_exists($k, $counts)) {
            $counts[$k] = (int) $r->c;
        }
    }

    return [
        'batch_id' => $batchId,
        'counts'   => $counts,
    ];
}

/* آخر دفعة pending لليوزر (الصفحة بترجع تكمّل عليها بعد أي refresh) */
function crm_csv_latest_pending($userId)
{
    $row = crm_db()->query(
        "SELECT id FROM eh_crm_import_batches
          WHERE user_id = ? AND status = 'pending'
          ORDER BY id DESC LIMIT 1",
        [(int) $userId]
    )->first();
    return $row ? (int) $row->id : null;
}

/* صفوف دفعة للمراجعة (المالك فقط يقدر يشوف دفعته) */
function crm_csv_get_staging($batchId, $userId)
{
    $batchId = (int) $batchId;
    $batch   = crm_db()->query("SELECT * FROM eh_crm_import_batches WHERE id = ?", [$batchId])->first();
    if (!$batch || (int) $batch->user_id !== (int) $userId) {
        return ['batch' => null, 'rows' => []];
    }
    $rows = crm_db()->query(
        "SELECT * FROM eh_crm_import_staging WHERE batch_id = ? ORDER BY row_no ASC",
        [$batchId]
    )->results();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'         => (int) $r->id,
            'row_no'     => (int) $r->row_no,
            'phone_raw'  => (string) $r->phone_raw,
            'phone_norm' => (string) $r->phone_norm,
            'name'       => (string) $r->name,
            'city'       => (string) $r->city,
            'project'    => (string) $r->project,
            'source'     => (string) $r->source,
            'message'    => (string) $r->message,
            'notes'      => (string) $r->notes,
            'row_status' => (string) $r->row_status,
            'row_msg'    => (string) $r->row_msg,
        ];
    }
    return ['batch' => $batch, 'rows' => $out];
}

/* دفعات استيراد اليوزر (للجدول في batches.php) */
function crm_csv_get_batches($userId, $limit = 50)
{
    /* ملاحظة مهمة: الـLIMIT لازم يتدمج كرقم في الـSQL مش يتربط كـparam —
       كلاس DB في UserSpice بيربط كل القيم نص، وLIMIT '50' خطأ SQL بيرجّع نتيجة فاضية */
    $rows = crm_db()->query(
        "SELECT id, file_name, total_rows, inserted, dup_db, dup_file, invalid,
                status, created_at, committed_at
           FROM eh_crm_import_batches
          WHERE user_id = ?
          ORDER BY id DESC
          LIMIT " . (int) $limit,
        [(int) $userId]
    )->results();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'          => (int) $r->id,
            'file_name'   => (string) $r->file_name,
            'total_rows'  => (int) $r->total_rows,
            'inserted'    => (int) $r->inserted,
            'dup_db'      => (int) $r->dup_db,
            'dup_file'    => (int) $r->dup_file,
            'invalid'     => (int) $r->invalid,
            'status'      => (string) $r->status,
            'created_at'  => (string) $r->created_at,
            'committed_at'=> (string) $r->committed_at,
        ];
    }
    return $out;
}

/* تحديد صف كمستبعد (حتى لو كان ok) */
function crm_csv_exclude_row($rowId, $userId)
{
    $db   = crm_db();
    $row  = $db->query(
        "SELECT s.id, s.batch_id, s.row_status, b.user_id
           FROM eh_crm_import_staging s
           JOIN eh_crm_import_batches b ON b.id = s.batch_id
          WHERE s.id = ?", [(int) $rowId]
    )->first();
    if (!$row || (int) $row->user_id !== (int) $userId) {
        crm_err(crm_t('CRM_ERR_ROW_NOT_OWN'), null, 403);
    }
    $db->update('eh_crm_import_staging', (int) $rowId, ['row_status' => 'excluded']);
    crm_ok(crm_t('CRM_MSG_ROW_EXCLUDED'));
}

/* تعديل صف في منطقة المراجعة */
function crm_csv_edit_row($rowId, array $fields, $userId)
{
    $db   = crm_db();
    $row  = $db->query(
        "SELECT s.id, s.batch_id, b.user_id
           FROM eh_crm_import_staging s
           JOIN eh_crm_import_batches b ON b.id = s.batch_id
          WHERE s.id = ?", [(int) $rowId]
    )->first();
    if (!$row || (int) $row->user_id !== (int) $userId) {
        crm_err(crm_t('CRM_ERR_ROW_NOT_OWN'), null, 403);
    }

    $allowed = ['phone_raw', 'name', 'city', 'project', 'source', 'message', 'notes'];
    $update  = [];
    foreach ($allowed as $k) {
        if (array_key_exists($k, $fields)) {
            $update[$k] = trim((string) $fields[$k]);
        }
    }
    if (isset($fields['phone_raw'])) {
        $norm = crm_norm_phone($fields['phone_raw']);
        $update['phone_norm'] = $norm;
        $update['row_status'] = $norm !== '' && crm_phone_valid($norm) ? 'ok' : 'invalid';
        $update['row_msg']    = $update['row_status'] === 'ok' ? '' : crm_t('CRM_CSV_PHONE_INVALID');
    }
    if (empty($update)) {
        crm_err(crm_t('CRM_ERR_NO_EDIT'), null, 400);
    }
    $db->update('eh_crm_import_staging', (int) $rowId, $update);
    crm_ok(crm_t('CRM_MSG_ROW_UPDATED'));
}

/* تأكيد الدفعة: نقل صفوف "ok" إلى eh_crm_leads + توزيع تلقائي */
function crm_csv_confirm_batch($batchId, $userId)
{
    $db   = crm_db();
    $now  = crm_now();
    $batchId = (int) $batchId;

    $batch = $db->query("SELECT * FROM eh_crm_import_batches WHERE id = ?", [$batchId])->first();
    if (!$batch) {
        crm_err(crm_t('CRM_ERR_BATCH_NOT_FOUND'), null, 404);
    }
    if ((int) $batch->user_id !== (int) $userId) {
        crm_err(crm_t('CRM_ERR_BATCH_NOT_OWN'), null, 403);
    }
    if ($batch->status !== 'pending') {
        crm_err(crm_t('CRM_ERR_BATCH_DONE') . ' (' . $batch->status . ')', null, 400);
    }

    $rows = $db->query(
        "SELECT * FROM eh_crm_import_staging WHERE batch_id = ? AND row_status = 'ok' ORDER BY row_no ASC",
        [$batchId]
    )->results();

    $inserted = 0;
    $leadIds  = [];
    foreach ($rows as $r) {
        if ($r->phone_norm === '' || !crm_phone_valid((string) $r->phone_norm)) {
            continue;
        }
        $exists = $db->query("SELECT id FROM eh_crm_leads WHERE phone = ?", [(string) $r->phone_norm])->first();
        if ($exists) {
            continue;
        }
        $db->insert('eh_crm_leads', [
            'phone'         => (string) $r->phone_norm,
            'phone_raw'     => (string) $r->phone_raw,
            'name'          => (string) $r->name,
            'city'          => (string) $r->city,
            'message'       => (string) $r->message,
            'source_id'     => null,
            'project_id'    => null,
            'notes'         => (string) $r->notes,
            'created_by'    => (int) $userId,
            'import_batch_id' => $batchId,
            'status'        => 'new',
            'calls_count'   => 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
        $leadId = (int) $db->query("SELECT LAST_INSERT_ID() AS id")->first()->id;
        $leadIds[] = $leadId;
        $inserted++;
    }

    /* علّم الصفوف المنجزة كمستوردة */
    $db->query(
        "UPDATE eh_crm_import_staging SET row_status = 'imported' WHERE batch_id = ? AND row_status = 'ok'",
        [$batchId]
    );

    $db->update('eh_crm_import_batches', $batchId, [
        'inserted'     => $inserted,
        'status'       => 'committed',
        'committed_at' => $now,
    ]);

    /* توزيع تلقائي لو الخيار مفعل */
    $assigned = 0;
    if (crm_setting('auto_assign_on_import', '0') === '1' && $inserted > 0) {
        $assigned = crm_assign_leads($leadIds, $userId, 'initial');
        $db->update('eh_crm_import_batches', $batchId, ['assigned_count' => $assigned]);
    }

    crm_log('import_confirm', 'eh_crm_import_batches', $batchId, [
        'inserted' => $inserted, 'assigned' => $assigned,
    ]);

    crm_ok(lang('CRM_MSG_LEADS_IMPORTED', [$inserted]), [
        'inserted' => $inserted,
        'assigned' => $assigned,
        'batch_id' => $batchId,
    ]);
}

/* إلغاء دفعة pending (يحذف صفوف المراجعة الخاصة بها) */
function crm_csv_cancel_batch($batchId, $userId)
{
    $db   = crm_db();
    $batchId = (int) $batchId;
    $batch = $db->query("SELECT * FROM eh_crm_import_batches WHERE id = ?", [$batchId])->first();
    if (!$batch || (int) $batch->user_id !== (int) $userId) {
        crm_err(crm_t('CRM_ERR_BATCH_NOT_OWN'), null, 403);
    }
    if ($batch->status !== 'pending') {
        crm_err(crm_t('CRM_ERR_BATCH_CANCEL_DONE'), null, 400);
    }
    $db->query("DELETE FROM eh_crm_import_staging WHERE batch_id = ?", [$batchId]);
    $db->update('eh_crm_import_batches', $batchId, ['status' => 'cancelled']);
    crm_ok(crm_t('CRM_MSG_BATCH_CANCELLED'));
}
