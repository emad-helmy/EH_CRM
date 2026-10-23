-- =============================================================================
--  CRM — boudy-elhegaz  |  الخطوة 1/ج: جداول الداتابيز
--  File: usersc/sql/001_crm_schema.sql
--  Target: UserSpice 6.1.7 (MySQL / MariaDB) — charset utf8mb4
--  ملاحظة: السكربت آمن يتشغل أكثر من مرة (IF NOT EXISTS).
--          مايرجّع أي تأثير على جداول UserSpice الأصلية.
-- =============================================================================



-- -----------------------------------------------------------------------------
-- 1) eh_crm_settings — إعدادات النظام (key/value)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `eh_crm_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `skey` varchar(80) NOT NULL,
  `svalue` text DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eh_crm_settings_skey` (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 2) eh_crm_sources — مصدر العميل (فيسبوك / تيك توك / ...)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `eh_crm_sources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `color` varchar(30) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eh_crm_sources_name` (`name`),
  KEY `idx_eh_crm_sources_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 3) eh_crm_projects — المشاريع العقارية
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `eh_crm_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `developer` varchar(150) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eh_crm_projects_name` (`name`),
  KEY `idx_eh_crm_projects_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 4) eh_crm_call_results — تصنيفات نتيجة المكالمة
--    is_contact       : اتكلم مع العميل فعلًا؟
--    is_interest      : نتيجة تعتبر "مهتم"؟
--    is_final         : تقفل الليد (خلاص مفيش متابعة)؟
--    requires_followup: لازم يتحدد لها ميعاد متابعة؟
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `eh_crm_call_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key_name` varchar(60) NOT NULL,
  `name_ar` varchar(120) NOT NULL,
  `color` varchar(30) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_contact` tinyint(1) NOT NULL DEFAULT 0,
  `is_interest` tinyint(1) NOT NULL DEFAULT 0,
  `is_final` tinyint(1) NOT NULL DEFAULT 0,
  `requires_followup` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eh_crm_call_results_key` (`key_name`),
  KEY `idx_eh_crm_call_results_active` (`active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 5) eh_crm_sales_profile — بروفايل موظف المبيعات
--    available = 0  →  إجازة / موقوف، مايستقبلش أرقام جديدة
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `eh_crm_sales_profile` (
  `user_id` int(11) NOT NULL,
  `max_leads` int(11) NOT NULL DEFAULT 0,      -- 0 = بدون سقف
  `target_monthly` int(11) NOT NULL DEFAULT 0, -- تارجت شهري (تقارير)
  `available` tinyint(1) NOT NULL DEFAULT 1,   -- 0 = إجازة / موقوف
  `notes` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  KEY `idx_eh_crm_sales_available` (`available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 6) eh_crm_import_batches — دفعات الاستيراد
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `eh_crm_import_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `method` varchar(20) NOT NULL DEFAULT 'csv', -- csv | single
  `file_name` varchar(255) DEFAULT NULL,
  `total_rows` int(11) NOT NULL DEFAULT 0,
  `inserted` int(11) NOT NULL DEFAULT 0,
  `dup_db` int(11) NOT NULL DEFAULT 0,
  `dup_file` int(11) NOT NULL DEFAULT 0,
  `invalid` int(11) NOT NULL DEFAULT 0,
  `assigned_count` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'pending', -- pending | committed | cancelled
  `created_at` datetime DEFAULT NULL,
  `committed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_eh_crm_batches_user` (`user_id`),
  KEY `idx_eh_crm_batches_status` (`status`),
  KEY `idx_eh_crm_batches_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 7) eh_crm_import_staging — منطقة المراجعة قبل التسجيل في الداتابيز
--    row_status: ok | dup_db | dup_file | invalid | excluded | imported
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `eh_crm_import_staging` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `row_no` int(11) NOT NULL DEFAULT 0,
  `phone_raw` varchar(60) DEFAULT NULL,
  `phone_norm` varchar(30) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `project` varchar(150) DEFAULT NULL,
  `source` varchar(120) DEFAULT NULL,
  `message` text DEFAULT NULL,           -- سأل عن إيه / الرسالة اللي بعتها
  `notes` text DEFAULT NULL,
  `row_status` varchar(20) NOT NULL DEFAULT 'ok',
  `row_msg` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_eh_crm_staging_batch` (`batch_id`,`row_status`),
  KEY `idx_eh_crm_staging_phone` (`phone_norm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 8) eh_crm_leads — العميل المحتمل (الجدول الرئيسي)
--    status: new | assigned | contacted | followup | meeting | closed | recycled
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `eh_crm_leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(30) NOT NULL,          -- النمرة بعد التطبيع (فريدة)
  `phone_raw` varchar(60) DEFAULT NULL,  -- زي ما اتكتبت
  `phone2` varchar(30) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `email` varchar(155) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,           -- سأل عن إيه / الرسالة اللي بعتها (فيسبوك/واتس)
  `source_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,             -- ملاحظات مدخل البيانات
  `created_by` int(11) NOT NULL DEFAULT 0,
  `import_batch_id` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `current_result_id` int(11) DEFAULT NULL,
  `last_call_at` datetime DEFAULT NULL,
  `next_followup_at` datetime DEFAULT NULL,
  `calls_count` int(11) NOT NULL DEFAULT 0,
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eh_crm_leads_phone` (`phone`),
  KEY `idx_eh_crm_leads_status` (`status`),
  KEY `idx_eh_crm_leads_assigned` (`assigned_to`,`status`),
  KEY `idx_eh_crm_leads_followup` (`next_followup_at`),
  KEY `idx_eh_crm_leads_source` (`source_id`),
  KEY `idx_eh_crm_leads_project` (`project_id`),
  KEY `idx_eh_crm_leads_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 9) eh_crm_lead_assignments — سجل كل عملية توزيع/نقل (تاريخ كامل)
--    mode  : equal (الحالي — توزيع بالتساوي) | round_robin | least_loaded | manual
--    reason: initial | manual | transfer | bulk_transfer | recycled
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `eh_crm_lead_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL DEFAULT 0,
  `mode` varchar(20) NOT NULL DEFAULT 'round_robin',
  `reason` varchar(30) NOT NULL DEFAULT 'initial',
  `note` varchar(255) DEFAULT NULL,
  `assigned_at` datetime NOT NULL,
  `unassigned_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_eh_crm_assign_lead` (`lead_id`),
  KEY `idx_eh_crm_assign_user` (`user_id`,`unassigned_at`),
  KEY `idx_eh_crm_assign_at` (`assigned_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 10) eh_crm_call_logs — سجل المكالمات
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `eh_crm_call_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `result_id` int(11) DEFAULT NULL,
  `result_key` varchar(60) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `duration_min` int(11) NOT NULL DEFAULT 0,
  `next_followup_at` datetime DEFAULT NULL,
  `call_at` datetime NOT NULL,
  `prev_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_eh_crm_calls_lead` (`lead_id`),
  KEY `idx_eh_crm_calls_user` (`user_id`,`call_at`),
  KEY `idx_eh_crm_calls_result` (`result_id`),
  KEY `idx_eh_crm_calls_followup` (`next_followup_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 11) eh_crm_activity_log — لوج الحركات
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `eh_crm_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `action` varchar(60) NOT NULL,
  `entity` varchar(40) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `meta` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_eh_crm_act_user` (`user_id`,`created_at`),
  KEY `idx_eh_crm_act_entity` (`entity`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
