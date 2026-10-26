<?php
/*
 =============================================================================
 CRM — boudy-elhegaz  |  crm_calls.php
 -----------------------------------------------------------------------------
  تسجيل المكالمات + لوحة مسئول المبيعات (الخطوة 5).

  منطق الحالة الجديدة للعميل بعد النتيجة:
    is_final = 1          → منتهي (closed + closed_at)
    requires_followup = 1 → متابعة (followup + next_followup_at إلزامي)
    is_contact = 1        → اتكلم (contacted)
    غير كده               → الحالة الحالية تفضل زي ما هي (مفيش رد/مشغول...)
 =============================================================================
*/

if (!defined('CRM_BOOT_LOADED') && !function_exists('crm_db')) {
    die('Direct access not allowed.');
}

/* ------------------------------------------------------------------ */
/* نتائج المكالمات                                                     */
/* ------------------------------------------------------------------ */

/** كل نتائج المكالمات الفعالة (للقوائم المنسدلة ومنطق الحالة) */
function crm_call_results(): array
{
    return crm_db()->query(
        "SELECT id, key_name, name_ar, color, is_contact, is_interest, is_final, requires_followup
           FROM plg_ehcrm_call_results
          WHERE active = 1
          ORDER BY sort_order ASC, id ASC"
    )->results();
}

/* ------------------------------------------------------------------ */
/* تسجيل مكالمة                                                         */
/* ------------------------------------------------------------------ */

/**
 * تسجيل مكالمة لعميل + تحديث حالته.
 *   $leadId       : العميل
 *   $userId       : المسجّل (لازم يكون الموظف المسندله، إلا لو أدمن/مدير)
 *   $resultKey    : key_name من plg_ehcrm_call_results
 *   $notes        : ملاحظات اختيارية
 *   $nextFollowup : ميعاد المتابعة "YYYY-MM-DDTHH:MM" (مطلوب لو النتيجة محتاجة متابعة)
 *
 * يرجّع: ['status' => الحالة الجديدة, 'result' => اسم النتيجة, 'next_followup_at' => ...]
 */
function crm_call_save(int $leadId, int $userId, string $resultKey, string $notes = '', $nextFollowup = null): array
{
    $db   = crm_db();
    $lead = $db->query("SELECT * FROM plg_ehcrm_leads WHERE id = ? LIMIT 1", [$leadId])->first();
    if (!$lead) {
        crm_err(crm_t('CRM_ERR_LEAD_NOT_FOUND'), null, 404);
    }

    /* الملكية: الموظف يسجل على عملائه بس (الأدمن/المدير فوقهم) */
    $byPass = crm_is_admin($userId) || crm_is_sales_manager($userId);
    if (!$byPass && (int) $lead->assigned_to !== (int) $userId) {
        crm_err(crm_t('CRM_ERR_LEAD_NOT_ASSIGNED'), null, 403);
    }
    if ($lead->status === 'closed') {
        crm_err(crm_t('CRM_ERR_LEAD_CLOSED'), null, 400);
    }

    $res = $db->query(
        "SELECT * FROM plg_ehcrm_call_results WHERE key_name = ? AND active = 1 LIMIT 1",
        [$resultKey]
    )->first();
    if (!$res) {
        crm_err(crm_t('CRM_ERR_BAD_RESULT'), null, 400);
    }

    /* ميعاد المتابعة: "YYYY-MM-DDTHH:MM" من الواجهة → datetime */
    $fu = null;
    if ($nextFollowup !== null && $nextFollowup !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', (string) $nextFollowup)) {
            crm_err(crm_t('CRM_ERR_BAD_FOLLOWUP'), null, 400);
        }
        $fu = str_replace('T', ' ', substr((string) $nextFollowup, 0, 16)) . ':00';
    }
    if ((int) $res->requires_followup === 1 && $fu === null) {
        crm_err(crm_t('CRM_ERR_RESULT_NEEDS_DATE'), null, 400);
    }

    /* الحالة الجديدة حسب flags النتيجة */
    $newStatus = (string) $lead->status;
    if ((int) $res->is_final === 1) {
        $newStatus = 'closed';
    } elseif ((int) $res->requires_followup === 1) {
        $newStatus = 'followup';
    } elseif ((int) $res->is_contact === 1) {
        $newStatus = 'contacted';
    }
    $isClosed   = ($newStatus === 'closed');
    $prevStatus = (string) $lead->status;
    $now        = crm_now();

    $db->update('plg_ehcrm_leads', $leadId, [
        'status'            => $newStatus,
        'current_result_id' => (int) $res->id,
        'last_call_at'      => $now,
        'calls_count'       => (int) $lead->calls_count + 1,
        'next_followup_at'  => $isClosed ? null : $fu,
        'closed_at'         => $isClosed ? $now : $lead->closed_at,
        'updated_at'        => $now,
    ]);

    $db->insert('plg_ehcrm_call_logs', [
        'lead_id'           => $leadId,
        'user_id'           => (int) $userId,
        'result_id'         => (int) $res->id,
        'result_key'        => (string) $res->key_name,
        'notes'             => $notes !== '' ? $notes : null,
        'duration_min'      => 0,
        'next_followup_at'  => $isClosed ? null : $fu,
        'call_at'           => $now,
        'prev_status'       => $prevStatus,
        'new_status'        => $newStatus,
        'created_at'        => $now,
    ]);

    crm_log('call_save', 'plg_ehcrm_leads', $leadId);

    return [
        'status'           => $newStatus,
        'result'           => (string) $res->name_ar,
        'next_followup_at' => $isClosed ? null : $fu,
    ];
}

/* ------------------------------------------------------------------ */
/* عروض لوحة المبيعات                                                  */
/* ------------------------------------------------------------------ */

/** كروت الملخّص: مفتوح / لسه ما اتكلمتهمش / متابعة النهاردة / مكالمات النهاردة */
function crm_my_stats(int $uid): array
{
    $db  = crm_db();
    $row = $db->query(
        "SELECT
            SUM(CASE WHEN status <> 'closed' THEN 1 ELSE 0 END) AS open_cnt,
            SUM(CASE WHEN status <> 'closed' AND calls_count = 0 THEN 1 ELSE 0 END) AS uncontacted,
            SUM(CASE WHEN status <> 'closed' AND next_followup_at IS NOT NULL
                      AND next_followup_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                     THEN 1 ELSE 0 END) AS today_follow
           FROM plg_ehcrm_leads
          WHERE assigned_to = ?",
        [(int) $uid]
    )->first();

    $callsToday = $db->query(
        "SELECT COUNT(*) AS c FROM plg_ehcrm_call_logs WHERE user_id = ? AND call_at >= CURDATE()",
        [(int) $uid]
    )->first()->c;

    return [
        'open'         => (int) ($row->open_cnt ?? 0),
        'uncontacted'  => (int) ($row->uncontacted ?? 0),
        'today_follow' => (int) ($row->today_follow ?? 0),
        'calls_today'  => (int) $callsToday,
    ];
}

/**
 * عملاء موظف معيّن حسب التاب:
 *   new    → لسه ما اتكلمش عليهم (calls_count = 0 وغير منتهي)
 *   today  → متابعاتهم النهاردة أو متأخرة (أي حاجة قبل بكرة)
 *   closed → المنتهين
 *   all    → كل غير المنتهي (الافتراضي)
 * + بحث بالهاتف أو الاسم.
 * + $include : معرّفات عملاء بتتفرض تظهر مهما كان فلتر التاب
 *   (العميل اللي لسه متسجّلة عليه مكالمة ميفضلش يختفي من الجدول فجأة).
 */
function crm_my_leads(int $uid, string $tab, string $q = '', array $include = [], int $limit = 300): array
{
    $sql = "SELECT l.id, l.phone, l.name, l.city, l.status, l.calls_count,
                   l.last_call_at, l.next_followup_at, l.message,
                   r.name_ar AS result_name, r.color AS result_color
              FROM plg_ehcrm_leads l
              LEFT JOIN plg_ehcrm_call_results r ON r.id = l.current_result_id
             WHERE l.assigned_to = ?";
    $params = [(int) $uid];

    switch ($tab) {
        case 'new':
            $tabCond = " l.calls_count = 0";
            break;
        case 'today':
            $tabCond = " l.next_followup_at IS NOT NULL
                         AND l.next_followup_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'closed':
            $tabCond = " l.status = 'closed'";
            break;
        default:
            $tabCond = " l.status <> 'closed'";
    }

    if ($include) {
        $ids = implode(',', array_map('intval', $include));
        $sql .= " AND ((" . $tabCond . ") OR l.id IN (" . $ids . "))";
    } else {
        $sql .= " AND " . $tabCond;
    }

    if ($q !== '') {
        $sql .= " AND (l.phone LIKE ? OR l.name LIKE ?) ";
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY l.updated_at DESC, l.id DESC LIMIT " . (int) $limit;

    $rows = crm_db()->query($sql, $params)->results();

    return array_map(function ($r) {
        return [
            'id'               => (int) $r->id,
            'phone'            => (string) $r->phone,
            'name'             => (string) $r->name,
            'city'             => (string) $r->city,
            'message'          => (string) $r->message,
            'status'           => (string) $r->status,
            'calls_count'      => (int) $r->calls_count,
            'last_call_at'     => (string) ($r->last_call_at ?? ''),
            'next_followup_at' => (string) ($r->next_followup_at ?? ''),
            'result_name'      => (string) ($r->result_name ?? ''),
            'result_color'     => (string) ($r->result_color ?? ''),
        ];
    }, $rows);
}

/** سجل مكالمات الموظف (الأحدث أولًا) */
function crm_my_calls(int $uid, int $limit = 200): array
{
    $rows = crm_db()->query(
        "SELECT c.call_at, c.notes, c.next_followup_at, c.result_key,
                l.id AS lead_id, l.phone, l.name,
                r.name_ar AS result_name, r.color AS result_color
           FROM plg_ehcrm_call_logs c
           LEFT JOIN plg_ehcrm_leads l ON l.id = c.lead_id
           LEFT JOIN plg_ehcrm_call_results r ON r.id = c.result_id
          WHERE c.user_id = ?
          ORDER BY c.call_at DESC, c.id DESC
          LIMIT " . (int) $limit,
        [(int) $uid]
    )->results();

    return array_map(function ($r) {
        return [
            'call_at'          => (string) $r->call_at,
            'lead_id'          => (int) $r->lead_id,
            'phone'            => (string) ($r->phone ?? ''),
            'name'             => (string) ($r->name ?? ''),
            'result_name'      => (string) ($r->result_name ?? $r->result_key),
            'result_color'     => (string) ($r->result_color ?? ''),
            'notes'            => (string) ($r->notes ?? ''),
            'next_followup_at' => (string) ($r->next_followup_at ?? ''),
        ];
    }, $rows);
}
