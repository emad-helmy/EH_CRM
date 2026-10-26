<?php
/**
 * CRM plugin — functions.php (auto-loaded by UserSpice when active)
 */
if (count(get_included_files()) == 1) die();

define('CRM_PLUGIN_DIR', dirname(__FILE__));

/* ---- نشر صفحات البلجن من files/ إلى usersc/ (نفس أسلوب البلجن الـdemo) ---- */
if (!function_exists('crm_deploy_pages')) {
    function crm_deploy_pages()
    {
        $src = CRM_PLUGIN_DIR . '/files';
        $dst = dirname(CRM_PLUGIN_DIR, 2); // .../usersc
        foreach (glob($src . '/*.php') ?: [] as $from) {
            $to = $dst . '/' . basename($from);
            if (!file_exists($to) || filemtime($from) > filemtime($to)) {
                @copy($from, $to);
            }
        }
    }
}

/* ---- لغة البلجن: دمج مفاتيح CRM_* في $lang وقت التشغيل ---- */
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

crm_deploy_pages();
crm_load_lang();
