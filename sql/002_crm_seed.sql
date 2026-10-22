-- =============================================================================
--  CRM — boudy-elhegaz  |  الخطوة 1/ب: البرمشنز + الصفحات + المنيو + الداتا الافتراضية
--  File: usersc/sql/002_crm_seed.sql
--  تشغيله بعد 001_crm_schema.sql
--  آمن يتشغل أكثر من مرة (كل الإضافات محمية بـ NOT EXISTS).
--  ملاحظة: لو غيّرت أسماء ملفات صفحات الـ CRM لازم تعدّل الـ path هنا كمان
--          لأنه هو اللي securePage() بتقارنه (usersc/xxx.php).
-- =============================================================================

SET NAMES utf8mb4;

-- =============================================================================
-- 1) البرمشنز
-- =============================================================================
INSERT INTO `permissions` (`name`, `descrip`)
SELECT 'CRM Data Entry', 'CRM — مدخل البيانات (إدخال أرقام العملاء)'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `name` = 'CRM Data Entry');

INSERT INTO `permissions` (`name`, `descrip`)
SELECT 'CRM Sales', 'CRM — مسئول مبيعات (عملائه فقط + تسجيل المكالمات)'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `name` = 'CRM Sales');

INSERT INTO `permissions` (`name`, `descrip`)
SELECT 'CRM Sales Manager', 'CRM — مدير مبيعات (كل العملاء + التقارير + التوزيع)'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `name` = 'CRM Sales Manager');

SET @p_entry := COALESCE((SELECT `id` FROM `permissions` WHERE `name` = 'CRM Data Entry'    LIMIT 1), 0);
SET @p_sales := COALESCE((SELECT `id` FROM `permissions` WHERE `name` = 'CRM Sales'         LIMIT 1), 0);
SET @p_mgr   := COALESCE((SELECT `id` FROM `permissions` WHERE `name` = 'CRM Sales Manager' LIMIT 1), 0);
SET @p_admin := COALESCE((SELECT `id` FROM `permissions` WHERE `name` = 'Administrator'     LIMIT 1), 2);

-- =============================================================================
-- 2) الصفحات
--    private = 1 → محتاجة برمشن | private = 0 → عامة
--    المصدر الرسمي لعنوان الصفحة هو pages.title (تعدّله من لوحة التحكم → Pages)
-- =============================================================================
INSERT INTO `pages` (`page`, `title`, `private`, `re_auth`, `core`, `lang_key`)
SELECT t.pg, t.ti, t.pv, 0, 0, NULL FROM (
            SELECT 'usersc/index.php'               AS pg, 'الرئيسية'             AS ti, 1 AS pv
  UNION ALL SELECT 'usersc/no_access.php',              'مفيش صلاحيات',          0
  UNION ALL SELECT 'usersc/lead_import.php',        'إدخال عملاء (CSV)',     1
  UNION ALL SELECT 'usersc/lead_add.php',           'إضافة عميل',            1
  UNION ALL SELECT 'usersc/batches.php',            'دفعات الإدخال',         1
  UNION ALL SELECT 'usersc/leads.php',              'كل العملاء',            1
  UNION ALL SELECT 'usersc/lead_view.php',          'بيانات العميل',         1
  UNION ALL SELECT 'usersc/my_leads.php',           'عملائي',                1
  UNION ALL SELECT 'usersc/my_calls.php',           'سجل مكالماتي',          1
  UNION ALL SELECT 'usersc/distribute.php',         'التوزيع / النقل',       1
  UNION ALL SELECT 'usersc/reports.php',            'التقارير',              1
  UNION ALL SELECT 'usersc/settings.php',           'إعدادات CRM',           1
) t
WHERE NOT EXISTS (SELECT 1 FROM `pages` p WHERE p.`page` = t.pg);

-- =============================================================================
-- 3) ربط البرمشنز بالصفحات
-- =============================================================================
INSERT INTO `permission_page_matches` (`permission_id`, `page_id`)
SELECT x.perm, p.`id`
FROM (
            SELECT 'usersc/index.php'                   AS pg, 1         AS perm   -- أي يوزر مسجّل
  UNION ALL SELECT 'usersc/index.php',                         @p_entry
  UNION ALL SELECT 'usersc/index.php',                         @p_sales
  UNION ALL SELECT 'usersc/index.php',                         @p_mgr
  UNION ALL SELECT 'usersc/index.php',                         @p_admin
  UNION ALL SELECT 'usersc/lead_import.php',      @p_entry
  UNION ALL SELECT 'usersc/lead_import.php',      @p_admin
  UNION ALL SELECT 'usersc/lead_add.php',         @p_entry
  UNION ALL SELECT 'usersc/lead_add.php',         @p_admin
  UNION ALL SELECT 'usersc/batches.php',          @p_entry
  UNION ALL SELECT 'usersc/batches.php',          @p_admin
  UNION ALL SELECT 'usersc/leads.php',            @p_mgr
  UNION ALL SELECT 'usersc/leads.php',            @p_admin
  UNION ALL SELECT 'usersc/lead_view.php',        @p_sales
  UNION ALL SELECT 'usersc/lead_view.php',        @p_mgr
  UNION ALL SELECT 'usersc/lead_view.php',        @p_admin
  UNION ALL SELECT 'usersc/my_leads.php',         @p_sales
  UNION ALL SELECT 'usersc/my_leads.php',         @p_admin
  UNION ALL SELECT 'usersc/my_calls.php',         @p_sales
  UNION ALL SELECT 'usersc/my_calls.php',         @p_admin
  UNION ALL SELECT 'usersc/distribute.php',       @p_mgr
  UNION ALL SELECT 'usersc/distribute.php',       @p_admin
  UNION ALL SELECT 'usersc/reports.php',          @p_mgr
  UNION ALL SELECT 'usersc/reports.php',          @p_admin
  UNION ALL SELECT 'usersc/settings.php',         @p_mgr
  UNION ALL SELECT 'usersc/settings.php',         @p_admin
) x
JOIN `pages` p ON p.`page` = x.pg
WHERE x.perm IS NOT NULL
  AND x.perm > 0
  AND NOT EXISTS (
        SELECT 1 FROM `permission_page_matches` m
        WHERE m.`permission_id` = x.perm AND m.`page_id` = p.`id`
  );

-- =============================================================================
-- 4) منيو CRM (Menu 1 = المنيو الرئيسي بتاع الموقع — القالب بيقراه افتراضيًا)
-- =============================================================================
INSERT INTO `us_menu_items`
  (`menu`, `type`, `label`, `link`, `icon_class`, `link_target`, `parent`, `display_order`, `disabled`, `permissions`, `tags`)
SELECT 1, 'dropdown', 'CRM', '', 'fas fa-address-book', '_self', 0, 2, 0, '[2,3,4,5]', NULL
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `us_menu_items` WHERE `menu` = 1 AND `type` = 'dropdown' AND `label` = 'CRM');

SET @menu_crm := (SELECT `id` FROM `us_menu_items` WHERE `menu` = 1 AND `type` = 'dropdown' AND `label` = 'CRM' LIMIT 1);

INSERT INTO `us_menu_items`
  (`menu`, `type`, `label`, `link`, `icon_class`, `link_target`, `parent`, `display_order`, `disabled`, `permissions`, `tags`)
SELECT 1, 'link', t.lb, t.lk, t.ic, '_self', @menu_crm, t.dord, 0, t.pm, NULL FROM (
            SELECT 'CRM_MENU_HOME' AS lb, 'usersc/index.php'             AS lk, 'fas fa-gauge-high'          AS ic, 1 AS dord, '[2,3,4,5]' AS pm
  UNION ALL SELECT 'CRM_NAV_IMPORT',            'usersc/lead_import.php', 'fas fa-file-csv',              2, '[2,3]'
  UNION ALL SELECT 'CRM_LEADS_TITLE',           'usersc/leads.php',       'fas fa-users',                 3, '[2,5]'
  UNION ALL SELECT 'CRM_NAV_MY_LEADS',          'usersc/my_leads.php',    'fas fa-phone-volume',          4, '[2,4]'
  UNION ALL SELECT 'CRM_NAV_MY_CALLS',          'usersc/my_calls.php',    'fas fa-clipboard-list',        5, '[2,4]'
  UNION ALL SELECT 'CRM_NAV_DISTRIBUTE',        'usersc/distribute.php',  'fas fa-shuffle',               6, '[2,5]'
  UNION ALL SELECT 'CRM_NAV_BATCHES',           'usersc/batches.php',     'fas fa-layer-group',           7, '[2,3]'
  UNION ALL SELECT 'CRM_NAV_REPORTS',           'usersc/reports.php',     'fas fa-chart-line',            8, '[2,5]'
  UNION ALL SELECT 'CRM_NAV_SETTINGS',          'usersc/settings.php',    'fas fa-sliders',               9, '[2,5]'
) t
WHERE @menu_crm IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `us_menu_items` m WHERE m.`parent` = @menu_crm AND m.`link` = t.lk);

-- =============================================================================
-- 5) الإعدادات الافتراضية
-- =============================================================================
INSERT INTO `eh_crm_settings` (`skey`, `svalue`, `updated_at`)
SELECT t.k, t.v, NOW() FROM (
            SELECT 'distribution_mode'     AS k, 'equal'                                     AS v
  UNION ALL SELECT 'max_leads_per_sales',        '0'
  UNION ALL SELECT 'auto_assign_on_import',      '1'
  UNION ALL SELECT 'recycle_days',               '3'
  UNION ALL SELECT 'recycle_statuses',           'no_answer,busy,number_off'
  UNION ALL SELECT 'last_assigned_user',         '0'
  UNION ALL SELECT 'default_followup_hours',     '24'
  UNION ALL SELECT 'crm_company_name',           'boudy-elhegaz'
) t
WHERE NOT EXISTS (SELECT 1 FROM `eh_crm_settings` s WHERE s.`skey` = t.k);

-- =============================================================================
-- 6) تصنيفات نتيجة المكالمة
-- =============================================================================
INSERT INTO `eh_crm_call_results`
  (`key_name`, `name_ar`, `color`, `sort_order`, `is_contact`, `is_interest`, `is_final`, `requires_followup`, `active`, `created_at`)
SELECT t.kk, t.na, t.co, t.so, t.ic, t.ii, t.ifin, t.rf, 1, NOW() FROM (
            SELECT 'interested'     AS kk, 'مهتم'            AS na, 'success'   AS co, 10 AS so, 1 AS ic, 1 AS ii, 0 AS ifin, 1 AS rf
  UNION ALL SELECT 'callback',            'إعادة اتصال',         'info',      20, 1, 1, 0, 1
  UNION ALL SELECT 'meeting',            'حجز معاينة',          'primary',   30, 1, 1, 0, 1
  UNION ALL SELECT 'visited',            'تم الزيارة',          'primary',   40, 1, 1, 0, 1
  UNION ALL SELECT 'sold',               'تم التعاقد',          'success',   50, 1, 1, 1, 0
  UNION ALL SELECT 'no_answer',          'لا يوجد رد',          'secondary', 60, 0, 0, 0, 1
  UNION ALL SELECT 'busy',               'مشغول / أغلق',        'warning',   70, 0, 0, 0, 1
  UNION ALL SELECT 'number_off',         'مغلق',                'secondary', 80, 0, 0, 0, 1
  UNION ALL SELECT 'not_interested',     'غير مهتم',            'danger',    90, 1, 0, 1, 0
  UNION ALL SELECT 'not_eligible',       'خارج النطاق / الميزانية','secondary',100, 1, 0, 1, 0
  UNION ALL SELECT 'wrong_number',       'رقم خطأ',             'dark',     110, 0, 0, 1, 0
  UNION ALL SELECT 'duplicate',          'مكرر',                'dark',     120, 0, 0, 1, 0
) t
WHERE NOT EXISTS (SELECT 1 FROM `eh_crm_call_results` r WHERE r.`key_name` = t.kk);

-- =============================================================================
-- 7) المصادر الافتراضية
-- =============================================================================
INSERT INTO `eh_crm_sources` (`name`, `color`, `active`, `sort_order`, `created_at`)
SELECT t.nm, t.co, 1, t.so, NOW() FROM (
            SELECT 'فيسبوك'   AS nm, 'primary'   AS co, 10 AS so
  UNION ALL SELECT 'تيك توك',        'dark',      20
  UNION ALL SELECT 'إنستجرام',       'danger',    30
  UNION ALL SELECT 'واتساب',         'success',   40
  UNION ALL SELECT 'الموقع',         'info',      50
  UNION ALL SELECT 'إحالة عميل',     'warning',   60
  UNION ALL SELECT 'أخرى',           'secondary', 99
) t
WHERE NOT EXISTS (SELECT 1 FROM `eh_crm_sources` s WHERE s.`name` = t.nm);
