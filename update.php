<?php
/* update.php — تحديث البلجن (idempotent) */
require_once __DIR__ . '/../../../users/init.php';
if (!in_array($user->data()->id, $master_account)) { Redirect::to($us_url_root . 'users/admin.php'); die(); }
require_once __DIR__ . '/functions.php';
crm_deploy_pages();
err('CRM updated');
Redirect::to($us_url_root . 'users/admin.php?view=plugins');
