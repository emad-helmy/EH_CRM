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

/* ---- sync ini ---- */
if (!function_exists('crm_sync_ini')) {
    function crm_sync_ini()
    {
        global $abs_us_root, $us_url_root;
        $db = DB::getInstance();
        $all = $db->query("SELECT plugin, status FROM us_plugins")->results();
        $ini = [];
        foreach ($all as $p) {
            $ini[$p->plugin] = ($p->status === 'active') ? 1 : 2;
        }
        write_php_ini($ini, $abs_us_root . $us_url_root . 'usersc/plugins/plugins.ini.php');
    }
}

crm_deploy_pages();
crm_load_lang();
