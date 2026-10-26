<?php
require_once __DIR__ . '/../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) {
    die();
}
require_once __DIR__ . '/scripts/lib/crm_func.php';
require_once __DIR__ . '/scripts/lib/crm_calls.php';

/* نتائج المكالمات بتتزرع للجافاسكربت (فلتر الموديل + تلوين الشارات) */
$crmCallResults = array_map(function ($r) {
    return [
        'key'               => (string) $r->key_name,
        'name'              => (string) $r->name_ar,
        'color'             => (string) ($r->color ?: 'secondary'),
        'requires_followup' => (int) $r->requires_followup,
    ];
}, crm_call_results());
?>
<div class="row">
  <div class="col-md-12">&nbsp;</div>
</div>

<!-- ============================ كروت الملخّص ============================ -->
<div class="row">
    <div class="col-6 col-lg-3">
        <div class="card card-outline card-primary">
            <div class="card-body py-2 text-center">
                <div id="stOpen" class="fs-3 fw-bold">…</div>
                <div class="small text-muted"><?= lang('CRM_MY_OPEN_LEADS') ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card card-outline card-secondary">
            <div class="card-body py-2 text-center">
                <div id="stUncontacted" class="fs-3 fw-bold">…</div>
                <div class="small text-muted"><?= lang('CRM_MY_NOT_CALLED') ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card card-outline card-warning">
            <div class="card-body py-2 text-center">
                <div id="stToday" class="fs-3 fw-bold">…</div>
                <div class="small text-muted"><?= lang('CRM_MY_FOLLOWUP_TODAY') ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card card-outline card-success">
            <div class="card-body py-2 text-center">
                <div id="stCalls" class="fs-3 fw-bold">…</div>
                <div class="small text-muted"><?= lang('CRM_MY_CALLS_TODAY') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ============================ قائمة العملاء ============================ -->
<div class="row">
    <div class="col-12">
        <div class="card card-outline card-primary">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <ul class="nav nav-pills" id="leadTabs">
                    <li class="nav-item"><a class="nav-link active" data-tab="new" href="javascript:void(0)"><?= lang('CRM_STATUS_NEW') ?></a></li>
                    <li class="nav-item"><a class="nav-link" data-tab="today" href="javascript:void(0)"><?= lang('CRM_MY_FOLLOWUP_TODAY') ?></a></li>
                    <li class="nav-item"><a class="nav-link" data-tab="all" href="javascript:void(0)"><?= lang('CRM_MY_ALL_LEADS') ?></a></li>
                    <li class="nav-item"><a class="nav-link" data-tab="closed" href="javascript:void(0)"><?= lang('CRM_MY_CLOSED') ?></a></li>
                </ul>
                <div class="d-flex gap-2">
                    <input type="text" id="leadQ" class="form-control form-control-sm" style="width:auto"
                           placeholder="<?= lang('CRM_MY_SEARCH_PH') ?>">
                    <button class="btn btn-sm btn-outline-primary" id="btnSearch">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover align-middle mb-0 crm-click" id="leadsTable">
                    <thead>
                        <tr>
                            <th class="crm-opt" style="width:6%">#</th>
                            <th style="width:13%"><?= lang('CRM_LEADS_TH_PHONE') ?></th>
                            <th style="width:14%"><?= lang('CRM_LEADS_TH_NAME') ?></th>
                            <th class="crm-opt" style="width:9%"><?= lang('CRM_LEADS_TH_CITY') ?></th>
                            <th style="width:12%"><?= lang('CRM_COMMON_STATUS_ONLY') ?></th>
                            <th class="crm-opt" style="width:12%"><?= lang('CRM_MY_LAST_RESULT') ?></th>
                            <th class="crm-opt" style="width:13%"><?= lang('CRM_LEADS_TH_LAST_CALL') ?></th>
                            <th class="crm-opt" style="width:13%"><?= lang('CRM_LEADS_TH_NEXT') ?></th>
                            <th style="width:14%"><?= lang('CRM_LEADS_TH_ACTIONS') ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
window.CRM_CALL_RESULTS = <?= json_encode($crmCallResults, JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php crm_render_js_boot(); ?>
<?php crm_flatpickr_boot(); ?>
<script>
(function () {
    var $ = window.jQuery;
    var currentTab = 'new';
    var lastCalledId = 0;   /* العميل اللي لسه سجّلت عليه مكالمة — يفضل ظاهر محدّد في الجدول */
    var resultsMap = {};
    (window.CRM_CALL_RESULTS || []).forEach(function (r) { resultsMap[r.key] = r; });

    var statusLabels = (window.CRM && window.CRM.lang && window.CRM.lang.status) || {};
    var statusBgs = {
        'new': 'bg-secondary', 'assigned': 'bg-secondary', 'contacted': 'bg-info',
        'followup': 'bg-warning', 'meeting': 'bg-primary', 'closed': 'bg-dark', 'recycled': 'bg-danger'
    };

    /* ------------------------------ الكروت ------------------------------ */
    function loadStats() {
        crm.get('my_stats', {}, function (d) {
            $('#stOpen').text(crm.num(d.open));
            $('#stUncontacted').text(crm.num(d.uncontacted));
            $('#stToday').text(crm.num(d.today_follow));
            $('#stCalls').text(crm.num(d.calls_today));
        });
    }

    /* ------------------------------ القائمة ------------------------------ */
    function loadLeads(includeId) {
        var payload = { tab: currentTab, q: $('#leadQ').val() || '' };
        if (includeId) { payload.include = includeId; }
        crm.get('my_leads_list', payload, function (rows) {
            rows = rows || [];
            var tb = $('#leadsTable tbody').empty();
            if (!rows.length) {
                tb.append('<tr><td colspan="9" class="text-center text-muted py-4"><?= lang('CRM_MY_NO_LEADS') ?></td></tr>');
                return;
            }
            rows.forEach(function (l) {
                var canCall = l.status !== 'closed';
                var justCalled = (lastCalledId && l.id === lastCalledId);
                tb.append('<tr data-id="' + l.id + '"' + (justCalled ? ' class="table-success"' : '') + '>' +
                    '<td class="crm-opt">' + crm.num(l.id) + '</td>' +
                    '<td>' + crm.e(l.phone) + '</td>' +
                    '<td>' + crm.e(l.name) + '</td>' +
                    '<td class="crm-opt">' + crm.e(l.city) + '</td>' +
                    '<td><span class="badge ' + (statusBgs[l.status] || 'bg-secondary') + '">' +
                        crm.e(statusLabels[l.status] || l.status) + '</span></td>' +
                    '<td class="crm-opt">' + (l.result_name
                        ? '<span class="badge bg-' + crm.e(l.result_color || 'secondary') + '">' + crm.e(l.result_name) + '</span>'
                        : '<span class="text-muted">—</span>') + '</td>' +
                    '<td class="crm-opt">' + (crm.e(crm.dt(l.last_call_at)) || '<span class="text-muted">—</span>') + '</td>' +
                    '<td class="crm-opt">' + (l.next_followup_at
                        ? '<span class="badge bg-warning text-dark">' + crm.e(crm.dt(l.next_followup_at)) + '</span>'
                        : '<span class="text-muted">—</span>') + '</td>' +
                    '<td class="text-nowrap">' +
                        '<a class="btn btn-xs btn-outline-secondary" ' +
                        'href="<?= $us_url_root ?>usersc/eh_lead_view.php?lead=' + l.id + '" title="<?= lang('CRM_LEADS_CARD_TITLE') ?>">' +
                        '<i class="fas fa-eye"></i></a> ' +
                        (canCall
                            ? '<button class="btn btn-xs btn-outline-primary call-btn" data-id="' + l.id + '" ' +
                              'data-label="' + crm.e((l.name || '') + ' — ' + l.phone) + '" title="<?= lang('CRM_CALL_LOG') ?>">' +
                              '<i class="fas fa-phone-alt"></i><?= lang('CRM_CALL_ACTION') ?></button>'
                            : '') + '</td>' +
                    '</tr>');
            });
        });
    }

    function reload() {
        loadStats();
        loadLeads();
    }

    /* ------------------------------ الأحداث ------------------------------ */
    /* لمس أي صف → يفتح كارت العميل (الأزرار شغالة زي ما هي) */
    $('#leadsTable tbody').on('click', 'tr', function (e) {
        if ($(e.target).closest('a, button').length) { return; }
        var id = $(this).data('id');
        if (id) { window.location = 'eh_lead_view.php?lead=' + id; }
    });

    $('#leadTabs').on('click', '.nav-link', function () {
        $('#leadTabs .nav-link').removeClass('active');
        $(this).addClass('active');
        currentTab = $(this).data('tab');
        lastCalledId = 0;
        loadLeads();
    });

    $('#btnSearch').on('click', function () { lastCalledId = 0; loadLeads(); });
    $('#leadQ').on('keypress', function (e) {
        if (e.which === 13) { lastCalledId = 0; loadLeads(); }
    });

    $(document).on('click', '.call-btn', function () {
        var id = $(this).data('id');
        var label = $(this).data('label');
        openCallModal(id, label);
    });

    function openCallModal(leadId, leadLabel) {
        crm.prompt({
            title: crmT('CRM_CALL_LOG_TITLE') + leadLabel,
            confirmLabel: crmT('CRM_CALL_SAVE'),
            fields: [
                {
                    name: 'result_key', label: crmT('CRM_CALL_RESULT'), type: 'select', required: true,
                    placeholder: crmT('CRM_CALL_PICK_RESULT'),
                    options: (window.CRM_CALL_RESULTS || []).map(function (r) {
                        return { value: r.key, label: r.name };
                    })
                },
                {
                    name: 'notes', label: crmT('CRM_COMMON_NOTES'), type: 'textarea',
                    placeholder: crmT('CRM_CALL_NOTES_PH')
                },
                {
                    name: 'next_followup', label: crmT('CRM_CALL_FOLLOWUP_DATE'), type: 'datetime-local',
                    placeholder: '',
                    requiredIf: function (vals) {
                        var r = resultsMap[vals.result_key];
                        return !!(r && r.requires_followup == 1);
                    }
                }
            ]
        }, function (vals) {
            crm.post('call_save', {
                lead_id: leadId,
                result_key: vals.result_key,
                notes: vals.notes,
                next_followup: vals.next_followup ? vals.next_followup.replace('T', ' ') + ':00' : ''
            }, function () {
                /* الكروت تتحدّث + نفس التاب يعيد التحميل مع إجبار ظهور العميل اللي اتسجلت عليه المكالمة
                   (مفيش اختفاء فجأة من الجدول) */
                lastCalledId = leadId;
                loadStats();
                loadLeads(leadId);
            });
        });
    }

    /* ------------------------------ البداية ------------------------------ */
    reload();
})();
</script>
<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php';
?>
