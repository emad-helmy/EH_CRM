<?php
/*
 =============================================================================
 usersc/eh_settings.php — إعدادات CRM (للمدير)
 -----------------------------------------------------------------------------
  تبويبات: نتائج المكالمات | المصادر | المشاريع | الفريق | الإعدادات
  القوائم بتت embed JSON من PHP، والحفظ عن طريق data_in_.php (SweetAlert2).
 */
require_once __DIR__ . '/../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) {
    die();
}
require_once __DIR__ . '/plugins/eh_crm/assets/includes/crm_func.php';
require_once __DIR__ . '/plugins/eh_crm/assets/includes/crm_admin.php';

$crmResults  = crm_admin_results();
$crmSources  = crm_admin_sources();
$crmProjects = crm_admin_projects();
$crmTeam     = crm_admin_team();
$crmSt       = crm_admin_settings_get();
$crmColors   = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'dark'];

/* قيم الإعدادات للنموذج */
$setAuto      = ($crmSt['auto_assign_on_import'] === '1');
$setHours     = max(1, (int) ($crmSt['default_followup_hours'] ?: 24));
$setRecycleD  = (int) $crmSt['recycle_days'];
$setRecycleS  = $crmSt['recycle_statuses'];
$setCompany   = $crmSt['crm_company_name'];

$crmAdminJson = json_encode([
    'results'  => $crmResults,
    'sources'  => $crmSources,
    'projects' => $crmProjects,
    'team'     => $crmTeam,
    'colors'   => $crmColors,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<div class="row">
  <div class="col-md-12">&nbsp;</div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-sliders"></i><?= lang('CRM_NAV_SETTINGS') ?></h3>
            </div>
            <div class="card-body">

                <!-- ============================ التبويبات ============================ -->
                <ul class="nav nav-tabs" id="crmSettingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-results" type="button">
                            <i class="fas fa-phone-volume"></i><?= lang('CRM_SET_TAB_RESULTS') ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-sources" type="button">
                            <i class="fas fa-bullhorn"></i> <?= lang('CRM_SET_SOURCES') ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-projects" type="button">
                            <i class="fas fa-building"></i> <?= lang('CRM_SET_PROJECTS') ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-team" type="button">
                            <i class="fas fa-users"></i><?= lang('CRM_SET_TAB_TEAM') ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-settings" type="button">
                            <i class="fas fa-cog"></i><?= lang('CRM_SET_TAB_SETTINGS') ?>
                        </button>
                    </li>
                </ul>

                <div class="tab-content pt-3">

                    <!-- ======================== نتائج المكالمات ======================== -->
                    <div class="tab-pane fade show active" id="tab-results" role="tabpanel">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <div class="text-muted small">
                                <?= lang('CRM_SET_RESULTS_HINT') ?>
                            </div>
                            <button class="btn btn-sm btn-primary" id="btnAddResult">
                                <i class="fas fa-plus"></i><?= lang('CRM_SET_ADD_RESULT') ?>
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0" id="tResults">
                                <thead>
                                    <tr>
                                        <th class="crm-opt" style="width:5%">#</th>
                                        <th class="crm-opt" style="width:15%"><?= lang('CRM_SET_KEY') ?></th>
                                        <th style="width:18%"><?= lang('CRM_COMMON_NAME_ONLY') ?></th>
                                        <th class="crm-opt"><?= lang('CRM_SET_FLAGS') ?></th>
                                        <th style="width:10%"><?= lang('CRM_LEADS_STATUS') ?></th>
                                        <th style="width:10%"><?= lang('CRM_LEADS_TH_ACTIONS') ?></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ======================== المصادر ======================== -->
                    <div class="tab-pane fade" id="tab-sources" role="tabpanel">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <div class="text-muted small"><?= lang('CRM_SET_SOURCES_HINT') ?></div>
                            <button class="btn btn-sm btn-primary" id="btnAddSource">
                                <i class="fas fa-plus"></i><?= lang('CRM_SET_ADD_SOURCE') ?>
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0" id="tSources">
                                <thead>
                                    <tr>
                                        <th class="crm-opt" style="width:5%">#</th>
                                        <th><?= lang('CRM_COMMON_NAME_ONLY') ?></th>
                                        <th class="crm-opt" style="width:15%"><?= lang('CRM_SET_COLOR') ?></th>
                                        <th style="width:12%"><?= lang('CRM_LEADS_STATUS') ?></th>
                                        <th style="width:12%"><?= lang('CRM_LEADS_TH_ACTIONS') ?></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ======================== المشاريع ======================== -->
                    <div class="tab-pane fade" id="tab-projects" role="tabpanel">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <div class="text-muted small"><?= lang('CRM_SET_PROJECTS_HINT') ?></div>
                            <button class="btn btn-sm btn-primary" id="btnAddProject">
                                <i class="fas fa-plus"></i><?= lang('CRM_SET_ADD_PROJECT') ?>
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0" id="tProjects">
                                <thead>
                                    <tr>
                                        <th class="crm-opt" style="width:5%">#</th>
                                        <th><?= lang('CRM_COMMON_NAME_ONLY') ?></th>
                                        <th class="crm-opt"><?= lang('CRM_SET_DEVELOPER') ?></th>
                                        <th class="crm-opt"><?= lang('CRM_COMMON_CITY') ?></th>
                                        <th style="width:12%"><?= lang('CRM_LEADS_STATUS') ?></th>
                                        <th style="width:12%"><?= lang('CRM_LEADS_TH_ACTIONS') ?></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ======================== الفريق ======================== -->
                    <div class="tab-pane fade" id="tab-team" role="tabpanel">
                        <div class="alert alert-info py-2 mb-3">
                            <?= lang('CRM_SET_TEAM_HINT') ?>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0" id="tTeam">
                                <thead>
                                    <tr>
                                        <th><?= lang('CRM_LEADS_EMPLOYEE') ?></th>
                                        <th style="width:12%"><?= lang('CRM_LEADS_STATUS') ?></th>
                                        <th style="width:12%"><?= lang('CRM_SET_ASSIGNED_LEADS') ?></th>
                                        <th class="crm-opt" style="width:12%"><?= lang('CRM_REP_MONTHLY_TARGET') ?></th>
                                        <th class="crm-opt" style="width:12%"><?= lang('CRM_COMMON_NOTES') ?></th>
                                        <th style="width:10%"><?= lang('CRM_LEADS_TH_ACTIONS') ?></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ======================== الإعدادات ======================== -->
                    <div class="tab-pane fade" id="tab-settings" role="tabpanel">
                        <div class="alert alert-info py-2">
                            <?= lang('CRM_SET_DIST_STYLE') ?><strong><?= lang('CRM_SET_DIST_STYLE_VALUE') ?></strong><?= lang('CRM_SET_DIST_FIXED') ?>
                        </div>
                        <form id="settingsForm" class="row g-3" onsubmit="return false">
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="setAuto"<?= $setAuto ? ' checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="setAuto">
                                        <?= lang('CRM_SET_AUTO_DIST') ?>
                                    </label>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small mb-1" for="setHours"><?= lang('CRM_SET_FOLLOWUP_HOURS') ?></label>
                                <input type="number" min="1" id="setHours" class="form-control form-control-sm" value="<?= (int) $setHours ?>">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small mb-1" for="setRecycleDays"><?= lang('CRM_SET_RECYCLE_DAYS') ?></label>
                                <input type="number" min="0" id="setRecycleDays" class="form-control form-control-sm" value="<?= (int) $setRecycleD ?>">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small mb-1" for="setRecycleStatuses"><?= lang('CRM_SET_RECYCLE_STATUSES') ?></label>
                                <input type="text" id="setRecycleStatuses" class="form-control form-control-sm"
                                       value="<?= htmlspecialchars($setRecycleS, ENT_QUOTES) ?>" placeholder="no_answer,busy,number_off">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small mb-1" for="setCompany"><?= lang('CRM_SET_COMPANY') ?></label>
                                <input type="text" id="setCompany" class="form-control form-control-sm"
                                       value="<?= htmlspecialchars($setCompany, ENT_QUOTES) ?>">
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary btn-sm" id="btnSaveSettings" type="button">
                                    <i class="fas fa-save"></i><?= lang('CRM_SET_SAVE_SETTINGS') ?>
                                </button>
                            </div>
                        </form>
                        <div class="alert alert-info py-2 mt-3 mb-0">
                            <i class="fas fa-recycle"></i>
                            <?= lang('CRM_SET_RECYCLE_HINT') ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>window.CRM_ADMIN = <?= $crmAdminJson ?>;</script>
<?php crm_render_js_boot(); ?>
<script>
(function () {
    var $ = window.jQuery;
    var D = window.CRM_ADMIN || { results: [], sources: [], projects: [], team: [], colors: [] };
    var COLORS = (D.colors || []).map(function (c) { return { value: c, label: c }; });

    function stBadge(active) {
        return active == 1
            ? '<span class="badge bg-success"><?= lang('CRM_SET_ENABLED') ?></span>'
            : '<span class="badge bg-secondary"><?= lang('CRM_SET_DISABLED') ?></span>';
    }

    /* ------------------------------ نتائج المكالمات ------------------------------ */
    function renderResults() {
        var tb = $('#tResults tbody').empty();
        if (!D.results.length) {
            tb.append('<tr><td colspan="6" class="text-center text-muted py-3"><?= lang('CRM_SET_NO_RESULTS') ?></td></tr>');
            return;
        }
        D.results.forEach(function (r, i) {
            var flags = '';
            if (r.is_contact == 1) { flags += '<span class="badge bg-info"><?= lang('CRM_SET_FLAG_CONTACT') ?></span> '; }
            if (r.is_interest == 1) { flags += '<span class="badge bg-primary"><?= lang('CRM_SET_FLAG_INTEREST') ?></span> '; }
            if (r.is_final == 1) { flags += '<span class="badge bg-dark"><?= lang('CRM_SET_FLAG_FINAL') ?></span> '; }
            if (r.requires_followup == 1) { flags += '<span class="badge bg-warning text-dark"><?= lang('CRM_SET_FLAG_FOLLOWUP') ?></span> '; }
            tb.append('<tr>' +
                '<td class="crm-opt">' + crm.num(i + 1) + '</td>' +
                '<td class="crm-opt"><code>' + crm.e(r.key_name) + '</code></td>' +
                '<td><span class="badge bg-' + crm.e(r.color || 'secondary') + '">' + crm.e(r.name_ar) + '</span></td>' +
                '<td class="crm-opt">' + (flags || '<span class="text-muted">—</span>') + '</td>' +
                '<td>' + stBadge(r.active) + '</td>' +
                '<td><button class="btn btn-xs btn-outline-primary edit-result" data-idx="' + i + '"><i class="fas fa-pen"></i><?= lang('CRM_COMMON_EDIT') ?></button></td>' +
                '</tr>');
        });
    }

    function resultModal(idx) {
        var r = idx >= 0 ? D.results[idx] : null;
        crm.prompt({
            title: r ? crmT('CRM_SET_EDIT_RESULT') + r.name_ar : crmT('CRM_SET_NEW_RESULT'),
            confirmLabel: crmT('CRM_COMMON_SAVE'),
            fields: [
                { name: 'name_ar', label: crmT('CRM_SET_RESULT_NAME_AR'), value: r ? r.name_ar : '', required: true },
                {
                    name: 'key_name', label: crmT('CRM_SET_KEY_EN'), value: r ? r.key_name : '',
                    required: !r, placeholder: r ? crmT('CRM_SET_KEY_KEEP') : crmT('CRM_SET_KEY_EXAMPLE')
                },
                { name: 'color', label: crmT('CRM_SET_COLOR'), type: 'select', value: (r && r.color) ? r.color : 'primary', options: COLORS },
                { name: 'is_contact', label: crmT('CRM_SET_CHK_CONTACT'), type: 'checkbox', value: r ? r.is_contact == 1 : false },
                { name: 'is_interest', label: crmT('CRM_SET_CHK_INTEREST'), type: 'checkbox', value: r ? r.is_interest == 1 : false },
                {
                    name: 'is_final', label: crmT('CRM_SET_CHK_FINAL'), type: 'checkbox',
                    value: r ? r.is_final == 1 : false, placeholder: crmT('CRM_SET_FINAL_NO_DATE')
                },
                { name: 'requires_followup', label: crmT('CRM_SET_CHK_FOLLOWUP'), type: 'checkbox', value: r ? r.requires_followup == 1 : false },
                { name: 'active', label: crmT('CRM_SET_CHK_ACTIVE'), type: 'checkbox', value: r ? r.active == 1 : true }
            ]
        }, function (v) {
            crm.post('result_save', {
                id: r ? r.id : 0,
                name_ar: v.name_ar, key_name: v.key_name, color: v.color,
                is_contact: v.is_contact, is_interest: v.is_interest,
                is_final: v.is_final, requires_followup: v.requires_followup,
                active: v.active
            }, function () { reloadKeepTab(); });
        });
    }

    /* ------------------------------ المصادر ------------------------------ */
    function renderSources() {
        var tb = $('#tSources tbody').empty();
        if (!D.sources.length) {
            tb.append('<tr><td colspan="5" class="text-center text-muted py-3"><?= lang('CRM_SET_NO_SOURCES') ?></td></tr>');
            return;
        }
        D.sources.forEach(function (s, i) {
            tb.append('<tr>' +
                '<td class="crm-opt">' + crm.num(i + 1) + '</td>' +
                '<td>' + crm.e(s.name) + '</td>' +
                '<td class="crm-opt"><span class="badge bg-' + crm.e(s.color || 'secondary') + '">' + crm.e(s.color || '—') + '</span></td>' +
                '<td>' + stBadge(s.active) + '</td>' +
                '<td><button class="btn btn-xs btn-outline-primary edit-source" data-idx="' + i + '"><i class="fas fa-pen"></i><?= lang('CRM_COMMON_EDIT') ?></button></td>' +
                '</tr>');
        });
    }

    function sourceModal(idx) {
        var s = idx >= 0 ? D.sources[idx] : null;
        crm.prompt({
            title: s ? crmT('CRM_SET_EDIT_SOURCE') + s.name : crmT('CRM_SET_NEW_SOURCE'),
            confirmLabel: crmT('CRM_COMMON_SAVE'),
            fields: [
                { name: 'name', label: crmT('CRM_SET_SOURCE_NAME'), value: s ? s.name : '', required: true, placeholder: crmT('CRM_SET_SOURCE_EXAMPLE') },
                { name: 'color', label: crmT('CRM_SET_COLOR'), type: 'select', value: (s && s.color) ? s.color : 'primary', options: COLORS },
                { name: 'active', label: crmT('CRM_SET_CHK_SHOW'), type: 'checkbox', value: s ? s.active == 1 : true }
            ]
        }, function (v) {
            crm.post('source_save', {
                id: s ? s.id : 0, name: v.name, color: v.color, active: v.active
            }, function () { reloadKeepTab(); });
        });
    }

    /* ------------------------------ المشاريع ------------------------------ */
    function renderProjects() {
        var tb = $('#tProjects tbody').empty();
        if (!D.projects.length) {
            tb.append('<tr><td colspan="6" class="text-center text-muted py-3"><?= lang('CRM_SET_NO_PROJECTS') ?></td></tr>');
            return;
        }
        D.projects.forEach(function (p, i) {
            tb.append('<tr>' +
                '<td class="crm-opt">' + crm.num(i + 1) + '</td>' +
                '<td>' + crm.e(p.name) + '</td>' +
                '<td class="crm-opt">' + (crm.e(p.developer) || '<span class="text-muted">—</span>') + '</td>' +
                '<td class="crm-opt">' + (crm.e(p.city) || '<span class="text-muted">—</span>') + '</td>' +
                '<td>' + stBadge(p.active) + '</td>' +
                '<td><button class="btn btn-xs btn-outline-primary edit-project" data-idx="' + i + '"><i class="fas fa-pen"></i><?= lang('CRM_COMMON_EDIT') ?></button></td>' +
                '</tr>');
        });
    }

    function projectModal(idx) {
        var p = idx >= 0 ? D.projects[idx] : null;
        crm.prompt({
            title: p ? crmT('CRM_SET_EDIT_PROJECT') + p.name : crmT('CRM_SET_NEW_PROJECT'),
            confirmLabel: crmT('CRM_COMMON_SAVE'),
            fields: [
                { name: 'name', label: crmT('CRM_SET_PROJECT_NAME'), value: p ? p.name : '', required: true },
                { name: 'developer', label: crmT('CRM_SET_DEVELOPER'), value: p ? p.developer : '', placeholder: crmT('CRM_SET_OPTIONAL') },
                { name: 'city', label: crmT('CRM_COMMON_CITY'), value: p ? p.city : '', placeholder: crmT('CRM_SET_OPTIONAL') },
                { name: 'active', label: crmT('CRM_SET_CHK_SHOW'), type: 'checkbox', value: p ? p.active == 1 : true }
            ]
        }, function (v) {
            crm.post('project_save', {
                id: p ? p.id : 0, name: v.name, developer: v.developer, city: v.city, active: v.active
            }, function () { reloadKeepTab(); });
        });
    }

    /* ------------------------------ الفريق ------------------------------ */
    function renderTeam() {
        var tb = $('#tTeam tbody').empty();
        if (!D.team.length) {
            tb.append('<tr><td colspan="6" class="text-center text-muted py-3"><?= lang('CRM_SET_NO_TEAM') ?></td></tr>');
            return;
        }
        D.team.forEach(function (u, i) {
            tb.append('<tr>' +
                '<td>' + crm.e(u.name) + (u.email ? ' <span class="text-muted small">(' + crm.e(u.email) + ')</span>' : '') + '</td>' +
                '<td>' + (u.available == 1
                    ? '<span class="badge bg-success"><?= lang('CRM_AVAIL_AVAILABLE') ?></span>'
                    : '<span class="badge bg-danger"><?= lang('CRM_AVAIL_LEAVE') ?></span>') + '</td>' +
                '<td>' + crm.num(u.open_leads) + '</td>' +
                '<td class="crm-opt">' + (u.target_monthly ? crm.num(u.target_monthly) : '<span class="text-muted">—</span>') + '</td>' +
                '<td class="crm-opt">' + (crm.e(u.notes) || '<span class="text-muted">—</span>') + '</td>' +
                '<td><button class="btn btn-xs btn-outline-primary edit-profile" data-idx="' + i + '"><i class="fas fa-pen"></i><?= lang('CRM_COMMON_EDIT') ?></button></td>' +
                '</tr>');
        });
    }

    function profileModal(idx) {
        var u = idx >= 0 ? D.team[idx] : null;
        if (!u) { return; }
        crm.prompt({
            title: crmT('CRM_SET_PROFILE') + u.name,
            confirmLabel: crmT('CRM_COMMON_SAVE'),
            fields: [
                { name: 'available', label: crmT('CRM_SET_CHK_AVAILABLE'), type: 'checkbox', value: u.available == 1 },
                {
                    name: 'target_monthly', label: crmT('CRM_SET_TARGET_MONTHLY'), value: u.target_monthly || '',
                    placeholder: crmT('CRM_SET_NO_TARGET')
                },
                {
                    name: 'max_leads', label: crmT('CRM_SET_MAX_LEADS'), value: u.max_leads || '',
                    placeholder: crmT('CRM_SET_NO_CAP')
                },
                { name: 'notes', label: crmT('CRM_COMMON_NOTES'), value: u.notes, placeholder: crmT('CRM_SET_OPTIONAL') }
            ]
        }, function (v) {
            crm.post('sales_profile_save', {
                user_id: u.user_id,
                available: v.available,
                max_leads: v.max_leads || 0,
                target_monthly: v.target_monthly || 0,
                notes: v.notes
            }, function () { reloadKeepTab(); });
        });
    }

    /* ------------------------------ الإعدادات ------------------------------ */
    $('#btnSaveSettings').on('click', function () {
        crm.post('settings_save', {
            auto_assign_on_import: $('#setAuto').is(':checked') ? 1 : 0,
            default_followup_hours: $('#setHours').val() || 0,
            recycle_days: $('#setRecycleDays').val() || 0,
            recycle_statuses: $('#setRecycleStatuses').val() || '',
            crm_company_name: $('#setCompany').val() || ''
        });
    });

    /* ------------------------------ الأحداث ------------------------------ */
    $('#btnAddResult').on('click', function () { resultModal(-1); });
    $('#btnAddSource').on('click', function () { sourceModal(-1); });
    $('#btnAddProject').on('click', function () { projectModal(-1); });

    $('#tResults tbody').on('click', '.edit-result', function () { resultModal(+$(this).data('idx')); });
    $('#tSources tbody').on('click', '.edit-source', function () { sourceModal(+$(this).data('idx')); });
    $('#tProjects tbody').on('click', '.edit-project', function () { projectModal(+$(this).data('idx')); });
    $('#tTeam tbody').on('click', '.edit-profile', function () { profileModal(+$(this).data('idx')); });

    /* إعادة تحميل الصفحة من نفس التبويب */
    function reloadKeepTab() {
        var active = document.querySelector('#crmSettingsTabs .nav-link.active');
        var t = active ? active.getAttribute('data-bs-target') : '';
        if (t && history.replaceState) { history.replaceState(null, '', t); }
        location.reload();
    }

    /* مزامنة الـhash مع التبويب المفتوح (أي تبديل → الرابط يتغيّر) */
    $('#crmSettingsTabs').on('shown.bs.tab', 'button[data-bs-target]', function () {
        var t = this.getAttribute('data-bs-target');
        if (t && history.replaceState) { history.replaceState(null, '', t); }
    });

    /* ------------------------------ البداية ------------------------------ */
    renderResults();
    renderSources();
    renderProjects();
    renderTeam();

    /* رجوع لتبويب محفوظ — على DOMContentLoaded عشان Bootstrap
       (اللي في الـfooter) يكون اتنفّذ قبل التبديل */
    function restoreTab() {
        if (!location.hash) { return; }
        var b = document.querySelector('#crmSettingsTabs [data-bs-target="' + location.hash + '"]');
        if (b && window.bootstrap && window.bootstrap.Tab) {
            new window.bootstrap.Tab(b).show();
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', restoreTab);
    } else {
        restoreTab();
    }
})();
</script>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
