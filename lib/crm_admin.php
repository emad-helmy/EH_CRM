<?php
/*
 =============================================================================
 CRM — boudy-elhegaz  |  crm_admin.php
 -----------------------------------------------------------------------------
  الخطوة 7: إعدادات CRM — قوائم + حفظ (نتائج المكالمات / المصادر / المشاريع /
  بروفايل فريق المبيعات / إعدادات eh_crm_settings).
  الصفحات بتستدعي القوائم وتستبدلها JSON للجافاسكربت، والحفظ عن طريق
  data_in_.php (result_save | source_save | project_save | sales_profile_save |
  settings_save).
 =============================================================================
*/

if (!defined('CRM_BOOT_LOADED') && !function_exists('crm_db')) {
    die('Direct access not allowed.');
}

/* crm_full_name محتاجة مكتبة leads (require_once → مش هتتكرر) */
require_once __DIR__ . '/crm_leads.php';

/* ------------------------------------------------------------------ */
/* القوائم (للصفحة: تتحوّل JSON وتحوّل لجافاسكربت)                     */
/* ------------------------------------------------------------------ */

/** كل تصنيفات نتائج المكالمات (حتى الموقوفة) */
function crm_admin_results()
{
    return crm_db()->query(
        "SELECT id, key_name, name_ar, color, sort_order,
                is_contact, is_interest, is_final, requires_followup, active
           FROM eh_crm_call_results
          ORDER BY sort_order ASC, id ASC"
    )->results();
}

/** كل المصادر (حتى الموقوفة) */
function crm_admin_sources()
{
    return crm_db()->query(
        "SELECT id, name, color, sort_order, active
           FROM eh_crm_sources
          ORDER BY sort_order ASC, id ASC"
    )->results();
}

/** كل المشاريع (حتى الموقوفة) */
function crm_admin_projects()
{
    return crm_db()->query(
        "SELECT id, name, developer, city, sort_order, active
           FROM eh_crm_projects
          ORDER BY sort_order ASC, id ASC"
    )->results();
}

/**
 * فريق المبيعات + بروفايلاته + حملهم الحالي:
 *   available / max_leads / target_monthly / notes من eh_crm_sales_profile
 *   open_leads = عدد عملائه غير المنتهين دلوقتي
 */
function crm_admin_team()
{
    $users = crm_sales_users();

    $profiles = [];
    foreach (crm_db()->query(
        "SELECT user_id, available, max_leads, target_monthly, notes FROM eh_crm_sales_profile"
    )->results() as $r) {
        $profiles[(int) $r->user_id] = $r;
    }

    $loads = [];
    foreach (crm_db()->query(
        "SELECT assigned_to, COUNT(*) AS cnt
           FROM eh_crm_leads
          WHERE assigned_to IS NOT NULL AND status <> 'closed'
          GROUP BY assigned_to"
    )->results() as $r) {
        $loads[(int) $r->assigned_to] = (int) $r->cnt;
    }

    return array_map(function ($u) use ($profiles, $loads) {
        $p = isset($profiles[(int) $u->id]) ? $profiles[(int) $u->id] : null;
        return [
            'user_id'       => (int) $u->id,
            'name'          => crm_full_name($u->fname, $u->lname, $u->username),
            'email'         => (string) $u->email,
            'available'     => $p ? (int) $p->available : 1,
            'max_leads'     => $p ? (int) $p->max_leads : 0,
            'target_monthly' => $p ? (int) $p->target_monthly : 0,
            'notes'         => $p ? (string) $p->notes : '',
            'open_leads'    => isset($loads[(int) $u->id]) ? $loads[(int) $u->id] : 0,
        ];
    }, $users);
}

/* ------------------------------------------------------------------ */
/* الحفظ                                                               */
/* ------------------------------------------------------------------ */

/** حفظ تصنيف نتيجة مكالمة (id=0 → إضافة) */
function crm_admin_result_save(array $p)
{
    $id    = (int) ($p['id'] ?? 0);
    $name  = trim((string) ($p['name_ar'] ?? ''));
    $key   = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($p['key_name'] ?? '')));
    $color = trim((string) ($p['color'] ?? ''));

    if ($name === '') {
        crm_err(crm_t('CRM_ERR_RESULT_NAME'), null, 400);
    }

    $flags = [
        'is_contact'        => !empty($p['is_contact']) ? 1 : 0,
        'is_interest'       => !empty($p['is_interest']) ? 1 : 0,
        'is_final'          => !empty($p['is_final']) ? 1 : 0,
        'requires_followup' => !empty($p['requires_followup']) ? 1 : 0,
    ];
    if ($flags['is_final'] && $flags['requires_followup']) {
        crm_err(crm_t('CRM_ERR_FINAL_RESULT'), null, 400);
    }

    $db = crm_db();

    if ($id > 0) {
        $row = $db->query("SELECT id FROM eh_crm_call_results WHERE id = ?", [$id])->first();
        if (!$row) {
            crm_err(crm_t('CRM_ERR_RESULT_NOT_FOUND'), null, 404);
        }
        $data = [
            'name_ar' => $name,
            'color'   => $color,
            'active'  => !empty($p['active']) ? 1 : 0,
        ] + $flags;
        if ($key !== '') {
            $dup = $db->query("SELECT id FROM eh_crm_call_results WHERE key_name = ? AND id <> ?", [$key, $id])->first();
            if ($dup) {
                crm_err(crm_t('CRM_ERR_RESULT_KEY_DUP'), null, 400);
            }
            $data['key_name'] = $key;
        }
        $db->update('eh_crm_call_results', $id, $data);
        return ['id' => $id];
    }

    if ($key === '') {
        crm_err(crm_t('CRM_ERR_RESULT_KEY'), null, 400);
    }
    $dup = $db->query("SELECT id FROM eh_crm_call_results WHERE key_name = ?", [$key])->first();
    if ($dup) {
        crm_err(crm_t('CRM_ERR_RESULT_KEY_DUP'), null, 400);
    }
    $db->insert('eh_crm_call_results', [
        'key_name'   => $key,
        'name_ar'    => $name,
        'color'      => $color,
        'sort_order' => 100,
        'active'     => 1,
        'created_at' => crm_now(),
    ] + $flags);
    return ['id' => (int) $db->lastId()];
}

/** حفظ مصدر إدخال (id=0 → إضافة) */
function crm_admin_source_save(array $p)
{
    $id    = (int) ($p['id'] ?? 0);
    $name  = trim((string) ($p['name'] ?? ''));
    $color = trim((string) ($p['color'] ?? ''));
    if ($name === '') {
        crm_err(crm_t('CRM_ERR_SOURCE_NAME'), null, 400);
    }

    $db = crm_db();
    if ($id > 0) {
        $row = $db->query("SELECT id FROM eh_crm_sources WHERE id = ?", [$id])->first();
        if (!$row) {
            crm_err(crm_t('CRM_ERR_SOURCE_NOT_FOUND'), null, 404);
        }
        $dup = $db->query("SELECT id FROM eh_crm_sources WHERE name = ? AND id <> ?", [$name, $id])->first();
        if ($dup) {
            crm_err(crm_t('CRM_ERR_SOURCE_DUP'), null, 400);
        }
        $db->update('eh_crm_sources', $id, [
            'name'   => $name,
            'color'  => $color,
            'active' => !empty($p['active']) ? 1 : 0,
        ]);
        return ['id' => $id];
    }

    $dup = $db->query("SELECT id FROM eh_crm_sources WHERE name = ?", [$name])->first();
    if ($dup) {
        crm_err(crm_t('CRM_ERR_SOURCE_DUP'), null, 400);
    }
    $db->insert('eh_crm_sources', [
        'name'       => $name,
        'color'      => $color,
        'active'     => 1,
        'sort_order' => 100,
        'created_at' => crm_now(),
    ]);
    return ['id' => (int) $db->lastId()];
}

/** حفظ مشروع عقاري (id=0 → إضافة) */
function crm_admin_project_save(array $p)
{
    $id        = (int) ($p['id'] ?? 0);
    $name      = trim((string) ($p['name'] ?? ''));
    $developer = trim((string) ($p['developer'] ?? ''));
    $city      = trim((string) ($p['city'] ?? ''));
    if ($name === '') {
        crm_err(crm_t('CRM_ERR_PROJECT_NAME'), null, 400);
    }

    $db = crm_db();
    if ($id > 0) {
        $row = $db->query("SELECT id FROM eh_crm_projects WHERE id = ?", [$id])->first();
        if (!$row) {
            crm_err(crm_t('CRM_ERR_PROJECT_NOT_FOUND'), null, 404);
        }
        $dup = $db->query("SELECT id FROM eh_crm_projects WHERE name = ? AND id <> ?", [$name, $id])->first();
        if ($dup) {
            crm_err(crm_t('CRM_ERR_PROJECT_DUP'), null, 400);
        }
        $db->update('eh_crm_projects', $id, [
            'name'      => $name,
            'developer' => $developer,
            'city'      => $city,
            'active'    => !empty($p['active']) ? 1 : 0,
        ]);
        return ['id' => $id];
    }

    $dup = $db->query("SELECT id FROM eh_crm_projects WHERE name = ?", [$name])->first();
    if ($dup) {
        crm_err(crm_t('CRM_ERR_PROJECT_DUP'), null, 400);
    }
    $db->insert('eh_crm_projects', [
        'name'       => $name,
        'developer'  => $developer,
        'city'       => $city,
        'active'     => 1,
        'sort_order' => 100,
        'created_at' => crm_now(),
    ]);
    return ['id' => (int) $db->lastId()];
}

/** حفظ بروفايل موظف مبيعات (متاح/سقف/تارجت/ملاحظات) */
function crm_admin_profile_save(array $p)
{
    $uid = (int) ($p['user_id'] ?? 0);
    if ($uid <= 0) {
        crm_err(crm_t('CRM_ERR_PICK_EMPLOYEE'), null, 400);
    }
    /* لازم يكون فعلًا من فريق المبيعات */
    $pid = crm_perm_id('sales');
    $ok  = $pid > 0 && crm_db()->query(
        "SELECT user_id FROM user_permission_matches WHERE user_id = ? AND permission_id = ?",
        [$uid, $pid]
    )->first();
    if (!$ok) {
        crm_err(crm_t('CRM_ERR_NOT_SALES_TEAM'), null, 400);
    }

    $data = [
        'available'      => !empty($p['available']) ? 1 : 0,
        'max_leads'      => max(0, (int) ($p['max_leads'] ?? 0)),
        'target_monthly' => max(0, (int) ($p['target_monthly'] ?? 0)),
        'notes'          => mb_substr(trim((string) ($p['notes'] ?? '')), 0, 255),
        'updated_at'     => crm_now(),
    ];

    $db  = crm_db();
    $row = $db->query("SELECT user_id FROM eh_crm_sales_profile WHERE user_id = ?", [$uid])->first();
    if ($row) {
        /* ملاحظة: update() في UserSpice بيفتراض عمود اسمه id — والجدول ده الـ
           PK بتاعه user_id، فبنعمل UPDATE صريح بالـWHERE المناسب */
        $db->query(
            "UPDATE eh_crm_sales_profile
                SET available = ?, max_leads = ?, target_monthly = ?, notes = ?, updated_at = ?
              WHERE user_id = ?",
            [
                $data['available'], $data['max_leads'], $data['target_monthly'],
                $data['notes'], $data['updated_at'], $uid,
            ]
        );
    } else {
        $db->insert('eh_crm_sales_profile', ['user_id' => $uid] + $data);
    }
    return ['user_id' => $uid];
}

/* ------------------------------------------------------------------ */
/* الإعدادات العامة (eh_crm_settings)                                      */
/* ------------------------------------------------------------------ */

/** المفاتيح القابلة للتعديل من صفحة الإعدادات (قائمة مسموحة — مفيش كتابة لغيرها) */
function crm_admin_settings_keys()
{
    return [
        'auto_assign_on_import'  => 'bool',
        'default_followup_hours' => 'int',
        'recycle_days'           => 'int',
        'recycle_statuses'       => 'text',
        'crm_company_name'       => 'text',
    ];
}

/** قيم الإعدادات القابلة للتعديل حاليًا */
function crm_admin_settings_get()
{
    $out = [];
    foreach (crm_admin_settings_keys() as $k => $type) {
        $out[$k] = (string) crm_setting($k, '');
    }
    return $out;
}

/** حفظ الإعدادات (المفاتيح المسموحة فقط) */
function crm_admin_settings_save(array $p)
{
    $saved = 0;
    foreach (crm_admin_settings_keys() as $k => $type) {
        if (!array_key_exists($k, $p)) {
            continue;   /* المفتاح مش مبعت → سيبه زي ما هو */
        }
        if ($type === 'bool') {
            $v = !empty($p[$k]) ? '1' : '0';
        } elseif ($type === 'int') {
            $v = (string) max(0, (int) $p[$k]);
        } else {
            $v = mb_substr(trim((string) $p[$k]), 0, 500);
        }
        if (crm_set_setting($k, $v) !== false) {
            $saved++;
        }
    }
    if ($saved === 0) {
        crm_err(crm_t('CRM_ERR_NO_SETTINGS'), null, 400);
    }
    return ['saved' => $saved];
}
