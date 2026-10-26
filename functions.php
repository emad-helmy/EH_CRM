<?php
/**
 * CRM plugin — functions.php (auto-loaded by UserSpice when active)
 */
if (count(get_included_files()) == 1) die();

define('CRM_PLUGIN_DIR', dirname(__FILE__));

/* ---- نشر صفحات البلجن من files/ إلى usersc/ (نفس أسلوب البلجن الـdemo) ---- */
if (!function_exists('crm_deploy_pages')) {
    /**
     * بينسخ files/*.php إلى usersc/ ويرجّع تقرير مفصّل:
     * ['copied' => [], 'skipped' => [], 'failed' => []]
     */
    function crm_deploy_pages()
    {
        $report = ['copied' => [], 'skipped' => [], 'failed' => []];
        $src = str_replace('\\', '/', CRM_PLUGIN_DIR . '/files');
        $dst = dirname(CRM_PLUGIN_DIR, 2); // .../usersc

        if (!is_dir($src)) {
            $report['failed'][] = "مجلد files/ مش موجود: {$src} — اتأكد إن ملفات البلجن اترفعت كاملة";
            return $report;
        }
        if (!is_dir($dst)) {
            $report['failed'][] = "مجلد usersc مش موجود: {$dst}";
            return $report;
        }
        if (!is_writable($dst)) {
            $report['failed'][] = "مجلد usersc مش قابل للكتابة ({$dst}) — ظبط الصلاحيات (755) والـownership";
            return $report;
        }

        $files = glob($src . '/*.php');
        if (!is_array($files) || count($files) === 0) {
            // fallback لو الـglob فشل
            $files = [];
            foreach (scandir($src) ?: [] as $f) {
                if (substr($f, -4) === '.php') {
                    $files[] = $src . '/' . $f;
                }
            }
        }
        if (empty($files)) {
            $report['failed'][] = "مفيش صفحات PHP في files/";
            return $report;
        }

        foreach ($files as $from) {
            $to = $dst . '/' . basename($from);
            clearstatcache(true, $to);
            if (file_exists($to) && filemtime($from) <= filemtime($to)) {
                $report['skipped'][] = basename($from);
                continue;
            }
            if (@copy($from, $to)) {
                $report['copied'][] = basename($from);
            } else {
                $report['failed'][] = basename($from) . ' → ' . $to . ' (copy فشلت — شوف صلاحيات المجلد)';
            }
        }
        return $report;
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
