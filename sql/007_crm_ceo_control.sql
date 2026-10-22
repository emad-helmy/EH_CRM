-- =============================================================================
--  CRM — boudy-elhegaz  |  صفحة لوحة تحكم المشرف
--  File: usersc/sql/007_crm_ceo_control.sql
--  شغّله بعد 001 + 002 (+ 003 لو عايز مشرف المبيعات).
--
--  بيعمل إيه:
--    1) يسجّل الصفحة usersc/eh_ceo_control.php في جدول pages (private = 1)
--    2) يربطها بمشرف المبيعات + مدير المبيعات + الأدمن
--
--  ملحوظة: الصفحة نفسها مفيهاش أي check للصلاحيات —
--  التحكم كله من UserSpice (pages + permission_page_matches + securePage).
--  عايز تغيّر مين يدخلها؟ من لوحة التحكم → Permissions (للصفحات والمنيو).
--
--  آمن يتشغل أكثر من مرة.
-- =============================================================================

SET NAMES utf8mb4;

-- =============================================================================
-- 1) الصفحة
-- =============================================================================
INSERT INTO `pages` (`page`, `title`, `private`, `re_auth`, `core`, `lang_key`)
SELECT 'usersc/eh_ceo_control.php', 'لوحة تحكم المشرف', 1, 0, 0, NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `pages` WHERE `page` = 'usersc/eh_ceo_control.php');

-- =============================================================================
-- 2) ربط البرمشنات بالصفحة
-- =============================================================================
SET @p_sup   := (SELECT `id` FROM `permissions` WHERE `name` = 'CRM Supervisor'   LIMIT 1);
SET @p_mgr   := (SELECT `id` FROM `permissions` WHERE `name` = 'CRM Sales Manager' LIMIT 1);
SET @p_admin := COALESCE((SELECT `id` FROM `permissions` WHERE `name` = 'Administrator'  LIMIT 1), 2);

INSERT INTO `permission_page_matches` (`permission_id`, `page_id`)
SELECT x.perm, p.`id`
FROM (
            SELECT @p_sup   AS perm
  UNION ALL SELECT @p_mgr
  UNION ALL SELECT @p_admin
) x
JOIN `pages` p ON p.`page` = 'usersc/eh_ceo_control.php'
WHERE NOT EXISTS (
    SELECT 1
      FROM `permission_page_matches` m
     WHERE m.`permission_id` = x.perm
       AND m.`page_id` = p.`id`
);

-- =============================================================================
-- 3) عنصر في المنيو (اختياري — لو مش عايزه امسح الجزء ده)
-- =============================================================================
-- INSERT INTO `us_menu_items` (`menu`, `link`, `icon`, `title`, `permissions`)
-- SELECT 1, 'usersc/eh_ceo_control.php', 'fas fa-user-shield', 'لوحة تحكم المشرف',
--        CONCAT('[', @p_admin, ',', @p_sup, ',', @p_mgr, ']')
-- FROM DUAL WHERE NOT EXISTS (
--     SELECT 1 FROM `us_menu_items` WHERE `menu` = 1 AND `link` = 'usersc/eh_ceo_control.php'
-- );

SELECT @p_sup AS supervisor_perm_id, @p_mgr AS manager_perm_id, @p_admin AS admin_perm_id;
