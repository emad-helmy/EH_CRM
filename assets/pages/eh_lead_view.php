<?php
/*
 =============================================================================
 usersc/eh_lead_view.php — كارت العميل: بياناته + سجل مكالماته + (تسجيل مكالمة / نقل)
 -----------------------------------------------------------------------------
  الفتح من: كل العملاء / عملائي  →  lead_view.php?lead=ID
  البيانات بتيجي من lead_card (sales يشوف عملائه بس — المدير يشوف الكل).
 =============================================================================
 */
require_once __DIR__ . '/../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) {
    die();
}
require_once $abs_us_root . $us_url_root . 'usersc/plugins/eh_crm/lib/crm_func.php';

$leadId      = (int) ($_GET['lead'] ?? 0);
$canTransfer = crm_is_sales_manager();   // النقل لمدير/مشرف/أدمن فقط
?>

<div class="row">
  <div class="col-md-12">&nbsp;</div>
</div>

<?php if ($leadId <= 0): ?>

    <div class="alert alert-danger">
        <?= lang('CRM_CARD_BAD_ID') ?>
        <a class="alert-link" href="<?= $us_url_root ?>usersc/eh_leads.php"><?= lang('CRM_CARD_BACK_LEADS') ?></a>
    </div>

<?php else: ?>

<div id="leadCardWrap">
    <!-- ============================ بيانات العميل ============================ -->
    <div class="row">
        <div class="col-12">
            <div class="card card-outline card-primary">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-id-card"></i><?= lang('CRM_CARD_TITLE') ?>
                        <span class="text-muted fw-normal" id="lvTitle"></span>
                    </h3>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php if (crm_can_page('usersc/eh_leads.php')) { ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= $us_url_root ?>usersc/eh_leads.php">
                                <i class="fas fa-users"></i><?= lang('CRM_LEADS_TITLE') ?>
                            </a>
                        <?php } ?>
                        <?php if (crm_can_page('usersc/eh_my_leads.php')) { ?>
                            <a class="btn btn-sm btn-outline-success" href="<?= $us_url_root ?>usersc/eh_my_leads.php">
                                <i class="fas fa-phone-volume"></i><?= lang('CRM_NAV_MY_LEADS') ?>
                            </a>
                        <?php } ?>
                        <button class="btn btn-sm btn-primary" id="btnCall" style="display:none">
                            <i class="fas fa-phone-alt"></i><?= lang('CRM_CALL_LOG') ?>
                        </button>
                        <?php if ($canTransfer) { ?>
                            <button class="btn btn-sm btn-warning" id="btnTransfer" style="display:none">
                                <i class="fas fa-right-left"></i><?= lang('CRM_TRANSFER_OTHER') ?>
                            </button>
                        <?php } ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-lg-3"><div class="small text-muted"><?= lang('CRM_LEADS_TH_NAME') ?></div><div class="fw-semibold" id="lvName">—</div></div>
                        <div class="col-6 col-lg-3"><div class="small text-muted"><?= lang('CRM_LEADS_TH_PHONE') ?></div><div class="fw-bold" id="lvPhone">—</div></div>
                        <div class="col-6 col-lg-3"><div class="small text-muted"><?= lang('CRM_CARD_PHONE2') ?></div><div id="lvPhone2">—</div></div>
                        <div class="col-6 col-lg-3"><div class="small text-muted"><?= lang('CRM_LEADS_TH_CITY') ?></div><div id="lvCity">—</div></div>
                        <div class="col-6 col-lg-3"><div class="small text-muted"><?= lang('CRM_CARD_EMAIL') ?></div><div id="lvEmail">—</div></div>
                        <div class="col-6 col-lg-3"><div class="small text-muted"><?= lang('CRM_COMMON_STATUS_ONLY') ?></div><div id="lvStatus">—</div></div>
                        <div class="col-6 col-lg-3"><div class="small text-muted"><?= lang('CRM_MY_LAST_RESULT') ?></div><div id="lvResult">—</div></div>
                        <div class="col-6 col-lg-3"><div class="small text-muted"><?= lang('CRM_CARD_ASSIGNED_TO') ?></div><div id="lvAssigned">—</div></div>
                        <div class="col-6 col-lg-3"><div class="small text-muted"><?= lang('CRM_LEADS_SOURCE') ?></div><div id="lvSource">—</div></div>
                        <div class="col-6 col-lg-3"><div class="small text-muted"><?= lang('CRM_LEADS_PROJECT') ?></div><div id="lvProject">—</div></div>
                        <div class="col-6 col-lg-3"><div class="small text-muted"><?= lang('CRM_CARD_CREATED_BY') ?></div><div id="lvCreatedBy">—</div></div>
                        <div class="col-6 col-lg-3"><div class="small text-muted"><?= lang('CRM_LEADS_TH_CREATED') ?></div><div id="lvCreatedAt">—</div></div>
                        <div class="col-6 col-lg-3"><div class="small text-muted"><?= lang('CRM_LEADS_TH_LAST_CALL') ?></div><div id="lvLastCall">—</div></div>
                        <div class="col-6 col-lg-3"><div class="small text-muted"><?= lang('CRM_CARD_CALLS') ?></div><div id="lvCalls">—</div></div>
                        <div class="col-6 col-lg-3"><div class="small text-muted"><?= lang('CRM_LEADS_TH_NEXT') ?></div><div id="lvNext">—</div></div>
                        <div class="col-6 col-lg-3"><div class="small text-muted"><?= lang('CRM_CARD_CLOSED_AT') ?></div><div id="lvClosedAt">—</div></div>
                        <div class="col-12"><div class="small text-muted"><?= lang('CRM_COMMON_MESSAGE') ?></div><div id="lvMessage">—</div></div>
                        <div class="col-12"><div class="small text-muted"><?= lang('CRM_CARD_NOTES') ?></div><div id="lvNotes">—</div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================ سجل المكالمات ============================ -->
    <div class="row">
        <div class="col-12">
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-clipboard-list"></i><?= lang('CRM_CARD_CALLS_LOG') ?>
                        <span class="badge bg-dark" id="lvCallsCount">0</span>
                    </h3>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm table-hover align-middle mb-0" id="lvCallsTable">
                        <thead>
                            <tr>
                                <th style="width:5%">#</th>
                                <th style="width:15%"><?= lang('CRM_COMMON_DATE') ?></th>
                                <th style="width:17%"><?= lang('CRM_LEADS_EMPLOYEE') ?></th>
                                <th style="width:15%"><?= lang('CRM_COMMON_RESULT') ?></th>
                                <th><?= lang('CRM_COMMON_NOTES_HDR') ?></th>
                                <th style="width:17%"><?= lang('CRM_LEADS_TH_NEXT') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="6" class="text-center text-muted py-4"><?= lang('CRM_COMMON_LOADING') ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php crm_render_js_boot(); ?>
<?php crm_flatpickr_boot(); ?>
<script>
(function () {
    var $ = window.jQuery;
    var LEAD_ID      = <?= $leadId ?>;
    var CAN_TRANSFER = <?= $canTransfer ? 'true' : 'false' ?>;
    var current      = null;
    var resultsRows  = [];
    var resultsMap   = {};

    var statusLabels = (window.CRM && window.CRM.lang && window.CRM.lang.status) || {};
    var statusBgs = {
        'new': 'bg-secondary', 'assigned': 'bg-secondary', 'contacted': 'bg-info',
        'followup': 'bg-warning', 'meeting': 'bg-primary', 'closed': 'bg-dark', 'recycled': 'bg-danger'
    };

    function txt(id, v) { $(id).text(v ? v : '—'); }

    /* ------------------------------ تحميل الكارت ------------------------------ */
    function loadCard() {
        crm.get('lead_card', { id: LEAD_ID }, function (d) {
            var l = d.lead;
            current = l;

            $('#lvTitle').text('— ' + (l.name || l.phone));
            txt('#lvName', l.name);
            txt('#lvPhone', l.phone);
            txt('#lvPhone2', l.phone2);
            txt('#lvCity', l.city);
            txt('#lvEmail', l.email);
            $('#lvStatus').html('<span class="badge ' + (statusBgs[l.status] || 'bg-secondary') + '">' +
                crm.e(statusLabels[l.status] || l.status) + '</span>');
            $('#lvResult').html(l.result_name
                ? '<span class="badge bg-' + crm.e(l.result_color || 'secondary') + '">' + crm.e(l.result_name) + '</span>'
                : '—');
            txt('#lvAssigned', l.assigned_name);
            txt('#lvSource', l.source_name);
            txt('#lvProject', l.project_name);
            txt('#lvCreatedBy', l.created_by_name);
            txt('#lvCreatedAt', crm.dt(l.created_at));
            txt('#lvLastCall', crm.dt(l.last_call_at));
            txt('#lvCalls', crm.num(l.calls_count));
            txt('#lvNext', crm.dt(l.next_followup_at));
            txt('#lvClosedAt', crm.dt(l.closed_at));
            txt('#lvMessage', l.message);
            txt('#lvNotes', l.notes);

            /* سجل المكالمات */
            var calls = d.calls || [];
            $('#lvCallsCount').text(crm.num(calls.length));
            var tb = $('#lvCallsTable tbody').empty();
            if (!calls.length) {
                tb.append('<tr><td colspan="6" class="text-center text-muted py-4"><?= lang('CRM_CARD_NO_CALLS') ?></td></tr>');
            } else {
                calls.forEach(function (c, i) {
                    tb.append('<tr>' +
                        '<td>' + crm.num(calls.length - i) + '</td>' +
                        '<td>' + crm.e(crm.dt(c.call_at)) + '</td>' +
                        '<td>' + crm.e(c.user_name) + '</td>' +
                        '<td><span class="badge bg-' + crm.e(c.result_color || 'secondary') + '">' +
                            crm.e(c.result_name) + '</span></td>' +
                        '<td>' + (crm.e(c.notes) || '<span class="text-muted">—</span>') + '</td>' +
                        '<td>' + (c.next_followup_at
                            ? '<span class="badge bg-warning text-dark">' + crm.e(crm.dt(c.next_followup_at)) + '</span>'
                            : '<span class="text-muted">—</span>') + '</td>' +
                        '</tr>');
                });
            }

            /* الأزرار */
            $('#btnCall').toggle(l.status !== 'closed');
            $('#btnTransfer').toggle(!!CAN_TRANSFER && l.status !== 'closed');
        }, function () {
            $('#leadCardWrap').html(
                '<div class="alert alert-danger"><?= lang('CRM_CARD_NO_ACCESS') ?>' +
                '<a class="alert-link" href="<?= $us_url_root ?>usersc/eh_index.php"><?= lang('CRM_CARD_BACK_HOME') ?></a></div>'
            );
        });
    }

    /* ------------------------------ نتائج المكالمات ------------------------------ */
    crm.get('results_list', {}, function (rows) {
        resultsRows = rows || [];
        resultsRows.forEach(function (r) { resultsMap[r.key] = r; });
    });

    /* ------------------------------ تسجيل مكالمة ------------------------------ */
    $('#btnCall').on('click', function () {
        if (!current) { return; }
        crm.prompt({
            title: crmT('CRM_CALL_LOG_TITLE') + (current.name || current.phone),
            confirmLabel: crmT('CRM_CALL_SAVE'),
            fields: [
                {
                    name: 'result_key', label: crmT('CRM_CALL_RESULT'), type: 'select', required: true,
                    placeholder: crmT('CRM_CALL_PICK_RESULT'),
                    options: resultsRows.map(function (r) { return { value: r.key, label: r.name }; })
                },
                { name: 'notes', label: crmT('CRM_COMMON_NOTES'), type: 'textarea', placeholder: crmT('CRM_CALL_NOTES_PH') },
                {
                    name: 'next_followup', label: crmT('CRM_CALL_FOLLOWUP_DATE'), type: 'datetime-local',
                    requiredIf: function (vals) {
                        var r = resultsMap[vals.result_key];
                        return !!(r && r.followup == 1);
                    }
                }
            ]
        }, function (vals) {
            crm.post('call_save', {
                lead_id: LEAD_ID,
                result_key: vals.result_key,
                notes: vals.notes,
                next_followup: vals.next_followup ? vals.next_followup.replace('T', ' ') + ':00' : ''
            }, function () {
                loadCard();
            });
        });
    });

    /* ------------------------------ نقل لموظف تاني ------------------------------ */
    $('#btnTransfer').on('click', function () {
        crm.get('sales_load', {}, function (users) {
            var opts = (users || [])
                .filter(function (u) {
                    return !(current && u.id === current.assigned_to);   // نشيل الحالي
                })
                .map(function (u) {
                    return {
                        value: u.id,
                        label: u.name + ' — ' + crmT('CRM_LOAD_LABEL') + ' ' + crm.num(u.open) + (u.available ? '' : crmT('CRM_AVAIL_LEAVE_SHORT'))
                    };
                });
            crm.prompt({
                title: crmT('CRM_TRANSFER_TITLE'),
                confirmLabel: crmT('CRM_TRANSFER'),
                fields: [
                    {
                        name: 'target', label: crmT('CRM_TRANSFER_EMPLOYEE'), type: 'select', required: true,
                        placeholder: crmT('CRM_TRANSFER_PICK'), options: opts
                    },
                    { name: 'note', label: crmT('CRM_TRANSFER_NOTE'), placeholder: crmT('CRM_TRANSFER_REASON') }
                ]
            }, function (v) {
                crm.post('bulk_transfer', {
                    lead_ids: [LEAD_ID],
                    to_user: v.target,
                    note: v.note
                }, function () {
                    loadCard();
                });
            });
        });
    });

    /* ------------------------------ البداية ------------------------------ */
    loadCard();
})();
</script>
<?php endif; ?>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
