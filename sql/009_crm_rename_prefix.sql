-- =============================================================================
-- 009_crm_rename_prefix.sql
-- تغيير برفيكس جداول CRM من bh_ إلى eh_crm_ (لتنصيبات موجودة).
-- شغّل يدويًا من phpMyAdmin بعد تثبيت البلجن (idempotent — يتجاهل إذا لم توجد).
-- =============================================================================
-- 1) إعادة تسمية الجداول (bh_* → eh_crm_*)
RENAME TABLE `bh_settings`          TO `eh_crm_settings`;
RENAME TABLE `bh_sources`           TO `eh_crm_sources`;
RENAME TABLE `bh_projects`          TO `eh_crm_projects`;
RENAME TABLE `bh_call_results`      TO `eh_crm_call_results`;
RENAME TABLE `bh_sales_profile`     TO `eh_crm_sales_profile`;
RENAME TABLE `bh_import_batches`    TO `eh_crm_import_batches`;
RENAME TABLE `bh_import_staging`    TO `eh_crm_import_staging`;
RENAME TABLE `bh_leads`             TO `eh_crm_leads`;
RENAME TABLE `bh_lead_assignments`  TO `eh_crm_lead_assignments`;
RENAME TABLE `bh_call_logs`         TO `eh_crm_call_logs`;
RENAME TABLE `bh_activity_log`      TO `eh_crm_activity_log`;
RENAME TABLE `bh_page_move`         TO `eh_crm_page_move`;

-- 2) تحديث us_menu_items (link references unaffected — links use usersc/*.php)
-- 3) تحديث bh_page_move references in 004_crm_home.sql are internal to that migration (already run)
-- =============================================================================
-- ملاحظة: إذا ظهر خطأ "Table doesn't exist" لجدول (لأنه أُعيد تسميته قبل كده)،
-- تجاهل الخطأ وتابع. MySQL بيتوقف عند أول خطأ — شغّل كل statement بشكل منفصل إذا needed.
-- =============================================================================
