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

/* ---------- 2) نشر الصفحات من files/ إلى usersc/ ---------- */
$deployReport = crm_deploy_pages();
foreach ($deployReport['copied'] as $f)  { err("Deployed: {$f}"); }
foreach ($deployReport['skipped'] as $f) { err("Deploy skipped (up-to-date): {$f}"); }
foreach ($deployReport['failed'] as $f)  { err("DEPLOY FAILED: {$f}"); }
foreach ($deployReport['missing'] as $f) { err("DEPLOY MISSING SOURCE: {$f}"); }

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
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>EH CRM — Install</title>
<style>
  body { font-family: Tahoma, Arial, sans-serif; background:#f4f6f9; padding:24px; }
  .box { background:#fff; border:1px solid #ddd; border-radius:8px; max-width:720px; margin:0 auto 16px; padding:16px 20px; }
  h1 { font-size:20px; margin:0 0 8px; }
  ul { margin:8px 0; padding-right:20px; }
  .ok { color:#1a7f37; } .warn { color:#9a6700; } .bad { color:#c62828; }
  code { background:#eef; padding:1px 5px; border-radius:4px; }
</style>
</head>
<body>
  <div class="box">
    <h1>EH CRM — تم التثبيت ✅</h1>
    <p>الخطوات الجاية:</p>
    <ul>
      <li>شغّل <code>sql/database.sql</code> من phpMyAdmin (الجداول + تسجيل الصفحات).</li>
      <li>فعّل البلجن من <code>Admin → Plugins</code>.</li>
      <li>افتح صفحة الـConfigure: <b>انشاء الصلاحيات</b> ثم <b>انشاء القوائم</b>.</li>
    </ul>
  </div>

  <div class="box">
    <h1>تقرير نشر الصفحات (files/ → usersc/)</h1>
    <?php if (!empty($deployReport['copied'])): ?>
      <p class="ok"><b>اتنشرت (<?= count($deployReport['copied']) ?>):</b></p>
      <ul><?php foreach ($deployReport['copied'] as $f) { echo '<li>' . htmlspecialchars($f) . '</li>'; } ?></ul>
    <?php endif; ?>
    <?php if (!empty($deployReport['skipped'])): ?>
      <p class="warn"><b>موجودة ومحدّثة (<?= count($deployReport['skipped']) ?>):</b></p>
      <ul><?php foreach ($deployReport['skipped'] as $f) { echo '<li>' . htmlspecialchars($f) . '</li>'; } ?></ul>
    <?php endif; ?>
    <?php if (!empty($deployReport['missing'])): ?>
      <p class="bad"><b>ناقصة من files/ (<?= count($deployReport['missing']) ?>) — الرفع على السيرفر مش كامل:</b></p>
      <ul><?php foreach ($deployReport['missing'] as $f) { echo '<li>' . htmlspecialchars($f) . '</li>'; } ?></ul>
    <?php endif; ?>
    <?php if (!empty($deployReport['failed'])): ?>
      <p class="bad"><b>فشل (<?= count($deployReport['failed']) ?>):</b></p>
      <ul><?php foreach ($deployReport['failed'] as $f) { echo '<li>' . htmlspecialchars($f) . '</li>'; } ?></ul>
    <?php endif; ?>
    <?php if (empty($deployReport['failed'])): ?>
      <p class="ok">كل الصفحات اتنشرت بنجاح.</p>
    <?php endif; ?>
  </div>

  <?php if (!empty($langResults)): ?>
  <div class="box">
    <h1>تقرير ملفات اللغة</h1>
    <ul><?php foreach ($langResults as $lr) { echo '<li>' . htmlspecialchars($lr) . '</li>'; } ?></ul>
  </div>
  <?php endif; ?>
</body>
</html>