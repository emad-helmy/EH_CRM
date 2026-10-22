-- =============================================================================
-- 010_crm_pages_rename.sql
-- تحديث مسارات صفحات CRM بعد إضافة برفيكس eh_.
-- شغّل يدويًا من phpMyAdmin.
-- =============================================================================

-- 1) تحديث صفحات CRM القديمة (usersc/xxx.php → usersc/eh_xxx.php)
UPDATE `pages` SET `page` = 'usersc/eh_index.php'        WHERE `page` = 'usersc/index.php'        AND `private` = 1;
UPDATE `pages` SET `page` = 'usersc/eh_leads.php'        WHERE `page` = 'usersc/leads.php'        AND `private` = 1;
UPDATE `pages` SET `page` = 'usersc/eh_lead_add.php'     WHERE `page` = 'usersc/lead_add.php'     AND `private` = 1;
UPDATE `pages` SET `page` = 'usersc/eh_lead_view.php'    WHERE `page` = 'usersc/lead_view.php'    AND `private` = 1;
UPDATE `pages` SET `page` = 'usersc/eh_lead_import.php'  WHERE `page` = 'usersc/lead_import.php'  AND `private` = 1;
UPDATE `pages` SET `page` = 'usersc/eh_batches.php'      WHERE `page` = 'usersc/batches.php'      AND `private` = 1;
UPDATE `pages` SET `page` = 'usersc/eh_distribute.php'   WHERE `page` = 'usersc/distribute.php'   AND `private` = 1;
UPDATE `pages` SET `page` = 'usersc/eh_my_leads.php'     WHERE `page` = 'usersc/my_leads.php'     AND `private` = 1;
UPDATE `pages` SET `page` = 'usersc/eh_my_calls.php'     WHERE `page` = 'usersc/my_calls.php'     AND `private` = 1;
UPDATE `pages` SET `page` = 'usersc/eh_reports.php'      WHERE `page` = 'usersc/reports.php'      AND `private` = 1;
UPDATE `pages` SET `page` = 'usersc/eh_settings.php'     WHERE `page` = 'usersc/settings.php'     AND `private` = 1;
UPDATE `pages` SET `page` = 'usersc/eh_no_access.php'    WHERE `page` = 'usersc/no_access.php'    AND `private` = 1;

-- 2) ceo_control (already eh_ from 007)
-- 3) eh_configure.php (new page for admin/supervisor)
INSERT INTO `pages` (`page`, `private`) SELECT 'usersc/eh_configure.php', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `pages` WHERE `page` = 'usersc/eh_configure.php');

-- 4) menu links update
UPDATE `us_menu_items` SET `link` = 'usersc/eh_index.php'        WHERE `link` = 'usersc/index.php';
UPDATE `us_menu_items` SET `link` = 'usersc/eh_leads.php'        WHERE `link` = 'usersc/leads.php';
UPDATE `us_menu_items` SET `link` = 'usersc/eh_lead_add.php'     WHERE `link` = 'usersc/lead_add.php';
UPDATE `us_menu_items` SET `link` = 'usersc/eh_lead_view.php'    WHERE `link` = 'usersc/lead_view.php';
UPDATE `us_menu_items` SET `link` = 'usersc/eh_lead_import.php'  WHERE `link` = 'usersc/lead_import.php';
UPDATE `us_menu_items` SET `link` = 'usersc/eh_batches.php'      WHERE `link` = 'usersc/batches.php';
UPDATE `us_menu_items` SET `link` = 'usersc/eh_distribute.php'   WHERE `link` = 'usersc/distribute.php';
UPDATE `us_menu_items` SET `link` = 'usersc/eh_my_leads.php'     WHERE `link` = 'usersc/my_leads.php';
UPDATE `us_menu_items` SET `link` = 'usersc/eh_my_calls.php'     WHERE `link` = 'usersc/eh_my_calls.php';
UPDATE `us_menu_items` SET `link` = 'usersc/eh_reports.php'      WHERE `link` = 'usersc/reports.php';
UPDATE `us_menu_items` SET `link` = 'usersc/eh_settings.php'     WHERE `link` = 'usersc/settings.php';
UPDATE `us_menu_items` SET `link` = 'usersc/eh_no_access.php'    WHERE `link` = 'usersc/no_access.php';
UPDATE `us_menu_items` SET `link` = 'usersc/eh_ceo_control.php'  WHERE `link` = 'usersc/ceo_control.php';

-- 5) no_access.php references in code (usersc/index.php → eh_no_access redirect) already updated via code
-- 6) eh_no_access.php is public (private=0) — keep as-is
UPDATE `pages` SET `private` = 0 WHERE `page` = 'usersc/eh_no_access.php';
