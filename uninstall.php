<?php
/* uninstall.php — يلغي تسجيل البلجن وينظف كل اللي الـinstall عمله */
require_once __DIR__ . '/../../../users/init.php';

if (!in_array($user->data()->id, $master_account)) {
    Redirect::to($us_url_root . 'users/admin.php');
    die();
}

require_once __DIR__ . '/functions.php';
$db = DB::getInstance();

/* 1) شيل dropdown الـCRM وعناصره من أي منيو (الإنشاء بيبقى في منيو يختاره الأدمن) */
$dropdowns = $db->query("SELECT id, menu FROM us_menu_items WHERE type = 'dropdown' AND label = 'CRM'")->results();
foreach ($dropdowns as $d) {
    $db->query(
        "DELETE FROM us_menu_items WHERE menu = ? AND (id = ? OR parent = ?)",
        [(int)$d->menu, (int)$d->id, (int)$d->id]
    );
}

/* 2) شيل الصفحات المنشورة في usersc/ (نفس صفحات files/) */
$dst = dirname(CRM_PLUGIN_DIR, 2); // .../usersc
foreach (glob(CRM_PLUGIN_DIR . '/files/*.php') ?: [] as $f) {
    $path = $dst . '/' . basename($f);
    if (is_file($path)) { @unlink($path); }
}

/* 3) شيل صفوف الصفحات من جدول pages (بأسماء محددة — مش LIKE عشان صفحات بلجنات تانية زي eh_chat متتمسش) */
$crmPages = [
    'usersc/eh_dashboard.php', 'usersc/eh_leads.php', 'usersc/eh_lead_view.php',
    'usersc/eh_lead_import.php', 'usersc/eh_batches.php', 'usersc/eh_distribute.php',
    'usersc/eh_my_leads.php', 'usersc/eh_my_calls.php', 'usersc/eh_reports.php',
    'usersc/eh_settings.php', 'usersc/eh_ceo_control.php', 'usersc/eh_no_access.php',
];
$ph = implode(',', array_fill(0, count($crmPages), '?'));
$db->query("DELETE FROM pages WHERE page IN ({$ph})", $crmPages);

/* 4) شيل جداول البلجن (اللي عملها sql/database.sql) */
$crmTables = [
    'plg_ehcrm_activity_log',
    'plg_ehcrm_call_logs',
    'plg_ehcrm_call_results',
    'plg_ehcrm_import_batches',
    'plg_ehcrm_import_staging',
    'plg_ehcrm_leads',
    'plg_ehcrm_lead_assignments',
    'plg_ehcrm_projects',
    'plg_ehcrm_sales_profile',
    'plg_ehcrm_settings',
    'plg_ehcrm_sources',
];
foreach ($crmTables as $t) {
    $db->query("DROP TABLE IF EXISTS `{$t}`");
}

/* 5) شيل مفاتيح اللغة اللي الـinstall أضافها (بلوك EH_CRM LANG) */
$langDir = $dst . '/lang/';
foreach (['en-US.php', 'ar-EG.php'] as $lg) {
    $p = $langDir . $lg;
    if (!is_file($p)) { continue; }
    $cur = (string)file_get_contents($p);
    $re  = '~/\* ==== EH_CRM LANG BEGIN ==== \*/.*?/\* ==== EH_CRM LANG END ==== \*/\n?~s';
    if (preg_match($re, $cur)) {
        $cur = preg_replace($re, '', $cur, 1);
        $cur = preg_replace("/\n{3,}/", "\n\n", $cur);
        @file_put_contents($p, $cur);
    }
}

/* 6) شيل البلجن من us_plugins */
$db->query("DELETE FROM us_plugins WHERE plugin = 'eh_crm'");

err('CRM uninstalled — tables, pages, menu items and lang keys removed');
Redirect::to($us_url_root . 'users/admin.php?view=plugins');
