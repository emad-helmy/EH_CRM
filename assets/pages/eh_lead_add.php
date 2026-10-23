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
    <div class="col-12 col-md-6">
        <div class="card card-outline card-success">
            <div class="card-header"><h3 class="card-title mb-0"><?= lang('CRM_ADD_TITLE') ?></h3></div>
            <div class="card-body">
                <form id="frmLead">
                    <div class="mb-3">
                        <label class="form-label"><?= lang('CRM_COMMON_PHONE') ?><span class="text-danger">*</span></label>
                        <input type="text" name="phone" id="leadPhone" class="form-control" placeholder="01012345678" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= lang('CRM_COMMON_NAME') ?></label>
                        <input type="text" name="name" id="leadName" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= lang('CRM_COMMON_CITY_ONLY') ?></label>
                        <input type="text" name="city" id="leadCity" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= lang('CRM_LEADS_PROJECT') ?></label>
                        <input type="text" name="project" id="leadProject" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= lang('CRM_LEADS_SOURCE') ?></label>
                        <select name="source_id" id="leadSource" class="form-select">
                            <option value=""><?= lang('CRM_ADD_PICK_SOURCE') ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= lang('CRM_COMMON_MESSAGE') ?></label>
                        <input type="text" name="message" id="leadMessage" class="form-control" placeholder="<?= lang('CRM_COMMON_MESSAGE_PH') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= lang('CRM_COMMON_NOTES') ?></label>
                        <textarea name="notes" id="leadNotes" class="form-control" rows="2"></textarea>
                    </div>
                    <button class="btn btn-success w-100" id="btnSave"><?= lang('CRM_ADD_SAVE') ?></button>
                </form>
                <div class="mt-3" id="leadResult"></div>
                <a class="btn btn-outline-secondary mt-2" href="<?= $us_url_root ?>usersc/eh_index.php"><?= lang('CRM_ADD_BACK_HOME') ?></a>
            </div>
        </div>
    </div>
</div>

<?php crm_render_js_boot(); ?>
<script>
(function () {
    var $ = window.jQuery;

    /* مصادر للقائمة المنسدلة */
    crm.get('sources_list', {}, function (data) {
        if (!data) { return; }
        var sel = $('#leadSource');
        data.forEach(function (s) {
            sel.append('<option value="' + s.id + '">' + crm.e(s.name) + '</option>');
        });
    });

    $('#btnSave').on('click', function (e) {
        e.preventDefault();
        var btn = $(this).prop('disabled', true).text(crmT('CRM_SAVING'));
        crm.post('lead_create', {
            phone: $('#leadPhone').val(),
            name: $('#leadName').val(),
            city: $('#leadCity').val(),
            project: $('#leadProject').val(),
            source_id: $('#leadSource').val(),
            message: $('#leadMessage').val(),
            notes: $('#leadNotes').val()
        }, function (data) {
            $('#frmLead')[0].reset();
            $('#leadResult').html('<div class="alert alert-success"><?= lang('CRM_MSG_LEAD_ADDED_NUM') ?>' + (data && data.lead_id) + (data && data.assigned ? crmT('CRM_MSG_LEAD_ASSIGNED') : '') + '</div>');
        }).always(function () {
            btn.prop('disabled', false).text(crmT('CRM_ADD_SAVE'));
        });
    });
})();
</script>
<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php';
?>
