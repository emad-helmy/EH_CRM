<?php
/*
 =============================================================================
 CRM — boudy-elhegaz  |  crm_reports.php
 -----------------------------------------------------------------------------
  الخطوة 7: تقارير الفترة (حسب الموظف / المصدر / نتيجة المكالمة) — كلها للمدير.
  كل دالة تاخد نطاق تاريخ (from/to بصيغة YYYY-MM-DD) وتطلّع أرقام الفترة دي.
 =============================================================================
*/

if (!defined('CRM_BOOT_LOADED') && !function_exists('crm_db')) {
    die('Direct access not allowed.');
}

/* crm_full_name محتاجة مكتبة leads (require_once → مش هتتكرر) */
require_once __DIR__ . '/crm_leads.php';

/**
 * تنظيف نطاق التاريخ: [from → 00:00:00 / to → 23:59:59].
 * أي قيمة غلط بتتتجاهل (بحدود واسعة بدل ما تكسّر الاستعلام).
 */
function crm_report_range($from, $to)
{
    $f = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $from) ? $from . ' 00:00:00' : '1970-01-01 00:00:00';
    $t = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $to)   ? $to   . ' 23:59:59' : '2099-12-31 23:59:59';
    return [$f, $t];
}

/* ------------------------------------------------------------------ */
/* حسب الموظف                                                          */
/* ------------------------------------------------------------------ */
/**
 * أرقام كل مسئول مبيعات في الفترة:
 *   leads_assigned : عملاء اتسندوا ليه في الفترة (تاريخ التوزيع)
 *   calls          : مكالمات سجّلها في الفترة
 *   closed         : عملاء اتقفلوا وهو ماسكهم في الفترة
 *   followups_open : متابعات مفتوحة دلوقتي (الحالة الحالية — مش بالفترة)
 *   target_monthly : التارجت الشهري من بروفايله
 */
function crm_report_by_user($from, $to)
{
    list($f, $t) = crm_report_range($from, $to);
    $db = crm_db();

    $out = [];
    foreach (crm_sales_users() as $u) {
        $out[(int) $u->id] = [
            'user_id'        => (int) $u->id,
            'name'           => crm_full_name($u->fname, $u->lname, $u->username),
            'leads_assigned' => 0,
            'calls'          => 0,
            'closed'         => 0,
            'followups_open' => 0,
            'target_monthly' => 0,
        ];
    }

    /* مكالمات الفترة */
    $rows = $db->query(
        "SELECT user_id, COUNT(*) AS cnt
           FROM eh_crm_call_logs
          WHERE call_at BETWEEN ? AND ?
          GROUP BY user_id",
        [$f, $t]
    )->results();
    foreach ($rows as $r) {
        if (isset($out[(int) $r->user_id])) {
            $out[(int) $r->user_id]['calls'] = (int) $r->cnt;
        }
    }

    /* عملاء اتسندوا في الفترة */
    $rows = $db->query(
        "SELECT assigned_to, COUNT(*) AS cnt
           FROM eh_crm_leads
          WHERE assigned_at BETWEEN ? AND ? AND assigned_to IS NOT NULL
          GROUP BY assigned_to",
        [$f, $t]
    )->results();
    foreach ($rows as $r) {
        if (isset($out[(int) $r->assigned_to])) {
            $out[(int) $r->assigned_to]['leads_assigned'] = (int) $r->cnt;
        }
    }

    /* منتهين في الفترة (المسندله ليهم وقت الإغلاق) */
    $rows = $db->query(
        "SELECT assigned_to, COUNT(*) AS cnt
           FROM eh_crm_leads
          WHERE closed_at BETWEEN ? AND ? AND assigned_to IS NOT NULL
          GROUP BY assigned_to",
        [$f, $t]
    )->results();
    foreach ($rows as $r) {
        if (isset($out[(int) $r->assigned_to])) {
            $out[(int) $r->assigned_to]['closed'] = (int) $r->cnt;
        }
    }

    /* متابعات مفتوحة حاليًا (الحالة الحالية) */
    $rows = $db->query(
        "SELECT assigned_to, COUNT(*) AS cnt
           FROM eh_crm_leads
          WHERE assigned_to IS NOT NULL
            AND next_followup_at IS NOT NULL
            AND status IN ('followup', 'meeting')
          GROUP BY assigned_to"
    )->results();
    foreach ($rows as $r) {
        if (isset($out[(int) $r->assigned_to])) {
            $out[(int) $r->assigned_to]['followups_open'] = (int) $r->cnt;
        }
    }

    /* التارجت الشهري */
    $rows = $db->query("SELECT user_id, target_monthly FROM eh_crm_sales_profile")->results();
    foreach ($rows as $r) {
        if (isset($out[(int) $r->user_id])) {
            $out[(int) $r->user_id]['target_monthly'] = (int) $r->target_monthly;
        }
    }

    $list = array_values($out);
    usort($list, function ($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    return $list;
}

/* ------------------------------------------------------------------ */
/* حسب المصدر                                                          */
/* ------------------------------------------------------------------ */
/**
 * أرقام كل مصدر في الفترة (عملاء أُدخلوا بالفترة دي):
 *   leads      : عملاء جدد من المصدر ده في الفترة
 *   calls      : مكالمات على عملاء المصدر ده في الفترة
 *   contacted  : عملاء اتكلم عليهم (مميزين) في الفترة
 *   closed     : عملاء من المصدر ده اتقفلوا في الفترة
 */
function crm_report_by_source($from, $to)
{
    list($f, $t) = crm_report_range($from, $to);
    $db = crm_db();

    $out = [];
    foreach ($db->query("SELECT id, name FROM eh_crm_sources ORDER BY sort_order ASC, id ASC")->results() as $s) {
        $out[(int) $s->id] = [
            'source_id'  => (int) $s->id,
            'name'       => (string) $s->name,
            'leads'      => 0,
            'calls'      => 0,
            'contacted'  => 0,
            'closed'     => 0,
        ];
    }

    /* عملاء جدد في الفترة */
    $rows = $db->query(
        "SELECT source_id, COUNT(*) AS cnt
           FROM eh_crm_leads
          WHERE created_at BETWEEN ? AND ?
          GROUP BY source_id",
        [$f, $t]
    )->results();
    foreach ($rows as $r) {
        $id = (int) $r->source_id;
        if (!isset($out[$id])) {
            $out[$id] = ['source_id' => $id, 'name' => crm_t('CRM_REP_NO_SOURCE'), 'leads' => 0, 'calls' => 0, 'contacted' => 0, 'closed' => 0];
        }
        $out[$id]['leads'] = (int) $r->cnt;
    }

    /* مكالمات الفترة على عملاء كل مصدر */
    $rows = $db->query(
        "SELECT l.source_id, COUNT(*) AS cnt, COUNT(DISTINCT c.lead_id) AS contacted
           FROM eh_crm_call_logs c
           JOIN eh_crm_leads l ON l.id = c.lead_id
          WHERE c.call_at BETWEEN ? AND ?
          GROUP BY l.source_id",
        [$f, $t]
    )->results();
    foreach ($rows as $r) {
        $id = (int) $r->source_id;
        if (!isset($out[$id])) {
            $out[$id] = ['source_id' => $id, 'name' => crm_t('CRM_REP_NO_SOURCE'), 'leads' => 0, 'calls' => 0, 'contacted' => 0, 'closed' => 0];
        }
        $out[$id]['calls']     = (int) $r->cnt;
        $out[$id]['contacted'] = (int) $r->contacted;
    }

    /* منتهين في الفترة */
    $rows = $db->query(
        "SELECT source_id, COUNT(*) AS cnt
           FROM eh_crm_leads
          WHERE closed_at BETWEEN ? AND ?
          GROUP BY source_id",
        [$f, $t]
    )->results();
    foreach ($rows as $r) {
        $id = (int) $r->source_id;
        if (!isset($out[$id])) {
            $out[$id] = ['source_id' => $id, 'name' => crm_t('CRM_REP_NO_SOURCE'), 'leads' => 0, 'calls' => 0, 'contacted' => 0, 'closed' => 0];
        }
        $out[$id]['closed'] = (int) $r->cnt;
    }

    return array_values($out);
}

/* ------------------------------------------------------------------ */
/* حسب نتيجة المكالمة                                                  */
/* ------------------------------------------------------------------ */
/** عدد المكالمات في الفترة مجمّع حسب النتيجة */
function crm_report_by_result($from, $to)
{
    list($f, $t) = crm_report_range($from, $to);

    $rows = crm_db()->query(
        "SELECT c.result_key, MIN(r.name_ar) AS name_ar, MIN(r.color) AS color, COUNT(*) AS cnt
           FROM eh_crm_call_logs c
           LEFT JOIN eh_crm_call_results r ON r.key_name = c.result_key
          WHERE c.call_at BETWEEN ? AND ?
          GROUP BY c.result_key
          ORDER BY cnt DESC",
        [$f, $t]
    )->results();

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'key'   => (string) $r->result_key,
            'name'  => ($r->name_ar !== null && $r->name_ar !== '') ? (string) $r->name_ar : (string) $r->result_key,
            'color' => (string) ($r->color ?? ''),
            'count' => (int) $r->cnt,
        ];
    }
    return $out;
}
