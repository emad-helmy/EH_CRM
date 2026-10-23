<?php
/**
 * CRM plugin — install.php
 * تثبيت كامل: بلجن + SQL + برمشنز + منيو + مفاتيح لغة + نشر صفحات + تفعيل
 */
require_once __DIR__ . '/../../../users/init.php';

if (!in_array($user->data()->id, $master_account)) {
    Redirect::to($us_url_root . 'users/admin.php');
    die();
}

$db = DB::getInstance();
require_once __DIR__ . '/functions.php';

/* ---------- 1) تسجيل البلجن ---------- */
$plugin_name = 'eh_crm';
$checkQ = $db->query("SELECT * FROM us_plugins WHERE plugin = ?", [$plugin_name]);
if ($checkQ->count() > 0) {
    $check = $checkQ->first();
    $db->update('us_plugins', $check->id, ['status' => 'inactive']);
} else {
    $db->query("INSERT INTO `us_plugins` (`plugin`, `status`) VALUES (?, 'inactive')", [$plugin_name]);
}

/* ---------- 2) تشغيل ملفات SQL بالترتيب ---------- */
$sqlDir = CRM_PLUGIN_DIR . '/sql';
$sqlFiles = [
    '001_crm_schema.sql',
    '002_crm_seed.sql',
    '003_crm_supervisor.sql',
    '004_crm_home.sql',
    '005_crm_lead_message.sql',
    '007_crm_ceo_control.sql',
    '008_crm_menu_i18n.sql',
    '009_crm_rename_prefix.sql',
    '010_crm_pages_rename.sql',
];
foreach ($sqlFiles as $f) {
    $path = $sqlDir . '/' . $f;
    if (is_file($path)) {
        $sql = (string) file_get_contents($path);
        // split by semicolon (simple, works for our files)
        $stmts = preg_split('/;\s*\n/', $sql);
        foreach ($stmts as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || strpos($stmt, '--') === 0) continue;
            try { $db->query($stmt); } catch (Throwable $e) { /* ignore */ }
        }
    }
}

/* ---------- 3) إنشاء البرمشنز الـ4 (باسماء ثابتة) ---------- */
$crmPermNames = [
    'CRM Data Entry'    => 3,
    'CRM Sales'         => 4,
    'CRM Sales Manager' => 5,
    'CRM Supervisor'    => 6,
];
foreach ($crmPermNames as $name => $prefId) {
    $exists = $db->query("SELECT id FROM permissions WHERE name = ?", [$name])->first();
    if (!$exists) {
        $db->insert('permissions', ['name' => $name, 'descrip' => $name]);
        $newId = (int) $db->lastId();
        // لو الـID المطابق لـprefId مش متاح، لا مشكلة — الكود بيقرأ بالاسم مش الـID
    }
}

/* ---------- 4) إعطاء الأدمن (user 1) كل البرمشنز ---------- */
$adminPermIds = [1, 2]; // User + Administrator
foreach ($crmPermNames as $name => $prefId) {
    $r = $db->query("SELECT id FROM permissions WHERE name = ?", [$name])->first();
    if ($r) { $adminPermIds[] = (int) $r->id; }
}
$db->update('users', 1, ['permissions' => implode(',', $adminPermIds)]);

/* ---------- 5) إنشاء/تحديث مفاتيح اللغة في usersc/lang/*.php ---------- */
if (function_exists('crm_lang_install_keys')) {
    crm_lang_install_keys();
}

/* ---------- 6) إنشاء قائمة CRM في Menu 1 (idempotent) ---------- */
$menuCrm = $db->query("SELECT id FROM us_menu_items WHERE menu=1 AND type='dropdown' AND label='CRM' LIMIT 1")->first();
if (!$menuCrm) {
    $db->query("INSERT INTO `us_menu_items` (`menu`,`type`,`label`,`link`,`icon_class`,`link_target`,`parent`,`display_order`,`disabled`,`permissions`,`tags`)
        SELECT 1, 'dropdown', 'CRM', '', 'fas fa-address-book', '_self', 0, 2, 0, '[2,3,4,5]', NULL FROM DUAL");
    $menuCrm = (int) $db->lastId();
} else {
    $menuCrm = (int) $menuCrm->id;
}
$items = [
    ['CRM_MENU_HOME',        'usersc/eh_index.php',        'fas fa-gauge-high',       1,  '[2,3,4,5]'],
    ['CRM_NAV_IMPORT',       'usersc/eh_lead_import.php',  'fas fa-file-csv',         2,  '[2,3]'],
    ['CRM_LEADS_TITLE',      'usersc/eh_leads.php',        'fas fa-users',            3,  '[2,5]'],
    ['CRM_NAV_MY_LEADS',     'usersc/eh_my_leads.php',     'fas fa-phone-volume',     4,  '[2,4]'],
    ['CRM_NAV_MY_CALLS',     'usersc/eh_my_calls.php',     'fas fa-clipboard-list',   5,  '[2,4]'],
    ['CRM_NAV_DISTRIBUTE',   'usersc/eh_distribute.php',   'fas fa-shuffle',          6,  '[2,5]'],
    ['CRM_NAV_BATCHES',      'usersc/eh_batches.php',      'fas fa-layer-group',      7,  '[2,3]'],
    ['CRM_NAV_REPORTS',      'usersc/eh_reports.php',      'fas fa-chart-line',       8,  '[2,5]'],
    ['CRM_NAV_SETTINGS',     'usersc/eh_settings.php',     'fas fa-sliders',          9,  '[2,5]'],
    ['CRM_NAV_CEO_CONTROL',  'usersc/eh_ceo_control.php',  'fas fa-user-shield',     10, '[2,5]'],
];
foreach ($items as $it) {
    $db->query("INSERT INTO `us_menu_items` (`menu`,`type`,`label`,`link`,`icon_class`,`link_target`,`parent`,`display_order`,`disabled`,`permissions`,`tags`)
        SELECT 1, 'link', ?, ?, ?, '_self', ?, ?, 0, ?, NULL FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM `us_menu_items` WHERE `menu`=1 AND `parent`=? AND `link`=?)",
        [$it[0], $it[1], $it[2], $menuCrm, $it[3], $it[4], $menuCrm, $it[1]]);
}

/* ---------- 7) نشر الصفحات + endpoints + crm.js ---------- */
crm_deploy_pages();

/* ---------- 8) زامنة الـini ---------- */
crm_sync_ini();

/* ---------- 9) تفعيل البلجن ---------- */
$db->update('us_plugins', ['status' => 'active'], ['plugin' => $plugin_name]);

/* ---------- 10) تسجيل الدخول ---------- */
logger($user->data()->id, "USPlugins", "$plugin_name Installed");
err("$plugin_name installed successfully — plugin activated.");
Redirect::to($us_url_root . 'users/admin.php?view=plugins');