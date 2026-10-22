<?php
/*
 =============================================================================
 usersc/reports.php — التقارير + التصدير (للمدير)
 -----------------------------------------------------------------------------
  تقارير بفترة تاريخ: KPIs + رسمين (Chart.js) + جداول حسب الموظف / المصدر /
  نتيجة المكالمة + تصدير CSV لكل جدول.
  البيانات من: stats_by_user | stats_by_source | stats_by_result
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
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-chart-line"></i><?= lang('CRM_REP_TITLE') ?></h3>
            </div>
            <div class="card-body">
                <!-- ============================ الفترة ============================ -->
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1" for="rFrom"><?= lang('CRM_REP_FROM') ?></label>
                        <input type="date" id="rFrom" class="form-control form-control-sm">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1" for="rTo"><?= lang('CRM_REP_TO') ?></label>
                        <input type="date" id="rTo" class="form-control form-control-sm">
                    </div>
                    <div class="col-12 col-md-3 d-grid">
                        <button class="btn btn-sm btn-primary" id="btnRun"><i class="fas fa-sync"></i><?= lang('CRM_REP_RUN') ?></button>
                    </div>
                </div>

                <!-- ============================ KPIs ============================ -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-info"><i class="fas fa-phone-alt"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text"><?= lang('CRM_REP_CALLS') ?></span>
                                <span class="info-box-number" id="kpiCalls">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-primary"><i class="fas fa-user-plus"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text"><?= lang('CRM_REP_NEW_LEADS') ?></span>
                                <span class="info-box-number" id="kpiLeads">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-dark"><i class="fas fa-check-circle"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text"><?= lang('CRM_REP_CLOSED') ?></span>
                                <span class="info-box-number" id="kpiClosed">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning"><i class="fas fa-bell"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text"><?= lang('CRM_REP_OPEN_FOLLOWUPS') ?></span>
                                <span class="info-box-number" id="kpiFollow">0</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================ الرسوم ============================ -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-lg-7">
                        <div class="card card-outline card-info h-100">
                            <div class="card-header"><h3 class="card-title mb-0"><?= lang('CRM_REP_CALLS') ?><?= lang('CRM_REP_BY_USER') ?></h3></div>
                            <div class="card-body"><canvas id="chartUsers" height="140"></canvas></div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-5">
                        <div class="card card-outline card-success h-100">
                            <div class="card-header"><h3 class="card-title mb-0"><?= lang('CRM_REP_LEADS_BY_SOURCE') ?></h3></div>
                            <div class="card-body"><canvas id="chartSources" height="140"></canvas></div>
                        </div>
                    </div>
                </div>

                <!-- ============================ حسب الموظف ============================ -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <h5 class="mb-0"><i class="fas fa-users"></i><?= lang('CRM_REP_BY_USER') ?></h5>
                    <button class="btn btn-sm btn-outline-success" id="btnExpUsers">
                        <i class="fas fa-file-excel"></i><?= lang('CRM_LEADS_EXPORT') ?>
                    </button>
                </div>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-hover align-middle mb-0" id="tUsers">
                        <thead>
                            <tr>
                                <th><?= lang('CRM_LEADS_EMPLOYEE') ?></th>
                                <th><?= lang('CRM_REP_ASSIGNED') ?></th>
                                <th><?= lang('CRM_REP_CALLS_SIMPLE') ?></th>
                                <th><?= lang('CRM_STATUS_CLOSED') ?></th>
                                <th class="crm-opt"><?= lang('CRM_REP_OPEN_FOLLOWUPS') ?></th>
                                <th class="crm-opt"><?= lang('CRM_REP_MONTHLY_TARGET') ?></th>
                            </tr>
                        </thead>
                        <tbody><tr><td colspan="6" class="text-center text-muted py-3"><?= lang('CRM_COMMON_LOADING') ?></td></tr></tbody>
                    </table>
                </div>

                <!-- ============================ حسب المصدر ============================ -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <h5 class="mb-0"><i class="fas fa-bullhorn"></i><?= lang('CRM_REP_BY_SOURCE') ?></h5>
                    <button class="btn btn-sm btn-outline-success" id="btnExpSources">
                        <i class="fas fa-file-excel"></i><?= lang('CRM_LEADS_EXPORT') ?>
                    </button>
                </div>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-hover align-middle mb-0" id="tSources">
                        <thead>
                            <tr>
                                <th><?= lang('CRM_COMMON_SOURCE') ?></th>
                                <th><?= lang('CRM_REP_NEW_LEADS') ?></th>
                                <th><?= lang('CRM_REP_CALLS_SIMPLE') ?></th>
                                <th><?= lang('CRM_REP_TALKED') ?></th>
                                <th class="crm-opt"><?= lang('CRM_STATUS_CLOSED') ?></th>
                                <th class="crm-opt"><?= lang('CRM_REP_CALL_RATE') ?></th>
                            </tr>
                        </thead>
                        <tbody><tr><td colspan="6" class="text-center text-muted py-3"><?= lang('CRM_COMMON_LOADING') ?></td></tr></tbody>
                    </table>
                </div>

                <!-- ============================ حسب النتيجة ============================ -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <h5 class="mb-0"><i class="fas fa-phone-volume"></i><?= lang('CRM_REP_BY_RESULT') ?></h5>
                    <button class="btn btn-sm btn-outline-success" id="btnExpResults">
                        <i class="fas fa-file-excel"></i><?= lang('CRM_LEADS_EXPORT') ?>
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" id="tResults">
                        <thead>
                            <tr>
                                <th><?= lang('CRM_COMMON_RESULT') ?></th>
                                <th><?= lang('CRM_REP_CALLS_COUNT') ?></th>
                                <th><?= lang('CRM_REP_PERCENT') ?></th>
                            </tr>
                        </thead>
                        <tbody><tr><td colspan="3" class="text-center text-muted py-3"><?= lang('CRM_COMMON_LOADING') ?></td></tr></tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<?php crm_render_js_boot(); ?>
<script src="<?= $us_url_root ?>users/js/chart.umd.min.js"></script>
<script>
(function () {
    var $ = window.jQuery;
    var RAW = { users: [], sources: [], results: [] };
    var chartUsers = null, chartSources = null;

    var palette = ['#0d6efd', '#6f42c1', '#d63384', '#dc3545', '#fd7e14', '#ffc107',
                   '#198754', '#20c997', '#0dcaf0', '#6c757d', '#343a40', '#6610f2'];

    /* ------------------------------ الفترة ------------------------------ */
    function ymd(d) {
        return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
    }
    (function () {
        var to = new Date(), from = new Date();
        from.setDate(from.getDate() - 29);
        $('#rTo').val(ymd(to));
        $('#rFrom').val(ymd(from));
    })();

    function period() {
        return { from: $('#rFrom').val(), to: $('#rTo').val() };
    }

    /* ------------------------------ الموظفين ------------------------------ */
    function renderUsers(rows) {
        RAW.users = rows || [];
        var sumCalls = 0, sumFollow = 0;
        var tb = $('#tUsers tbody').empty();
        if (!RAW.users.length) {
            tb.append('<tr><td colspan="6" class="text-center text-muted py-3"><?= lang('CRM_REP_NO_TEAM') ?></td></tr>');
        }
        RAW.users.forEach(function (r) {
            sumCalls += r.calls;
            sumFollow += r.followups_open;
            tb.append('<tr>' +
                '<td>' + crm.e(r.name) + '</td>' +
                '<td>' + crm.num(r.leads_assigned) + '</td>' +
                '<td>' + crm.num(r.calls) + '</td>' +
                '<td>' + crm.num(r.closed) + '</td>' +
                '<td class="crm-opt">' + (r.followups_open
                    ? '<span class="badge bg-warning text-dark">' + crm.num(r.followups_open) + '</span>'
                    : '0') + '</td>' +
                '<td class="crm-opt">' + (r.target_monthly ? crm.num(r.target_monthly) : '<span class="text-muted">—</span>') + '</td>' +
                '</tr>');
        });
        $('#kpiCalls').text(crm.num(sumCalls));
        $('#kpiFollow').text(crm.num(sumFollow));

        /* رسم الأعمدة */
        var ctx = document.getElementById('chartUsers');
        if (!ctx || !window.Chart) { return; }
        var labels = RAW.users.map(function (r) { return r.name; });
        var data = RAW.users.map(function (r) { return r.calls; });
        if (chartUsers) {
            chartUsers.data.labels = labels;
            chartUsers.data.datasets[0].data = data;
            chartUsers.update();
            return;
        }
        chartUsers = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{ label: crmT('CRM_REP_CALLS_SIMPLE'), data: data, backgroundColor: '#0d6efd' }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    /* ------------------------------ المصادر ------------------------------ */
    function renderSources(rows) {
        RAW.sources = rows || [];
        var sumLeads = 0, sumClosed = 0;
        var tb = $('#tSources tbody').empty();
        if (!RAW.sources.length) {
            tb.append('<tr><td colspan="6" class="text-center text-muted py-3"><?= lang('CRM_REP_NO_DATA') ?></td></tr>');
        }
        RAW.sources.forEach(function (r) {
            sumLeads += r.leads;
            sumClosed += r.closed;
            var pct = r.leads ? Math.round(r.contacted / r.leads * 100) : 0;
            tb.append('<tr>' +
                '<td><span class="badge bg-' + crm.e(r.leads ? 'primary' : 'secondary') + '">' + crm.e(r.name) + '</span></td>' +
                '<td>' + crm.num(r.leads) + '</td>' +
                '<td>' + crm.num(r.calls) + '</td>' +
                '<td>' + crm.num(r.contacted) + '</td>' +
                '<td class="crm-opt">' + crm.num(r.closed) + '</td>' +
                '<td class="crm-opt">' + (r.leads
                    ? '<span class="badge bg-info">' + crm.num(pct) + '%</span>'
                    : '<span class="text-muted">—</span>') + '</td>' +
                '</tr>');
        });
        $('#kpiLeads').text(crm.num(sumLeads));
        $('#kpiClosed').text(crm.num(sumClosed));

        /* رسم الدونات */
        var ctx = document.getElementById('chartSources');
        if (!ctx || !window.Chart) { return; }
        var withLeads = RAW.sources.filter(function (r) { return r.leads > 0; });
        var labels = withLeads.map(function (r) { return r.name; });
        var data = withLeads.map(function (r) { return r.leads; });
        var colors = withLeads.map(function (r, i) { return palette[i % palette.length]; });
        if (chartSources) {
            chartSources.data.labels = labels;
            chartSources.data.datasets[0].data = data;
            chartSources.data.datasets[0].backgroundColor = colors;
            chartSources.update();
            return;
        }
        chartSources = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{ data: data, backgroundColor: colors }]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        });
    }

    /* ------------------------------ النتائج ------------------------------ */
    function renderResults(rows) {
        RAW.results = rows || [];
        var total = 0;
        RAW.results.forEach(function (r) { total += r.count; });
        var tb = $('#tResults tbody').empty();
        if (!RAW.results.length) {
            tb.append('<tr><td colspan="3" class="text-center text-muted py-3"><?= lang('CRM_REP_NO_CALLS') ?></td></tr>');
        }
        RAW.results.forEach(function (r) {
            var pct = total ? Math.round(r.count / total * 100) : 0;
            tb.append('<tr>' +
                '<td><span class="badge bg-' + crm.e(r.color || 'secondary') + '">' + crm.e(r.name) + '</span></td>' +
                '<td>' + crm.num(r.count) + '</td>' +
                '<td>' + crm.num(pct) + '%</td>' +
                '</tr>');
        });
    }

    /* ------------------------------ التحميل ------------------------------ */
    function loadReport() {
        var p = period();
        crm.get('stats_by_user', p, renderUsers);
        crm.get('stats_by_source', p, renderSources);
        crm.get('stats_by_result', p, renderResults);
    }

    /* ------------------------------ التصدير ------------------------------ */
    /* تنزيل xlsx من السيرفر بنفس فترة التقرير المختارة (Excel حقيقي) */
    function dlReport(what) {
        var p = period();
        window.location = crm.cfg.urls.show + '?' + $.param({
            action: 'export_xlsx', what: what, from: p.from, to: p.to
        });
    }
    $('#btnExpUsers').on('click', function () { dlReport('by_user'); });
    $('#btnExpSources').on('click', function () { dlReport('by_source'); });
    $('#btnExpResults').on('click', function () { dlReport('by_result'); });

    /* ------------------------------ الأحداث ------------------------------ */
    $('#btnRun').on('click', loadReport);
    $('#rFrom, #rTo').on('change', loadReport);

    /* ------------------------------ البداية ------------------------------ */
    loadReport();
})();
</script>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
