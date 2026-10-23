-- =============================================================================
--  CRM — boudy-elhegaz  |  ملف اختياري: دور "مشرف مبيعات"
--  File: usersc/sql/003_crm_supervisor.sql
--  شغّله بعد 001 + 002 — واختاري: لو مش عايز الدور ده مش لازم تشغّله.
--
--  بيعمل إيه:
--    1) بيعمل برمشن 'CRM Supervisor'
--    2) يربطه بصفحات المتابعة (نفس مدير المبيعات ما عدا صفحة الإعدادات)
--    3) يضيفه لعناصر المنيو المقابلة (بس العناصر اللي لسه زي ما هي)
--    4) يخليه "مستوى مدير" في الإعداد manager_perm_ids → يشوف كل العملاء،
--       مش عملائه بس.
--
--  آمن يتشغل أكثر من مرة.
--  عايز تغيّر نطاقه؟ من لوحة التحكم → Permissions (للصفحات والمنيو)،
--  و/أو من إعداد manager_perm_ids (تبان في صفحة إعدادات CRM لاحقًا).
-- =============================================================================



-- =============================================================================
-- 1) البرمشن
-- =============================================================================
INSERT INTO `permissions` (`name`, `descrip`)
SELECT 'CRM Supervisor', 'CRM — مشرف مبيعات (متابعة الفريق والتقارير)'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `name` = 'CRM Supervisor');

SET @p_sup := (SELECT `id` FROM `permissions` WHERE `name` = 'CRM Supervisor' LIMIT 1);

-- =============================================================================
-- 2) صفحات المشرف
-- =============================================================================
INSERT INTO `permission_page_matches` (`permission_id`, `page_id`)
SELECT @p_sup, p.`id`
FROM `pages` p
WHERE p.`page` IN (
            'usersc/eh_index.php',
            'usersc/eh_leads.php',
            'usersc/eh_lead_view.php',
            'usersc/eh_distribute.php',
            'usersc/eh_reports.php'
      )
  AND NOT EXISTS (
            SELECT 1 FROM `permission_page_matches` m
            WHERE m.`permission_id` = @p_sup AND m.`page_id` = p.`id`
      );

-- =============================================================================
-- 3) عناصر المنيو (بنضيف رقم البرمشن للعناصر اللي لسه زي ما هي)
-- =============================================================================
UPDATE `us_menu_items`
SET `permissions` = CONCAT('[2,5,', @p_sup, ']')
WHERE `menu` = 1
  AND `permissions` = '[2,5]'
  AND `link` IN (
            'usersc/eh_leads.php',
            'usersc/eh_distribute.php',
            'usersc/eh_reports.php'
      );

UPDATE `us_menu_items`
SET `permissions` = CONCAT('[2,3,4,5,', @p_sup, ']')
WHERE `menu` = 1
  AND `permissions` = '[2,3,4,5]'
  AND `link` = 'usersc/eh_index.php';

-- =============================================================================
-- 4) مستوى مدير (يشوف كل العملاء)
-- =============================================================================
INSERT INTO `eh_crm_settings` (`skey`, `svalue`, `updated_at`)
SELECT 'manager_perm_ids', CAST(@p_sup AS CHAR), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eh_crm_settings` WHERE `skey` = 'manager_perm_ids');

SELECT @p_sup AS supervisor_perm_id;
