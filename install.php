<?php
require_once __DIR__ . '/../../../users/init.php';

if (!in_array($user->data()->id, $master_account)) {
    Redirect::to($us_url_root . 'users/admin.php');
    die();
}

$db = DB::getInstance();
require_once __DIR__ . '/functions.php';

/* 1) تسجيل البلجن */
$db->query("INSERT INTO `us_plugins` (`plugin`, `status`) SELECT 'crm', 'inactive' FROM DUAL
            WHERE NOT EXISTS (SELECT 1 FROM `us_plugins` WHERE `plugin` = 'crm')");

/* 2) صفحات البلجن (deploy) */
crm_deploy_pages();

/* 3) الجداول / البيانات — تُشغّل يدويًا من phpMyAdmin (ملفات sql/001..008) */
/* 4) زامنة الـini */
crm_sync_ini();

err('CRM installed — شغّل ملفات sql/ يدويًا من phpMyAdmin، ثم فعّل البلجن (Activate)');
logger($user->data()->id, "USPlugins", "CRM Installed");
Redirect::to($us_url_root . 'users/admin.php?view=plugins&err=CRM installed - run sql files manually, then Activate');
