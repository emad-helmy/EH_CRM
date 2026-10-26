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
    $db->query("INSERT INTO `us_plugins` (`plugin`, `status`) VALUES (?, 'active')", [$plugin_name]);
} else {
    $db->query("UPDATE `us_plugins` SET `status` = 'active' WHERE `plugin` = ?", [$plugin_name]);
}

/* ---------- 2) نشر الصفحات + endpoints + crm.js ---------- */
crm_deploy_pages();

/* ---------- 3) دمج ملفات اللغة (lang/) ---------- */
/*
 * القواعد:
 *  - الملف مش موجود في usersc/lang → بينقل زي ما هو.
 *  - الملف موجود → بنستخرج مفاتيح ملف البلجن (include معزول) وبنضيف
 *    المفاتيح الناقصة بس في آخر الملف الهدف — مفيش أي مفتاح موجود
 *    بيتعدل أو بيتمسح (تعديلات المستخدم محفوظة).
 *  - آمن للتكرار: لو كله موجود مفيش أي تغيير.
 */
$langSrcDir = __DIR__ . '/lang';
$langDstDir = $abs_us_root . $us_url_root . 'usersc/lang';
if (!is_dir($langDstDir)) {
    mkdir($langDstDir, 0755, true);
}

/** يرجّع مفاتيح ملف لغة معزولة ($lang = array_merge($lang, array(...))) */
function crm_lang_keys($file)
{
    $lang = [];
    include $file;
    return $lang;
}

$langResults = [];
foreach (glob($langSrcDir . '/*.php') ?: [] as $srcFile) {
    $base    = basename($srcFile);
    $dstFile = $langDstDir . '/' . $base;
    $pluginLang = crm_lang_keys($srcFile);

    if (!is_file($dstFile)) {
        copy($srcFile, $dstFile);
        $langResults[] = "{$base}: تم نقله (ملف جديد)";
        continue;
    }

    $targetLang = crm_lang_keys($dstFile);
    $missing = [];
    foreach ($pluginLang as $k => $v) {
        if (!array_key_exists($k, $targetLang)) {
            $missing[$k] = $v;
        }
    }
    if (empty($missing)) {
        $langResults[] = "{$base}: كله موجود — مفيش تغيير";
        continue;
    }

    $block = ["", "/* ==== EH_CRM LANG BEGIN ==== */", '$lang = array_merge($lang, array('];
    foreach ($missing as $k => $v) {
        $block[] = "    " . var_export((string)$k, true) . " => " . var_export((string)$v, true) . ",";
    }
    $block[] = "));";
    $block[] = "/* ==== EH_CRM LANG END ==== */";
    file_put_contents($dstFile, implode("\n", $block) . "\n", FILE_APPEND);
    $langResults[] = "{$base}: تمت إضافة " . count($missing) . " مفتاح جديد";
}

/* ---------- 4) تسجيل ---------- */
logger($user->data()->id, "USPlugins", "eh_crm Installed");
err("EH_CRM installed — run sql/ files manually, then Activate.");
if (!empty($langResults)) {
    foreach ($langResults as $lr) {
        err("Lang: " . $lr);
    }
}