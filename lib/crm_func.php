<?php
/*
=============================================================================
 CRM — boudy-elhegaz  |  crm_func.php
-----------------------------------------------------------------------------
 كل الدوال المشتركة: الصلاحيات، ردود الـJSON، تطبيع الأرقام، الإعدادات، اللوج.
 مافيهاش أي output.
=============================================================================
*/

if (!defined('CRM_PERM_ENTRY_NAME')) {
    define('CRM_PERM_ENTRY_NAME', 'CRM Data Entry');     // مدخل البيانات
    define('CRM_PERM_SALES_NAME', 'CRM Sales');          // مبيعات
    define('CRM_PERM_MGR_NAME', 'CRM Sales Manager');    // مدير مبيعات
    define('CRM_PERM_SUPERVISOR_NAME', 'CRM Supervisor'); // مشرف
    define('CRM_ADMIN_PERM', 2);                         // Administrator
}

/* ------------------------------------------------------------------ */
/* الداتابيز                                                            */
/* ------------------------------------------------------------------ */
function crm_db()
{
    return DB::getInstance();
}

/** عدد صفوف سريع (يرجّع int دايمًا) */
function crm_count($sql, $params = [])
{
    $row = crm_db()->query($sql, $params)->first();
    if (!$row) {
        return 0;
    }
    $vals = array_values((array) $row);
    return (int) ($vals[0] ?? 0);
}

/* ------------------------------------------------------------------ */
/* الصلاحيات                                                            */
/* ------------------------------------------------------------------ */

/**
 * خريطة أرقام البرمشنز من الداتابيز (مخفية في static للسرعة).
 * ترجّع: ['entry'=>id, 'sales'=>id, 'mgr'=>id]  و 0 لو البرمشن مش موجود.
 */
function crm_perm_map()
{
    static $map = null;
    if ($map === null) {
        $map  = ['entry' => 0, 'sales' => 0, 'mgr' => 0, 'supervisor' => 0];
        $rows = crm_db()->query(
            "SELECT id, name FROM permissions WHERE name IN (?,?,?,?)",
            [CRM_PERM_ENTRY_NAME, CRM_PERM_SALES_NAME, CRM_PERM_MGR_NAME, CRM_PERM_SUPERVISOR_NAME]
        )->results();

        foreach ($rows as $r) {
            if ($r->name === CRM_PERM_ENTRY_NAME) {
                $map['entry'] = (int) $r->id;
            } elseif ($r->name === CRM_PERM_SALES_NAME) {
                $map['sales'] = (int) $r->id;
            } elseif ($r->name === CRM_PERM_MGR_NAME) {
                $map['mgr'] = (int) $r->id;
            } elseif ($r->name === CRM_PERM_SUPERVISOR_NAME) {
                $map['supervisor'] = (int) $r->id;
            }
        }
    }
    return $map;
}

function crm_perm_id($key)
{
    $map = crm_perm_map();
    return isset($map[$key]) ? (int) $map[$key] : 0;
}

/**
 * أرقام كل البرمشنز المربوطة بصفحات المشروع (من permission_page_matches).
 * ده المصدر الوحيد للحقيقة — نفس اللي securePage() بتقراه بالظبط.
 * فلو الأدمن ضاف برمشن جديد (مشرف مبيعات مثلًا) لصفحة من صفحات المشروع من
 * لوحة التحكم، بيدخل هنا أوتوماتيك من غير أي تعديل في الكود.
 *
 * إزاي بنعرف صفحة "المشروع"؟ عمود pages.core:
 *   core = 1  → صفحات UserSpice الأصلية
 *   core = 0  → صفحات المشروع (اللي إحنا عملناها أو الأدمن ضافها)
 *
 * ملحوظة مهمة: الصفحة الرئيسية usersc/eh_index.php مستثناة عن قصد — دي مفتوحة
 * لأي يوزر مسجّل (برمشن User العادي)، فلو حسبناها كانت أي صلاحية عادية هتبقى
 * "دور" وهي مش كده.
 */
function crm_page_perm_ids()
{
    static $ids = null;
    if ($ids === null) {
        $ids  = [];
        $rows = crm_db()->query(
            "SELECT DISTINCT m.permission_id AS pid
               FROM permission_page_matches m
               JOIN pages p ON p.id = m.page_id
              WHERE p.core = 0
                AND p.page NOT LIKE 'users/%'
                AND p.page <> 'usersc/eh_index.php'"
        )->results();
        foreach ($rows as $r) {
            if ((int) $r->pid > 0) {
                $ids[] = (int) $r->pid;
            }
        }
    }
    return $ids;
}

/**
 * أرقام البرمشنز اللي بتُعتبر "مستوى مدير" (بتشوف كل العملاء مش عملائها بس).
 *   = برمشن مدير المبيعات (بالاسم)  +  أي أرقام في الإعداد manager_perm_ids
 * مثال: لو عايز "مشرف المبيعات" يشوف كل حاجة زي المدير، حط رقم برمشنه
 *       في manager_perm_ids  (مثلًا '6' أو '6,7').
 */
function crm_mgr_perm_ids()
{
    static $ids = null;
    if ($ids === null) {
        $ids = [];
        $mgr = crm_perm_id('mgr');
        if ($mgr > 0) {
            $ids[] = $mgr;
        }
        $extra = (string) crm_setting('manager_perm_ids', '');
        foreach (preg_split('/[^0-9]+/', $extra, -1, PREG_SPLIT_NO_EMPTY) as $n) {
            $ids[] = (int) $n;
        }
        $ids = array_values(array_unique(array_filter($ids)));
    }
    return $ids;
}

/**
 * فحص برمشن.
 *   $userId = null → اليوزر الحالي من الجلسة (الوضع العادي).
 *   $userId = رقم  → بنفحص بالرقم ده صريح (مهم وقت تسجيل الدخول لأن
 *                    $user->isLoggedIn() بيكون لسه false هناك).
 */
function crm_has_perm($pid, $userId = null)
{
    $pid = (int) $pid;
    if ($pid <= 0) {
        return false;
    }
    return $userId === null ? hasPerm([$pid]) : hasPerm([$pid], (int) $userId);
}

function crm_is_admin($userId = null)
{
    return crm_has_perm(CRM_ADMIN_PERM, $userId);
}

/** هل اليوزر عنده برمشن CRM معين؟ (entry | sales | mgr) */
function crm_is_role($key, $userId = null)
{
    if ($key === 'mgr') {   // مدير المبيعات + أي برمشن في "مجموعة المدير"
        foreach (crm_mgr_perm_ids() as $pid) {
            if (crm_has_perm($pid, $userId)) {
                return true;
            }
        }
        return false;
    }
    return crm_has_perm(crm_perm_id($key), $userId);
}

function crm_is_entry($userId = null)
{
    return crm_is_admin($userId) || crm_is_role('entry', $userId);
}

function crm_is_sales($userId = null)
{
    return crm_is_admin($userId) || crm_is_role('sales', $userId);
}

function crm_is_sales_manager($userId = null)
{
    return crm_is_admin($userId) || crm_is_role('mgr', $userId);
}

function crm_is_supervisor($userId = null)
{
    return crm_is_admin($userId) || crm_is_role('supervisor', $userId);
}

/**
 * هل اليوزر عنده أي صلاحية على الـ CRM؟
 * مبنية على permission_page_matches (نفس مصدر securePage) — يعني مش
 * محتاجين نعدّل الكود لما يتضاف برمشن/دور جديد.
 */
function crm_has_any_role($userId = null)
{
    if (crm_is_admin($userId)) {
        return true;
    }
    $ids = crm_page_perm_ids();
    if (!$ids) {
        return false;
    }
    return $userId === null ? hasPerm($ids) : hasPerm($ids, (int) $userId);
}

/**
 * هل اليوزر يقدر يفتح صفحة المشروع دي؟ (نفس منطق securePage بالظبط)
 * بنستخدمها عشان نخبّي لينكات الصفحات اللي اليوزر مش مسموح له بيها.
 *   $pagePath = 'usersc/eh_settings.php'
 */
function crm_can_page($pagePath, $userId = null)
{
    static $cache = [];
    $key = $pagePath . '|' . (int) $userId;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $row = crm_db()->query("SELECT id FROM pages WHERE page LIKE ? LIMIT 1", ['%' . $pagePath])->first();
    if (!$row) {
        return $cache[$key] = false;
    }

    $ids = [];
    $rows = crm_db()->query(
        "SELECT permission_id FROM permission_page_matches WHERE page_id = ?",
        [(int) $row->id]
    )->results();
    foreach ($rows as $r) {
        if ((int) $r->permission_id > 0) {
            $ids[] = (int) $r->permission_id;
        }
    }
    if (!$ids) {                       // زي securePage: مفيش ربط = أدمن بس
        $ids = [CRM_ADMIN_PERM];
    }

    return $cache[$key] = ($userId === null ? hasPerm($ids) : hasPerm($ids, (int) $userId));
}

/** أدوار اليوزر الحالي كمصفوفة (بتتبعت للـJS) */
function crm_user_roles()
{
    return [
        'admin' => crm_is_admin(),
        'entry' => crm_is_role('entry'),
        'sales' => crm_is_role('sales'),
        'mgr'   => crm_is_role('mgr'),
    ];
}

/** بوابة الأندبوينتس: لو مش من الأدوار المطلوبة يرجّع JSON ويموت */
function crm_require_roles(array $keys, $adminAllowed = true)
{
    if (!crm_user_id()) {
        crm_json(false, crm_t('CRM_COMMON_LOGIN_FIRST'), null, 401);
    }
    if ($adminAllowed && crm_is_admin()) {
        return true;
    }
    foreach ($keys as $k) {
        if (crm_is_role($k)) {
            return true;
        }
    }
    crm_json(false, crm_t('CRM_COMMON_NO_PERM'), null, 403);
}

/** بوابة الأندبوينتس: أي دور CRM يكفي */
function crm_require_any_crm_role()
{
    if (!crm_user_id()) {
        crm_json(false, crm_t('CRM_COMMON_LOGIN_FIRST'), null, 401);
    }
    if (!crm_has_any_role()) {
        crm_json(false, crm_t('CRM_COMMON_NO_CRM_PERM'), null, 403);
    }
    return true;
}

/* ------------------------------------------------------------------ */
/* اليوزر                                                               */
/* ------------------------------------------------------------------ */
function crm_user_id()
{
    global $user;
    if (isset($user) && $user->isLoggedIn()) {
        return (int) $user->data()->id;
    }
    return 0;
}

function crm_user_name($userId = null)
{
    global $user;
    if ($userId === null || $userId == crm_user_id()) {
        if (isset($user) && $user->isLoggedIn()) {
            $d  = $user->data();
            $nm = trim(($d->fname ?? '') . ' ' . ($d->lname ?? ''));
            return $nm !== '' ? $nm : (string) $d->username;
        }
        return '';
    }

    $row = crm_db()->query("SELECT fname, lname, username FROM users WHERE id = ?", [(int) $userId])->first();
    if (!$row) {
        return '—';
    }
    $nm = trim(($row->fname ?? '') . ' ' . ($row->lname ?? ''));
    return $nm !== '' ? $nm : (string) $row->username;
}

/** لينك الصفحة الرئيسية للمشروع (لو مش من مستخدمي المشروع → الجذر) */
function crm_home_url($userId = null)
{
    global $us_url_root;

    return crm_has_any_role($userId) ? $us_url_root . 'usersc/eh_index.php' : $us_url_root;
}

/* ------------------------------------------------------------------ */
/* ردود الـ AJAX  (الشكل الموحّد)                                       */
/*    {"success":bool,"msg":"...","data":{...}}                        */
/* ------------------------------------------------------------------ */
function crm_json($success, $msg = '', $data = null, $httpCode = 200)
{
    // نتخلص من أي output جانبي (مسافات/BOM/تحذيرات من ملفات تانية) عشان الـJSON يطلع نضيف
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(
        ['success' => (bool) $success, 'msg' => (string) $msg, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function crm_ok($msg = '', $data = null)
{
    crm_json(true, $msg, $data);
}

function crm_err($msg = '', $data = null, $httpCode = 200)
{
    crm_json(false, $msg, $data, $httpCode);
}

/* ------------------------------------------------------------------ */
/* قراءة المدخلات (قيم خام — التنظيف وقت العرض بـ crm_e)                */
/* ------------------------------------------------------------------ */
function crm_post($key, $default = '')
{
    if (!isset($_POST[$key])) {
        return $default;
    }
    $v = $_POST[$key];
    return is_array($v) ? $v : trim((string) $v);
}

function crm_get($key, $default = '')
{
    if (!isset($_GET[$key])) {
        return $default;
    }
    $v = $_GET[$key];
    return is_array($v) ? $v : trim((string) $v);
}

function crm_post_int($key, $default = 0)
{
    return (int) crm_post($key, $default);
}

function crm_get_int($key, $default = 0)
{
    return (int) crm_get($key, $default);
}

/** مصفوفة أرقام من مدخل (للمفاتيح المختارة) */
function crm_int_list($arr)
{
    $out = [];
    foreach ((array) $arr as $v) {
        $n = (int) $v;
        if ($n > 0) {
            $out[$n] = $n;
        }
    }
    return array_values($out);
}

/* ------------------------------------------------------------------ */
/* التليفون                                                             */
/* ------------------------------------------------------------------ */

/**
 * تطبيع رقم التليفون المصري:
 *  +20 101 234 5678  /  00201012345678  /  1012345678  /  01012345678
 *  كله يبقى  01012345678
 * وبيحوّل الأرقام العربية (٠١٢...) لإنجليزية الأول.
 */
function crm_norm_phone($raw)
{
    $s = (string) $raw;
    $s = strtr($s, [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ]);
    $s = preg_replace('/[^0-9+]/', '', $s);
    if ($s === null || $s === '') {
        return '';
    }
    if ($s[0] === '+') {
        $s = substr($s, 1);
    }
    if (substr($s, 0, 2) === '00') {
        $s = substr($s, 2);
    }
    if (substr($s, 0, 2) === '20' && strlen($s) >= 12) {
        $s = substr($s, 2);
    }
    if (strlen($s) === 10 && $s[0] === '1') {
        $s = '0' . $s;
    }
    return $s;
}

/** رقم مقبول؟ (8 لـ 15 رقم) */
function crm_phone_valid($norm)
{
    return (bool) preg_match('/^[0-9]{8,15}$/', (string) $norm);
}

/** موبايل مصري؟ 01 + 9 أرقام */
function crm_is_egy_mobile($norm)
{
    return (bool) preg_match('/^01[0-9]{9}$/', (string) $norm);
}

/* ------------------------------------------------------------------ */
/* الوقت                                                               */
/* ------------------------------------------------------------------ */
function crm_now()
{
    return date('Y-m-d H:i:s');
}

function crm_today()
{
    return date('Y-m-d');
}

/** يحوّل أي مدخل تاريخ (datetime-local / نص) لـ 'Y-m-d H:i:s' أو null */
function crm_datetime_or_null($val)
{
    $val = trim((string) $val);
    if ($val === '') {
        return null;
    }
    $val = str_replace('T', ' ', $val);
    $ts  = strtotime($val);
    if ($ts === false || $ts <= 0) {
        return null;
    }
    return date('Y-m-d H:i:s', $ts);
}

/** تاريخ مختصر للعرض */
function crm_fmt_dt($val, $withTime = true)
{
    if (empty($val) || $val === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = strtotime($val);
    if ($ts === false) {
        return '—';
    }
    return $withTime ? date('Y-m-d H:i', $ts) : date('Y-m-d', $ts);
}

/* ------------------------------------------------------------------ */
/* الإعدادات (eh_crm_settings)                                              */
/* ------------------------------------------------------------------ */
function crm_settings($force = false)
{
    static $cache = null;
    if ($cache === null || $force) {
        $cache = [];
        $rows  = crm_db()->query("SELECT skey, svalue FROM eh_crm_settings")->results();
        foreach ($rows as $r) {
            $cache[$r->skey] = $r->svalue;
        }
    }
    return $cache;
}

function crm_setting($key, $default = null)
{
    $all = crm_settings();
    return array_key_exists($key, $all) ? $all[$key] : $default;
}

function crm_set_setting($key, $value)
{
    $row = crm_db()->query("SELECT id FROM eh_crm_settings WHERE skey = ?", [$key])->first();
    $val = is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    $now = crm_now();

    if ($row) {
        return crm_db()->update('eh_crm_settings', $row->id, ['svalue' => $val, 'updated_at' => $now]);
    }
    return crm_db()->insert('eh_crm_settings', ['skey' => $key, 'svalue' => $val, 'updated_at' => $now]);
}

/* ------------------------------------------------------------------ */
/* اللوج (eh_crm_activity_log)                                              */
/* ------------------------------------------------------------------ */
function crm_log($action, $entity = null, $entityId = null, $meta = null)
{
    if ($meta !== null && !is_string($meta)) {
        $meta = json_encode($meta, JSON_UNESCAPED_UNICODE);
    }
    return crm_db()->insert('eh_crm_activity_log', [
        'user_id'    => crm_user_id(),
        'action'     => (string) $action,
        'entity'     => $entity,
        'entity_id'  => $entityId === null ? null : (int) $entityId,
        'meta'       => $meta,
        'ip'         => ipCheck(),
        'created_at' => crm_now(),
    ]);
}

/* ------------------------------------------------------------------ */
/* أدوات عرض                                                            */
/* ------------------------------------------------------------------ */
function crm_e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function crm_num($n)
{
    return number_format((float) $n);
}

/** كل موظفي المبيعات (يوزرز عنده برمشن CRM Sales ومفعّلين) */
function crm_sales_users()
{
    $pid = crm_perm_id('sales');
    if ($pid <= 0) {
        return [];
    }
    return crm_db()->query(
        "SELECT DISTINCT u.id, u.username, u.fname, u.lname, u.email
           FROM users u
           JOIN user_permission_matches pm ON pm.user_id = u.id
          WHERE pm.permission_id = ? AND u.active = 1
          ORDER BY u.id",
        [$pid]
    )->results();
}

/* ------------------------------------------------------------------ */
/* تحميل JS                                                            */
/* ------------------------------------------------------------------ */

/**
 * ترجمة آمنة: لو المفتاح ناقض رجّع اسم المفتاح نفسه (مش نص فاضي)
 * عشان نلاحظ الغلط بسرعة بدل ما يبقى carré فاضي في الشاشة.
 */
function crm_t(string $key): string
{
    $v = lang($key);
    if ($v === '' || strpos((string)$v, 'Missing Text') !== false) {
        return $key;
    }
    return (string)$v;
}

/** كل مفاتيح CRM_* (عربي/إنجليزي) بتتبعت للـJS مرة واحدة */
function crm_lang_js(): array
{
    global $lang;

    $out = [];
    if (is_array($lang)) {
        foreach ($lang as $k => $v) {
            if (strpos((string)$k, 'CRM_') === 0) {
                $out[$k] = $v;
            }
        }
    }
    return $out;
}

function crm_js_config()
{
    global $us_url_root;

    return [
        'root'     => $us_url_root,
        'token'    => Token::generate(),
        'userId'   => crm_user_id(),
        'userName' => crm_user_name(),
        'roles'    => crm_user_roles(),
        'urls'     => [
            'in'   => $us_url_root . 'usersc/plugins/eh_crm/ajax/data_in_.php',
            'show' => $us_url_root . 'usersc/plugins/eh_crm/ajax/show_data_.php',
        ],
        'lang'     => [
            'dir'     => 'rtl',
            'strings' => crm_lang_js(),
            /* قايمة الحالات — بتستخد��ها الصفحات من غير ما تكررها */
            'status'  => [
                'new'       => crm_t('CRM_STATUS_NEW'),
                'assigned'  => crm_t('CRM_STATUS_ASSIGNED'),
                'contacted' => crm_t('CRM_STATUS_CONTACTED'),
                'followup'  => crm_t('CRM_STATUS_FOLLOWUP'),
                'meeting'   => crm_t('CRM_STATUS_MEETING'),
                'recycled'  => crm_t('CRM_STATUS_RECYCLED'),
                'closed'    => crm_t('CRM_STATUS_CLOSED'),
            ],
        ],
    ];
}

/** يطبع بلوك الـJS المشترك + SweetAlert2 + crm.js — يتنادى قبل سكربت الصفحة */
function crm_render_js_boot()
{
    global $us_url_root, $userspice_nonce;
    $nonce = htmlspecialchars($userspice_nonce ?? '', ENT_QUOTES, 'UTF-8');
    $cfg   = json_encode(crm_js_config(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $swal  = $us_url_root . 'usersc/assets/plugins/sweetalert2/';
    ?>
    <link rel="stylesheet" href="<?= $swal ?>sweetalert2.min.css" nonce="<?= $nonce ?>">
    <style nonce="<?= $nonce ?>">
    /*
     * لمسات الموبايل للـCRM — مضمّنة هنا عشان تتطبق على كل صفحات المشروع
     * حتى لو ملف خارجي قديم أو ناقص على السيرفر.
     * الفكرة: الجدول يفضل على قد الصفحة بالظبط على الموبايل (من غير أي
     * سكرول أفقي) — الأعمدة الثانوية متعلّمة بـ .crm-opt بتتخفى على
     * الشاشات الصغيرة، ولمس أي صف متعلّم بـ .crm-click بيفتح كارت العميل.
     */

    /* أعمدة Bootstrap لازم تنكمش تحت محتواها عشان الجداول تفضل جوه الكارت */
    .row > [class*="col-"] {
        min-width: 0;
    }

    /* الصفوف القابلة للضغط عليها */
    .crm-click tbody tr {
        cursor: pointer;
    }

    @media (max-width: 575.98px) {
        /* الأعمدة الثانوية بتتخفى عشان الجدول يبان على قد الصفحة */
        .crm-opt {
            display: none !important;
        }

        /* خلايا أصغر + خط أنحف */
        .table-responsive .table > :not(caption) > * > * {
            padding: 0.4rem 0.45rem;
            font-size: 0.85rem;
        }

        /* تمرير أفقي مريح على اللمس (للجداول اللي مابيخفهاش حاجة) */
        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }

        /* الأزرار داخل رؤوس الكروت تاخد صف كامل بدل ما تتزاحم */
        .card-header .btn,
        .card-header .form-select,
        .card-header .form-control {
            margin-bottom: 0.25rem;
        }
    }
    </style>
    <script nonce="<?= $nonce ?>" src="<?= $swal ?>sweetalert2.min.js"></script>
    <script nonce="<?= $nonce ?>">window.CRM = <?= $cfg ?>;</script>
    <script nonce="<?= $nonce ?>" src="<?= $us_url_root ?>usersc/plugins/eh_crm/assets/js/crm.js"></script>
    <?php
}

/** يطبع روابط flatpickr (منتقي التاريخ والوقت) — يتنادى قبل سكربت الصفحة اللي بيستخدم موديل فيه تاريخ */
function crm_flatpickr_boot()
{
    global $us_url_root, $userspice_nonce;
    $nonce = htmlspecialchars($userspice_nonce ?? '', ENT_QUOTES, 'UTF-8');
    $fp    = $us_url_root . 'usersc/assets/plugins/flatpickr/';
    ?>
    <link rel="stylesheet" href="<?= $fp ?>flatpickr.min.css" nonce="<?= $nonce ?>">
    <script nonce="<?= $nonce ?>" src="<?= $fp ?>flatpickr.min.js"></script>
    <script nonce="<?= $nonce ?>" src="<?= $fp ?>l10n/ar.js"></script>
    <?php
}
