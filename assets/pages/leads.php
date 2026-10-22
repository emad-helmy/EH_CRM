<?php
/*
 =============================================================================
 usersc/leads.php — كل العملاء (للمدير): جدول كامل + فلاتر
 -----------------------------------------------------------------------------
  الحالة / المصدر / المشروع / الموظف / تاريخ الإدخال / بحث
  الجدول بـDataTables (فرز + بحث سريع + ترقيم الصفحات).
 =============================================================================
 */
require_once __DIR__ . '/../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) {
    die();
}
require_once $abs_us_root . $us_url_root . 'usersc/plugins/eh_crm/lib/crm_func.php';
?>

<div class="row">
  <div class="col-md-12">&nbsp;</div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card card-outline card-primary">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="card-title mb-0">
                    <i class="fas fa-users"></i><?= lang('CRM_LEADS_TITLE') ?>
                    <span class="badge bg-dark" id="leadsCount">…</span>
                </h3>
                <button class="btn btn-sm btn-outline-success" id="btnExport">
                    <i class="fas fa-file-excel"></i><?= lang('CRM_LEADS_EXPORT') ?>
                </button>
            </div>
            <div class="card-body">
                <!-- ============================ الفلاتر ============================ -->
                <div class="row g-2 align-items-end mb-3" id="leadFilters">
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1" for="fStatus"><?= lang('CRM_COMMON_STATUS_ONLY') ?></label>
                        <select id="fStatus" class="form-select form-select-sm">
                            <option value=""><?= lang('CRM_LEADS_ALL') ?></option>
                            <option value="new"><?= lang('CRM_LEADS_STATUS_NEW') ?></option>
                            <option value="assigned"><?= lang('CRM_STATUS_ASSIGNED') ?></option>
                            <option value="contacted"><?= lang('CRM_STATUS_CONTACTED') ?></option>
                            <option value="followup"><?= lang('CRM_STATUS_FOLLOWUP') ?></option>
                            <option value="meeting"><?= lang('CRM_STATUS_MEETING') ?></option>
                            <option value="closed"><?= lang('CRM_STATUS_CLOSED') ?></option>
                            <option value="recycled"><?= lang('CRM_LEADS_STATUS_RECYCLED') ?></option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1" for="fSource"><?= lang('CRM_LEADS_SOURCE') ?></label>
                        <select id="fSource" class="form-select form-select-sm">
                            <option value=""><?= lang('CRM_LEADS_ALL') ?></option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1" for="fProject"><?= lang('CRM_LEADS_PROJECT') ?></label>
                        <select id="fProject" class="form-select form-select-sm">
                            <option value=""><?= lang('CRM_LEADS_ALL') ?></option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1" for="fRep"><?= lang('CRM_LEADS_EMPLOYEE') ?></label>
                        <select id="fRep" class="form-select form-select-sm">
                            <option value="0"><?= lang('CRM_LEADS_ALL') ?></option>
                            <option value="-1"><?= lang('CRM_COMMON_UNASSIGNED') ?></option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1" for="fFrom"><?= lang('CRM_LEADS_FROM_DATE') ?></label>
                        <input type="date" id="fFrom" class="form-control form-control-sm">
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1" for="fTo"><?= lang('CRM_LEADS_TO_DATE') ?></label>
                        <input type="date" id="fTo" class="form-control form-control-sm">
                    </div>
                    <div class="col-9 col-md-6 col-xl-4">
                        <label class="form-label small mb-1" for="fQ"><?= lang('CRM_LEADS_SEARCH_LBL') ?></label>
                        <input type="text" id="fQ" class="form-control form-control-sm"
                               placeholder="<?= lang('CRM_LEADS_SEARCH_PH') ?>">
                    </div>
                    <div class="col-3 col-md-6 col-xl-2 d-grid">
                        <button class="btn btn-sm btn-primary" id="btnFilter">
                            <i class="fas fa-filter"></i><?= lang('CRM_LEADS_APPLY') ?>
                        </button>
                    </div>
                    <div class="col-12 text-start">
                        <button class="btn btn-sm btn-outline-secondary" id="btnReset">
                            <i class="fas fa-eraser"></i><?= lang('CRM_LEADS_CLEAR') ?>
                        </button>
                    </div>
                </div>

                <!-- ============================ الجدول ============================ -->
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 w-100 crm-click" id="allLeadsTable">
                        <thead>
                            <tr>
                                <th class="crm-opt">#</th>
                                <th><?= lang('CRM_LEADS_TH_PHONE') ?></th>
                                <th><?= lang('CRM_LEADS_TH_NAME') ?></th>
                                <th class="crm-opt"><?= lang('CRM_LEADS_TH_CITY') ?></th>
                                <th><?= lang('CRM_COMMON_STATUS_ONLY') ?></th>
                                <th class="crm-opt"><?= lang('CRM_LEADS_SOURCE') ?></th>
                                <th class="crm-opt"><?= lang('CRM_LEADS_PROJECT') ?></th>
                                <th class="crm-opt"><?= lang('CRM_LEADS_EMPLOYEE') ?></th>
                                <th class="crm-opt"><?= lang('CRM_LEADS_TH_CREATED') ?></th>
                                <th class="crm-opt"><?= lang('CRM_LEADS_TH_LAST_CALL') ?></th>
                                <th class="crm-opt"><?= lang('CRM_LEADS_TH_NEXT') ?></th>
                                <th><?= lang('CRM_LEADS_TH_ACTIONS') ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php crm_render_js_boot(); ?>
<link rel="stylesheet" href="<?= $us_url_root ?>users/js/pagination/datatables.min.css">
<script src="<?= $us_url_root ?>users/js/pagination/datatables.min.js"></script>
<script>
(function () {
    var $ = window.jQuery;
    var dt = null;
    var lastRows = [];   /* آخر نتيجة مفلترة (لتصدير CSV) */

    var statusLabels = (window.CRM && window.CRM.lang && window.CRM.lang.status) || {};
    var statusBgs = {
        'new': 'bg-secondary', 'assigned': 'bg-secondary', 'contacted': 'bg-info',
        'followup': 'bg-warning', 'meeting': 'bg-primary', 'closed': 'bg-dark', 'recycled': 'bg-danger'
    };

    function initTable() {
        var opts = {
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], ['10', '25', '50', '100', crmT('CRM_COMMON_ALL')]],
            order: [[8, 'desc']],
            language: {
                search: crmT('CRM_COMMON_SEARCH'),
                info: crmT('CRM_DT_INFO'),
                infoEmpty: crmT('CRM_DT_INFO_EMPTY'),
                infoFiltered: crmT('CRM_DT_INFO_FILTERED'),
                lengthMenu: crmT('CRM_DT_LENGTH'),
                zeroRecords: crmT('CRM_DT_ZERO'),
                emptyTable: crmT('CRM_DT_EMPTY'),
                paginate: { first: crmT('CRM_DT_FIRST'), last: crmT('CRM_DT_LAST'), next: crmT('CRM_DT_NEXT'), previous: crmT('CRM_DT_PREV') }
            },
            columnDefs: [
                { orderable: false, targets: 11 },
                /* الأعمدة الثانوية بتتخفى على الموبايل (crm-opt) */
                { className: 'crm-opt', targets: [0, 3, 5, 6, 7, 8, 9, 10] }
            ]
        };
        dt = (window.DataTable) ? new DataTable('#allLeadsTable', opts) : $('#allLeadsTable').DataTable(opts);
    }

    /* ------------------------------ الفلاتر ------------------------------ */
    function loadDropdowns() {
        crm.get('sources_list', {}, function (rows) {
            var s = $('#fSource').find('option:not(:first)').remove().end();
            (rows || []).forEach(function (r) {
                s.append('<option value="' + r.id + '">' + crm.e(r.name) + '</option>');
            });
        });
        crm.get('projects_list', {}, function (rows) {
            var p = $('#fProject').find('option:not(:first)').remove().end();
            (rows || []).forEach(function (r) {
                p.append('<option value="' + r.id + '">' + crm.e(r.name) + '</option>');
            });
        });
        crm.get('sales_load', {}, function (rows) {
            var t = $('#fRep').find('option:not(:first)').remove().end();
            (rows || []).forEach(function (r) {
                t.append('<option value="' + r.id + '">' + crm.e(r.name) + ' (' + crm.num(r.open) + ')</option>');
            });
        });
    }

    function filters() {
        return {
            status: $('#fStatus').val() || '',
            source_id: $('#fSource').val() || 0,
            project_id: $('#fProject').val() || 0,
            assigned_to: $('#fRep').val() || 0,
            date_from: $('#fFrom').val() || '',
            date_to: $('#fTo').val() || '',
            q: $.trim($('#fQ').val() || '')
        };
    }

    function loadLeads() {
        $('#btnFilter').prop('disabled', true);
        crm.get('all_leads_list', filters(), function (rows) {
            $('#btnFilter').prop('disabled', false);
            rows = rows || [];
            lastRows = rows;
            var data = rows.map(function (l) {
                var st = '<span class="badge ' + (statusBgs[l.status] || 'bg-secondary') + '">' +
                    crm.e(statusLabels[l.status] || l.status) + '</span>';
                return [
                    crm.num(l.id),
                    crm.e(l.phone),
                    l.name ? crm.e(l.name) : '<span class="text-muted">—</span>',
                    l.city ? crm.e(l.city) : '<span class="text-muted">—</span>',
                    st,
                    l.source_name ? crm.e(l.source_name) : '<span class="text-muted">—</span>',
                    l.project_name ? crm.e(l.project_name) : '<span class="text-muted">—</span>',
                    l.assigned_name
                        ? crm.e(l.assigned_name)
                        : '<span class="badge bg-secondary"><?= lang('CRM_COMMON_UNASSIGNED') ?></span>',
                    crm.dt(l.created_at),
                    l.last_call_at ? crm.dt(l.last_call_at) : '<span class="text-muted">—</span>',
                    l.next_followup_at
                        ? '<span class="badge bg-warning text-dark">' + crm.dt(l.next_followup_at) + '</span>'
                        : '<span class="text-muted">—</span>',
                    '<a class="btn btn-xs btn-outline-info" href="<?= $us_url_root ?>usersc/lead_view.php?lead=' +
                        l.id + '" title="<?= lang('CRM_LEADS_CARD_TITLE') ?>"><i class="fas fa-eye"></i> <?= lang('CRM_LEADS_VIEW') ?></a>'
                ];
            });
            dt.clear();
            if (data.length) { dt.rows.add(data); }
            dt.draw();
            $('#leadsCount').text(crm.num(rows.length) + crmT('CRM_COMMON_CLIENTS_COUNT'));
        }, function () {
            $('#btnFilter').prop('disabled', false);
        });
    }

    /* ------------------------------ الأحداث ------------------------------ */
    $('#btnFilter').on('click', loadLeads);
    $('#btnReset').on('click', function () {
        $('#fStatus, #fSource, #fProject').val('');
        $('#fRep').val('0');
        $('#fFrom, #fTo, #fQ').val('');
        loadLeads();
    });
    $('#fQ').on('keypress', function (e) { if (e.which === 13) { loadLeads(); } });
    $('#leadFilters').on('change', 'select, input[type="date"]', loadLeads);

    /* لمس أي صف → يفتح كارت العميل (زي ما هو على الموبايل والديسكتوب) */
    $('#allLeadsTable tbody').on('click', 'tr', function () {
        var d = dt.row(this).data();
        if (!d) { return; }
        var id = String(d[0]).replace(/\D/g, '');
        if (id) { window.location = 'lead_view.php?lead=' + id; }
    });

    /* تصدير النتائج المفلترة Excel (xlsx من السيرفر — بنفس الفلاتر) */
    $('#btnExport').on('click', function () {
        if (!lastRows.length) {
            crm.toast('warning', crmT('CRM_LEADS_EXPORT_EMPTY'));
            return;
        }
        window.location = crm.cfg.urls.show + '?' + $.param(
            $.extend({ action: 'export_xlsx', what: 'leads' }, filters())
        );
    });

    /* ------------------------------ البداية ------------------------------ */
    initTable();
    loadDropdowns();
    loadLeads();
})();
</script>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
