<?php
/*
=============================================================================
 CRM — boudy-elhegaz  |  crm_boot.php
-----------------------------------------------------------------------------
 بووت أندبوينتس الـAJAX:
   usersc/plugins/eh_crm/assets/includes/data_in_.php   (كل عمليات الإدخال)
   usersc/plugins/eh_crm/assets/includes/show_data_.php (كل عمليات العرض)

 مهم: الملف ده **مايطبعش أي حاجة** على الشاشة لأنه بيتستخدم جوه ردود JSON.

 صفحات الواجهة نفسها (usersc/*.php) مش بتستخدم الملف ده — دي صفحات UserSpice
 عادية: init → prep → securePage.
=============================================================================
*/

if (!defined('CRM_BOOT_LOADED')) {
    define('CRM_BOOT_LOADED', true);

    /* users/init.php — المسار: usersc/scripts/lib  →  جذر الموقع */
    if (!class_exists('DB')) {
        require_once dirname(__DIR__, 5) . '/users/init.php';
    }

    require_once __DIR__ . '/crm_func.php';
    require_once __DIR__ . '/crm_csv.php';
    require_once __DIR__ . '/crm_assign.php';
    require_once __DIR__ . '/crm_calls.php';
    require_once __DIR__ . '/crm_leads.php';
    require_once __DIR__ . '/crm_reports.php';
    require_once __DIR__ . '/crm_admin.php';
}
