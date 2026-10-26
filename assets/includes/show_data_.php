<?php
/*
=============================================================================
 CRM — boudy-elhegaz  |  show_data_.php
-----------------------------------------------------------------------------
 كل عمليات **العرض** في الـCRM.
 النداء: GET  →  usersc/plugins/eh_crm/assets/includes/show_data_.php?action=...
 الرد: {"success":bool,"msg":"...","data":{...}}

 العمليات الحالية :
   - الخطوة 2 : ping | stats_overview | sales_load | results_list | sources_list | projects_list
   - الخطوة 3 : import_template_info | staging_list | batches_list
   - الخطوة 4 : unassigned_list | assigned_list
   - الخطوة 5 : my_stats | my_leads_list | my_calls_list
   - الخطوة 6 : all_leads_list | lead_card
   - الخطوة 7 : stats_by_user | stats_by_source | stats_by_result | sales_profiles
   - إعادة التدوير : recycle_list
   - تصدير Excel   : export_xlsx (what=leads|by_user|by_source|by_result)
=============================================================================
*/

/* عزل أي output جانبي (BOM / مسافات / تحذيرات) عن رد الـJSON */
ob_start();

require_once __DIR__ . '/crm_boot.php';

if (!crm_user_id()) {
    crm_json(false, crm_t('CRM_COMMON_LOGIN_FIRST'), null, 401);
}

$crm_action = crm_get('action');
$crm_uid    = crm_user_id();

switch ($crm_action) {

    /* ---------------------------------------------------------------- */
    case 'ping':
        crm_require_any_crm_role();
        crm_ok('pong', ['time' => crm_now(), 'userId' => $crm_uid, 'roles' => crm_user_roles()]);
        break;

    /* ---------------------------------------------------------------- */
    /* إحصائيات عامة (كروت الداشبورد)                                    */
    /* الموظف العادي (مبيعات) يشوف إحصائياته هو فقط                      */
    /* ---------------------------------------------------------------- */
    case 'stats_overview':
        crm_require_any_crm_role();

        $scope  = '';
        $params = [];
        if (!crm_is_sales_manager()) {
            if (crm_is_role('sales')) {
                // مسئول المبيعات: عملائه اللي اتوزّعوا عليه بس
                $scope    = ' AND assigned_to = ? ';
                $params[] = $crm_uid;
            } elseif (crm_is_role('entry')) {
                // مدخل البيانات: الأرقام اللي هو دخّلها بس
                $scope    = ' AND created_by = ? ';
                $params[] = $crm_uid;
            }
        }

        $row = crm_db()->query(
            "SELECT
                COUNT(*)                                                                   AS total,
                SUM(status = 'new')                                                        AS is_new,
                SUM(status IN ('assigned','contacted','followup','meeting','recycled'))    AS is_open,
                SUM(status = 'closed')                                                     AS is_closed,
                SUM(status <> 'closed' AND assigned_to IS NOT NULL)                        AS is_assigned,
                SUM(status <> 'closed' AND calls_count > 0)                                AS is_called,
                SUM(status <> 'closed' AND next_followup_at IS NOT NULL
                    AND next_followup_at <= NOW())                                         AS is_due,
                SUM(DATE(created_at) = CURDATE())                                          AS created_today,
                SUM(status <> 'closed' AND calls_count = 0 AND assigned_to IS NOT NULL)    AS not_called_yet
             FROM plg_ehcrm_leads WHERE 1 = 1 " . $scope,
            $params
        )->first();

        crm_ok('', [
            'total'          => (int) ($row->total ?? 0),
            'new'            => (int) ($row->is_new ?? 0),
            'open'           => (int) ($row->is_open ?? 0),
            'closed'         => (int) ($row->is_closed ?? 0),
            'assigned'       => (int) ($row->is_assigned ?? 0),
            'called'         => (int) ($row->is_called ?? 0),
            'due_followups'  => (int) ($row->is_due ?? 0),
            'created_today'  => (int) ($row->created_today ?? 0),
            'not_called_yet' => (int) ($row->not_called_yet ?? 0),
        ]);
        break;

    /* ---------------------------------------------------------------- */
    /* حمل كل موظفي المبيعات (للتوزيع والمتابعة)                          */
    /* ---------------------------------------------------------------- */
    case 'sales_load':
        crm_require_roles(['mgr']);

        $rows = crm_db()->query(
            "SELECT u.id, u.username, u.fname, u.lname, u.email,
                    COALESCE(p.available, 1) AS available,
                    COALESCE(p.max_leads, 0) AS max_leads,
                    COALESCE(p.target_monthly, 0) AS target_monthly,
                    (SELECT COUNT(*) FROM plg_ehcrm_leads l WHERE l.assigned_to = u.id)                        AS total,
                    (SELECT COUNT(*) FROM plg_ehcrm_leads l WHERE l.assigned_to = u.id AND l.status <> 'closed') AS open_leads,
                    (SELECT COUNT(*) FROM plg_ehcrm_leads l WHERE l.assigned_to = u.id AND l.status = 'closed') AS closed_leads,
                    (SELECT COUNT(*) FROM plg_ehcrm_leads l WHERE l.assigned_to = u.id
                        AND l.status <> 'closed' AND l.calls_count > 0)                                  AS called,
                    (SELECT COUNT(*) FROM plg_ehcrm_call_logs c WHERE c.user_id = u.id
                        AND DATE(c.call_at) = CURDATE())                                                 AS calls_today
               FROM users u
               JOIN user_permission_matches pm ON pm.user_id = u.id
               LEFT JOIN plg_ehcrm_sales_profile p    ON p.user_id  = u.id
              WHERE pm.permission_id = ? AND u.active = 1
              GROUP BY u.id
              ORDER BY open_leads ASC, u.id ASC",
            [crm_perm_id('sales')]
        )->results();

        $out = [];
        foreach ($rows as $r) {
            $nm = trim(($r->fname ?? '') . ' ' . ($r->lname ?? ''));
            $out[] = [
                'id'             => (int) $r->id,
                'name'           => $nm !== '' ? $nm : (string) $r->username,
                'username'       => (string) $r->username,
                'email'          => (string) $r->email,
                'available'      => (int) $r->available,
                'max_leads'      => (int) $r->max_leads,
                'target_monthly' => (int) $r->target_monthly,
                'total'          => (int) $r->total,
                'open'           => (int) $r->open_leads,
                'closed'         => (int) $r->closed_leads,
                'called'         => (int) $r->called,
                'calls_today'    => (int) $r->calls_today,
            ];
        }
        crm_ok('', $out);
        break;

    /* ---------------------------------------------------------------- */
    /* قوائم مساعدة                                                      */
    /* ---------------------------------------------------------------- */
    case 'results_list':
        crm_require_any_crm_role();
        $rows = crm_db()->query(
            "SELECT id, key_name, name_ar, color, sort_order, is_contact, is_interest, is_final, requires_followup
               FROM plg_ehcrm_call_results WHERE active = 1 ORDER BY sort_order ASC, id ASC"
        )->results();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'        => (int) $r->id,
                'key'       => (string) $r->key_name,
                'name'      => (string) $r->name_ar,
                'color'     => (string) $r->color,
                'contact'   => (int) $r->is_contact,
                'interest'  => (int) $r->is_interest,
                'final'     => (int) $r->is_final,
                'followup'  => (int) $r->requires_followup,
            ];
        }
        crm_ok('', $out);
        break;

    case 'sources_list':
        crm_require_any_crm_role();
        $rows = crm_db()->query(
            "SELECT id, name, color FROM plg_ehcrm_sources WHERE active = 1 ORDER BY sort_order ASC, name ASC"
        )->results();
        crm_ok('', array_map(function ($r) {
            return ['id' => (int) $r->id, 'name' => (string) $r->name, 'color' => (string) $r->color];
        }, $rows));
        break;

    case 'projects_list':
        crm_require_any_crm_role();
        $rows = crm_db()->query(
            "SELECT id, name, developer, city FROM plg_ehcrm_projects WHERE active = 1 ORDER BY sort_order ASC, name ASC"
        )->results();
        crm_ok('', array_map(function ($r) {
            return [
                'id'        => (int) $r->id,
                'name'      => (string) $r->name,
                'developer' => (string) $r->developer,
                'city'      => (string) $r->city,
            ];
        }, $rows));
        break;

    /* ---------------------------------------------------------------- */
    /* الخطوة 3: استيراد CSV                                             */
    /* ---------------------------------------------------------------- */
    case 'import_template_info':
        crm_require_any_crm_role();
        crm_ok('', [
            'columns'    => crm_csv_template_columns(),
            'template_url' => $us_url_root . 'usersc/plugins/eh_crm/assets/leads_template.csv',
        ]);
        break;

    case 'staging_list':
        crm_require_roles(['entry']);
        $batchId = crm_get_int('batch_id');
        if ($batchId <= 0) {
            crm_err(crm_t('CRM_ERR_BAD_BATCH'), null, 400);
        }
        crm_ok('', crm_csv_get_staging($batchId, crm_user_id()));
        break;

    case 'batches_list':
        crm_require_roles(['entry']);
        crm_ok('', crm_csv_get_batches(crm_user_id()));
        break;

    /* ---------------------------------------------------------------- */
    /* الخطوة 4: التوزيع / النقل                                         */
    /* ---------------------------------------------------------------- */
    case 'unassigned_list':
        crm_require_roles(['mgr']);
        crm_ok('', crm_dist_unassigned());
        break;

    case 'assigned_list':
        crm_require_roles(['mgr']);
        crm_ok('', crm_dist_assigned(crm_get_int('user_id')));
        break;

    /* ---------------------------------------------------------------- */
    /* الخطوة 5: لوحة المبيعات + سجل مكالماتي                            */
    /* ---------------------------------------------------------------- */
    case 'my_stats':
        crm_require_roles(['sales']);
        crm_ok('', crm_my_stats($crm_uid));
        break;

    case 'my_leads_list':
        crm_require_roles(['sales']);
        /* include=1,2,3 → عملاء متسجّلة عليهم مكالمة لسه — يفضلوا ظاهرين حتى لو خرجوا من فلتر التاب */
        $inc = array_filter(array_map('intval', explode(',', (string) crm_get('include'))));
        crm_ok('', crm_my_leads($crm_uid, crm_get('tab'), crm_get('q'), $inc));
        break;

    case 'my_calls_list':
        crm_require_roles(['sales']);
        crm_ok('', crm_my_calls($crm_uid));
        break;

    /* ---------------------------------------------------------------- */
    /* الخطوة 6: كل العملاء (مدير) + كارت العميل                          */
    /* ---------------------------------------------------------------- */
    case 'all_leads_list':
        crm_require_roles(['mgr']);
        crm_ok('', crm_all_leads([
            'status'      => crm_get('status'),
            'source_id'   => crm_get_int('source_id'),
            'project_id'  => crm_get_int('project_id'),
            'assigned_to' => crm_get_int('assigned_to'),
            'date_from'   => crm_get('date_from'),
            'date_to'     => crm_get('date_to'),
            'q'           => crm_get('q'),
        ], 1000));
        break;

    case 'lead_card':
        crm_require_roles(['sales', 'mgr']);
        $leadCardId = crm_get_int('id');
        if ($leadCardId <= 0) {
            crm_err(crm_t('CRM_ERR_BAD_LEAD'), null, 400);
        }
        crm_ok('', crm_lead_card($leadCardId, $crm_uid, crm_is_sales_manager()));
        break;

    /* ---------------------------------------------------------------- */
    /* الخطوة 7: التقارير + إعدادات الفريق                               */
    /* ---------------------------------------------------------------- */
    case 'stats_by_user':
        crm_require_roles(['mgr']);
        crm_ok('', crm_report_by_user(crm_get('from'), crm_get('to')));
        break;

    case 'stats_by_source':
        crm_require_roles(['mgr']);
        crm_ok('', crm_report_by_source(crm_get('from'), crm_get('to')));
        break;

    case 'stats_by_result':
        crm_require_roles(['mgr']);
        crm_ok('', crm_report_by_result(crm_get('from'), crm_get('to')));
        break;

    case 'sales_profiles':
        crm_require_roles(['mgr']);
        crm_ok('', crm_admin_team());
        break;

    case 'recycle_list':
        crm_require_roles(['mgr']);
        crm_ok('', crm_recycle_preview());
        break;

    /* ---------------------------------------------------------------- */
    /* تصدير Excel (xlsx) — للمدير، بنفس فلاتر الصفحات                   */
    /*   what=leads      → كل العملاء بالفلاتر (حتى 3000 صف)            */
    /*   what=by_user    → تقرير الفترة حسب الموظف                      */
    /*   what=by_source  → تقرير الفترة حسب المصدر                      */
    /*   what=by_result  → تقرير الفترة حسب النتيجة                     */
    /* ---------------------------------------------------------------- */
    case 'export_xlsx':
        crm_require_roles(['mgr']);
        require_once __DIR__ . '/SimpleXLSXGen.php';

        $what = crm_get('what');

        if ($what === 'leads') {
            $sheet = [['#', crm_t('CRM_EXCEL_PHONE'), crm_t('CRM_EXCEL_NAME'), crm_t('CRM_EXCEL_CITY'), crm_t('CRM_EXCEL_MESSAGE'), crm_t('CRM_EXCEL_CLOSED'),
                       crm_t('CRM_EXCEL_SOURCE'), crm_t('CRM_EXCEL_PROJECT'), crm_t('CRM_LEADS_EMPLOYEE'), crm_t('CRM_LEADS_TH_CREATED'), crm_t('CRM_LEADS_TH_LAST_CALL'), crm_t('CRM_LEADS_TH_NEXT')]];
            $expRows = crm_all_leads([
                'status'      => crm_get('status'),
                'source_id'   => crm_get_int('source_id'),
                'project_id'  => crm_get_int('project_id'),
                'assigned_to' => crm_get_int('assigned_to'),
                'date_from'   => crm_get('date_from'),
                'date_to'     => crm_get('date_to'),
                'q'           => crm_get('q'),
            ], 3000);
            foreach ($expRows as $l) {
                $sheet[] = [
                    $l['id'],
                    \Shuchkin\SimpleXLSXGen::raw($l['phone']),   /* نص عشان صفر البداية مايروحش */
                    $l['name'], $l['city'], $l['message'],
                    crm_lead_status_label($l['status']),
                    $l['source_name'], $l['project_name'],
                    $l['assigned_name'] !== '' ? $l['assigned_name'] : crm_t('CRM_EXCEL_UNASSIGNED'),
                    $l['created_at'], $l['last_call_at'], $l['next_followup_at'],
                ];
            }
            $fname = 'leads_' . gmdate('Ymd_His') . '.xlsx';

        } elseif (in_array($what, ['by_user', 'by_source', 'by_result'], true)) {
            $expFrom = crm_get('from');
            $expTo   = crm_get('to');

            if ($what === 'by_user') {
                $sheet = [[crm_t('CRM_LEADS_EMPLOYEE'), crm_t('CRM_REP_ASSIGNED'), crm_t('CRM_REP_CALLS_SIMPLE'), crm_t('CRM_REP_CLOSED_SIMPLE'), crm_t('CRM_REP_OPEN_FOLLOWUPS'), crm_t('CRM_REP_MONTHLY_TARGET')]];
                foreach (crm_report_by_user($expFrom, $expTo) as $r) {
                    $sheet[] = [$r['name'], $r['leads_assigned'], $r['calls'], $r['closed'],
                                $r['followups_open'], $r['target_monthly']];
                }
            } elseif ($what === 'by_source') {
                $sheet = [[crm_t('CRM_EXCEL_SOURCE'), crm_t('CRM_REP_NEW_LEADS'), crm_t('CRM_REP_CALLS_SIMPLE'), crm_t('CRM_REP_TALKED'), crm_t('CRM_REP_CLOSED_SIMPLE'), crm_t('CRM_EXCEL_CALL_RATE')]];
                foreach (crm_report_by_source($expFrom, $expTo) as $r) {
                    $sheet[] = [
                        $r['name'], $r['leads'], $r['calls'], $r['contacted'], $r['closed'],
                        $r['leads'] ? round($r['contacted'] / $r['leads'] * 100) : 0,
                    ];
                }
            } else {
                $byRes = crm_report_by_result($expFrom, $expTo);
                $total = 0;
                foreach ($byRes as $r) {
                    $total += $r['count'];
                }
                $sheet = [[crm_t('CRM_EXCEL_RESULT'), crm_t('CRM_EXCEL_CALLS_COUNT'), crm_t('CRM_EXCEL_PERCENT_TOTAL')]];
                foreach ($byRes as $r) {
                    $sheet[] = [$r['name'], $r['count'], $total ? round($r['count'] / $total * 100) : 0];
                }
            }
            $fname = 'crm_report_' . $what . '_'
                   . preg_replace('/\D/', '', (string) $expFrom) . '_'
                   . preg_replace('/\D/', '', (string) $expTo) . '.xlsx';

        } else {
            crm_err(crm_t('CRM_ERR_BAD_EXPORT'), null, 400);
        }

        /* تنزيل الملف مباشرة — بدل رد الـJSON */
        \Shuchkin\SimpleXLSXGen::fromArray($sheet, crm_t('CRM_EXCEL_SHEET_NAME'))
            ->rightToLeft(true)
            ->freezePanes('A2')
            ->downloadAs($fname);
        exit;

    /* ---------------------------------------------------------------- */
    default:
        crm_err(crm_t('CRM_ERR_UNKNOWN_ACTION') . ': ' . $crm_action, null, 400);
        break;
}
