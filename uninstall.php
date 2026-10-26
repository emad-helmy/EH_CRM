<?php
/* uninstall.php — يلغي تسجيل البلجن (الجداول تبقى يدويًا) */
require_once __DIR__ . '/../../../users/init.php';

if (!in_array($user->data()->id, $master_account)) {
    Redirect::to($us_url_root . 'users/admin.php');
    die();
}

require_once __DIR__ . '/functions.php';

/* 1) شيل عناصر المنيو */
$db = DB::getInstance();
$menuCrm = $db->query("SELECT id FROM us_menu_items WHERE menu=1 AND type='dropdown' AND label='CRM' LIMIT 1")->first();
if ($menuCrm) {
    $db->query("DELETE FROM us_menu_items WHERE menu=1 AND (id=? OR parent=?)", [(int)$menuCrm->id, (int)$menuCrm->id]);
}

/* 2) شيل الصفحات المنشورة من usersc/ */
$pages = [
    'eh_index.php','eh_leads.php','eh_lead_add.php','eh_lead_view.php','eh_lead_import.php',
    'eh_batches.php','eh_distribute.php','eh_my_leads.php','eh_my_calls.php','eh_reports.php',
    'eh_settings.php','eh_no_access.php','eh_ceo_control.php','eh_configure.php',
];
$dst = dirname(CRM_PLUGIN_DIR, 2); // .../usersc
foreach ($pages as $f) {
    $path = $dst . '/' . $f;
    if (is_file($path)) { @unlink($path); }
}
$endpoints = ['data_in_.php', 'show_data_.php'];
foreach ($endpoints as $f) {
    $path = $dst . '/scripts/' . $f;
    if (is_file($path)) { @unlink($path); }
}
$jsPath = $dst . '/assets/crm.js';
if (is_file($jsPath)) { @unlink($jsPath); }

/* 3) شيل البلجن من us_plugins */
$db->query("DELETE FROM us_plugins WHERE plugin = 'eh_crm'");

err('CRM uninstalled (tables remain — drop manually if needed)');
Redirect::to($us_url_root . 'users/admin.php?view=plugins');