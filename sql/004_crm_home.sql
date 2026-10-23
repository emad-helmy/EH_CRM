-- =============================================================================
--  CRM — EH_CRM plugin  |  004_crm_home.sql
--  صفحات CRM + no_access + ceo_control + eh_configure
--  آمن يتشغل أكثر من مرة (idempotent).
-- =============================================================================
-- 1) الصفحات (private = 1 → محتاجة برمشن | private = 0 → عامة)
INSERT INTO `pages` (`page`, `title`, `private`, `re_auth`, `core`, `lang_key`)
SELECT t.pg, t.ti, t.pv, 0, 0, NULL FROM (
            SELECT 'usersc/eh_index.php'        AS pg, 'الرئيسية'             AS ti, 1 AS pv
  UNION ALL SELECT 'usersc/eh_no_access.php',       'مفيش صلاحيات',          0
  UNION ALL SELECT 'usersc/eh_ceo_control.php',     'لوحة تحكم المشرف',      1
  UNION ALL SELECT 'usersc/eh_configure.php',       'إعدادات البلجن',        1
) t
WHERE NOT EXISTS (SELECT 1 FROM `pages` p WHERE p.`page` = t.pg);

-- 2) no_access.php عامة (private = 0)
UPDATE `pages` SET `private` = 0 WHERE `page` = 'usersc/eh_no_access.php';

-- 3) صفحات private = 1 (محتاجة برمشن) — already set in INSERT above
-- 4) ceo_control + configure are admin/supervisor only — permissions linked in 007 and configure.php