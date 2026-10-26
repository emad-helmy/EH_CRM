<?php
/* uninstall.php — يلغي تسجيل البلجن وينظف اللي الـinstall نشره (الجداول تبقى يدويًا) */
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

/* 3) شيل مفاتيح اللغة اللي الـinstall أضافها (بلوك EH_CRM LANG) */
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

/* 4) شيل البلجن من us_plugins */
$db->query("DELETE FROM us_plugins WHERE plugin = 'eh_crm'");

err('CRM uninstalled (tables remain — drop manually if needed)');
Redirect::to($us_url_root . 'users/admin.php?view=plugins');
