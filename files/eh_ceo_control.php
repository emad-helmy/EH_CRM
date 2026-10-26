<?php
/*
 =============================================================================
  usersc/eh_ceo_control.php — لوحة تحكم المشرف
 -----------------------------------------------------------------------------
  - إنشاء مستخدم جديد (البرمشنات checkboxes — كلها ما عدا 1 و 2)
  - تعديل مستخدم قديم (الاسم/الإيميل/كلمة السر/البرمشنات/الصورة)
  - بلوك / فك بلوك مستخدم
  - البرمشن 1 (User) بيتسجل أوتوماتيك مع أي مستخدم جديد
  - كل الصلاحيات فوق 2 متاحة (3، 5، 6، 20... إلخ)
  - برمشن 2 (Administrator) مش بيظهر في القوائم

  الصلاحيات: مفيش أي منطق تاني — البوابة الوحيدة هي securePage() بتاع
  UserSpice (pages + permission_page_matches). مين يدخل الصفحة ومين لأ
  بيتحسب من UserSpice نفسه، ومن لوحة التحكم → Permissions.
 =============================================================================
 */
require_once __DIR__ . '/../users/init.php';

if (!securePage(Server::get('PHP_SELF'))) {
    die();
}

$db = DB::getInstance();

/* =============================================================================
 * دوال الصفحة (كلها خاصة بالصفحة — مفيش منطق صلاحيات هنا)
 * ============================================================================= */

/** رد JSON بنفس شكل الـCRM: {"success":bool,"msg":"...","data":{...}} */
function ceo_json($success, $msg = '', $data = null, $code = 200)
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(
        ['success' => (bool)$success, 'msg' => (string)$msg, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

/** هل الطلب AJAX؟ */
function ceo_is_ajax()
{
    if (isset($_GET['action']) && $_GET['action'] === 'ceo_user_info') {
        return true;
    }
    $x = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return strtolower((string)$x) === 'xmlhttprequest';
}

/** اسم البرمشن: العربي من descrip والإنجليزي من name حسب لغة الموقع */
function ceo_perm_label($perm)
{
    $langCode = strtolower((string)($_SESSION['us_lang'] ?? 'en-US'));
    $isRtl    = in_array(substr($langCode, 0, 2), ['ar', 'he', 'fa', 'ur', 'sd', 'ps', 'dv', 'yi'], true);

    $name = trim((string)($perm->name ?? ''));
    $desc = trim((string)($perm->descrip ?? ''));

    $label = $isRtl ? ($desc !== '' ? $desc : $name) : ($name !== '' ? $name : $desc);

    return $label !== '' ? $label : lang('CEO_PERM_NAME');
}

/** اسم المستخدم للعرض */
function ceo_display_name($row)
{
    $name = trim(($row->fname ?? '') . ' ' . ($row->lname ?? ''));
    return $name !== '' ? $name : ($row->username ?? '');
}

/** برمشنات اليوزر (اللي أكبر من 1) */
function ceo_user_perms($db, $userId)
{
    $rows = $db->query(
        "SELECT p.`id`, p.`name`, p.`descrip`
           FROM user_permission_matches upm
           JOIN permissions p ON p.`id` = upm.`permission_id`
          WHERE upm.`user_id` = ? AND upm.`permission_id` > 1
          ORDER BY p.`id` ASC",
        [$userId]
    )->results();

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'      => (int)$r->id,
            'name'    => trim((string)$r->name),
            'descrip' => trim((string)$r->descrip),
        ];
    }
    return $out;
}

/** رابط صورة اليوزر — من profile_pic (بلجن الصورة) ولو مش موجودة Gravatar */
function ceo_user_pic_url($row)
{
    global $abs_us_root, $us_url_root;

    if (!empty($row->profile_pic)) {
        $name = basename($row->profile_pic);
        $file = $abs_us_root . $us_url_root . 'usersc/plugins/profile_pic/files/' . $name;
        if (file_exists($file)) {
            return $us_url_root . 'usersc/plugins/profile_pic/files/' . $name;
        }
    }
    return fetchProfilePicture($row->id);
}

/** رفع صورة اليوزر — usersc/plugins/profile_pic/files/ + users.profile_pic */
function ceo_upload_pic($userId, $currentPic, &$errors)
{
    global $abs_us_root, $us_url_root;

    if (empty($_FILES['profile_pic']) || empty($_FILES['profile_pic']['tmp_name'])) {
        return null;
    }

    $tempFile = $_FILES['profile_pic']['tmp_name'];
    if (!is_uploaded_file($tempFile)) {
        $errors[] = 'ملف الصورة غير صالح';
        return null;
    }

    $mimeMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
    ];

    $fileType = mime_content_type($tempFile);
    if (!array_key_exists($fileType, $mimeMap)) {
        $errors[] = 'الصورة يجب أن تكون jpg أو png أو gif';
        return null;
    }

    if ($_FILES['profile_pic']['size'] > 5 * 1024 * 1024) {
        $errors[] = 'حجم الصورة أكبر من 5MB';
        return null;
    }

    $targetDir = $abs_us_root . $us_url_root . 'usersc/plugins/profile_pic/files/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $ext        = $mimeMap[$fileType];
    $uniqueName = (int)$userId . '-' . date('Y-m-d') . '-' . uniqid() . '.' . $ext;

    if (!move_uploaded_file($tempFile, $targetDir . $uniqueName)) {
        $errors[] = 'فشل رفع الصورة';
        return null;
    }

    /* مسح الصورة القديمة */
    if (!empty($currentPic)) {
        $oldFile = $targetDir . basename($currentPic);
        if (file_exists($oldFile)) {
            unlink($oldFile);
        }
    }

    return $uniqueName;
}

/* =============================================================================
 * فرع الـAJAX — نفس الصفحة هي الـendpoint (بعد securePage، قبل أي HTML)
 * ============================================================================= */
if (ceo_is_ajax()) {

    $action = isset($_POST['action']) ? (string)$_POST['action'] : (string)($_GET['action'] ?? '');
    $meId   = (int)$user->data()->id;

    /* ------------------------------ إضافة ------------------------------ */
    if ($action === 'ceo_add_user') {
        if (!Token::check(Input::get('csrf'))) {
            ceo_json(false, 'انتهت صلاحية الجلسة، حدّث الصفحة وحاول تاني', null, 419);
        }

        $errors   = [];
        /* ناخد القيم خام (زي ما UserSpice كور بيعمل) — Input::get بيعمل htmlspecialchars
           وده كان هيبوّظ كلمة السر لو فيها & أو < */
        $username = trim((string)($_POST['username'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['confirm'] ?? '');
        $fname    = ucfirst(trim((string)($_POST['fname'] ?? '')));
        $lname    = ucfirst(trim((string)($_POST['lname'] ?? '')));

        /* البرمشنات المختارة — لازم تكون > 2 (1 بيتسجل أوتوماتيك و2 ممنوع) */
        $permIds = (array)($_POST['permission_ids'] ?? []);
        $permIds = array_map('intval', $permIds);
        $permIds = array_values(array_unique(array_filter($permIds, function ($id) {
            return $id > 2;
        })));

        if ($username === '') {
            $errors[] = 'اسم المستخدم مطلوب';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'البريد الإلكتروني غير صالح';
        }
        if ($fname === '' || $lname === '') {
            $errors[] = 'الاسم الأول والاسم الأخير مطلوبان';
        }
        if (strlen($password) < $settings->min_pw || strlen($password) > $settings->max_pw) {
            $errors[] = "كلمة السر يجب أن تكون بين {$settings->min_pw} و {$settings->max_pw} حرف";
        }
        if ($password !== $confirm) {
            $errors[] = 'تأكيد كلمة السر غير مطابق';
        }
        if (empty($permIds)) {
            $errors[] = 'اختر ادارة واحدة على الأقل';
        }
        $exists = $db->query(
            "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1",
            [$username, $email]
        )->count();
        if ($exists > 0) {
            $errors[] = 'اسم المستخدم أو البريد الإلكتروني مستخدم من قبل';
        }

        if (!empty($errors)) {
            ceo_json(false, implode(' • ', $errors), null, 400);
        }

        $now            = date('Y-m-d H:i:s');
        $vericode       = uniqid() . randomString(15);
        $vericodeExpiry = date('Y-m-d H:i:s', strtotime("+{$settings->join_vericode_expiry} hours", strtotime($now)));

        $db->insert('users', [
            'password'           => password_hash($password, PASSWORD_BCRYPT, ['cost' => 13]),
            'username'           => $username,
            'email'              => $email,
            'fname'              => $fname,
            'lname'              => $lname,
            'permissions'        => 1,   /* العمود القديم — 1 = User (مش محظور) */
            'join_date'          => date('Y-m-d'),
            'email_verified'     => 1,
            'vericode'           => hashVericode($vericode),
            'force_pr'           => 0,
            'vericode_expiry'    => $vericodeExpiry,
            'oauth_tos_accepted' => true,
            'language'           => 'en-US',
            'active'             => 1,
            'created'            => $now,
            'modified'           => $now,
        ]);
        $newUserId = (int)$db->lastId();

        /* البرمشن 1 (User) أوتوماتيك + البرمشنات المختارة */
        addPermission(array_merge([1], $permIds), $newUserId);

        /* الصورة (اختياري) */
        $newPic = ceo_upload_pic($newUserId, null, $errors);
        if ($newPic) {
            $db->update('users', $newUserId, ['profile_pic' => $newPic]);
        }

        logger($meId, 'CEO Control', "Added user #$newUserId with permissions: " . implode(',', $permIds));
        ceo_json(true, 'تم إضافة المستخدم بنجاح', ['id' => $newUserId]);
    }

    /* ------------------------------ تعديل ------------------------------ */
    if ($action === 'ceo_update_user') {
        if (!Token::check(Input::get('csrf'))) {
            ceo_json(false, 'انتهت صلاحية الجلسة، حدّث الصفحة وحاول تاني', null, 419);
        }

        $errors = [];
        $editId = (int)Input::get('user_id');
        $target = $db->query("SELECT * FROM users WHERE id = ?", [$editId])->first();

        if (!$target) {
            ceo_json(false, 'المستخدم غير موجود', null, 404);
        }
        if ((int)$target->protected === 1) {
            ceo_json(false, 'لا يمكن تعديل هذا المستخدم', null, 403);
        }
        if ($editId === $meId) {
            ceo_json(false, 'لا يمكن تعديل حسابك من هنا', null, 403);
        }

        $fname   = ucfirst(trim((string)($_POST['fname'] ?? '')));
        $lname   = ucfirst(trim((string)($_POST['lname'] ?? '')));
        $email   = trim((string)($_POST['email'] ?? ''));
        $newPass = (string)($_POST['password'] ?? '');
        $newConf = (string)($_POST['confirm'] ?? '');

        $permIds = (array)($_POST['permission_ids'] ?? []);
        $permIds = array_map('intval', $permIds);
        $permIds = array_values(array_unique(array_filter($permIds, function ($id) {
            return $id > 2;
        })));

        if ($fname === '' || $lname === '') {
            $errors[] = 'الاسم الأول والاسم الأخير مطلوبان';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'البريد الإلكتروني غير صالح';
        }
        if ($newPass !== '') {
            if (strlen($newPass) < $settings->min_pw || strlen($newPass) > $settings->max_pw) {
                $errors[] = "كلمة السر يجب أن تكون بين {$settings->min_pw} و {$settings->max_pw} حرف";
            }
            if ($newPass !== $newConf) {
                $errors[] = 'تأكيد كلمة السر غير مطابق';
            }
        }
        if (empty($permIds)) {
            $errors[] = 'اختر ادارة واحدة على الأقل';
        }
        $emailExists = $db->query(
            "SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1",
            [$email, $editId]
        )->count();
        if ($emailExists > 0) {
            $errors[] = 'البريد الإلكتروني مستخدم من قبل';
        }

        if (!empty($errors)) {
            ceo_json(false, implode(' • ', $errors), null, 400);
        }

        $newPic = ceo_upload_pic($editId, $target->profile_pic, $errors);

        $fields = [
            'fname'    => $fname,
            'lname'    => $lname,
            'email'    => $email,
            'modified' => date('Y-m-d H:i:s'),
        ];
        if ($newPass !== '') {
            $fields['password'] = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 13]);
            $fields['force_pr'] = 0;
        }
        if ($newPic) {
            $fields['profile_pic'] = $newPic;
        }

        try {
            $user->update($fields, $editId);

            /* نحدّث البرمشنات: 1 أوتوماتيك + المختار */
            $db->query("DELETE FROM user_permission_matches WHERE user_id = ? AND permission_id > 1", [$editId]);
            addPermission(array_merge([1], $permIds), $editId);

            logger($meId, 'CEO Control', "Updated user #$editId.");
            ceo_json(true, 'تم حفظ التعديلات بنجاح', ['id' => $editId]);
        } catch (Throwable $e) {
            ceo_json(false, $e->getMessage(), null, 500);
        }
    }

    /* ------------------------------ بلوك ------------------------------ */
    if ($action === 'ceo_toggle_block') {
        if (!Token::check(Input::get('csrf'))) {
            ceo_json(false, 'انتهت صلاحية الجلسة، حدّث الصفحة وحاول تاني', null, 419);
        }

        $targetId = (int)Input::get('user_id');
        $target   = $db->query("SELECT id, permissions, protected FROM users WHERE id = ?", [$targetId])->first();

        if (!$target) {
            ceo_json(false, 'المستخدم غير موجود', null, 404);
        }
        if ($targetId === $meId) {
            ceo_json(false, 'لا يمكن حظر حسابك', null, 403);
        }
        if ((int)$target->protected === 1) {
            ceo_json(false, 'لا يمكن حظر هذا المستخدم', null, 403);
        }

        /* 0 = محظور (securePage بيفتح banned.php) — 1 = راجع تاني */
        $isBannedTarget = (int)$target->permissions === 0;
        $newState       = $isBannedTarget ? 1 : 0;

        $db->update('users', $targetId, [
            'permissions' => $newState,
            'active'      => $newState,
            'modified'    => date('Y-m-d H:i:s'),
        ]);

        logger($meId, 'CEO Control', ($newState ? 'Unblocked user #' : 'Blocked user #') . $targetId);
        ceo_json(true, $newState ? 'تم فك البلوك عن المستخدم' : 'تم حظر المستخدم', ['id' => $targetId, 'active' => $newState]);
    }

    /* ------------------------- بيانات يوزر (تعديل) ------------------------- */
    if ($action === 'ceo_user_info') {
        $uid = (int)Input::get('user_id');
        $row = $db->query(
            "SELECT id, username, email, fname, lname, profile_pic FROM users WHERE id = ?",
            [$uid]
        )->first();

        if (!$row) {
            ceo_json(false, 'المستخدم غير موجود', null, 404);
        }

        $permIds = [];
        foreach (ceo_user_perms($db, $uid) as $p) {
            $permIds[] = $p['id'];
        }

        ceo_json(true, '', [
            'id'       => (int)$row->id,
            'username' => (string)$row->username,
            'email'    => (string)$row->email,
            'fname'    => (string)$row->fname,
            'lname'    => (string)$row->lname,
            'perms'    => $permIds,
            'pic'      => ceo_user_pic_url($row),
        ]);
    }

    ceo_json(false, 'عملية غير معروفة', null, 400);
}

/* =============================================================================
 * الصفحة
 * ============================================================================= */
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

/* البرمشنات المتاحة — كلها ما عدا 1 (User) و 2 (Administrator) */
$permissionOptions = $db->query(
    "SELECT id, name, descrip FROM permissions WHERE id NOT IN (1, 2) ORDER BY id ASC"
)->results();

/* كل المستخدمين ما عدا اللي عندهم برمشن 2 (Administrator) */
$usersList = $db->query(
    "SELECT u.id, u.username, u.email, u.fname, u.lname, u.permissions, u.protected, u.profile_pic
       FROM users u
      WHERE NOT EXISTS (
            SELECT 1 FROM user_permission_matches upm
             WHERE upm.user_id = u.id AND upm.permission_id = 2
      )
      ORDER BY u.id DESC
      LIMIT 500"
)->results();

$csrf = Token::generate();
?>

<div class="row">
  <div class="col-md-12">&nbsp;</div>

  <div class="col-12">
    <div class="card card-outline card-primary">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h3 class="card-title mb-0">
          <i class="fas fa-user-shield"></i> <?= lang('CEO_PAGE_TITLE') ?>
          <span class="badge bg-warning"><?= count($usersList) ?></span>
        </h3>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
          <i class="fas fa-user-plus"></i> <?= lang('CEO_ADD_USER') ?>
        </button>
      </div>

      <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0" style="min-width: 900px;">
          <thead>
            <tr>
              <th><?= lang('CEO_USER_COL') ?></th>
              <th class="d-none d-lg-table-cell"><?= lang('CEO_EMAIL_COL') ?></th>
              <th class="d-none d-md-table-cell"><?= lang('CEO_PERMS_COL') ?></th>
              <th><?= lang('CEO_STATUS_COL') ?></th>
              <th><?= lang('CEO_ACTIONS_COL') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($usersList as $row) {
              $rowPerms   = ceo_user_perms($db, $row->id);
              $rowBanned  = (int)$row->permissions === 0;
              $rowProtect = (int)$row->protected === 1;
              $isMe       = (int)$row->id === (int)$user->data()->id;
            ?>
              <tr>
                <td>
                  <img src="<?= htmlspecialchars(ceo_user_pic_url($row)) ?>" class="ceo-user-img" alt="user">
                  <div><strong><?= htmlspecialchars(ceo_display_name($row)) ?></strong></div>
                  <small class="text-muted"><?= htmlspecialchars($row->username) ?></small>
                </td>
                <td class="d-none d-lg-table-cell"><?= htmlspecialchars($row->email) ?></td>
                <td class="d-none d-md-table-cell">
                  <?php if (empty($rowPerms)) { ?>
                    <span class="text-muted"><?= lang('CEO_NO_PERM') ?></span>
                  <?php } else {
                    foreach ($rowPerms as $p) {
                      $short = $p['name'] !== '' ? $p['name'] : ceo_perm_label($p);
                      $full  = $p['descrip'] !== '' ? $p['descrip'] : $short;
                      ?>
                      <span class="badge rounded-pill bg-primary fs-6 fw-normal" title="<?= htmlspecialchars($full) ?>"><?= htmlspecialchars($short) ?></span>
                    <?php }
                  } ?>
                </td>
                <td>
                  <?php if ($rowBanned) { ?>
                    <span class="badge bg-danger"><?= lang('CEO_BLOCKED') ?></span>
                  <?php } else { ?>
                    <span class="badge bg-success"><?= lang('CEO_ACTIVE') ?></span>
                  <?php } ?>
                </td>
                <td class="ceo-actions">
                  <?php if ($rowProtect || $isMe) { ?>
                    <span class="btn btn-sm btn-outline-primary disabled"><?= lang('CEO_EDIT') ?></span>
                  <?php } else { ?>
                    <button type="button" class="btn btn-sm btn-outline-primary ceo-edit-btn" data-user-id="<?= (int)$row->id ?>">
                      <?= lang('CEO_EDIT') ?>
                    </button>
                  <?php } ?>

                  <?php if (!$rowProtect && !$isMe) { ?>
                    <button type="button"
                            class="btn btn-sm <?= $rowBanned ? 'btn-outline-success' : 'btn-outline-danger' ?> ceo-block-btn"
                            data-user-id="<?= (int)$row->id ?>"
                            data-banned="<?= $rowBanned ? '1' : '0' ?>">
                      <?= $rowBanned ? lang('CEO_UNBLOCK') : lang('CEO_BLOCK') ?>
                    </button>
                  <?php } ?>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ===================== إضافة مستخدم ===================== -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="ceoAddForm" method="post" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title"><?= lang('CEO_NEW_USER') ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label"><?= lang('GEN_UNAME') ?></label>
              <input type="text" name="username" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= lang('GEN_EMAIL') ?></label>
              <input type="email" name="email" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= lang('GEN_FNAME') ?></label>
              <input type="text" name="fname" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= lang('GEN_LNAME') ?></label>
              <input type="text" name="lname" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= lang('GEN_PASS') ?></label>
              <div class="input-group">
                <input type="password" id="add_password" name="password" class="form-control" required autocomplete="new-password">
                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#add_password">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= lang('CEO_PASS_CONFIRM') ?></label>
              <div class="input-group">
                <input type="password" id="add_confirm" name="confirm" class="form-control" required autocomplete="new-password">
                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#add_confirm">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- البرمشنات (checkboxes) -->
          <div class="mt-3">
            <label class="form-label fw-bold"><?= lang('CEO_PERM_NAME') ?></label>
            <div class="row g-2">
              <?php foreach ($permissionOptions as $perm) { ?>
                <div class="col-md-6 col-lg-6">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="permission_ids[]"
                           value="<?= (int)$perm->id ?>" id="add_perm_<?= (int)$perm->id ?>">
                    <label class="form-check-label" for="add_perm_<?= (int)$perm->id ?>">
                      <?= htmlspecialchars(ceo_perm_label($perm)) ?>
                    </label>
                  </div>
                </div>
              <?php } ?>
            </div>
          </div>

          <div class="mt-3">
            <label class="form-label"><?= lang('CEO_PROFILE_PIC') ?></label>
            <input type="file" name="profile_pic" class="form-control" accept="image/jpeg,image/png,image/gif">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= lang('CEO_CANCEL') ?></button>
          <button type="submit" class="btn btn-primary"><?= lang('CEO_ADD_USER') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== تعديل مستخدم ===================== -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="ceoEditForm" method="post" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="ceoEditTitle"><?= lang('CEO_EDIT_USER') ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="user_id" id="ceoEditUserId" value="">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label"><?= lang('GEN_UNAME') ?></label>
              <input type="text" id="ceoEditUsername" class="form-control" disabled>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= lang('GEN_EMAIL') ?></label>
              <input type="email" name="email" id="ceoEditEmail" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= lang('GEN_FNAME') ?></label>
              <input type="text" name="fname" id="ceoEditFname" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= lang('GEN_LNAME') ?></label>
              <input type="text" name="lname" id="ceoEditLname" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= lang('GEN_PASS') ?> (<?= lang('CEO_OPTIONAL') ?>)</label>
              <div class="input-group">
                <input type="password" id="edit_password" name="password" class="form-control" autocomplete="new-password">
                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#edit_password">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= lang('CEO_PASS_CONFIRM') ?> (<?= lang('CEO_OPTIONAL') ?>)</label>
              <div class="input-group">
                <input type="password" id="edit_confirm" name="confirm" class="form-control" autocomplete="new-password">
                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#edit_confirm">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
          </div>

          <div class="mt-3">
            <label class="form-label fw-bold"><?= lang('CEO_PERM_NAME') ?></label>
            <div class="row g-2">
              <?php foreach ($permissionOptions as $perm) { ?>
                <div class="col-md-6 col-lg-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="permission_ids[]"
                           value="<?= (int)$perm->id ?>" id="edit_perm_<?= (int)$perm->id ?>">
                    <label class="form-check-label" for="edit_perm_<?= (int)$perm->id ?>">
                      <?= htmlspecialchars(ceo_perm_label($perm)) ?>
                    </label>
                  </div>
                </div>
              <?php } ?>
            </div>
          </div>

          <div class="mt-3">
            <label class="form-label"><?= lang('CEO_PROFILE_PIC') ?> (<?= lang('CEO_OPTIONAL') ?>)</label>
            <div class="d-flex align-items-center gap-3">
              <img src="" id="ceoEditPic" class="ceo-user-img" alt="pic">
              <input type="file" name="profile_pic" class="form-control" accept="image/jpeg,image/png,image/gif">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= lang('CEO_CANCEL') ?></button>
          <button type="submit" class="btn btn-warning"><?= lang('CEO_SAVE') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- SweetAlert2 (من بلجن الـCRM) -->
<link rel="stylesheet" href="<?= $us_url_root ?>usersc/plugins/eh_crm/assets/plugins/sweetalert2/sweetalert2.min.css" nonce="<?= htmlspecialchars($userspice_nonce ?? '') ?>">
<script nonce="<?= htmlspecialchars($userspice_nonce ?? '') ?>" src="<?= $us_url_root ?>usersc/plugins/eh_crm/assets/plugins/sweetalert2/sweetalert2.min.js"></script>

<script nonce="<?= htmlspecialchars($userspice_nonce ?? '') ?>">
  var ceoDir = (String(document.documentElement.lang || 'en').toLowerCase().split(/[-_]/)[0] === 'ar') ? 'rtl' : 'ltr';
  var ceoUrl = '<?= $us_url_root ?>usersc/eh_ceo_control.php';

  function ceoSwal(icon, title, text) {
    Swal.fire({
      icon: icon,
      title: title,
      text: text || '',
      direction: ceoDir,
      confirmButtonText: '<?= htmlspecialchars(lang('CEO_OK')) ?>'
    });
  }

  function ceoCloseModal(id) {
    var el = document.getElementById(id);
    if (!el) { return; }
    if (window.bootstrap && window.bootstrap.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(el).hide();
    }
  }

  /* إرسال operation للصفحة نفسها (البوابة: securePage بتاع UserSpice) */
  function ceoSend(formData, modalId, after) {
    var form = formData.__form || null;
    var btn = form ? form.querySelector('button[type=submit]') : null;
    if (btn) { btn.disabled = true; }

    return fetch(ceoUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (btn) { btn.disabled = false; }
        if (modalId) { ceoCloseModal(modalId); }
        if (res && res.success) {
          after(res);
        } else {
          ceoSwal('error', '<?= htmlspecialchars(lang('CEO_ERROR')) ?>', (res && res.msg) ? res.msg : '<?= htmlspecialchars(lang('CEO_UNEXPECTED')) ?>');
        }
      })
      .catch(function () {
        if (btn) { btn.disabled = false; }
        if (modalId) { ceoCloseModal(modalId); }
        ceoSwal('error', '<?= htmlspecialchars(lang('CEO_ERROR')) ?>', '<?= htmlspecialchars(lang('CEO_CONN_FAIL')) ?>');
      });
  }

  /* ------------------------------ إضافة ------------------------------ */
  document.getElementById('ceoAddForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = this;
    var fd = new FormData(form);
    fd.append('action', 'ceo_add_user');
    fd.__form = form;
    ceoSend(fd, 'addUserModal', function (res) {
      form.reset();
      Swal.fire({
        icon: 'success',
        title: res.msg || '<?= htmlspecialchars(lang('CEO_DONE')) ?>',
        direction: ceoDir,
        timer: 1200,
        showConfirmButton: false
      }).then(function () { window.location.reload(); });
    });
  });

  /* ------------------------------ تعديل ------------------------------ */
  document.getElementById('ceoEditForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = this;
    var fd = new FormData(form);
    fd.append('action', 'ceo_update_user');
    fd.__form = form;
    ceoSend(fd, 'editUserModal', function (res) {
      form.reset();
      Swal.fire({
        icon: 'success',
        title: res.msg || '<?= htmlspecialchars(lang('CEO_DONE')) ?>',
        direction: ceoDir,
        timer: 1200,
        showConfirmButton: false
      }).then(function () { window.location.reload(); });
    });
  });

  /* ------------------------- فتح موديل التعديل ------------------------ */
  document.addEventListener('click', function (e) {
    var editBtn = e.target.closest('.ceo-edit-btn');
    if (editBtn) {
      var uid = editBtn.getAttribute('data-user-id');
      var url = ceoUrl + '?action=ceo_user_info&user_id=' + encodeURIComponent(uid);
      fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res || !res.success) {
            ceoSwal('error', '<?= htmlspecialchars(lang('CEO_ERROR')) ?>', (res && res.msg) ? res.msg : '<?= htmlspecialchars(lang('CEO_UNEXPECTED')) ?>');
            return;
          }
          var d = res.data || {};
          document.getElementById('ceoEditUserId').value = d.id || '';
          document.getElementById('ceoEditUsername').value = d.username || '';
          document.getElementById('ceoEditEmail').value = d.email || '';
          document.getElementById('ceoEditFname').value = d.fname || '';
          document.getElementById('ceoEditLname').value = d.lname || '';
          document.getElementById('ceoEditTitle').textContent = '<?= htmlspecialchars(lang('CEO_EDIT_USER')) ?> — ' + ((d.fname || '') + ' ' + (d.lname || '')).trim();
          document.getElementById('ceoEditPic').src = d.pic || '';

          /* البرمشنات: نعلم اللي اليوزر عنده */
          var mine = d.perms || [];
          document.querySelectorAll('#editUserModal input[name="permission_ids[]"]').forEach(function (cb) {
            cb.checked = mine.indexOf(parseInt(cb.value, 10)) !== -1;
          });

          /* كلمة السر فاضية دايمًا */
          document.getElementById('edit_password').value = '';
          document.getElementById('edit_confirm').value = '';

          var el = document.getElementById('editUserModal');
          if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(el).show();
          }
        })
        .catch(function () {
          ceoSwal('error', '<?= htmlspecialchars(lang('CEO_ERROR')) ?>', '<?= htmlspecialchars(lang('CEO_CONN_FAIL')) ?>');
        });
      return;
    }

    /* ------------------------------ بلوك ------------------------------ */
    var blockBtn = e.target.closest('.ceo-block-btn');
    if (blockBtn) {
      var bid = blockBtn.getAttribute('data-user-id');
      var isBanned = blockBtn.getAttribute('data-banned') === '1';
      var ask = isBanned ? '<?= htmlspecialchars(lang('CEO_CONFIRM_UNBLOCK')) ?>' : '<?= htmlspecialchars(lang('CEO_CONFIRM_BLOCK')) ?>';

      Swal.fire({
        icon: 'question',
        title: ask,
        direction: ceoDir,
        showCancelButton: true,
        confirmButtonText: '<?= htmlspecialchars(lang('CEO_OK')) ?>',
        cancelButtonText: '<?= htmlspecialchars(lang('CEO_CANCEL')) ?>'
      }).then(function (r) {
        if (!r.isConfirmed) { return; }
        var fd = new FormData();
        fd.append('action', 'ceo_toggle_block');
        fd.append('csrf', '<?= $csrf ?>');
        fd.append('user_id', bid);
        ceoSend(fd, null, function (res) {
          Swal.fire({
            icon: 'success',
            title: res.msg || '<?= htmlspecialchars(lang('CEO_DONE')) ?>',
            direction: ceoDir,
            timer: 1200,
            showConfirmButton: false
          }).then(function () { window.location.reload(); });
        });
      });
    }
  });

  /* ---------------------- إظهار/إخفاء كلمة السر ---------------------- */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.toggle-password');
    if (!btn) { return; }
    var input = document.querySelector(btn.getAttribute('data-target'));
    if (!input) { return; }
    var type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
    var icon = btn.querySelector('i');
    if (icon) { icon.toggleClass('bi-eye'); icon.toggleClass('bi-eye-slash'); }
  });
</script>

<style nonce="<?= htmlspecialchars($userspice_nonce ?? '') ?>">
  .ceo-user-img {
    width: 42px;
    height: 42px;
    object-fit: cover;
    border-radius: 50%;
  }
  .ceo-actions .btn {
    margin-bottom: 4px;
  }
  @media (max-width: 767px) {
    .ceo-user-img {
      width: 34px;
      height: 34px;
    }
    .table {
      font-size: 13px;
    }
  }

  /*
   * الموديل: بيسيب ألوان الثيم (دارك/وايت) بس بنخفّفها —
   * tertiary-bg بدل body-bg عشان مايبقاش سودا خالص (زي Bootstrap 4).
   */
  .modal-content {
    background-color: var(--bs-tertiary-bg, #f8f9fa);
    color: var(--bs-body-color, #212529);
    border: 1px solid var(--bs-border-color, #dee2e6);
  }
  .modal-content .modal-header,
  .modal-content .modal-footer {
    border-color: var(--bs-border-color, #dee2e6);
  }
  .modal-content .modal-title,
  .modal-content .form-label,
  .modal-content .form-check-label {
    color: var(--bs-body-color, #212529);
  }
  .modal-content .form-control,
  .modal-content .form-select {
    background-color: var(--bs-body-bg, #fff);
    color: var(--bs-body-color, #212529);
    border-color: var(--bs-border-color, #dee2e6);
  }
  .modal-content .form-control:focus,
  .modal-content .form-select:focus {
    background-color: var(--bs-body-bg, #fff);
    color: var(--bs-body-color, #212529);
    border-color: #86b7fe;
    box-shadow: 0 0 0 .25rem rgba(13, 110, 253, .25);
  }
  .modal-content .form-check-input {
    background-color: var(--bs-body-bg, #fff);
    border-color: var(--bs-border-color, #ced4da);
  }
  .modal-content .form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
  }
  .modal-content .input-group-text {
    background-color: var(--bs-secondary-bg, #e9ecef);
    color: var(--bs-body-color, #212529);
    border-color: var(--bs-border-color, #dee2e6);
  }
</style>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
