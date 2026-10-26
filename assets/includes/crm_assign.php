<?php
/*
=============================================================================
 CRM — boudy-elhegaz  |  crm_assign.php
-----------------------------------------------------------------------------
 محرّك توزيع الأرقام على موظفي المبيعات (الخطوة 3/4).
 القاعدة: توزيع بالتساوي دائمًا (دور ورا دور ascending by id) — بدون النظر
 للحمل الحالي ولا للسقف max_leads. لو حمل موظف زاد عن الآخر، مدير المبيعات
 أو الأدمن هو اللي يوازنه بنفسه بجدول النقل (bulk_transfer).
 يمكن استدعاؤه من:
   - تأكيد دفعة CSV (توزيع تلقائي)
   - صفحة التوزيع اليدوي / نقل الأرقام (الخطوة 4)
=============================================================================
*/

if (!defined('CRM_BOOT_LOADED')) {
    die('Direct access not allowed.');
}

/**
 * توزيع مجموعة من الأرقام على موظفي المبيعات المؤهلين.
 *
 *  $leadIds   : أرقام leds المراد توزيعها
 *  $assignedBy: معرف موظف المشروع الذي نفذ التوزيع
 *  $reason    : initial | manual | transfer | bulk_transfer | recycled
 *
 * يرجّع عدد الأرقام التي تم توزيعها فعلًا.
 */
function crm_assign_leads(array $leadIds, int $assignedBy, string $reason = 'initial'): int
{
    if (empty($leadIds)) {
        return 0;
    }
    $db      = crm_db();
    $pid     = crm_perm_id('sales');
    $assignedBy = (int) $assignedBy;
    if ($pid <= 0) {
        return 0;
    }

    /* الموظفون المستلمون للتوزيع: مفعّلين وليسوا في إجازة
       (الحمل الحالي والسقف max_leads ما يدخلوش في التوزيع خالص) */
    $users = $db->query(
        "SELECT u.id, COALESCE(p.available, 1) AS available
           FROM users u
           JOIN user_permission_matches pm ON pm.user_id = u.id
           LEFT JOIN plg_ehcrm_sales_profile p ON p.user_id = u.id
          WHERE pm.permission_id = ? AND u.active = 1
          ORDER BY u.id ASC",
        [$pid]
    )->results();

    $candidates = [];
    foreach ($users as $u) {
        if ((int) $u->available !== 1) {
            continue; // في إجازة
        }
        $candidates[] = (int) $u->id;
    }

    if (empty($candidates)) {
        return 0;
    }

    $now    = crm_now();
    $count  = 0;
    $pointer = 0;
    $n      = count($candidates);

    foreach ($leadIds as $leadId) {
        $leadId = (int) $leadId;

        /* التوزيع بالتساوي: رقم ورا رقم على المتاحين (ascending by id) */
        $uid     = $candidates[$pointer];
        $pointer = ($pointer + 1) % $n;

        $db->update('plg_ehcrm_leads', $leadId, [
            'assigned_to'  => $uid,
            'assigned_at'  => $now,
            'assigned_by'  => $assignedBy,
            'status'       => 'assigned',
            'updated_at'   => $now,
        ]);

        $db->insert('plg_ehcrm_lead_assignments', [
            'lead_id'      => $leadId,
            'user_id'      => $uid,
            'assigned_by'  => $assignedBy,
            'mode'         => 'equal',
            'reason'       => $reason,
            'note'         => null,
            'assigned_at'  => $now,
            'unassigned_at'=> null,
        ]);

        $count++;
    }

    return $count;
}

/**
 * توزيع كل الأرقام اللي لسه متسندتش لأي موظف (يدعم التوزيع اليدوي من الصفحة).
 * يرجّع: ['found' => عدد الأرقام المطلوبة, 'assigned' => اللي اتوزّعت فعلًا]
 * (الفرق = مفيش مرشح متاح دلوقتي: الكل في إجازة)
 */
function crm_assign_pending(int $assignedBy): array
{
    $rows = crm_db()->query(
        "SELECT id FROM plg_ehcrm_leads
          WHERE assigned_to IS NULL AND status <> 'closed'
          ORDER BY id ASC"
    )->results();

    $ids      = array_map(function ($r) { return (int) $r->id; }, $rows);
    $assigned = crm_assign_leads($ids, (int) $assignedBy, 'manual');

    return ['found' => count($ids), 'assigned' => $assigned];
}

/**
 * نقل مجموعة أرقام من موظف لموظف تاني (bulk_transfer).
 *   $leadIds : الأرقام المحددة
 *   $toUser  : الموظف المستلم (لازم يكون مبيعات مفعّل)
 *   $byUser  : المدير المنفّذ
 *   $note    : ملاحظة اختيارية تتسجل في سجل التوزيع
 *
 * قواعد النقل:
 *   - العميل المنتهي (closed) مش بينتقل.
 *   - لو متسند أصلًا للموظف المستلم → يتخطّى.
 *   - سقف max_leads لا يمنع النقل اليدوي (المدير هو اللي بيقرر).
 *
 * يرجّع: ['moved' => تم, 'skipped' => اتخطّي]
 */
function crm_transfer_leads(array $leadIds, int $toUser, int $byUser, string $note = ''): array
{
    $db = crm_db();

    /* الموظف المستلم لازم يكون مبيعات مفعّل */
    $pid = crm_perm_id('sales');
    $ok  = $pid > 0 ? $db->query(
        "SELECT u.id
           FROM users u
           JOIN user_permission_matches pm ON pm.user_id = u.id
          WHERE u.id = ? AND pm.permission_id = ? AND u.active = 1
          LIMIT 1",
        [(int) $toUser, $pid]
    )->first() : null;
    if (!$ok) {
        crm_err(crm_t('CRM_ERR_ASSIGNEE_INVALID'), null, 400);
    }

    $now    = crm_now();
    $byUser = (int) $byUser;
    $moved  = 0;
    $skipped = 0;

    foreach ($leadIds as $leadId) {
        $leadId = (int) $leadId;
        if ($leadId <= 0) {
            continue;
        }
        $lead = $db->query(
            "SELECT id, assigned_to, status FROM plg_ehcrm_leads WHERE id = ? LIMIT 1",
            [$leadId]
        )->first();
        if (!$lead || $lead->status === 'closed') {
            $skipped++;
            continue;
        }
        if ((int) $lead->assigned_to === (int) $toUser) {
            $skipped++;
            continue;
        }

        /* إغلاق التوزيع الحالي (السجل التاريخي بيفضل) */
        $db->query(
            "UPDATE plg_ehcrm_lead_assignments SET unassigned_at = ?
              WHERE lead_id = ? AND unassigned_at IS NULL",
            [$now, $leadId]
        );

        /* الحالة: لو متسند أول مرة تبقى assigned، وإلا تفضل على حالها */
        $newStatus = ((int) $lead->assigned_to === 0 || $lead->status === 'new')
            ? 'assigned'
            : (string) $lead->status;

        $db->update('plg_ehcrm_leads', $leadId, [
            'assigned_to' => (int) $toUser,
            'assigned_at' => $now,
            'assigned_by' => $byUser,
            'status'      => $newStatus,
            'updated_at'  => $now,
        ]);

        $db->insert('plg_ehcrm_lead_assignments', [
            'lead_id'       => $leadId,
            'user_id'       => (int) $toUser,
            'assigned_by'   => $byUser,
            'mode'          => 'manual',
            'reason'        => 'bulk_transfer',
            'note'          => $note !== '' ? $note : null,
            'assigned_at'   => $now,
            'unassigned_at' => null,
        ]);

        $moved++;
    }

    return ['moved' => $moved, 'skipped' => $skipped];
}

/* ------------------------------------------------------------------ */
/* قوائم العرض لصفحة التوزيع                                           */
/* ------------------------------------------------------------------ */

/** أرقام لسه متسندتش لأي موظف (للعرض + عداد صفحة التوزيع) */
function crm_dist_unassigned(int $limit = 300): array
{
    $rows = crm_db()->query(
        "SELECT l.id, l.phone, l.name, l.city, l.message, l.created_at,
                s.name AS source_name
           FROM plg_ehcrm_leads l
           LEFT JOIN plg_ehcrm_sources s ON s.id = l.source_id
          WHERE l.assigned_to IS NULL AND l.status <> 'closed'
          ORDER BY l.id ASC
          LIMIT " . (int) $limit
    )->results();

    return array_map(function ($r) {
        return [
            'id'         => (int) $r->id,
            'phone'      => (string) $r->phone,
            'name'       => (string) $r->name,
            'city'       => (string) $r->city,
            'message'    => (string) $r->message,
            'source'     => (string) ($r->source_name ?? ''),
            'created_at' => (string) $r->created_at,
        ];
    }, $rows);
}

/** أرقام موزّعة (فلترة اختيارية بموظف معين) — لجدول النقل */
function crm_dist_assigned(int $userId = 0, int $limit = 300): array
{
    $sql = "SELECT l.id, l.phone, l.name, l.status, l.assigned_at, l.last_call_at,
                   l.calls_count, u.id AS rep_id, u.username, u.fname, u.lname
              FROM plg_ehcrm_leads l
              LEFT JOIN users u ON u.id = l.assigned_to
             WHERE l.assigned_to IS NOT NULL AND l.status <> 'closed'";
    $params = [];
    if ($userId > 0) {
        $sql .= " AND l.assigned_to = ? ";
        $params[] = (int) $userId;
    }
    $sql .= " ORDER BY l.assigned_at DESC, l.id DESC LIMIT " . (int) $limit;

    $rows = crm_db()->query($sql, $params)->results();

    return array_map(function ($r) {
        $nm = trim(($r->fname ?? '') . ' ' . ($r->lname ?? ''));
        return [
            'id'           => (int) $r->id,
            'phone'        => (string) $r->phone,
            'name'         => (string) $r->name,
            'status'       => (string) $r->status,
            'rep_id'       => (int) $r->rep_id,
            'rep_name'     => $nm !== '' ? $nm : (string) $r->username,
            'assigned_at'  => (string) $r->assigned_at,
            'last_call_at' => (string) ($r->last_call_at ?? ''),
            'calls_count'  => (int) $r->calls_count,
        ];
    }, $rows);
}

/* ------------------------------------------------------------------ */
/* إعادة التدوير (العملاء الباردين → المجمّع)                           */
/* ------------------------------------------------------------------ */

/**
 * معاينة العملاء المرشحين للتدوير (زر المدير في صفحة التوزيع).
 *
 *  القواعد من plg_ehcrm_settings:
 *    recycle_days     : الأيام بعد آخر نشاط (افتراضي 3، والحد الأدنى يوم)
 *    recycle_statuses : مفاتيح نتايج المكالمة المراد تدويرها (مفصولة بفاصلة)
 *
 *  شروط المرشح:
 *    - متسند لموظف وغير منتهي
 *    - آخر نتيجة ليه (current_result) من قايمة recycle_statuses
 *    - مفيش متابعة مستقبلية موعود بيها (next_followup_at فاضية أو فاتت)
 *    - آخر نشاط (أحدث بين آخر مكالمة وآخر توزيع) أقدم من recycle_days
 *
 *  يرجّع: { days, count, rows[], disabled, msg }
 */
function crm_recycle_preview(int $limit = 500): array
{
    $days = max(1, (int) crm_setting('recycle_days', '3'));
    $keys = array_values(array_filter(array_map('trim', explode(',', (string) crm_setting('recycle_statuses', '')))));
    if (empty($keys)) {
        return [
            'days'     => $days,
            'count'    => 0,
            'rows'     => [],
            'disabled' => true,
            'msg'      => crm_t('CRM_DIST_NO_RECYCLE_CFG'),
        ];
    }

    $in     = implode(',', array_fill(0, count($keys), '?'));
    $params = array_merge($keys, [$days]);

    /* أساس "آخر نشاط": أحدث حاجة بين آخر مكالمة وتاريخ آخر توزيع — عشان
       العميل اللي اتوزّع دلوقتي ياخذ مهلة أيام من الموظف الجديد قبل ما
       يتدور تاني، وإلا كان هيرجع للمجمّع فورًا بعد كل توزيع */
    $sql = "SELECT l.id, l.phone, l.name,
                   GREATEST(l.assigned_at, COALESCE(l.last_call_at, l.assigned_at)) AS last_activity,
                   r.name_ar AS result_name,
                   u.fname, u.lname, u.username
              FROM plg_ehcrm_leads l
              JOIN plg_ehcrm_call_results r ON r.id = l.current_result_id
              LEFT JOIN users u ON u.id = l.assigned_to
             WHERE l.assigned_to IS NOT NULL
               AND l.status <> 'closed'
               AND r.key_name IN ($in)
               AND (l.next_followup_at IS NULL OR l.next_followup_at < NOW())
               AND GREATEST(l.assigned_at, COALESCE(l.last_call_at, l.assigned_at))
                   < DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY last_activity ASC
             LIMIT " . (int) $limit;

    $rows = crm_db()->query($sql, $params)->results();

    $list = [];
    foreach ($rows as $r) {
        $nm = trim(($r->fname ?? '') . ' ' . ($r->lname ?? ''));
        $list[] = [
            'id'            => (int) $r->id,
            'phone'         => (string) $r->phone,
            'name'          => (string) $r->name,
            'rep_name'      => $nm !== '' ? $nm : (string) $r->username,
            'result'        => (string) $r->result_name,
            'last_activity' => (string) $r->last_activity,
        ];
    }

    return ['days' => $days, 'count' => count($list), 'rows' => $list, 'disabled' => false, 'msg' => ''];
}

/**
 * تنفيذ التدوير: يرجّع المرشحين لمجمّع غير الموزّعة:
 *   - إقفال سطر التوزيع المفتوح (unassigned_at = الآن)
 *   - assigned_to = NULL + status = 'recycled' (حالة «إعادة»)
 *   - إلغاء ميعاد المتابعة القديم (الوعد ماينفعش مع الموظف الجديد)
 *   — التوزيع التاني بعدها بزر «وزّع دلوقتي» العادي (round-robin).
 *
 *  يرجّع: { moved, skipped }
 */
function crm_recycle_leads(): array
{
    $prev = crm_recycle_preview(2000);
    if (!empty($prev['disabled']) || empty($prev['rows'])) {
        return ['moved' => 0, 'skipped' => 0];
    }

    $db     = crm_db();
    $now    = crm_now();
    $moved  = 0;
    $skipped = 0;

    foreach ($prev['rows'] as $r) {
        /* إعادة فحص قبل التدوير (الحالة ممكن تتغير بين المعاينة والتنفيذ) */
        $lead = $db->query(
            "SELECT assigned_to, status FROM plg_ehcrm_leads WHERE id = ? LIMIT 1",
            [$r['id']]
        )->first();
        if (!$lead || (int) $lead->assigned_to === 0 || $lead->status === 'closed') {
            $skipped++;
            continue;
        }

        /* إقفال سطر التوزيع المفتوح */
        $db->query(
            "UPDATE plg_ehcrm_lead_assignments
                SET unassigned_at = ?
              WHERE lead_id = ? AND unassigned_at IS NULL",
            [$now, $r['id']]
        );

        /* رجوع للمجمّع */
        $db->query(
            "UPDATE plg_ehcrm_leads
                SET assigned_to = NULL, status = 'recycled',
                    next_followup_at = NULL, updated_at = ?
              WHERE id = ? AND assigned_to IS NOT NULL AND status <> 'closed'",
            [$now, $r['id']]
        );
        $moved++;
    }

    return ['moved' => $moved, 'skipped' => $skipped];
}
