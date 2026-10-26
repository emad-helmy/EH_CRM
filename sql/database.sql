-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 25, 2026 at 07:48 PM
-- Server version: 11.4.13-MariaDB-cll-lve-log
-- PHP Version: 8.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `boudtdio_users333`
--

-- --------------------------------------------------------

--
-- Table structure for table `plg_ehcrm_activity_log`
--

CREATE TABLE `plg_ehcrm_activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `action` varchar(60) NOT NULL,
  `entity` varchar(40) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `meta` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plg_ehcrm_call_logs`
--

CREATE TABLE `plg_ehcrm_call_logs` (
  `id` int(11) NOT NULL,
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
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `plg_ehcrm_call_results`
--

CREATE TABLE `plg_ehcrm_call_results` (
  `id` int(11) NOT NULL,
  `key_name` varchar(60) NOT NULL,
  `name_ar` varchar(120) NOT NULL,
  `color` varchar(30) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_contact` tinyint(1) NOT NULL DEFAULT 0,
  `is_interest` tinyint(1) NOT NULL DEFAULT 0,
  `is_final` tinyint(1) NOT NULL DEFAULT 0,
  `requires_followup` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plg_ehcrm_call_results`
--

INSERT INTO `plg_ehcrm_call_results` (`id`, `key_name`, `name_ar`, `color`, `sort_order`, `is_contact`, `is_interest`, `is_final`, `requires_followup`, `active`, `created_at`) VALUES
(1, 'interested', 'مهتم', 'success', 10, 1, 1, 0, 1, 1, '2026-10-16 08:15:52'),
(2, 'callback', 'إعادة اتصال', 'info', 20, 1, 1, 0, 1, 1, '2026-10-16 08:15:52'),
(3, 'meeting', 'حجز معاينة', 'primary', 30, 1, 1, 0, 1, 1, '2026-10-16 08:15:52'),
(4, 'visited', 'تم الزيارة', 'primary', 40, 1, 1, 0, 1, 1, '2026-10-16 08:15:52'),
(5, 'sold', 'تم التعاقد', 'success', 50, 1, 1, 1, 0, 1, '2026-10-16 08:15:52'),
(6, 'no_answer', 'لا يوجد رد', 'secondary', 60, 0, 0, 0, 1, 1, '2026-10-16 08:15:52'),
(7, 'busy', 'مشغول / أغلق', 'warning', 70, 0, 0, 0, 1, 1, '2026-10-16 08:15:52'),
(8, 'number_off', 'مغلق', 'secondary', 80, 0, 0, 0, 1, 1, '2026-10-16 08:15:52'),
(9, 'not_interested', 'غير مهتم', 'danger', 90, 1, 0, 1, 0, 1, '2026-10-16 08:15:52'),
(10, 'not_eligible', 'خارج النطاق / الميزانية', 'secondary', 100, 1, 0, 1, 0, 1, '2026-10-16 08:15:52'),
(11, 'wrong_number', 'رقم خطأ', 'dark', 110, 0, 0, 1, 0, 1, '2026-10-16 08:15:52'),
(12, 'duplicate', 'مكرر', 'dark', 120, 0, 0, 1, 0, 1, '2026-10-16 08:15:52');

-- --------------------------------------------------------

--
-- Table structure for table `plg_ehcrm_import_batches`
--

CREATE TABLE `plg_ehcrm_import_batches` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `method` varchar(20) NOT NULL DEFAULT 'csv',
  `file_name` varchar(255) DEFAULT NULL,
  `total_rows` int(11) NOT NULL DEFAULT 0,
  `inserted` int(11) NOT NULL DEFAULT 0,
  `dup_db` int(11) NOT NULL DEFAULT 0,
  `dup_file` int(11) NOT NULL DEFAULT 0,
  `invalid` int(11) NOT NULL DEFAULT 0,
  `assigned_count` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT NULL,
  `committed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `plg_ehcrm_import_staging`
--

CREATE TABLE `plg_ehcrm_import_staging` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `row_no` int(11) NOT NULL DEFAULT 0,
  `phone_raw` varchar(60) DEFAULT NULL,
  `phone_norm` varchar(30) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `project` varchar(150) DEFAULT NULL,
  `source` varchar(120) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `row_status` varchar(20) NOT NULL DEFAULT 'ok',
  `row_msg` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `plg_ehcrm_leads`
--

CREATE TABLE `plg_ehcrm_leads` (
  `id` int(11) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `phone_raw` varchar(60) DEFAULT NULL,
  `phone2` varchar(30) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `email` varchar(155) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
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
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `plg_ehcrm_lead_assignments`
--

CREATE TABLE `plg_ehcrm_lead_assignments` (
  `id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL DEFAULT 0,
  `mode` varchar(20) NOT NULL DEFAULT 'round_robin',
  `reason` varchar(30) NOT NULL DEFAULT 'initial',
  `note` varchar(255) DEFAULT NULL,
  `assigned_at` datetime NOT NULL,
  `unassigned_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `plg_ehcrm_projects`
--

CREATE TABLE `plg_ehcrm_projects` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `developer` varchar(150) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plg_ehcrm_sales_profile`
--

CREATE TABLE `plg_ehcrm_sales_profile` (
  `user_id` int(11) NOT NULL,
  `max_leads` int(11) NOT NULL DEFAULT 0,
  `target_monthly` int(11) NOT NULL DEFAULT 0,
  `available` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `plg_ehcrm_settings`
--

CREATE TABLE `plg_ehcrm_settings` (
  `id` int(11) NOT NULL,
  `skey` varchar(80) NOT NULL,
  `svalue` text DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plg_ehcrm_settings`
--

INSERT INTO `plg_ehcrm_settings` (`id`, `skey`, `svalue`, `updated_at`) VALUES
(1, 'distribution_mode', 'least_loaded', '2026-10-16 08:15:52'),
(2, 'max_leads_per_sales', '0', '2026-10-16 08:15:52'),
(3, 'auto_assign_on_import', '1', '2026-10-22 11:51:56'),
(4, 'recycle_days', '3', '2026-10-22 11:51:56'),
(5, 'recycle_statuses', 'no_answer,busy,number_off', '2026-10-22 11:51:56'),
(6, 'last_assigned_user', '0', '2026-10-16 08:15:52'),
(7, 'default_followup_hours', '24', '2026-10-22 11:51:56'),
(8, 'crm_company_name', 'boudy-elhegaz', '2026-10-22 11:51:56'),
(16, 'manager_perm_ids', '6', '2026-10-16 19:27:19');

-- --------------------------------------------------------

--
-- Table structure for table `plg_ehcrm_sources`
--

CREATE TABLE `plg_ehcrm_sources` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `color` varchar(30) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plg_ehcrm_sources`
--

INSERT INTO `plg_ehcrm_sources` (`id`, `name`, `color`, `active`, `sort_order`, `created_at`) VALUES
(1, 'فيسبوك', 'primary', 1, 10, '2026-10-16 08:15:52'),
(2, 'تيك توك', 'dark', 1, 20, '2026-10-16 08:15:52'),
(3, 'إنستجرام', 'danger', 1, 30, '2026-10-16 08:15:52'),
(4, 'واتساب', 'success', 1, 40, '2026-10-16 08:15:52'),
(5, 'الموقع', 'info', 1, 50, '2026-10-16 08:15:52'),
(6, 'إحالة عميل', 'warning', 1, 60, '2026-10-16 08:15:52'),
(7, 'أخرى', 'secondary', 1, 99, '2026-10-16 08:15:52');





-- Add to pages Table
INSERT INTO `pages` (`page`, `title`, `private`, `re_auth`, `core`, `lang_key`, `dwells`) VALUES
('usersc/eh_dashboard.php', 'CRM Dashboard', 1, 0, 0, NULL, 1),
('usersc/eh_no_access.php', 'مفيش صلاحيات', 0, 0, 0, NULL, 9),
('usersc/eh_lead_import.php', 'إدخال عملاء (CSV)', 1, 0, 0, NULL, 159),
('usersc/eh_batches.php', 'دفعات الإدخال', 1, 0, 0, NULL, 19),
('usersc/eh_leads.php', 'كل العملاء', 1, 0, 0, NULL, 50),
('usersc/eh_lead_view.php', 'بيانات العميل', 1, 0, 0, NULL, 4),
('usersc/eh_my_leads.php', 'عملائي', 1, 0, 0, NULL, 117),
('usersc/eh_my_calls.php', 'سجل مكالماتي', 1, 0, 0, NULL, 19),
('usersc/eh_distribute.php', 'التوزيع / النقل', 1, 0, 0, NULL, 47),
('usersc/eh_reports.php', 'التقارير', 1, 0, 0, NULL, 40),
('usersc/eh_settings.php', 'إعدادات CRM', 1, 0, 0, NULL, 79),
('usersc/eh_ceo_control.php', 'صفحة الادارة', 1, 0, 0, NULL, 816);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `plg_ehcrm_activity_log`
--
ALTER TABLE `plg_ehcrm_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plg_ehcrm_act_user` (`user_id`,`created_at`),
  ADD KEY `idx_plg_ehcrm_act_entity` (`entity`,`entity_id`);

--
-- Indexes for table `plg_ehcrm_call_logs`
--
ALTER TABLE `plg_ehcrm_call_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plg_ehcrm_calls_lead` (`lead_id`),
  ADD KEY `idx_plg_ehcrm_calls_user` (`user_id`,`call_at`),
  ADD KEY `idx_plg_ehcrm_calls_result` (`result_id`),
  ADD KEY `idx_plg_ehcrm_calls_followup` (`next_followup_at`);

--
-- Indexes for table `plg_ehcrm_call_results`
--
ALTER TABLE `plg_ehcrm_call_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_plg_ehcrm_call_results_key` (`key_name`),
  ADD KEY `idx_plg_ehcrm_call_results_active` (`active`,`sort_order`);

--
-- Indexes for table `plg_ehcrm_import_batches`
--
ALTER TABLE `plg_ehcrm_import_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plg_ehcrm_batches_user` (`user_id`),
  ADD KEY `idx_plg_ehcrm_batches_status` (`status`),
  ADD KEY `idx_plg_ehcrm_batches_created` (`created_at`);

--
-- Indexes for table `plg_ehcrm_import_staging`
--
ALTER TABLE `plg_ehcrm_import_staging`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plg_ehcrm_staging_batch` (`batch_id`,`row_status`),
  ADD KEY `idx_plg_ehcrm_staging_phone` (`phone_norm`);

--
-- Indexes for table `plg_ehcrm_leads`
--
ALTER TABLE `plg_ehcrm_leads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_plg_ehcrm_leads_phone` (`phone`),
  ADD KEY `idx_plg_ehcrm_leads_status` (`status`),
  ADD KEY `idx_plg_ehcrm_leads_assigned` (`assigned_to`,`status`),
  ADD KEY `idx_plg_ehcrm_leads_followup` (`next_followup_at`),
  ADD KEY `idx_plg_ehcrm_leads_source` (`source_id`),
  ADD KEY `idx_plg_ehcrm_leads_project` (`project_id`),
  ADD KEY `idx_plg_ehcrm_leads_created` (`created_at`);

--
-- Indexes for table `plg_ehcrm_lead_assignments`
--
ALTER TABLE `plg_ehcrm_lead_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plg_ehcrm_assign_lead` (`lead_id`),
  ADD KEY `idx_plg_ehcrm_assign_user` (`user_id`,`unassigned_at`),
  ADD KEY `idx_plg_ehcrm_assign_at` (`assigned_at`);

--
-- Indexes for table `plg_ehcrm_projects`
--
ALTER TABLE `plg_ehcrm_projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_plg_ehcrm_projects_name` (`name`),
  ADD KEY `idx_plg_ehcrm_projects_active` (`active`);

--
-- Indexes for table `plg_ehcrm_sales_profile`
--
ALTER TABLE `plg_ehcrm_sales_profile`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_plg_ehcrm_sales_available` (`available`);

--
-- Indexes for table `plg_ehcrm_settings`
--
ALTER TABLE `plg_ehcrm_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_plg_ehcrm_settings_skey` (`skey`);

--
-- Indexes for table `plg_ehcrm_sources`
--
ALTER TABLE `plg_ehcrm_sources`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_plg_ehcrm_sources_name` (`name`),
  ADD KEY `idx_plg_ehcrm_sources_active` (`active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `plg_ehcrm_activity_log`
--
ALTER TABLE `plg_ehcrm_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plg_ehcrm_call_logs`
--
ALTER TABLE `plg_ehcrm_call_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plg_ehcrm_call_results`
--
ALTER TABLE `plg_ehcrm_call_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `plg_ehcrm_import_batches`
--
ALTER TABLE `plg_ehcrm_import_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plg_ehcrm_import_staging`
--
ALTER TABLE `plg_ehcrm_import_staging`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plg_ehcrm_leads`
--
ALTER TABLE `plg_ehcrm_leads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plg_ehcrm_lead_assignments`
--
ALTER TABLE `plg_ehcrm_lead_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plg_ehcrm_projects`
--
ALTER TABLE `plg_ehcrm_projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plg_ehcrm_settings`
--
ALTER TABLE `plg_ehcrm_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `plg_ehcrm_sources`
--
ALTER TABLE `plg_ehcrm_sources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
