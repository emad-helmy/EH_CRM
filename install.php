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
if ($checkQ->count() === 0) {
    $db->query("INSERT INTO `us_plugins` (`plugin`, `status`) VALUES (?, 'inactive')", [$plugin_name]);
}

/* ---------- 2) تشغيل ملفات SQL بالترتيب (mysqli_multi_query) ---------- */
$host = Config::get('mysql/host');
$host = str_replace('localhost; port=3306', '127.0.0.1', $host);
$host = str_replace('localhost', '127.0.0.1', $host);
$port = 3306;
if (strpos($host, 'port=') !== false) {
    preg_match('/port=(\d+)/', $host, $m);
    if (isset($m[1])) { $port = (int)$m[1]; }
    $host = preg_replace('/;\s*port=\d+/', '', $host);
}
$mysqli = new mysqli($host, Config::get('mysql/username'), Config::get('mysql/password'), Config::get('mysql/db'), $port);
if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

$sqlDir = CRM_PLUGIN_DIR . '/sql';
$sqlFiles = [
    '001_crm_schema.sql',
    '002_crm_seed.sql',
    '003_crm_supervisor.sql',
    '004_crm_home.sql',
    '005_crm_lead_message.sql',
    '007_crm_ceo_control.sql',
    '008_crm_menu_i18n.sql',
];
foreach ($sqlFiles as $f) {
    $path = $sqlDir . '/' . $f;
    if (!is_file($path)) { continue; }
    $sql = (string) file_get_contents($path);
    // strip BOM
    if (substr($sql, 0, 3) === "\xEF\xBB\xBF") {
        $sql = substr($sql, 3);
    }
    if ($sql === '' || trim($sql) === '') { continue; }
    if ($mysqli->multi_query($sql)) {
        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->next_result());
    } else {
        // log error but continue
        error_log("EH_CRM SQL error in $f: " . $mysqli->error);
    }
}
$mysqli->close();

/* ---------- 3) إنشاء البرمشنز الـ4 ---------- */
$crmPermNames = ['CRM Data Entry', 'CRM Sales', 'CRM Sales Manager', 'CRM Supervisor'];
foreach ($crmPermNames as $name) {
    $exists = $db->query("SELECT id FROM permissions WHERE name = ?", [$name])->first();
    if (!$exists) {
        $db->insert('permissions', ['name' => $name, 'descrip' => $name]);
    }
}

/* ---------- 4) إعطاء الأدمن (user 1) كل البرمشنز ---------- */
$adminPermIds = [1, 2];
foreach ($crmPermNames as $name) {
    $r = $db->query("SELECT id FROM permissions WHERE name = ?", [$name])->first();
    if ($r) { $adminPermIds[] = (int) $r->id; }
}
$db->update('users', 1, ['permissions' => implode(',', $adminPermIds)]);

/* ---------- 5) تثبيت مفاتيح اللغة ---------- */
if (function_exists('crm_lang_install_keys')) {
    crm_lang_install_keys();
}

/* ---------- 6) نشر الصفحات + endpoints + crm.js ---------- */
crm_deploy_pages();

/* ---------- 7) تسجيل الدخول ---------- */
logger($user->data()->id, "USPlugins", "eh_crm Installed");
err("EH_CRM installed successfully — activate from Plugin Manager.");
Redirect::to($us_url_root . 'users/admin.php?view=plugins');