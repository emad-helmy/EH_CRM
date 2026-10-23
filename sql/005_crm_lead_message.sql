-- =============================================================================
--  CRM — boudy-elhegaz  |  الخطوة 3/ج: استبدال الميزانية بالسؤال/الرسالة
--  File: usersc/sql/005_crm_lead_message.sql
-- -----------------------------------------------------------------------------
--  القصة: مفيش حاجة اسمها "ميزانية" في تسجيل العملاء. بدلها حقل "message"
--         = العميل سأل عن إيه أو الرسالة اللي بعتها (فيسبوك / واتس / ...).
--
--  شغّل الملف ده **لو الداتابيز عندك شغالة بالفعل** (يعني شغّلت 001..004 قبل كده).
--  لو لسه مشغّلتش أي حاجة، شغّل 001..004 الجديدة بس — الملف ده مش هيعمل حاجة.
--
--  الملف ده آمن يتشغل أكثر من مرة:
--    - بيتأكد الأول إن العمود القديم "budget" موجود، ولو مش موجود يسيبها.
--  مايرجّع أي تأثير على باقي الجداول.
-- =============================================================================


-- -----------------------------------------------------------------------------
-- 1) eh_crm_leads: budget -> message
-- -----------------------------------------------------------------------------
SET @col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'eh_crm_leads'
       AND COLUMN_NAME  = 'budget'
);
SET @ddl := IF(@col = 1,
    'ALTER TABLE `eh_crm_leads` CHANGE COLUMN `budget` `message` text DEFAULT NULL',
    'SELECT ''eh_crm_leads: budget column not present, skipped'' AS info'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 2) eh_crm_import_staging: budget -> message
-- -----------------------------------------------------------------------------
SET @col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'eh_crm_import_staging'
       AND COLUMN_NAME  = 'budget'
);
SET @ddl := IF(@col = 1,
    'ALTER TABLE `eh_crm_import_staging` CHANGE COLUMN `budget` `message` text DEFAULT NULL',
    'SELECT ''eh_crm_import_staging: budget column not present, skipped'' AS info'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
