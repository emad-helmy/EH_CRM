-- =============================================================================
--  CRM — boudy-elhegaz  |  الخطوة 4: الرئيسية + نقل الصفحات + القائمة
--  File: usersc/sql/004_crm_home.sql
--  شغّله بعد 001 + 002 (+ 003 لو شغّلته). شغّله بعد ما ترفع الملفات.
--
--  بيعمل إيه:
--    1) يسجّل الصفحة الرئيسية usersc/eh_index.php (private = 1) ويربطها بـ
--       User + Administrator + أي صلاحية عندها أي صفحة من صفحات المشروع.
--    2) يسجّل usersc/eh_no_access.php كصفحة عامة (private = 0) — دي اللي
--       securePage() بترجّع عليها لما اليوزر مايكونش عنده صلاحية.
--    3) ينقل صفحات المشروع من usersc/crm/*.php إلى usersc/*.php
--       (بنفس العنوان والصلاحيات) ويحدّث لينكات القائمة.
--    4) ينقل قائمة "CRM" من منيو 2 (منيو الداشبورد/الأدمن) إلى منيو 1
--       (المنيو الرئيسي بتاع الموقع) — عشان الصفحات متبقى محتاجة
--       $menu_override في الكود.
--    5) عناوين عربية لصفحات المشروع في pages.title (المصدر الرسمي للعنوان —
--       تعدّله من لوحة التحكم → Pages).
--    6) يشيل صفحة usersc/crm/dashboard.php القديمة.
--
--  آمن يتشغل أكثر من مرة (idempotent) — شغّله ولو شغّلته قبل كده.
-- =============================================================================

SET NAMES utf8mb4;

-- =============================================================================
-- 1) الصفحة الرئيسية + صفحة "مفيش صلاحيات"
-- =============================================================================
INSERT INTO `pages` (`page`, `title`, `private`, `re_auth`, `core`, `lang_key`)
SELECT 'usersc/eh_index.php', 'الرئيسية', 1, 0, 0, NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `pages` WHERE `page` = 'usersc/eh_index.php');

INSERT INTO `pages` (`page`, `title`, `private`, `re_auth`, `core`, `lang_key`)
SELECT 'usersc/eh_no_access.php', 'مفيش صلاحيات', 0, 0, 0, NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `pages` WHERE `page` = 'usersc/eh_no_access.php');

SET @pg_home := (SELECT `id` FROM `pages` WHERE `page` = 'usersc/eh_index.php' LIMIT 1);

-- User (1) + Administrator (2) + أي صلاحية عندها أي صفحة من صفحات المشروع
INSERT INTO `permission_page_matches` (`permission_id`, `page_id`)
SELECT x.perm, @pg_home
  FROM (
            SELECT 1 AS perm
  UNION ALL SELECT COALESCE((SELECT `id` FROM `permissions` WHERE `name` = 'Administrator' LIMIT 1), 2)
  UNION ALL SELECT DISTINCT m.`permission_id`
              FROM `permission_page_matches` m
              JOIN `pages` p ON p.`id` = m.`page_id`
             WHERE p.`core` = 0
               AND p.`page` NOT LIKE 'users/%'
       ) x
 WHERE @pg_home IS NOT NULL
   AND x.perm IS NOT NULL
   AND x.perm > 0
   AND NOT EXISTS (
         SELECT 1 FROM `permission_page_matches` mm
         WHERE mm.`permission_id` = x.perm AND mm.`page_id` = @pg_home
       );

-- =============================================================================
-- 2) نقل صفحات المشروع: usersc/crm/*.php  →  usersc/*.php
-- =============================================================================
CREATE TEMPORARY TABLE IF NOT EXISTS `eh_crm_page_move` (
  `oldp` VARCHAR(255) NOT NULL,
  `newp` VARCHAR(255) NOT NULL
);

DELETE FROM `eh_crm_page_move`;
INSERT INTO `eh_crm_page_move` (`oldp`, `newp`) VALUES
  ('usersc/crm/lead_import.php', 'usersc/eh_lead_import.php'),
  ('usersc/crm/lead_add.php',    'usersc/eh_lead_add.php'),
  ('usersc/crm/batches.php',     'usersc/eh_batches.php'),
  ('usersc/crm/leads.php',       'usersc/eh_leads.php'),
  ('usersc/crm/lead_view.php',   'usersc/eh_lead_view.php'),
  ('usersc/crm/my_leads.php',    'usersc/eh_my_leads.php'),
  ('usersc/crm/my_calls.php',    'usersc/eh_my_calls.php'),
  ('usersc/crm/distribute.php',  'usersc/eh_distribute.php'),
  ('usersc/crm/reports.php',     'usersc/eh_reports.php'),
  ('usersc/crm/settings.php',    'usersc/eh_settings.php');

-- 2أ) الصفحات الجديدة (بنفس العنوان والخصوصية)
INSERT INTO `pages` (`page`, `title`, `private`, `re_auth`, `core`, `lang_key`)
SELECT m.`newp`, p.`title`, p.`private`, p.`re_auth`, p.`core`, p.`lang_key`
  FROM `eh_crm_page_move` m
  JOIN `pages` p ON p.`page` = m.`oldp`
 WHERE NOT EXISTS (SELECT 1 FROM `pages` x WHERE x.`page` = m.`newp`);

-- 2ب) نقل الصلاحيات من الصفحة القديمة للجديدة
INSERT INTO `permission_page_matches` (`permission_id`, `page_id`)
SELECT pm.`permission_id`, np.`id`
  FROM `eh_crm_page_move` m
  JOIN `pages` op ON op.`page` = m.`oldp`
  JOIN `pages` np ON np.`page` = m.`newp`
  JOIN `permission_page_matches` pm ON pm.`page_id` = op.`id`
 WHERE NOT EXISTS (
       SELECT 1 FROM `permission_page_matches` z
        WHERE z.`permission_id` = pm.`permission_id` AND z.`page_id` = np.`id`
     );

-- 2ج) نشيل الصفحات القديمة
DELETE pm FROM `permission_page_matches` pm
  JOIN `pages` p ON p.`id` = pm.`page_id`
  JOIN `eh_crm_page_move` m ON m.`oldp` = p.`page`;

DELETE p FROM `pages` p JOIN `eh_crm_page_move` m ON m.`oldp` = p.`page`;

-- 2د) لينكات القائمة
UPDATE `us_menu_items` i JOIN `eh_crm_page_move` m ON i.`link` = m.`oldp`
   SET i.`link` = m.`newp`;

-- =============================================================================
-- 3) أننا نحول الداشبورد القديم للرئيسية الجديدة
-- =============================================================================
UPDATE `us_menu_items`
   SET `link` = 'usersc/eh_index.php', `label` = 'CRM_MENU_HOME'
 WHERE `link` = 'usersc/crm/dashboard.php';

DELETE m FROM `permission_page_matches` m
  JOIN `pages` p ON p.`id` = m.`page_id`
 WHERE p.`page` = 'usersc/crm/dashboard.php';

DELETE FROM `pages` WHERE `page` = 'usersc/crm/dashboard.php';

-- =============================================================================
-- 4) قائمة "CRM" → منيو 1 (المنيو الرئيسي)
--    منيو 2 هو منيو الداشبورد/الأدمن، ومش المفروض صفحات المشروع تظهر فيه.
-- =============================================================================
SET @menu_crm := (SELECT `id` FROM `us_menu_items`
                   WHERE `type` = 'dropdown' AND `label` = 'CRM'
                   ORDER BY `id` LIMIT 1);

UPDATE `us_menu_items`
   SET `menu` = 1
 WHERE `menu` = 2
   AND (`id` = @menu_crm OR `parent` = @menu_crm OR `link` LIKE 'usersc/%');

UPDATE `us_menu_items`
   SET `parent` = 0
 WHERE `id` = @menu_crm;

-- =============================================================================
-- 5) مصفوفة صلاحيات عناصر القائمة
--    (لو مش عايز عنصر يظهر لدور معيّن، شيل رقم البرمشن من المصفوفة دي)
-- =============================================================================
UPDATE `us_menu_items` SET `permissions` = '[2,3]'
 WHERE `menu` = 1 AND `link` IN ('usersc/eh_lead_import.php', 'usersc/eh_batches.php');

UPDATE `us_menu_items` SET `permissions` = '[2,4]'
 WHERE `menu` = 1 AND `link` IN ('usersc/eh_my_leads.php', 'usersc/eh_my_calls.php');

UPDATE `us_menu_items` SET `permissions` = '[2,5]'
 WHERE `menu` = 1 AND `link` IN ('usersc/eh_leads.php', 'usersc/eh_distribute.php',
                                 'usersc/eh_reports.php', 'usersc/eh_settings.php');

UPDATE `us_menu_items` SET `permissions` = '[2,3,4,5]'
 WHERE `menu` = 1 AND `link` = 'usersc/eh_index.php';

-- دور المشرف (003) لو موجود → يتضاف للمنيو زي المدير
SET @p_sup := COALESCE((SELECT `id` FROM `permissions` WHERE `name` = 'CRM Supervisor' LIMIT 1), 0);

UPDATE `us_menu_items`
   SET `permissions` = CONCAT('[2,3,4,5,', @p_sup, ']')
 WHERE `menu` = 1 AND `link` = 'usersc/eh_index.php' AND @p_sup > 0;

UPDATE `us_menu_items`
   SET `permissions` = CONCAT('[2,5,', @p_sup, ']')
 WHERE `menu` = 1
   AND `link` IN ('usersc/eh_leads.php', 'usersc/eh_distribute.php', 'usersc/eh_reports.php')
   AND @p_sup > 0;

-- =============================================================================
-- 6) عناوين صفحات المشروع (pages.title = المصدر الرسمي للعنوان)
-- =============================================================================
UPDATE `pages` SET `title` = 'إدخال عملاء (CSV)' WHERE `page` IN ('usersc/eh_lead_import.php', 'usersc/crm/lead_import.php');
UPDATE `pages` SET `title` = 'إضافة عميل'        WHERE `page` IN ('usersc/eh_lead_add.php',    'usersc/crm/lead_add.php');
UPDATE `pages` SET `title` = 'دفعات الإدخال'     WHERE `page` IN ('usersc/eh_batches.php',     'usersc/crm/batches.php');
UPDATE `pages` SET `title` = 'كل العملاء'        WHERE `page` IN ('usersc/eh_leads.php',       'usersc/crm/leads.php');
UPDATE `pages` SET `title` = 'بيانات العميل'     WHERE `page` IN ('usersc/eh_lead_view.php',   'usersc/crm/lead_view.php');
UPDATE `pages` SET `title` = 'عملائي'            WHERE `page` IN ('usersc/eh_my_leads.php',    'usersc/crm/my_leads.php');
UPDATE `pages` SET `title` = 'سجل مكالماتي'      WHERE `page` IN ('usersc/eh_my_calls.php',    'usersc/crm/my_calls.php');
UPDATE `pages` SET `title` = 'التوزيع / النقل'   WHERE `page` IN ('usersc/eh_distribute.php',  'usersc/crm/distribute.php');
UPDATE `pages` SET `title` = 'التقارير'          WHERE `page` IN ('usersc/eh_reports.php',     'usersc/crm/reports.php');
UPDATE `pages` SET `title` = 'إعدادات CRM'       WHERE `page` IN ('usersc/eh_settings.php',    'usersc/crm/settings.php');

DROP TEMPORARY TABLE IF EXISTS `eh_crm_page_move`;
