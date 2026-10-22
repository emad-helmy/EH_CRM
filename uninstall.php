<?php
/* uninstall.php — يلغي تسجيل البلجن (الجداول يبقى يدويًا) */
require_once __DIR__ . '/../../../users/init.php';
if (!in_array($user->data()->id, $master_account)) { Redirect::to($us_url_root . 'users/admin.php'); die(); }
$db = DB::getInstance();
$db->query("DELETE FROM us_plugins WHERE plugin = 'eh_crm'");
crm_sync_ini();
err('CRM uninstalled (tables remain — drop manually if needed)');
Redirect::to($us_url_root . 'users/admin.php?view=plugins');
