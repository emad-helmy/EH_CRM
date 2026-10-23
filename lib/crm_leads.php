<?php
/*
 =============================================================================
 CRM — boudy-elhegaz  |  crm_leads.php
 -----------------------------------------------------------------------------
  الخطوة 6: «كل العملاء» (فلاتر للمدير) + «كارت العميل» (بياناته + سجل مكالماته).
 =============================================================================
*/

if (!defined('CRM_BOOT_LOADED') && !function_exists('crm_db')) {
    die('Direct access not allowed.');
}

/** اسم كامل من fname/lname ولو فاضي username (والعكس لو مفيش حاجة يبقى فاضي) */
function crm_full_name($fname, $lname, $username): string
{
    if ($username === null && $fname === null) {
        return '';   // متسندش (الجول مول من LEFT JOIN)
    }
    $n = trim(($fname ?? '') . ' ' . ($lname ?? ''));
    return $n !== '' ? $n : (string) ($username ?? '');
}

/* ------------------------------------------------------------------ */
/* كل العملاء (فلاتر)                                                  */
/* ------------------------------------------------------------------ */

/**
 * قائمة كل العملاء لصفحة المدير مع الفلاتر:
 *   status      : قيمة من قيم الحالة المعروفة (فارغ = الكل)
 *   source_id   : مصدر الإدخال (0 = الكل)
 *   project_id  : المشروع (0 = الكل)
 *   assigned_to : الموظف (-1 = غير متسند، 0 = الكل، رقم = موظف)
 *   date_from / date_to : على تاريخ الإدخال (YYYY-MM-DD)
 *   q           : بحث بالهاتف/الاسم/المدينة
 */
function crm_all_leads(array $f = [], int $limit = 1000): array
{
    $sql = "SELECT l.id, l.phone, l.name, l.city, l.status, l.calls_count,
                   l.created_at, l.last_call_at, l.next_followup_at,
                   s.name   AS source_name,
                   p.name   AS project_name,
                   r.name_ar AS result_name, r.color AS result_color,
                   u.fname, u.lname, u.username AS assigned_username
              FROM eh_crm_leads l
              LEFT JOIN eh_crm_sources s       ON s.id = l.source_id
              LEFT JOIN eh_crm_projects p      ON p.id = l.project_id
              LEFT JOIN eh_crm_call_results r  ON r.id = l.current_result_id
              LEFT JOIN users u            ON u.id = l.assigned_to
             WHERE 1 = 1";
    $params = [];

    $status = (string) ($f['status'] ?? '');
    $known  = ['new', 'assigned', 'contacted', 'followup', 'meeting', 'closed', 'recycled'];
    if ($status !== '' && in_array($status, $known, true)) {
        $sql   .= " AND l.status = ?";
        $params[] = $status;
    }

    $src = (int) ($f['source_id'] ?? 0);
    if ($src > 0) {
        $sql     .= " AND l.source_id = ?";
        $params[] = $src;
    }
    $prj = (int) ($f['project_id'] ?? 0);
    if ($prj > 0) {
        $sql     .= " AND l.project_id = ?";
        $params[] = $prj;
    }

    $asg = (int) ($f['assigned_to'] ?? 0);
    if ($asg === -1) {
        $sql .= " AND l.assigned_to IS NULL";
    } elseif ($asg > 0) {
        $sql     .= " AND l.assigned_to = ?";
        $params[] = $asg;
    }

    $df = (string) ($f['date_from'] ?? '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $df)) {
        $sql     .= " AND l.created_at >= ?";
        $params[] = $df . ' 00:00:00';
    }
    $dt = (string) ($f['date_to'] ?? '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) {
        $sql     .= " AND l.created_at <= ?";
        $params[] = $dt . ' 23:59:59';
    }

    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        $sql     .= " AND (l.phone LIKE ? OR l.name LIKE ? OR l.city LIKE ?)";
        $like     = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $limit = max(1, min(3000, (int) $limit));
    $sql  .= " ORDER BY l.id DESC LIMIT " . $limit;

    $rows = crm_db()->query($sql, $params)->results();

    return array_map(function ($r) {
        return [
            'id'               => (int) $r->id,
            'phone'            => (string) $r->phone,
            'name'             => (string) $r->name,
            'city'             => (string) $r->city,
            'status'           => (string) $r->status,
            'calls_count'      => (int) $r->calls_count,
            'created_at'       => (string) $r->created_at,
            'last_call_at'     => (string) ($r->last_call_at ?? ''),
            'next_followup_at' => (string) ($r->next_followup_at ?? ''),
            'source_name'      => (string) ($r->source_name ?? ''),
            'project_name'     => (string) ($r->project_name ?? ''),
            'result_name'      => (string) ($r->result_name ?? ''),
            'result_color'     => (string) ($r->result_color ?? ''),
            'assigned_name'    => crm_full_name($r->fname, $r->lname, $r->assigned_username),
        ];
    }, $rows);
}

/* ------------------------------------------------------------------ */
/* كارت العميل                                                         */
/* ------------------------------------------------------------------ */

/**
 * بيانات عميل واحد + سجل مكالماته.
 *   - المدير (والأدمن/المشرف) يشوف أي عميل
 *   - مسئول المبيعات يشوف عملائه بس
 * يرجّع ['lead' => [...], 'calls' => [...]]
 */
function crm_lead_card(int $id, int $viewerId, bool $isMgr): array
{
    $lead = crm_db()->query(
        "SELECT l.*, s.name AS source_name, p.name AS project_name,
                r.name_ar AS result_name, r.color AS result_color,
                au.fname AS a_fname, au.lname AS a_lname, au.username AS a_username,
                cu.fname AS c_fname, cu.lname AS c_lname, cu.username AS c_username
           FROM eh_crm_leads l
           LEFT JOIN eh_crm_sources s       ON s.id = l.source_id
           LEFT JOIN eh_crm_projects p      ON p.id = l.project_id
           LEFT JOIN eh_crm_call_results r  ON r.id = l.current_result_id
           LEFT JOIN users au           ON au.id = l.assigned_to
           LEFT JOIN users cu           ON cu.id = l.created_by
          WHERE l.id = ? LIMIT 1",
        [$id]
    )->first();

    if (!$lead) {
        crm_err(crm_t('CRM_ERR_LEAD_NOT_FOUND'), null, 404);
    }
    if (!$isMgr && (int) $lead->assigned_to !== (int) $viewerId) {
        crm_err(crm_t('CRM_ERR_LEAD_NOT_ASSIGNED'), null, 403);
    }

    $calls = crm_db()->query(
        "SELECT c.call_at, c.notes, c.next_followup_at, c.prev_status, c.new_status,
                c.result_key,
                r.name_ar AS result_name, r.color AS result_color,
                u.fname, u.lname, u.username
           FROM eh_crm_call_logs c
           LEFT JOIN eh_crm_call_results r ON r.id = c.result_id
           LEFT JOIN users u           ON u.id = c.user_id
          WHERE c.lead_id = ?
          ORDER BY c.call_at DESC, c.id DESC
          LIMIT 200",
        [$id]
    )->results();

    return [
        'lead' => [
            'id'               => (int) $lead->id,
            'phone'            => (string) $lead->phone,
            'phone2'           => (string) ($lead->phone2 ?? ''),
            'name'             => (string) ($lead->name ?? ''),
            'email'            => (string) ($lead->email ?? ''),
            'city'             => (string) ($lead->city ?? ''),
            'message'          => (string) ($lead->message ?? ''),
            'notes'            => (string) ($lead->notes ?? ''),
            'status'           => (string) $lead->status,
            'calls_count'      => (int) $lead->calls_count,
            'created_at'       => (string) $lead->created_at,
            'assigned_at'      => (string) ($lead->assigned_at ?? ''),
            'last_call_at'     => (string) ($lead->last_call_at ?? ''),
            'next_followup_at' => (string) ($lead->next_followup_at ?? ''),
            'closed_at'        => (string) ($lead->closed_at ?? ''),
            'source_name'      => (string) ($lead->source_name ?? ''),
            'project_name'     => (string) ($lead->project_name ?? ''),
            'result_name'      => (string) ($lead->result_name ?? ''),
            'result_color'     => (string) ($lead->result_color ?? ''),
            'assigned_name'    => crm_full_name($lead->a_fname, $lead->a_lname, $lead->a_username),
            'assigned_to'      => (int) $lead->assigned_to,
            'created_by_name'  => crm_full_name($lead->c_fname, $lead->c_lname, $lead->c_username),
        ],
        'calls' => array_map(function ($c) {
            return [
                'call_at'          => (string) $c->call_at,
                'user_name'        => crm_full_name($c->fname, $c->lname, $c->username),
                'result_name'      => (string) ($c->result_name ?? $c->result_key ?? ''),
                'result_color'     => (string) ($c->result_color ?? ''),
                'notes'            => (string) ($c->notes ?? ''),
                'next_followup_at' => (string) ($c->next_followup_at ?? ''),
                'prev_status'      => (string) $c->prev_status,
                'new_status'       => (string) $c->new_status,
            ];
        }, $calls),
    ];
}

/** تسمية حالة العميل بالعربي/الإنجليزي حسب لغة الموقع (نفس القايمة اللي بتتبعت للـJS) */
function crm_lead_status_label(string $status): string
{
    static $map = [
        'new'       => 'CRM_STATUS_NEW',
        'assigned'  => 'CRM_STATUS_ASSIGNED',
        'contacted' => 'CRM_STATUS_CONTACTED',
        'followup'  => 'CRM_STATUS_FOLLOWUP',
        'meeting'   => 'CRM_STATUS_MEETING',
        'recycled'  => 'CRM_STATUS_RECYCLED',
        'closed'    => 'CRM_STATUS_CLOSED',
    ];
    return isset($map[$status]) ? crm_t($map[$status]) : $status;
}
