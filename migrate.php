<?php
/* migrate.php — إعادة نشر صفحات البلجن (idempotent) */
require_once __DIR__ . '/../../../users/init.php';
if (!in_array($user->data()->id, $master_account)) { Redirect::to($us_url_root . 'users/admin.php'); die(); }
require_once __DIR__ . '/functions.php';
crm_deploy_pages();
err('CRM pages re-deployed');
Redirect::to($us_url_root . 'users/admin.php?view=plugins');
