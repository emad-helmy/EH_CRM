<?php
/*
=============================================================================
 CRM — boudy-elhegaz  |  data_in_.php
-----------------------------------------------------------------------------
 كل عمليات **الإدخال** في الـCRM.
 النداء: POST  →  usersc/scripts/data_in_.php
 الباراميتر الإلزامي: action
 الرد: {"success":bool,"msg":"...","data":{...}}

 الخطوات اللي بتبني الملف ده:
   - الخطوة 2 : الهيكل + حماية البرمشن + ping
   - الخطوة 3 : lead_create | import_upload | import_confirm | import_cancel ...
   - الخطوة 4 : assign_run | bulk_transfer
   - الخطوة 5 : call_save
   - الخطوة 6 : (لوحة المدير — العرض في show_data_.php)
   - الخطوة 7 : result_save | project_save | source_save | settings_save | sales_profile_save
   - إعادة التدوير : recycle_run
=============================================================================
*/

/* عزل أي output جانبي (BOM / مسافات / تحذيرات) عن رد الـJSON */
ob_start();

require_once __DIR__ . '/../lib/crm_boot.php';

/* لازم مسجّل دخول */
if (!crm_user_id()) {
    crm_json(false, crm_t('CRM_COMMON_LOGIN_FIRST'), null, 401);
}

$crm_action = crm_post('action');

/* حماية CSRF (نفس توكن UserSpice) */
if (!Token::check(crm_post('csrf'))) {
    crm_json(false, crm_t('CRM_COMMON_SESSION_END'), null, 419);
}

switch ($crm_action) {

    /* ---------------------------------------------------------------- */
    case 'ping':
        crm_require_any_crm_role();
        crm_ok('pong', [
            'time'   => crm_now(),
            'userId' => crm_user_id(),
            'roles'  => crm_user_roles(),
        ]);
        break;

    /* ---------------------------------------------------------------- */
    /* الخطوة 3: إدخال فردي + استيراد CSV                                */
    /* ---------------------------------------------------------------- */

    case 'lead_create':
        crm_require_roles(['entry']);
        $phoneRaw = crm_post('phone');
        $norm     = crm_norm_phone($phoneRaw);
        if ($norm === '' || !crm_phone_valid($norm)) {
            crm_err(crm_t('CRM_ERR_BAD_PHONE'), null, 400);
        }
        $exists = crm_db()->query("SELECT id FROM eh_crm_leads WHERE phone = ?", [$norm])->first();
        if ($exists) {
            crm_err(crm_t('CRM_ERR_PHONE_EXISTS'), null, 409);
        }
        $now = crm_now();
        crm_db()->insert('eh_crm_leads', [
            'phone'       => $norm,
            'phone_raw'   => $phoneRaw,
            'name'        => crm_post('name'),
            'city'        => crm_post('city'),
            'message'     => crm_post('message'),
            'source_id'   => crm_post_int('source_id'),
            'project_id'  => crm_post_int('project_id'),
            'notes'       => crm_post('notes'),
            'created_by'  => crm_user_id(),
            'status'      => 'new',
            'calls_count' => 0,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        $leadId = (int) crm_db()->query("SELECT LAST_INSERT_ID() AS id")->first()->id;
        $assigned = 0;
        if (crm_setting('auto_assign_on_import', '0') === '1') {
            $assigned = crm_assign_leads([$leadId], crm_user_id(), 'initial');
        }
        crm_log('lead_create', 'eh_crm_leads', $leadId);
        crm_ok(crm_t('CRM_MSG_LEAD_ADDED'), ['lead_id' => $leadId, 'assigned' => $assigned]);
        break;

    case 'import_upload':
        crm_require_roles(['entry']);
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            crm_err(crm_t('CRM_ERR_NO_FILE'), null, 400);
        }
        $file = $_FILES['file'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            crm_err(crm_t('CRM_ERR_NOT_CSV'), null, 400);
        }
        $rows = crm_csv_parse_uploaded($file['tmp_name']);
        if (empty($rows)) {
            crm_err(crm_t('CRM_ERR_FILE_EMPTY'), null, 400);
        }
        $res = crm_csv_create_batch(crm_user_id(), $file['name'], $rows);
        crm_ok(crm_t('CRM_MSG_FILE_PARSED'), $res);
        break;

    case 'import_confirm':
        crm_require_roles(['entry']);
        $batchId = crm_post_int('batch_id');
        if ($batchId <= 0) {
            crm_err(crm_t('CRM_ERR_BAD_BATCH'), null, 400);
        }
        crm_csv_confirm_batch($batchId, crm_user_id());
        break;

    case 'import_cancel':
        crm_require_roles(['entry']);
        $batchId = crm_post_int('batch_id');
        if ($batchId <= 0) {
            crm_err(crm_t('CRM_ERR_BAD_BATCH'), null, 400);
        }
        crm_csv_cancel_batch($batchId, crm_user_id());
        break;

    case 'staging_exclude':
        crm_require_roles(['entry']);
        $rowId = crm_post_int('row_id');
        if ($rowId <= 0) {
            crm_err(crm_t('CRM_ERR_BAD_ROW'), null, 400);
        }
        crm_csv_exclude_row($rowId, crm_user_id());
        break;

    case 'staging_edit':
        crm_require_roles(['entry']);
        $rowId = crm_post_int('row_id');
        if ($rowId <= 0) {
            crm_err(crm_t('CRM_ERR_BAD_ROW'), null, 400);
        }
        $fields = [];
        foreach (['phone_raw', 'name', 'city', 'project', 'source', 'message', 'notes'] as $k) {
            if (array_key_exists($k, $_POST)) {
                $fields[$k] = crm_post($k);
            }
        }
        crm_csv_edit_row($rowId, $fields, crm_user_id());
        break;

    /* ---------------------------------------------------------------- */
    /* الخطوة 4: التوزيع اليدوي + نقل الأرقام (مدير المبيعات)             */
    /* ---------------------------------------------------------------- */

    case 'assign_run':
        crm_require_roles(['mgr']);
        $res = crm_assign_pending(crm_user_id());
        crm_ok(
            $res['assigned'] > 0
                ? lang('CRM_MSG_DISTRIBUTED_N', [$res['assigned']])
                : crm_t('CRM_MSG_NO_DISTRIBUTION'),
            $res
        );
        break;

    case 'bulk_transfer':
        crm_require_roles(['mgr']);
        $toUser = crm_post_int('to_user');
        if ($toUser <= 0) {
            crm_err(crm_t('CRM_ERR_PICK_ASSIGNEE'), null, 400);
        }
        $leadIds = [];
        if (isset($_POST['lead_ids']) && is_array($_POST['lead_ids'])) {
            foreach ($_POST['lead_ids'] as $lid) {
                $leadIds[] = (int) $lid;
            }
        }
        if (empty($leadIds)) {
            crm_err(crm_t('CRM_ERR_PICK_LEADS'), null, 400);
        }
        $res   = crm_transfer_leads($leadIds, $toUser, crm_user_id(), crm_post('note'));
        $msg   = lang('CRM_MSG_TRANSFERRED_N', [$res['moved']]);
        if ($res['skipped'] > 0) {
            $msg .= lang('CRM_MSG_TRANSFER_SKIP', [$res['skipped']]);
        }
        crm_ok($msg, $res);
        break;

    /* ---------------------------------------------------------------- */
    /* الخطوة 5: تسجيل المكالمات (مسئول المبيعات)                        */
    /* ---------------------------------------------------------------- */

    case 'call_save':
        crm_require_roles(['sales', 'mgr']);
        $res = crm_call_save(
            crm_post_int('lead_id'),
            crm_user_id(),
            crm_post('result_key'),
            crm_post('notes'),
            crm_post('next_followup')
        );
        crm_ok(crm_t('CRM_MSG_CALL_SAVED') . ': ' . $res['result'], $res);
        break;

    /* ---------------------------------------------------------------- */
    /* الخطوة 7: إعدادات CRM (نتائج / مصادر / مشاريع / فريق / قواعد)     */
    /* ---------------------------------------------------------------- */

    case 'result_save':
        crm_require_roles(['mgr']);
        crm_ok(crm_t('CRM_MSG_RESULT_SAVED'), crm_admin_result_save([
            'id'                => crm_post_int('id'),
            'name_ar'           => crm_post('name_ar'),
            'key_name'          => crm_post('key_name'),
            'color'             => crm_post('color'),
            'is_contact'        => crm_post_int('is_contact'),
            'is_interest'       => crm_post_int('is_interest'),
            'is_final'          => crm_post_int('is_final'),
            'requires_followup' => crm_post_int('requires_followup'),
            'active'            => crm_post_int('active'),
        ]));
        break;

    case 'source_save':
        crm_require_roles(['mgr']);
        crm_ok(crm_t('CRM_MSG_SOURCE_SAVED'), crm_admin_source_save([
            'id'     => crm_post_int('id'),
            'name'   => crm_post('name'),
            'color'  => crm_post('color'),
            'active' => crm_post_int('active'),
        ]));
        break;

    case 'project_save':
        crm_require_roles(['mgr']);
        crm_ok(crm_t('CRM_MSG_PROJECT_SAVED'), crm_admin_project_save([
            'id'        => crm_post_int('id'),
            'name'      => crm_post('name'),
            'developer' => crm_post('developer'),
            'city'      => crm_post('city'),
            'active'    => crm_post_int('active'),
        ]));
        break;

    case 'sales_profile_save':
        crm_require_roles(['mgr']);
        crm_ok(crm_t('CRM_MSG_PROFILE_SAVED'), crm_admin_profile_save([
            'user_id'       => crm_post_int('user_id'),
            'available'     => crm_post_int('available'),
            'max_leads'     => crm_post_int('max_leads'),
            'target_monthly' => crm_post_int('target_monthly'),
            'notes'         => crm_post('notes'),
        ]));
        break;

    case 'settings_save':
        crm_require_roles(['mgr']);
        /* المفاتيح الغايبين مايت游戏代شوش (الصفحة بتبعتهم كلهم دايمًا) */
        $in = [];
        foreach (array_keys(crm_admin_settings_keys()) as $sk) {
            if (isset($_POST[$sk])) {
                $in[$sk] = crm_post($sk);
            }
        }
        crm_ok(crm_t('CRM_MSG_SETTINGS_SAVED'), crm_admin_settings_save($in));
        break;

    case 'recycle_run':
        crm_require_roles(['mgr']);
        $rec = crm_recycle_leads();
        $msg = $rec['moved']
            ? lang('CRM_MSG_RECYLED_POOL', [$rec['moved']])
            : crm_t('CRM_MSG_RECYCLE_NONE');
        if ($rec['skipped'] > 0) {
            $msg .= lang('CRM_MSG_RECYCLE_SKIP', [$rec['skipped']]);
        }
        crm_ok($msg, $rec);
        break;

    /* ---------------------------------------------------------------- */
    default:
        crm_err(crm_t('CRM_ERR_UNKNOWN_ACTION') . ': ' . $crm_action, null, 400);
        break;
}
