<?php
/*
=============================================================================
 usersc/index.php — الصفحة الرئيسية للمشروع (الداشبورد)
-----------------------------------------------------------------------------
 صفحة مشروع عادية في UserSpice بالظبط:
   init  →  prep (الهيدر + القايمة الجانبية)  →  securePage (تسجيل الدخول
   + الصلاحيات). يعني الصلاحيات بتتحدد من لوحة التحكم (Permissions) ومفيش
   أي توجيه حسب الدور ولا أي قائمة أدوار في الكود.
=============================================================================
*/

require_once __DIR__ . '/../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) {
    die();
}

/* helpers المشروع (فحص البرمشنز/الإعدادات/الشكل الموحّد للردود) */
require_once $abs_us_root . $us_url_root . 'usersc/plugins/eh_crm/lib/crm_func.php';

$crm_uid    = (int) $user->data()->id;
$crm_has    = crm_has_any_role($crm_uid);       // عنده أي صلاحية من صلاحيات المشروع؟
$crm_is_mgr = crm_is_sales_manager($crm_uid);   // مستوى مدير (يشوف الكل)؟
?>

<div class="row">
  <div class="col-md-12">&nbsp;</div>
</div>


<?php if (!$crm_has): ?>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-7">
            <div class="card card-outline card-danger">
                <div class="card-header">
                    <h3 class="card-title mb-0"><?= lang('CRM_DASH_NO_PERM_TITLE') ?></h3>
                </div>
                <div class="card-body">
                    <p class="mb-3">
                        <?= lang('CRM_DASH_NO_PERM_TEXT') ?>
                        <br>
                        <?= lang('CRM_DASH_NO_PERM_HINT') ?>
                        <strong>Users → Permissions</strong>.
                    </p>
                    <a class="btn btn-outline-secondary" href="<?= $us_url_root ?>users/logout.php">
                        <?= lang('CRM_COMMON_LOGOUT') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3 col-xl-2">
            <div class="card text-bg-primary h-100">
                <div class="card-body py-3">
                    <div class="small opacity-75"><?= lang('CRM_LEADS_TITLE') ?></div>
                    <div class="fs-3 fw-bold" id="csTotal">—</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 col-xl-2">
            <div class="card text-bg-secondary h-100">
                <div class="card-body py-3">
                    <div class="small opacity-75"><?= lang('CRM_DASH_NOT_ASSIGNED') ?></div>
                    <div class="fs-3 fw-bold" id="csNew">—</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 col-xl-2">
            <div class="card text-bg-info h-100">
                <div class="card-body py-3">
                    <div class="small opacity-75"><?= lang('CRM_DASH_ACTIVE_NOW') ?></div>
                    <div class="fs-3 fw-bold" id="csOpen">—</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 col-xl-2">
            <div class="card text-bg-warning h-100">
                <div class="card-body py-3">
                    <div class="small opacity-75"><?= lang('CRM_DASH_DUE') ?></div>
                    <div class="fs-3 fw-bold" id="csDue">—</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 col-xl-2">
            <div class="card text-bg-danger h-100">
                <div class="card-body py-3">
                    <div class="small opacity-75"><?= lang('CRM_DASH_NOT_CALLED') ?></div>
                    <div class="fs-3 fw-bold" id="csNotCalled">—</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 col-xl-2">
            <div class="card text-bg-success h-100">
                <div class="card-body py-3">
                    <div class="small opacity-75"><?= lang('CRM_DASH_CLOSED') ?></div>
                    <div class="fs-3 fw-bold" id="csClosed">—</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <?php if ($crm_is_mgr): ?>
        <div class="col-12 col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h3 class="card-title mb-0"><?= lang('CRM_DASH_TEAM_LOAD') ?></h3>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="crmReloadLoad">
                        <i class="fas fa-rotate"></i><?= lang('CRM_COMMON_REFRESH') ?>
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" id="crmSalesLoadTable">
                            <thead>
                                <tr>
                                    <th><?= lang('CRM_LEADS_EMPLOYEE') ?></th>
                                    <th class="text-center"><?= lang('CRM_COMMON_STATUS_ONLY') ?></th>
                                    <th class="text-center"><?= lang('CRM_DASH_WORKING_NOW') ?></th>
                                    <th class="text-center crm-opt"><?= lang('CRM_DASH_CALLED') ?></th>
                                    <th class="text-center crm-opt"><?= lang('CRM_MY_CALLS_TODAY') ?></th>
                                    <th class="text-center crm-opt"><?= lang('CRM_DASH_TOTAL') ?></th>
                                    <th class="text-center crm-opt"><?= lang('CRM_DASH_CLOSED') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="7" class="text-center text-muted py-4"><?= lang('CRM_COMMON_LOADING') ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="col-12 <?= $crm_is_mgr ? 'col-xl-4' : '' ?>">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title mb-0"><?= lang('CRM_DASH_QUICK') ?></h3></div>
                <div class="card-body d-grid gap-2">
                    <?php
                    // كل لينك بيظهر بس لو الصلاحية بتسمح بفتح الصفحة (نفس منطق securePage)
                    $crm_quick = [
                        ['usersc/lead_import.php', 'fas fa-file-csv',      lang('CRM_NAV_IMPORT'), 'btn-outline-primary'],
                        ['usersc/lead_add.php',    'fas fa-user-plus',     lang('CRM_NAV_ADD'),        'btn-outline-primary'],
                        ['usersc/batches.php',     'fas fa-layer-group',  lang('CRM_NAV_BATCHES'),     'btn-outline-primary'],
                        ['usersc/my_leads.php',    'fas fa-phone-volume', lang('CRM_NAV_MY_LEADS'),            'btn-outline-success'],
                        ['usersc/my_calls.php',    'fas fa-clipboard-list',lang('CRM_NAV_MY_CALLS'),      'btn-outline-success'],
                        ['usersc/leads.php',       'fas fa-users',        lang('CRM_LEADS_TITLE'),        'btn-outline-primary'],
                        ['usersc/distribute.php',  'fas fa-shuffle',      lang('CRM_NAV_DISTRIBUTE'),   'btn-outline-primary'],
                        ['usersc/reports.php',     'fas fa-chart-line',   lang('CRM_NAV_REPORTS'),          'btn-outline-primary'],
                        ['usersc/settings.php',    'fas fa-sliders',      lang('CRM_NAV_SETTINGS'),       'btn-outline-secondary'],
                    ];
                    $crm_shown = 0;
                    foreach ($crm_quick as [$crm_pg, $crm_ic, $crm_lb, $crm_cl]) {
                        if (!crm_can_page($crm_pg)) {
                            continue;
                        }
                        $crm_shown++;
                        if ($crm_pg === 'usersc/lead_add.php') {
                            // الإضافة بقت موديل من نفس الرئيسية
                            ?>
                            <button type="button" class="btn <?= $crm_cl ?>" data-bs-toggle="modal" data-bs-target="#mdlAddLead">
                                <i class="<?= $crm_ic ?>"></i> <?= $crm_lb ?>
                            </button>
                            <?php
                        } else {
                            ?>
                            <a class="btn <?= $crm_cl ?>" href="<?= $us_url_root ?><?= $crm_pg ?>">
                                <i class="<?= $crm_ic ?>"></i> <?= $crm_lb ?>
                            </a>
                            <?php
                        }
                    }
                    if ($crm_shown === 0) {
                        ?>
                        <span class="text-muted small"><?= lang('CRM_DASH_NO_PAGES') ?></span>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (crm_can_page('usersc/lead_add.php')) { ?>
    <!-- ===================== موديل إضافة عميل (من الرئيسية) ===================== -->
    <div class="modal fade" id="mdlAddLead" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> <?= lang('CRM_NAV_ADD') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('CRM_COMMON_CLOSE') ?>"></button>
                </div>
                <div class="modal-body">
                    <form id="idxFrmLead">
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label"><?= lang('CRM_COMMON_PHONE') ?><span class="text-danger">*</span></label>
                                <input type="text" id="idxLeadPhone" class="form-control" placeholder="01012345678" inputmode="tel">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label"><?= lang('CRM_COMMON_NAME') ?></label>
                                <input type="text" id="idxLeadName" class="form-control">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label"><?= lang('CRM_COMMON_CITY_ONLY') ?></label>
                                <input type="text" id="idxLeadCity" class="form-control">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label"><?= lang('CRM_LEADS_PROJECT') ?></label>
                                <input type="text" id="idxLeadProject" class="form-control">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label"><?= lang('CRM_LEADS_SOURCE') ?></label>
                                <select id="idxLeadSource" class="form-select">
                                    <option value=""><?= lang('CRM_ADD_PICK_SOURCE') ?></option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?= lang('CRM_COMMON_MESSAGE') ?></label>
                                <input type="text" id="idxLeadMessage" class="form-control" placeholder="<?= lang('CRM_COMMON_MESSAGE_PH') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?= lang('CRM_COMMON_NOTES') ?></label>
                                <textarea id="idxLeadNotes" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= lang('CRM_COMMON_CANCEL') ?></button>
                    <button type="button" class="btn btn-success" id="idxBtnSaveLead">
                        <i class="fas fa-save"></i><?= lang('CRM_ADD_SAVE') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>

    <script nonce="<?= htmlspecialchars($userspice_nonce ?? '', ENT_QUOTES, 'UTF-8') ?>">
    (function () {
        'use strict';

        function fill(id, v) {
            var el = document.getElementById(id);
            if (el) { el.textContent = crm.num(v); }
        }

        function loadStats() {
            crm.get('stats_overview', {}, function (d) {
                fill('csTotal', d.total);
                fill('csNew', d.new);
                fill('csOpen', d.open);
                fill('csDue', d.due_followups);
                fill('csNotCalled', d.not_called_yet);
                fill('csClosed', d.closed);
            });
        }

        function loadSalesLoad() {
            var tb = document.querySelector('#crmSalesLoadTable tbody');
            if (!tb) { return; }
            tb.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><?= lang('CRM_COMMON_LOADING') ?></td></tr>';

            crm.get('sales_load', {}, function (rows) {
                if (!rows || !rows.length) {
                    tb.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><?= lang('CRM_DASH_NO_SALES') ?></td></tr>';
                    return;
                }
                var html = '';
                rows.forEach(function (r) {
                    var badge = r.available
                        ? '<span class="badge text-bg-success"><?= lang('CRM_AVAIL_AVAILABLE') ?></span>'
                        : '<span class="badge text-bg-secondary"><?= lang('CRM_AVAIL_LEAVE') ?></span>';
                    var cap = r.max_leads > 0 ? ' <small class="text-muted">/ ' + crm.num(r.max_leads) + '</small>' : '';
                    html += '<tr>' +
                        '<td>' + crm.e(r.name) + '<br><small class="text-muted">' + crm.e(r.username) + '</small></td>' +
                        '<td class="text-center">' + badge + '</td>' +
                        '<td class="text-center fw-bold">' + crm.num(r.open) + cap + '</td>' +
                        '<td class="text-center crm-opt">' + crm.num(r.called) + '</td>' +
                        '<td class="text-center crm-opt">' + crm.num(r.calls_today) + '</td>' +
                        '<td class="text-center crm-opt">' + crm.num(r.total) + '</td>' +
                        '<td class="text-center crm-opt">' + crm.num(r.closed) + '</td>' +
                        '</tr>';
                });
                tb.innerHTML = html;
            }, function () {
                tb.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4"><?= lang('CRM_COMMON_LOAD_FAIL') ?></td></tr>';
            });
        }

        /* --------------------- موديل إضافة عميل من الرئيسية --------------------- */
        var mdlAddLead = document.getElementById('mdlAddLead');
        if (mdlAddLead) {
            var srcLoaded = false;
            mdlAddLead.addEventListener('shown.bs.modal', function () {
                if (srcLoaded) { return; }
                crm.get('sources_list', {}, function (rows) {
                    var sel = document.getElementById('idxLeadSource');
                    (rows || []).forEach(function (s) {
                        var o = document.createElement('option');
                        o.value = s.id;
                        o.textContent = s.name;
                        sel.appendChild(o);
                    });
                    srcLoaded = true;
                });
            });

            document.getElementById('idxBtnSaveLead').addEventListener('click', function () {
                var phone = document.getElementById('idxLeadPhone').value.trim();
                if (!phone) {
                    crm.toast('warning', crmT('CRM_ERR_PHONE_FIRST'));
                    return;
                }
                var btn = this;
                btn.disabled = true;
                crm.post('lead_create', {
                    phone: phone,
                    name: document.getElementById('idxLeadName').value,
                    city: document.getElementById('idxLeadCity').value,
                    project: document.getElementById('idxLeadProject').value,
                    source_id: document.getElementById('idxLeadSource').value,
                    message: document.getElementById('idxLeadMessage').value,
                    notes: document.getElementById('idxLeadNotes').value
                }, function (data) {
                    document.getElementById('idxFrmLead').reset();
                    bootstrap.Modal.getOrCreateInstance(mdlAddLead).hide();
                    crm.toast('success', crmT('CRM_MSG_LEAD_ADDED_NUM') + ((data && data.lead_id) || '') +
                        ((data && data.assigned) ? crmT('CRM_MSG_LEAD_ASSIGNED') : ''));
                    loadStats();       /* كروت الأرقام فوق */
                    loadSalesLoad();   /* حمل الفريق لو ظاهر */
                }).always(function () {
                    btn.disabled = false;
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            loadStats();
            loadSalesLoad();
            var btn = document.getElementById('crmReloadLoad');
            if (btn) {
                btn.addEventListener('click', function () {
                    loadStats();
                    loadSalesLoad();
                });
            }
        });
    })();
    </script>

<?php endif; ?>

<?php
/* مكتبة الجافاسكربت المشتركة (window.CRM + crm.js) */
crm_render_js_boot();
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
