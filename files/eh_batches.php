<?php
require_once __DIR__ . '/../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) {
    die();
}
require_once __DIR__ . '/plugins/eh_crm/assets/includes/crm_func.php';
?>

<div class="row">
  <div class="col-md-12">&nbsp;</div>
</div>

<div class="card card-outline card-info">
    <div class="card-header"><h3 class="card-title mb-0"><?= lang('CRM_BATCH_TITLE') ?></h3></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle" id="batchesTable">
                <thead>
                    <tr>
                        <th class="crm-opt" style="width:6%">#</th>
                        <th><?= lang('CRM_BATCH_FILE') ?></th>
                        <th style="width:10%"><?= lang('CRM_BATCH_ROWS') ?></th>
                        <th class="crm-opt" style="width:10%"><?= lang('CRM_BATCH_IMPORTED') ?></th>
                        <th class="crm-opt" style="width:10%"><?= lang('CRM_BATCH_DUP_DB') ?></th>
                        <th class="crm-opt" style="width:10%"><?= lang('CRM_BATCH_DUP_FILE') ?></th>
                        <th class="crm-opt" style="width:10%"><?= lang('CRM_BATCH_INVALID') ?></th>
                        <th style="width:12%"><?= lang('CRM_COMMON_STATUS_ONLY') ?></th>
                        <th class="crm-opt" style="width:16%"><?= lang('CRM_BATCH_CREATED') ?></th>
                        <th style="width:14%"><?= lang('CRM_BATCH_ACTION') ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php crm_render_js_boot(); ?>
<script>
(function () {
    var $ = window.jQuery;

    function load() {
        crm.get('batches_list', {}, function (data) {
            if (!data) {
                return;
            }
            var tbody = $('#batchesTable tbody').empty();
            var badge = { pending: 'warning', committed: 'success', cancelled: 'secondary' };
            data.forEach(function (b) {
                var tr = '<tr>' +
                    '<td class="crm-opt">' + b.id + '</td>' +
                    '<td>' + crm.e(b.file_name) + '</td>' +
                    '<td>' + b.total_rows + '</td>' +
                    '<td class="crm-opt">' + b.inserted + '</td>' +
                    '<td class="crm-opt">' + b.dup_db + '</td>' +
                    '<td class="crm-opt">' + b.dup_file + '</td>' +
                    '<td class="crm-opt">' + b.invalid + '</td>' +
                    '<td><span class="badge bg-' + (badge[b.status] || 'secondary') + '">' + b.status + '</span></td>' +
                    '<td class="crm-opt">' + crm.e(b.created_at) + '</td>' +
                    '<td><button class="btn btn-sm btn-outline-primary view-staging" data-id="' + b.id + '"><?= lang('CRM_BATCH_VIEW_ROWS') ?></button></td>' +
                    '</tr>';
                tbody.append(tr);
            });
        });
    }

    load();

    $(document).on('click', '.view-staging', function () {
        var id = $(this).data('id');
        crm.get('staging_list', { batch_id: id }, function (data) {
            if (!data || !data.rows) {
                crm.toast('error', crmT('CRM_BATCH_CANNOT_LOAD'));
                return;
            }
            var rows = data.rows;
            var html = '<div class="card card-body mt-3"><h6><?= lang('CRM_BATCH_LABEL') ?>' + id + ' — ' + rows.length + '<?= lang('CRM_BATCH_ROWS_COUNT') ?></h6>' +
                '<div class="table-responsive">' +
                '<table class="table table-sm"><thead><tr><th>#</th><th><?= lang('CRM_COMMON_PHONE_ONLY') ?></th><th><?= lang('CRM_COMMON_NAME_ONLY') ?></th><th><?= lang('CRM_COMMON_STATUS_ONLY') ?></th><th><?= lang('CRM_BATCH_NOTE') ?></th></tr></thead><tbody>';
            rows.forEach(function (r) {
                html += '<tr>' +
                    '<td>' + r.row_no + '</td>' +
                    '<td>' + crm.e(r.phone_raw) + '</td>' +
                    '<td>' + crm.e(r.name) + '</td>' +
                    '<td>' + r.row_status + '</td>' +
                    '<td>' + crm.e(r.row_msg) + '</td>' +
                    '</tr>';
            });
            html += '</tbody></table></div>' +
                '<button class="btn btn-secondary mt-2" onclick="this.closest(\'.card\').remove()"><?= lang('CRM_COMMON_CLOSE') ?></button>' +
                '</div>';
            $('#batchesTable').closest('.card').after(html);
        });
    });
})();
</script>
<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php';
?>
