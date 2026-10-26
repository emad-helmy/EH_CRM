<?php
/*
=============================================================================
 usersc/no_access.php — الصفحة اللي securePage() بترجّع عليها لما اليوزر
                        مايكونش عنده صلاحية على الصفحة اللي طلبها.

 مسجّلة في pages كصفحة **عامة** (private = 0) عن قصد، عشان لو رجّعنا عليها
 تاني ميحصلش redirect loop.
=============================================================================
*/

require_once __DIR__ . '/../users/init.php';

/* زائر مش مسجّل → صفحة اللوجين */
if (!$user->isLoggedIn()) {
    Redirect::to($us_url_root . 'users/login.php');
    exit;
}

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage(Server::get('PHP_SELF'))) {
    die();
}
?>

<div class="row">
  <div class="col-md-12">&nbsp;</div>
</div>


<div class="row justify-content-center">
    <div class="col-12 col-lg-7">
        <div class="card card-outline card-danger">
            <div class="card-header">
                <h3 class="card-title mb-0"><?= lang('CRM_NO_ACCESS_TITLE') ?></h3>
            </div>
            <div class="card-body">
                <p class="mb-3">
                    <?= lang('CRM_NO_ACCESS_TEXT') ?>
                </p>
                <p class="mb-4">
                    <?= lang('CRM_NO_ACCESS_HINT') ?>
                    <strong>Users → Permissions</strong>.
                </p>
                <a class="btn btn-outline-secondary" href="<?= $us_url_root ?>users/logout.php">
                    <?= lang('CRM_COMMON_LOGOUT') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
