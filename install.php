<?php
/**
 * CRM plugin — install.php
 * تثبيت: تسجيل البلجن + نشر الصفحات + endpoints + crm.js
 * (الجداول والبيانات: شغّل ملفات sql/ يدويًا من phpMyAdmin — آمنة يتشغل أكثر من مرة)
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

/* ---------- 2) نشر الصفحات + endpoints + crm.js ---------- */
crm_deploy_pages();

/* ---------- 3) تسجيل ---------- */
logger($user->data()->id, "USPlugins", "eh_crm Installed");
err("EH_CRM installed — run sql/ files manually, then Activate.");
Redirect::to($us_url_root . 'users/admin.php?view=plugins');