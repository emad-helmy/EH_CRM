<?php
/**
 * CRM plugin — functions.php (auto-loaded by UserSpice when active)
 */
if (count(get_included_files()) == 1) die();

define('CRM_PLUGIN_DIR', dirname(__FILE__));

/* ---- نشر صفحات البلجن من files/ إلى usersc/ (نفس طريقة eh_chat بالظبط) ---- */
if (!function_exists('crm_deploy_pages')) {
    /**
     * بينسخ صفحات files/ إلى usersc/ ويرجّع تقرير:
     * ['copied' => [], 'skipped' => [], 'failed' => [], 'missing' => []]
     * قائمة ثابتة زي eh_chat — لو ملف ناقص من الرفع بيظهر في missing
     */
    function crm_deploy_pages()
    {
        $report = ['copied' => [], 'skipped' => [], 'failed' => [], 'missing' => []];
        $src = __DIR__ . '/files';
        $dst = dirname(__DIR__, 2); // .../usersc

        $pages = [
            'eh_dashboard.php', 'eh_leads.php', 'eh_lead_view.php', 'eh_lead_import.php',
            'eh_batches.php', 'eh_distribute.php', 'eh_my_leads.php', 'eh_my_calls.php',
            'eh_reports.php', 'eh_settings.php', 'eh_ceo_control.php', 'eh_no_access.php',
        ];

        foreach ($pages as $f) {
            $from = "$src/$f";
            $to   = "$dst/$f";
            if (!file_exists($from)) {
                $report['missing'][] = $f;
                continue;
            }
            if (file_exists($to) && filemtime($from) <= filemtime($to)) {
                $report['skipped'][] = $f;
                continue;
            }
            if (@copy($from, $to)) {
                $report['copied'][] = $f;
            } else {
                $report['failed'][] = "{$f} → {$to} (copy فشلت — شوف صلاحيات مجلد usersc)";
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

/* ---- تشغيل ملف SQL — تقسيم statement بstatement (آمن للتكرار) ---- */
if (!function_exists('crm_run_sql_file')) {
    /**
     * يشغّل ملف .sql جملة جملة ويرجّع تقرير: ['ok' => int, 'failed' => []]
     * الملف المفروض يكون idempotent (CREATE IF NOT EXISTS / INSERT IGNORE / WHERE NOT EXISTS)
     */
    function crm_run_sql_file($file)
    {
        $db = DB::getInstance();
        $report = ['ok' => 0, 'failed' => []];
        if (!is_file($file)) {
            $report['failed'][] = 'الملف مش موجود: ' . $file;
            return $report;
        }
        $sql = (string)file_get_contents($file);

        // شيل أسطر الكومنتات والسطور الفاضية
        $lines = [];
        foreach (explode("\n", $sql) as $l) {
            $t = ltrim($l);
            if ($t === '' || strpos($t, '--') === 0) { continue; }
            $lines[] = $l;
        }

        // تقسيم على ; مع احترام النصوص المقتبسة
        $stmts = [];
        $cur  = '';
        $inS  = false; // '
        $inD  = false; // "
        $prev = '';
        foreach (str_split(implode("\n", $lines)) as $ch) {
            $cur .= $ch;
            if ($ch === "'" && !$inD) {
                if (!$inS || $prev !== '\\') { $inS = !$inS; }
            } elseif ($ch === '"' && !$inS) {
                if (!$inD || $prev !== '\\') { $inD = !$inD; }
            } elseif ($ch === ';' && !$inS && !$inD) {
                $stmts[] = $cur;
                $cur = '';
            }
            $prev = $ch;
        }
        if (trim($cur) !== '') { $stmts[] = $cur; }

        foreach ($stmts as $stmt) {
            $stmt = trim($stmt, " \t\r\n;");
            if ($stmt === '') { continue; }
            $db->query($stmt);
            if ($db->error()) {
                $report['failed'][] = substr($stmt, 0, 70) . '… → ' . $db->errorString();
            } else {
                $report['ok']++;
            }
        }
        return $report;
    }
}

/* النشر بيتعمل من install / activate / migrate / update صراحةً — مش مع كل تحميل صفحة */
crm_load_lang();
