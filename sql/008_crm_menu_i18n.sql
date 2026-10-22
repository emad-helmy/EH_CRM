-- =============================================================================
-- 008_crm_menu_i18n.sql
-- تحويل تسميات عناصر قائمة CRM من نص عربي (ثابت) إلى مفاتيح لغة (lang keys).
-- parseMenuLabel() في users/helpers/menus.php: إذا كانت التسمية مفتاحًا في $lang،
-- تُعرض الترجمة (عربي/إنجليزي) حسب لغة الموقع — وإلا تُعرض كما هي.
-- تشغيل: يدويًا من phpMyAdmin (مرة واحدة).
-- =============================================================================

-- 1) عناصر قائمة CRM → مفاتيح لغة (حسب link)
UPDATE `us_menu_items`
   SET `label` = 'CRM_MENU_HOME'
 WHERE `menu` = 1 AND `link` = 'usersc/eh_index.php' AND `parent` <> 0;

UPDATE `us_menu_items`
   SET `label` = 'CRM_NAV_IMPORT'
 WHERE `menu` = 1 AND `link` = 'usersc/eh_lead_import.php';

UPDATE `us_menu_items`
   SET `label` = 'CRM_LEADS_TITLE'
 WHERE `menu` = 1 AND `link` = 'usersc/eh_leads.php';

UPDATE `us_menu_items`
   SET `label` = 'CRM_NAV_MY_LEADS'
 WHERE `menu` = 1 AND `link` = 'usersc/eh_my_leads.php';

UPDATE `us_menu_items`
   SET `label` = 'CRM_NAV_MY_CALLS'
 WHERE `menu` = 1 AND `link` = 'usersc/eh_my_calls.php';

UPDATE `us_menu_items`
   SET `label` = 'CRM_NAV_DISTRIBUTE'
 WHERE `menu` = 1 AND `link` = 'usersc/eh_distribute.php';

UPDATE `us_menu_items`
   SET `label` = 'CRM_NAV_BATCHES'
 WHERE `menu` = 1 AND `link` = 'usersc/eh_batches.php';

UPDATE `us_menu_items`
   SET `label` = 'CRM_NAV_REPORTS'
 WHERE `menu` = 1 AND `link` = 'usersc/eh_reports.php';

UPDATE `us_menu_items`
   SET `label` = 'CRM_NAV_SETTINGS'
 WHERE `menu` = 1 AND `link` = 'usersc/eh_settings.php';

-- 2) عنصر "الرئيسية / لوحة المتابعة" القديم (crm/dashboard.php) if still present
UPDATE `us_menu_items`
   SET `label` = 'CRM_MENU_HOME'
 WHERE `link` = 'usersc/crm/dashboard.php';

-- 3) ceo_control (لو أضيف من 007)
UPDATE `us_menu_items`
   SET `label` = 'CRM_NAV_CEO_CONTROL'
 WHERE `menu` = 1 AND `link` = 'usersc/eh_ceo_control.php';
