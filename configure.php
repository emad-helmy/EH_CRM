<?php
/* configure.php — إعدادات البلجن (placeholder — الإعدادات في صفحة settings.php) */
require_once __DIR__ . '/../../../users/init.php';
if (!in_array($user->data()->id, $master_account)) { Redirect::to($us_url_root . 'users/admin.php'); die(); }
Redirect::to($us_url_root . 'usersc/settings.php');
