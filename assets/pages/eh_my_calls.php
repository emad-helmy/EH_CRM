<?php
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
        <div class="card card-outline card-info">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0"><?= lang('CRM_NAV_MY_CALLS') ?></h3>
                <span class="badge bg-dark" id="callsCount">…</span>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover align-middle mb-0" id="callsTable">
                    <thead>
                        <tr>
                            <th class="crm-opt" style="width:8%">#</th>
                            <th class="crm-opt" style="width:14%"><?= lang('CRM_CALL_DATE') ?></th>
                            <th style="width:14%"><?= lang('CRM_COMMON_PHONE') ?></th>
                            <th style="width:16%"><?= lang('CRM_COMMON_NAME') ?></th>
                            <th style="width:14%"><?= lang('CRM_COMMON_RESULT') ?></th>
                            <th class="crm-opt"><?= lang('CRM_COMMON_NOTES_HDR') ?></th>
                            <th style="width:14%"><?= lang('CRM_LEADS_TH_NEXT') ?></th>
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

    crm.get('my_calls_list', {}, function (rows) {
        rows = rows || [];
        $('#callsCount').text(crm.num(rows.length) + crmT('CRM_CALL_COUNT'));
        var tb = $('#callsTable tbody').empty();
        if (!rows.length) {
            tb.append('<tr><td colspan="7" class="text-center text-muted py-4"><?= lang('CRM_MY_NO_CALLS') ?></td></tr>');
            return;
        }
        rows.forEach(function (c, i) {
            tb.append('<tr>' +
                '<td class="crm-opt">' + crm.num(rows.length - i) + '</td>' +
                '<td class="crm-opt">' + crm.e(crm.dt(c.call_at)) + '</td>' +
                '<td>' + crm.e(c.phone) + '</td>' +
                '<td>' + crm.e(c.name) + '</td>' +
                '<td><span class="badge bg-' + crm.e(c.result_color || 'secondary') + '\">' +
                    crm.e(c.result_name) + '</span></td>' +
                '<td class="crm-opt">' + (crm.e(c.notes) || '<span class="text-muted">—</span>') + '</td>' +
                '<td>' + (c.next_followup_at
                    ? '<span class="badge bg-warning text-dark">' + crm.e(crm.dt(c.next_followup_at)) + '</span>'
                    : '<span class="text-muted">—</span>') + '</td>' +
                '</tr>');
        });
    });
})();
</script>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
