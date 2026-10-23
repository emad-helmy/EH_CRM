<?php
/**
 * CRM plugin — functions.php (auto-loaded by UserSpice when active)
 */
if (count(get_included_files()) == 1) die();

define('CRM_PLUGIN_DIR', dirname(__FILE__));

/* ---- نشر صفحات البلجن في usersc/ (مثل eh_chat) ---- */
if (!function_exists('crm_deploy_pages')) {
    function crm_deploy_pages()
    {
        $src = CRM_PLUGIN_DIR . '/assets/pages';
        $dst = dirname(CRM_PLUGIN_DIR, 2); // .../usersc
        foreach (['eh_index.php','eh_leads.php','eh_lead_add.php','eh_lead_view.php','eh_lead_import.php',
                  'eh_batches.php','eh_distribute.php','eh_my_leads.php','eh_my_calls.php','eh_reports.php',
                  'eh_settings.php','eh_no_access.php','eh_ceo_control.php','eh_configure.php'] as $f) {
            $from = "$src/$f";
            $to   = "$dst/$f";
            if (file_exists($from) && (!file_exists($to) || filemtime($from) > filemtime($to))) {
                @copy($from, $to);
            }
        }
        foreach (['data_in_.php','show_data_.php'] as $f) {
            $from = CRM_PLUGIN_DIR . "/ajax/$f";
            $to   = $dst . "/scripts/$f";
            if (file_exists($from) && (!file_exists($to) || filemtime($from) > filemtime($to))) {
                @mkdir(dirname($to), 0755, true);
                @copy($from, $to);
            }
        }
        $from = CRM_PLUGIN_DIR . '/assets/js/crm.js';
        $to   = $dst . '/assets/crm.js';
        if (file_exists($from) && (!file_exists($to) || filemtime($from) > filemtime($to))) {
            @copy($from, $to);
        }
    }
}

/* ---- لغة البلجن: دمج مفاتيح CRM_* في $lang ---- */
if (!function_exists('crm_load_lang')) {
    function crm_load_lang()
    {
        global $lang;
        static $loaded = false;
        if ($loaded) return;
        $loaded = true;
        if (!is_array($lang)) { $lang = []; }
        $lg = (string)($_SESSION['us_lang'] ?? 'en-US');
        $file = CRM_PLUGIN_DIR . '/lang/' . $lg . '.php';
        if (!file_exists($file)) {
            $file = CRM_PLUGIN_DIR . '/lang/en-US.php';
        }
        if (file_exists($file)) {
            include $file;
        }
    }
}

/* ---- لغة البلجن: تثبيت/إزالة مفاتيح CRM_* في usersc/lang/*.php (مثل eh_chat) ---- */
if (!function_exists('crm_lang_dst_dir')) {
    function crm_lang_dst_dir()
    {
        global $abs_us_root, $us_url_root;
        $d = rtrim($abs_us_root . $us_url_root, '/\\') . '/usersc/lang/';
        if (!is_dir($d)) { @mkdir($d, 0755, true); }
        return $d;
    }
}

if (!function_exists('crm_lang_marks')) {
    function crm_lang_marks()
    {
        return ["/* ==== CRM LANG BEGIN ==== */", "/* ==== CRM LANG END ==== */"];
    }
}

if (!function_exists('crm_lang_install_keys')) {
    function crm_lang_install_keys()
    {
        $names = ['en-US.php' => 'English', 'ar-EG.php' => 'Arabic (EG)', 'ar-SA.php' => 'Arabic (SA)'];
        $dir   = crm_lang_dst_dir();
        list($MB, $ME) = crm_lang_marks();
        $re    = '~' . preg_quote($MB, '~') . '(.*?)' . preg_quote($ME, '~') . '~s';

        foreach ($names as $lg => $lgName) {
            $src = CRM_PLUGIN_DIR . '/lang/' . $lg;
            if (!is_file($src)) { continue; }
            $srcTxt = (string) file_get_contents($src);
            if (!preg_match('/array_merge\s*\(\s*\$lang\s*,\s*array\s*\((.*?)\)\s*\)\s*;/s', $srcTxt, $m)) { continue; }
            $keys  = trim($m[1]);
            $block = $MB . "\n\$lang = array_merge(\$lang,array(\n\n" . $keys . "\n\n));\n" . $ME . "\n";

            $dst = $dir . $lg;
            if (!is_file($dst)) {
                @file_put_contents($dst, "<?php\n// CRM — {$lgName} strings (plugin)\n\n" . $block);
                continue;
            }

            $cur = (string) file_get_contents($dst);
            if (preg_match($re, $cur)) {
                $cur = preg_replace($re, $block, $cur, 1);
            } else {
                $cur = preg_replace('/\?>\s*$/', '', $cur);
                $cur = rtrim($cur, " \t\r\n") . "\n\n" . $block;
            }
            @file_put_contents($dst, $cur);
        }
    }
}

if (!function_exists('crm_lang_uninstall_keys')) {
    function crm_lang_uninstall_keys()
    {
        $names = ['en-US.php', 'ar-EG.php', 'ar-SA.php'];
        $dir   = crm_lang_dst_dir();
        list($MB, $ME) = crm_lang_marks();
        $re    = '~' . preg_quote($MB, '~') . '(.*?)' . preg_quote($ME, '~') . '~s';

        foreach ($names as $lg) {
            $dst = $dir . $lg;
            if (!is_file($dst)) { continue; }
            $cur = (string) file_get_contents($dst);

            if (preg_match($re, $cur)) {
                $cur = preg_replace($re, '', $cur, 1);
                $cur = preg_replace('/[ \t]*\n[ \t]*\n[ \t]*\n/s', "\n\n", $cur);
                $codeOnly = trim(preg_replace(['/^<\?php/', '/^\s*\/\/[^\n]*/m'], '', $cur));
                if ($codeOnly === '') { @unlink($dst); continue; }
                $cur = rtrim($cur, " \t\r\n") . "\n";
            }
            @file_put_contents($dst, $cur);
        }
    }
}

crm_deploy_pages();
crm_load_lang();
