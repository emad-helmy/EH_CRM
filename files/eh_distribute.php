<?php
require_once __DIR__ . '/../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) {
    die();
}
require_once __DIR__ . '/scripts/lib/crm_func.php';
?>

<div class="row">
  <div class="col-md-12">&nbsp;</div>
</div>

<div class="row">

    <!-- ============================ التوزيع التلقائي ============================ -->
    <div class="col-12 col-lg-5">
        <div class="card card-outline card-primary h-100">
            <div class="card-header"><h3 class="card-title mb-0"><?= lang('CRM_DIST_TITLE') ?></h3></div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div id="unassignedCount" class="display-5 fw-bold text-primary">…</div>
                    <div class="text-muted"><?= lang('CRM_DIST_HINT') ?></div>
                </div>
                <button class="btn btn-primary w-100" id="btnRunDist">
                    <i class="fas fa-shuffle me-1"></i><?= lang('CRM_DIST_NOW') ?>
                </button>

                <!-- ============================ إعادة تدوير الباردين ============================ -->
                <hr class="my-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                    <span class="fw-bold"><?= lang('CRM_DIST_COLD_TITLE') ?>
                        <span class="badge bg-warning text-dark" id="recycleCount">…</span>
                    </span>
                    <button class="btn btn-sm btn-outline-warning" id="btnRecycle" disabled>
                        <i class="fas fa-recycle me-1"></i><?= lang('CRM_DIST_COLD_BTN') ?>
                    </button>
                </div>
                <div class="small text-muted mb-0" id="recycleHint"><?= lang('CRM_COMMON_LOADING') ?></div>
                <ul class="list-unstyled small text-muted mt-3 mb-0">
                    <li class="mb-1"><i class="fas fa-check text-success me-1"></i><?= lang('CRM_DIST_HINT1') ?></li>
                    <li class="mb-1"><i class="fas fa-check text-success me-1"></i><?= lang('CRM_DIST_HINT2') ?></li>
                    <li class="mb-1"><i class="fas fa-check text-success me-1"></i><?= lang('CRM_DIST_HINT3') ?></li>
                    <li class="mb-1"><i class="fas fa-check text-success me-1"></i><?= lang('CRM_DIST_HINT4') ?></li>
                    <li><i class="fas fa-check text-success me-1"></i><?= lang('CRM_DIST_HINT5') ?></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ============================ حمل الفريق ============================ -->
    <div class="col-12 col-lg-7">
        <div class="card card-outline card-info h-100">
            <div class="card-header"><h3 class="card-title mb-0"><?= lang('CRM_DIST_TEAM_LOAD') ?></h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped align-middle mb-0" id="teamTable">
                    <thead>
                        <tr>
                            <th><?= lang('CRM_LEADS_EMPLOYEE') ?></th>
                            <th style="width:14%"><?= lang('CRM_LEADS_STATUS') ?></th>
                            <th style="width:12%" class="text-center"><?= lang('CRM_MY_OPEN_LEADS') ?></th>
                            <th style="width:12%" class="text-center crm-opt"><?= lang('CRM_DIST_CALLED_THEM') ?></th>
                            <th style="width:14%" class="text-center crm-opt"><?= lang('CRM_DIST_CALLS_TODAY') ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================ قائمة غير الموزّعة ============================ -->
    <div class="col-12">
        <div class="card card-outline card-secondary">
            <div class="card-header"><h3 class="card-title mb-0"><?= lang('CRM_DIST_UNASSIGNED_TITLE') ?></h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover align-middle mb-0" id="unassignedTable">
                    <thead>
                        <tr>
                            <th class="crm-opt" style="width:6%">#</th>
                            <th style="width:14%"><?= lang('CRM_COMMON_PHONE') ?></th>
                            <th style="width:18%"><?= lang('CRM_COMMON_NAME') ?></th>
                            <th class="crm-opt" style="width:22%"><?= lang('CRM_COMMON_CITY') ?></th>
                            <th class="crm-opt"><?= lang('CRM_COMMON_MESSAGE') ?></th>
                            <th class="crm-opt" style="width:14%"><?= lang('CRM_LEADS_TH_CREATED') ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================ نقل أرقام ============================ -->
    <div class="col-12">
        <div class="card card-outline card-warning">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="card-title mb-0"><?= lang('CRM_DIST_TRANSFER_TITLE') ?></h3>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <select id="filterRep" class="form-select form-select-sm" style="width:auto">
                        <option value="0"><?= lang('CRM_DIST_ALL_EMPLOYEES') ?></option>
                    </select>
                    <input type="text" id="transferNote" class="form-control form-control-sm" style="width:auto"
                           placeholder="<?= lang('CRM_DIST_NOTE_PH') ?>">
                    <select id="targetRep" class="form-select form-select-sm" style="width:auto">
                        <option value=""><?= lang('CRM_DIST_PICK_EMPLOYEE') ?></option>
                    </select>
                    <span class="badge bg-dark" id="selCountWrap"><?= lang('CRM_DIST_SELECTED') ?><span id="selCount">0</span></span>
                    <button class="btn btn-sm btn-warning" id="btnTransfer">
                        <i class="fas fa-exchange-alt me-1"></i><?= lang('CRM_DIST_TRANSFER_SELECTED') ?>
                    </button>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover align-middle mb-0" id="assignedTable">
                    <thead>
                        <tr>
                            <th style="width:4%"><input type="checkbox" id="checkAll" title="<?= lang('CRM_DIST_SELECT_ALL') ?>"></th>
                            <th class="crm-opt" style="width:6%">#</th>
                            <th style="width:14%"><?= lang('CRM_COMMON_PHONE') ?></th>
                            <th style="width:18%"><?= lang('CRM_COMMON_NAME') ?></th>
                            <th class="crm-opt" style="width:12%"><?= lang('CRM_LEADS_STATUS') ?></th>
                            <th class="crm-opt" style="width:18%"><?= lang('CRM_DIST_CURRENT_EMPLOYEE') ?></th>
                            <th class="crm-opt"><?= lang('CRM_DIST_ASSIGN_DATE') ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php crm_render_js_boot(); ?>
<script>
(function () {
    var $ = window.jQuery;
    var selected = {};   // { leadId: true }

    var statusLabels = (window.CRM && window.CRM.lang && window.CRM.lang.status) || {};

    /* ------------------------------ الفريق ------------------------------ */
    function loadTeam() {
        crm.get('sales_load', {}, function (rows) {
            rows = rows || [];
            var tb = $('#teamTable tbody').empty();
            var fr = $('#filterRep').find('option:not(:first)').remove().end();
            var tr = $('#targetRep').find('option:not(:first)').remove().end();

            if (!rows.length) {
                tb.append('<tr><td colspan="5" class="text-center text-muted py-4"><?= lang('CRM_DIST_NO_EMPLOYEES') ?></td></tr>');
                return;
            }
            rows.forEach(function (u) {
                var avail = u.available == 1;
                tb.append('<tr>' +
                    '<td>' + crm.e(u.name) + '<br><small class="text-muted">' + crm.e(u.username) + '</small></td>' +
                    '<td>' + (avail
                        ? '<span class="badge bg-success"><?= lang('CRM_AVAIL_AVAILABLE') ?></span>'
                        : '<span class="badge bg-secondary"><?= lang('CRM_AVAIL_LEAVE') ?></span>') + '</td>' +
                    '<td class="text-center fw-bold">' + crm.num(u.open) +
                        (u.max_leads > 0 ? ' <small class="text-muted">/ ' + crm.num(u.max_leads) + '</small>' : '') + '</td>' +
                    '<td class="text-center crm-opt">' + crm.num(u.called) + '</td>' +
                    '<td class="text-center crm-opt">' + crm.num(u.calls_today) + '</td>' +
                    '</tr>');

                fr.append('<option value="' + u.id + '">' + crm.e(u.name) + ' (' + crm.num(u.open) + ')</option>');
                /* الموظف في إجازة مش بيتسند عليه نقل */
                tr.append('<option value="' + u.id + '"' + (avail ? '' : ' disabled') + '>' +
                    crm.e(u.name) + ' — ' + crmT('CRM_LOAD_LABEL') + ' ' + crm.num(u.open) + (avail ? '' : crmT('CRM_AVAIL_LEAVE_SHORT')) + '</option>');
            });
        });
    }

    /* --------------------- الأرقام غير الموزّعة --------------------- */
    function loadUnassigned() {
        crm.get('unassigned_list', {}, function (rows) {
            rows = rows || [];
            $('#unassignedCount').text(crm.num(rows.length));
            var tb = $('#unassignedTable tbody').empty();
            if (!rows.length) {
                tb.append('<tr><td colspan="6" class="text-center text-muted py-4"><?= lang('CRM_DIST_NO_UNASSIGNED') ?></td></tr>');
                return;
            }
            rows.forEach(function (l) {
                tb.append('<tr>' +
                    '<td class="crm-opt">' + crm.num(l.id) + '</td>' +
                    '<td>' + crm.e(l.phone) + '</td>' +
                    '<td>' + crm.e(l.name) + '</td>' +
                    '<td class="crm-opt">' + crm.e(l.city) + '</td>' +
                    '<td class="crm-opt">' + crm.e(l.message) + '</td>' +
                    '<td class="crm-opt">' + crm.e(crm.dt(l.created_at)) + '</td>' +
                    '</tr>');
            });
        });
    }

    /* --------------------- جدول النقل (الأرقام المنسدة) --------------------- */
    function loadAssigned() {
        crm.get('assigned_list', { user_id: $('#filterRep').val() || 0 }, function (rows) {
            rows = rows || [];
            selected = {};
            var tb = $('#assignedTable tbody').empty();
            if (!rows.length) {
                tb.append('<tr><td colspan="7" class="text-center text-muted py-4"><?= lang('CRM_DIST_NO_MATCH') ?></td></tr>');
                updateSelCount();
                return;
            }
            rows.forEach(function (l) {
                tb.append('<tr>' +
                    '<td><input type="checkbox" class="row-sel" value="' + l.id + '"></td>' +
                    '<td class="crm-opt">' + crm.num(l.id) + '</td>' +
                    '<td>' + crm.e(l.phone) + '</td>' +
                    '<td>' + crm.e(l.name) + '</td>' +
                    '<td class="crm-opt"><span class="badge bg-secondary">' + crm.e(statusLabels[l.status] || l.status) + '</span></td>' +
                    '<td class="crm-opt">' + crm.e(l.rep_name) + '</td>' +
                    '<td class="crm-opt">' + crm.e(crm.dt(l.assigned_at)) + '</td>' +
                    '</tr>');
            });
            updateSelCount();
        });
    }

    function updateSelCount() {
        $('#selCount').text(Object.keys(selected).length);
    }

    /* --------------------- العملاء الباردين (إعادة التدوير) --------------------- */
    var recycleData = { count: 0, days: 3, rows: [], disabled: false, msg: '' };

    function loadRecycle() {
        crm.get('recycle_list', {}, function (d) {
            recycleData = d || { count: 0, rows: [], disabled: false, msg: '' };
            $('#recycleCount').text(crm.num(recycleData.count || 0));

            if (recycleData.disabled) {
                $('#btnRecycle').prop('disabled', true);
                $('#recycleHint').html('<i class="fas fa-info-circle me-1"></i>' +
                    crm.e(recycleData.msg || crmT('CRM_DIST_NO_RECYCLE')));
            } else if (!recycleData.count) {
                $('#btnRecycle').prop('disabled', true);
                $('#recycleHint').text(crmT('CRM_DIST_COLD_NONE_RULE') +
                    crm.num(recycleData.days) + crmT('CRM_DIST_COLD_RULE_END'));
            } else {
                $('#btnRecycle').prop('disabled', false);
                var reps = {};
                (recycleData.rows || []).forEach(function (r) {
                    var k = r.rep_name || crmT('CRM_DIST_UNKNOWN');
                    reps[k] = (reps[k] || 0) + 1;
                });
                var byRep = Object.keys(reps).map(function (k) {
                    return k + ' (' + crm.num(reps[k]) + ')';
                }).join('، ');
                $('#recycleHint').text(crmT('CRM_DIST_COLD_WILL') + crm.num(recycleData.count) + crmT('CRM_DIST_COLD_COUNT') +
                    crm.num(recycleData.days) + crmT('CRM_DIST_COLD_DAYS') + byRep +
                    crmT('CRM_DIST_COLD_BACK'));
            }
        });
    }

    $('#btnRecycle').on('click', function () {
        var n = recycleData.count || 0;
        if (!n) { crm.toast('warning', crmT('CRM_DIST_COLD_NONE')); return; }
        crm.confirm(
            crmT('CRM_DIST_COLD_ACTION') + crm.num(n) + crmT('CRM_DIST_COLD_CONFIRM'),
            function () {
                crm.post('recycle_run', {}, function () {
                    loadRecycle();
                    loadUnassigned();
                    loadAssigned();
                    loadTeam();
                });
            },
            crmT('CRM_DIST_COLD_CONFIRM_TITLE'), crmT('CRM_DIST_COLD_CONFIRM_BTN')
        );
    });

    /* ------------------------------ الأحداث ------------------------------ */
    $(document).on('change', '.row-sel', function () {
        var id = $(this).val();
        if (this.checked) { selected[id] = true; } else { delete selected[id]; }
        updateSelCount();
    });

    $('#checkAll').on('change', function () {
        var on = this.checked;
        selected = {};
        $('#assignedTable .row-sel').prop('checked', on).each(function () {
            if (on) { selected[$(this).val()] = true; }
        });
        updateSelCount();
    });

    $('#filterRep').on('change', function () {
        $('#checkAll').prop('checked', false);
        loadAssigned();
    });

    $('#btnRunDist').on('click', function () {
        var n = parseInt($('#unassignedCount').text(), 10) || 0;
        if (!n) { crm.toast('warning', crmT('CRM_DIST_NO_UNASSIGNED_NOW')); return; }
        crm.confirm(crmT('CRM_DIST_ACTION') + crm.num(n) + crmT('CRM_DIST_CONFIRM_DIST'), function () {
            crm.post('assign_run', {}, function (data) {
                loadUnassigned();
                loadTeam();
                loadAssigned();
            });
        }, crmT('CRM_DIST_CONFIRM_DIST_TITLE'), crmT('CRM_DIST_NOW'));
    });

    $('#btnTransfer').on('click', function () {
        var ids = Object.keys(selected);
        if (!ids.length) { crm.toast('warning', crmT('CRM_ERR_PICK_LEADS')); return; }
        var to = $('#targetRep').val();
        if (!to) { crm.toast('warning', crmT('CRM_ERR_PICK_ASSIGNEE')); return; }
        var toName = $('#targetRep option:selected').text();
        crm.confirm(crmT('CRM_TRANSFER_ACTION') + crm.num(ids.length) + crmT('CRM_DIST_CONFIRM_TRANSFER') + toName + '؟', function () {
            crm.post('bulk_transfer', {
                lead_ids: ids,
                to_user: to,
                note: $('#transferNote').val()
            }, function (data) {
                $('#checkAll').prop('checked', false);
                loadAssigned();
                loadTeam();
                loadUnassigned();
            });
        }, crmT('CRM_DIST_CONFIRM_TRANSFER_TITLE'), crmT('CRM_TRANSFER'));
    });

    /* ------------------------------ البداية ------------------------------ */
    loadTeam();
    loadUnassigned();
    loadAssigned();
    loadRecycle();
})();
</script>
<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php';
?>
