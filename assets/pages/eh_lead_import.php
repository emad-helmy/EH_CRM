<?php
require_once __DIR__ . '/../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) {
    die();
}
require_once $abs_us_root . $us_url_root . 'usersc/plugins/eh_crm/lib/crm_func.php';
require_once $abs_us_root . $us_url_root . 'usersc/plugins/eh_crm/lib/crm_csv.php';

$columns = crm_csv_template_columns();
/* آخر دفعة لم تُسجَّل بعد (لو موجودة الصفحة تفتح على المراجعة مباشرة بدل ما تبدأ من الأول) */
$pendingId = crm_csv_latest_pending(crm_user_id());
?>

<div class="row">
  <div class="col-md-12">&nbsp;</div>
</div>

<div class="row">
    <div class="col-12 col-lg-8">
        <div class="card card-outline card-primary">
            <div class="card-header"><h3 class="card-title mb-0"><?= lang('CRM_IMPORT_TITLE') ?></h3></div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <a class="btn btn-outline-secondary w-100" href="<?= $us_url_root ?>usersc/plugins/eh_crm/assets/leads_template.csv" download>
                            <i class="fas fa-download me-2"></i><?= lang('CRM_IMPORT_TEMPLATE') ?>
                        </a>
                        <div class="form-text mt-2"><?= lang('CRM_IMPORT_COLUMNS') ?><?= crm_e(implode(', ', array_values($columns))) ?></div>
                    </div>
                    <div class="col-md-6">
                        <input type="file" id="importFile" class="form-control" accept=".csv">
                    </div>
                </div>
                <button class="btn btn-primary w-100 mb-3" id="btnUpload"><?= lang('CRM_IMPORT_UPLOAD_REVIEW') ?></button>
                <div id="importResult"></div>

                <div class="mt-4" id="reviewSection"<?= $pendingId ? '' : ' style="display:none"' ?>>
                    <?php if ($pendingId) { ?>
                        <div class="alert alert-warning py-2">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <?= lang('CRM_IMPORT_PENDING_BATCH', [(int) $pendingId]) ?>
                        </div>
                    <?php } ?>
                    <h6><?= lang('CRM_IMPORT_REVIEW_TITLE') ?></h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle" id="reviewTable">
                            <thead>
                                <tr>
                                    <th class="crm-opt" style="width:5%">#</th>
                                    <th style="width:18%"><?= lang('CRM_COMMON_PHONE') ?></th>
                                    <th><?= lang('CRM_COMMON_NAME') ?></th>
                                    <th class="crm-opt"><?= lang('CRM_COMMON_CITY') ?></th>
                                    <th class="crm-opt"><?= lang('CRM_COMMON_PROJECT') ?></th>
                                    <th class="crm-opt"><?= lang('CRM_COMMON_SOURCE') ?></th>
                                    <th class="crm-opt"><?= lang('CRM_COMMON_MESSAGE') ?></th>
                                    <th style="width:14%"><?= lang('CRM_LEADS_STATUS') ?></th>
                                    <th style="width:16%"><?= lang('CRM_COMMON_ACTION') ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button class="btn btn-outline-danger" id="btnCancel"><?= lang('CRM_IMPORT_CANCEL_BATCH') ?></button>
                        <button class="btn btn-success" id="btnConfirm"><?= lang('CRM_IMPORT_CONFIRM_VALID') ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card card-outline card-info">
            <div class="card-header"><h6 class="card-title mb-0"><?= lang('CRM_COMMON_NOTES') ?></h6></div>
            <div class="card-body">
                <ul class="list-unstyled small">
                    <li><?= lang('CRM_IMPORT_NOTE_PH') ?></li>
                    <li><?= lang('CRM_IMPORT_NOTE_DUP') ?></li>
                    <li><?= lang('CRM_IMPORT_NOTE_AUTO') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php crm_render_js_boot(); ?>
<script>
(function () {
    var $ = window.jQuery;
    var batchId = <?= json_encode($pendingId) ?>; // آخر دفعة معلّقة (لو موجودة)

    $('#importFile').on('change', function () {
        var file = this.files[0];
        if (!file) { return; }
        var fd = new FormData();
        fd.append('action', 'import_upload');
        fd.append('csrf', window.CRM.token);
        fd.append('file', file);
        $('#btnUpload').prop('disabled', true).text(crmT('CRM_IMPORT_UPLOADING'));
        $.ajax({
            url: window.CRM.urls.in,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (res) {
            if (!res.success) {
                crm.toast('error', res.msg || crmT('CRM_IMPORT_UPLOAD_FAIL'));
                $('#btnUpload').prop('disabled', false).text(crmT('CRM_IMPORT_UPLOAD_REVIEW'));
                return;
            }
            batchId = res.data.batch_id;
            var c = res.data.counts;
            $('#importResult').html(
                '<div class="alert alert-info mb-3">' +
                '<?= lang('CRM_IMPORT_TOTAL') ?><strong>' + c.total + '</strong> | ' +
                '<?= lang('CRM_IMPORT_OK') ?><strong class="text-success">' + c.ok + '</strong> | ' +
                '<?= lang('CRM_IMPORT_INVALID') ?><strong class="text-danger">' + c.invalid + '</strong> | ' +
                '<?= lang('CRM_IMPORT_DUP_FILE') ?><strong class="text-warning">' + c.dup_file + '</strong> | ' +
                '<?= lang('CRM_IMPORT_DUP_DB') ?><strong class="text-warning">' + c.dup_db + '</strong>' +
                '</div>'
            );
            loadReview();
            $('#reviewSection').show();
            $('#btnUpload').prop('disabled', false).text(crmT('CRM_IMPORT_ANOTHER_FILE'));
        }).fail(function () {
            crm.toast('error', crmT('CRM_COMMON_CONN_FAIL'));
            $('#btnUpload').prop('disabled', false).text(crmT('CRM_IMPORT_UPLOAD_REVIEW'));
        });
    });

    /* كاش صفوف المراجعة (id => صف كامل) — الموديل بيقرأ من هنا مش من الجدول */
    var rowsById = {};

    function loadReview() {
        crm.get('staging_list', { batch_id: batchId }, function (data) {
            if (!data || !data.rows) { crm.toast('error', crmT('CRM_IMPORT_CANNOT_LOAD')); return; }
            var rows = data.rows;
            rowsById = {};
            var tbody = $('#reviewTable tbody').empty();
            var badgeClass = { ok: 'success', invalid: 'danger', dup_db: 'warning', dup_file: 'warning', excluded: 'secondary', imported: 'info' };
            rows.forEach(function (r) {
                rowsById[r.id] = r;
                var cls = badgeClass[r.row_status] || 'secondary';
                var btn = '';
                if (r.row_status === 'ok') {
                    btn = '<button class="btn btn-sm btn-outline-primary edit-row" data-id="' + r.id + '"><?= lang('CRM_COMMON_EDIT') ?></button>';
                } else if (r.row_status === 'excluded') {
                    btn = '<button class="btn btn-sm btn-outline-success restore-row" data-id="' + r.id + '"><?= lang('CRM_IMPORT_RESTORE') ?></button>';
                } else {
                    btn = '<button class="btn btn-sm btn-outline-primary edit-row" data-id="' + r.id + '"><?= lang('CRM_COMMON_EDIT') ?></button> ' +
                        '<button class="btn btn-sm btn-outline-danger exclude-row" data-id="' + r.id + '"><?= lang('CRM_IMPORT_EXCLUDE') ?></button>';
                }
                var tr = '<tr>' +
                    '<td class="crm-opt">' + r.row_no + '</td>' +
                    '<td>' + crm.e(r.phone_raw) + ' <small class="d-block text-muted">' + crm.e(r.phone_norm) + '</small></td>' +
                    '<td>' + crm.e(r.name) + '</td>' +
                    '<td class="crm-opt">' + crm.e(r.city) + '</td>' +
                    '<td class="crm-opt">' + crm.e(r.project) + '</td>' +
                    '<td class="crm-opt">' + crm.e(r.source) + '</td>' +
                    '<td class="crm-opt">' + crm.e(r.message) + '</td>' +
                    '<td><span class="badge bg-' + cls + '">' + r.row_status + '</span>' +
                    ' <small class="d-block text-muted">' + crm.e(r.row_msg) + '</small></td>' +
                    '<td>' + btn + '</td>' +
                    '</tr>';
                tbody.append(tr);
            });
        });
    }

    /* محرّر الصف — موديل في نص الشاشة فيه كل خانات الصف، بيستخدمه crmT('CRM_COMMON_EDIT') وcrmT('CRM_IMPORT_RESTORE') */
    function openRowEditor(id, title) {
        var r = rowsById[id] || {};
        crm.prompt({
            title: title || crmT('CRM_IMPORT_EDIT_ROW'),
            confirmLabel: crmT('CRM_COMMON_SAVE'),
            fields: [
                { name: 'phone_raw', label: crmT('CRM_COMMON_PHONE'), value: r.phone_raw || '', required: true },
                { name: 'name', label: crmT('CRM_COMMON_NAME'), value: r.name || '' },
                { name: 'city', label: crmT('CRM_COMMON_CITY'), value: r.city || '' },
                { name: 'project', label: crmT('CRM_COMMON_PROJECT'), value: r.project || '' },
                { name: 'source', label: crmT('CRM_COMMON_SOURCE'), value: r.source || '' },
                { name: 'message', label: crmT('CRM_COMMON_MESSAGE'), value: r.message || '' },
                { name: 'notes', label: crmT('CRM_COMMON_NOTES'), value: r.notes || '' }
            ]
        }, function (vals) {
            vals.action = 'staging_edit';
            vals.row_id = id;
            vals.csrf = window.CRM.token;
            $.post(window.CRM.urls.in, vals, function (res) {
                if (res.success) { crm.toast('success', crmT('CRM_COMMON_SAVED')); loadReview(); }
                else { crm.toast('error', res.msg); }
            }, 'json').fail(function () {
                crm.toast('error', crmT('CRM_COMMON_CONN_FAIL'));
            });
        });
    }

    /* لو فيه دفعة معلّقة، نحمّل مراجعتها على طول */
    if (batchId) { loadReview(); }

    $(document).on('click', '.edit-row', function () {
        openRowEditor($(this).data('id'), crmT('CRM_IMPORT_EDIT_ROW'));
    });

    /* عرض رسالة الخطأ القادمة من السيرفر (400/403...) بدل الصمت */
    function showPostErr(xhr) {
        var m = (xhr && xhr.responseJSON && xhr.responseJSON.msg) || crmT('CRM_COMMON_CONN_FAIL');
        crm.toast('error', m);
    }

    $(document).on('click', '.exclude-row', function () {
        var id = $(this).data('id');
        crm.confirm(crmT('CRM_IMPORT_CONFIRM_EXCLUDE'), function () {
            $.post(window.CRM.urls.in, { action: 'staging_exclude', row_id: id, csrf: window.CRM.token }, function (res) {
                if (res.success) { crm.toast('success', crmT('CRM_IMPORT_EXCLUDED')); loadReview(); }
                else { crm.toast('error', res.msg); }
            }, 'json').fail(showPostErr);
        }, crmT('CRM_IMPORT_EXCLUDE_ROW'), crmT('CRM_IMPORT_EXCLUDE'));
    });

    $(document).on('click', '.restore-row', function () {
        openRowEditor($(this).data('id'), crmT('CRM_IMPORT_RESTORE_ROW'));
    });

    $('#btnCancel').on('click', function () {
        if (!batchId) { crm.toast('warning', crmT('CRM_IMPORT_NO_PENDING')); return; }
        crm.confirm(crmT('CRM_IMPORT_CONFIRM_CANCEL_ALL'), function () {
            $.post(window.CRM.urls.in, { action: 'import_cancel', batch_id: batchId, csrf: window.CRM.token }, function (res) {
                if (res.success) { crm.toast('success', crmT('CRM_MSG_BATCH_CANCELLED')); location.reload(); }
                else { crm.toast('error', res.msg); }
            }, 'json').fail(showPostErr);
        }, crmT('CRM_IMPORT_CANCEL_BATCH'), crmT('CRM_IMPORT_CANCEL_BATCH'));
    });

    $('#btnConfirm').on('click', function () {
        if (!batchId) { crm.toast('warning', crmT('CRM_IMPORT_UPLOAD_FIRST')); return; }
        crm.confirm(crmT('CRM_IMPORT_CONFIRM_REGISTER'), function () {
            $.post(window.CRM.urls.in, { action: 'import_confirm', batch_id: batchId, csrf: window.CRM.token }, function (res) {
                if (res.success) {
                    var extra = res.data.assigned ? lang('CRM_IMPORT_DISTRIBUTED', [res.data.assigned]) : '';
                    crm.toast('success', res.msg + extra);
                    location.reload();
                } else {
                    crm.toast('error', res.msg);
                }
            }, 'json').fail(showPostErr);
        }, crmT('CRM_IMPORT_CONFIRM_REGISTER_TITLE'), crmT('CRM_IMPORT_CONFIRM_REGISTER_BTN'));
    });
})();
</script>
<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php';
?>
