<?php
/**
 * configure.php — UserSpice plugin config entry point (admin.php?view=plugins_config&plugin=eh_crm)
 */
require_once __DIR__ . '/functions.php';

if (!in_array($user->data()->id, $master_account)) {
    Redirect::to($us_url_root . 'users/admin.php');
}

include __DIR__ . '/plugin_info.php';
pluginActive($plugin_name);

$db = DB::getInstance();

// Load CRM helpers (defines CRM_PERM_* constants)
require_once __DIR__ . '/assets/includes/crm_func.php';

$csrf = Token::generate();
$msg = '';

// Handle create permissions (from sql/permissions.sql — idempotent)
if (!empty($_POST['action']) && $_POST['action'] === 'create_perms') {
    $token = $_POST['csrf'] ?? '';
    if (!Token::check($token)) {
        $msg = 'CSRF invalid';
    } else {
        $sqlFile = __DIR__ . '/sql/permissions.sql';
        if (is_file($sqlFile) && ($sqlContent = file_get_contents($sqlFile)) !== false) {
            $created = 0;
            $skipped = 0;
            // كل tuple في الملف: ('name', 'descrip')
            preg_match_all("/\(\s*'([^']*)'\s*,\s*'([^']*)'\s*\)/u", $sqlContent, $m, PREG_SET_ORDER);
            foreach ($m as $row) {
                $name    = trim($row[1]);
                $descrip = trim($row[2]);
                if ($name === '') {
                    continue;
                }
                $exists = $db->query("SELECT id FROM permissions WHERE name = ?", [$name])->first();
                if ($exists) {
                    $skipped++;
                    continue;
                }
                $db->insert('permissions', ['name' => $name, 'descrip' => $descrip]);
                $created++;
            }
            $msg = "انشاء الصلاحيات: تم إنشاء {$created} وصليحة جديدة، و{$skipped} موجودة بالفعل.";
        } else {
            $msg = 'permissions.sql not found';
        }
    }
}

// Handle create menu (from sql/us_menu_items.sql — منيو مختار + برمشنز ديناميك)
if (!empty($_POST['action']) && $_POST['action'] === 'create_menu') {
    $token = $_POST['csrf'] ?? '';
    if (!Token::check($token)) {
        $msg = 'CSRF invalid';
    } else {
        $menuIds = array_values(array_filter(array_unique(array_map('intval', (array)($_POST['menu_ids'] ?? []))), function ($id) { return $id > 0; }));
        if (empty($menuIds)) {
            $msg = 'اختر منيو واحد على الأقل قبل انشاء القوائم.';
        } else {
            // اتأكد إن المنيو المختارة موجودة فعلًا
            $validIds = [];
            foreach ($db->query("SELECT id FROM us_menus")->results() as $r) {
                $validIds[] = (int)$r->id;
            }
            $menuIds = array_values(array_intersect($menuIds, $validIds));
            if (empty($menuIds)) {
                $msg = 'المنيو المختارة غير موجودة.';
            } else {
                $sqlFile = __DIR__ . '/sql/us_menu_items.sql';
                if (!is_file($sqlFile) || ($sqlContent = file_get_contents($sqlFile)) === false) {
                    $msg = 'us_menu_items.sql not found';
                } else {
                    // IDs الحقيقية للصلاحيات اللي اتعملت فوق
                    $crmNames = [CRM_PERM_ENTRY_NAME, CRM_PERM_SALES_NAME, CRM_PERM_MGR_NAME, CRM_PERM_SUPERVISOR_NAME];
                    $crmIds = [];
                    foreach ($crmNames as $cn) {
                        $r = $db->query("SELECT id FROM permissions WHERE name = ?", [$cn])->first();
                        if ($r) {
                            $crmIds[] = (int)$r->id;
                        }
                    }
                    if (empty($crmIds)) {
                        $msg = 'اعمل «انشاء الصلاحيات» الأول قبل انشاء القوائم.';
                    } else {
                        // خريطة id => name لكل الصلاحيات الموجودة (عشان نعرف مين CRM قديم ومين لأ)
                        $permNameById = [];
                        foreach ($db->query("SELECT id, name FROM permissions")->results() as $p) {
                            $permNameById[(int)$p->id] = $p->name;
                        }
                        // فكّ escaping بتاع SQL strings (\" -> " و \' -> ')
                        $sqlUnescape = function ($s) {
                            return str_replace(['\\"', '\\\'', '\\\\'], ['"', "'", '\\'], $s);
                        };
                        // صف واحد من الـdump — 13 عمود:
                        // (menu, type, label, link, icon_class, li_class, a_class, link_target, parent, display_order, disabled, permissions, tags)
                        $rowRx = "/\(\s*(\d+)\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*\)/u";
                        preg_match_all($rowRx, $sqlContent, $m, PREG_SET_ORDER);
                        // البرمشنز الديناميك — closure مشتركة للـdropdown والعناصر
                        $dynPerms = function ($rawPerms) use ($permNameById, $crmNames, $crmIds) {
                            $orig = json_decode($rawPerms, true);
                            if (!is_array($orig)) {
                                $orig = [];
                            }
                            $keep = [];
                            foreach ($orig as $opid) {
                                $opid = (int)$opid;
                                $nm   = $permNameById[$opid] ?? null;
                                if ($nm === null || !in_array($nm, $crmNames, true)) {
                                    $keep[] = $opid;
                                }
                            }
                            return json_encode(array_map('strval', array_values(array_unique(array_merge($keep, $crmIds)))));
                        };
                        $inserted = 0;
                        $skipped  = 0;
                        foreach ($menuIds as $mid) {
                            // 1) صف الـdropdown (type='dropdown') — يتعمل أو يتلاقى في المنيو المختار، وده الـparent
                            $parentId = 0;
                            foreach ($m as $row) {
                                if ($sqlUnescape($row[2]) !== 'dropdown') {
                                    continue;
                                }
                                $dLabel = $sqlUnescape($row[3]);
                                $dIcon  = $sqlUnescape($row[5]);
                                $dTarget = $sqlUnescape($row[8]);
                                $dOrder = (int)$row[10];
                                $dDisabled = (int)$row[11];
                                $dTags  = $sqlUnescape($row[13]);
                                $dPerms = $dynPerms($sqlUnescape($row[12]));
                                $exists = $db->query(
                                    "SELECT id FROM us_menu_items WHERE menu = ? AND type = 'dropdown' AND label = ?",
                                    [$mid, $dLabel]
                                )->first();
                                if ($exists) {
                                    $parentId = (int)$exists->id;
                                    $skipped++;
                                } else {
                                    $db->query(
                                        "INSERT INTO `us_menu_items` (`menu`,`type`,`label`,`link`,`icon_class`,`li_class`,`a_class`,`link_target`,`parent`,`display_order`,`disabled`,`permissions`,`tags`)
                                         VALUES (?, 'dropdown', ?, '', ?, '', '', ?, 0, ?, ?, ?, ?)",
                                        [$mid, $dLabel, $dIcon, $dTarget, $dOrder, $dDisabled, $dPerms, $dTags]
                                    );
                                    $parentId = (int)$db->lastId();
                                    $inserted++;
                                }
                                break; // أول dropdown في الملف كفاية
                            }
                            // 2) العناصر — parent بتاعها بيبقى الـdropdown اللي اتعمل فوق
                            foreach ($m as $row) {
                                $type = $sqlUnescape($row[2]);
                                if ($type === 'dropdown') {
                                    continue;
                                }
                                $label    = $sqlUnescape($row[3]);
                                $link     = $sqlUnescape($row[4]);
                                $icon     = $sqlUnescape($row[5]);
                                $liClass  = $sqlUnescape($row[6]);
                                $aClass   = $sqlUnescape($row[7]);
                                $target   = $sqlUnescape($row[8]);
                                $order    = (int)$row[10];
                                $disabled = (int)$row[11];
                                $tags     = $sqlUnescape($row[13]);
                                $permJson = $dynPerms($sqlUnescape($row[12]));
                                // idempotent: نفس المنيو + نفس الـparent + نفس الـlink معناها متكرر
                                $exists = $db->query(
                                    "SELECT id FROM us_menu_items WHERE menu = ? AND parent = ? AND link = ?",
                                    [$mid, $parentId, $link]
                                )->first();
                                if ($exists) {
                                    $skipped++;
                                    continue;
                                }
                                $db->query(
                                    "INSERT INTO `us_menu_items` (`menu`,`type`,`label`,`link`,`icon_class`,`li_class`,`a_class`,`link_target`,`parent`,`display_order`,`disabled`,`permissions`,`tags`)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                                    [$mid, $type, $label, $link, $icon, $liClass, $aClass, $target, $parentId, $order, $disabled, $permJson, $tags]
                                );
                                $inserted++;
                            }
                        }
                        $msg = "انشاء القوائم: تم إضافة {$inserted} عنصر، و{$skipped} كانت موجودة بالفعل (منيو: " . implode(', ', $menuIds) . ").";
                    }
                }
            }
        }
    }
}

$crmPermNames = [CRM_PERM_ENTRY_NAME, CRM_PERM_SALES_NAME, CRM_PERM_MGR_NAME, CRM_PERM_SUPERVISOR_NAME];
// الصلاحيات + عدد المستخدمين اللي واخدين كل واحدة
$ph        = implode(',', array_fill(0, count($crmPermNames), '?'));
$permRows  = $db->query(
    "SELECT p.id, p.name, p.descrip,
            (SELECT COUNT(*) FROM user_permission_matches m WHERE m.permission_id = p.id) AS users_count
       FROM permissions p
      WHERE p.name IN ({$ph})
      ORDER BY p.id",
    $crmPermNames
)->results();

// كل المنيو الموجودة عشان الـcheckboxes
$menus = $db->query("SELECT id, menu_name FROM us_menus ORDER BY id")->results();
?>
<div class="content mt-3">
  <h2><i class="fas fa-cog"></i> EH_CRM Configuration</h2>
  <a href="<?= $us_url_root ?>users/admin.php?view=plugins">&larr; Back to Plugins</a>
  <hr>
  <?php if ($msg !== '') { ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php } ?>

  <div class="card mb-3">
    <div class="card-header"><strong>الصلاحيات / Permissions</strong></div>
    <div class="card-body">
      <p class="text-muted small">
        إنشاء صلاحيات الـCRM من ملف <code>sql/permissions.sql</code>.
        الزرار آمن للتكرار — الصلاحية الموجودة أصلًا مش هتتكرر.
      </p>
      <form method="post" class="mb-3">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="create_perms">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-user-shield"></i>
          انشاء الصلاحيات (Create Permissions)
        </button>
      </form>
      <table class="table table-sm">
        <thead>
          <tr>
            <th>#</th>
            <th>الاسم / Name</th>
            <th>الوصف</th>
            <th>عدد المستخدمين</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($permRows as $p): ?>
            <tr>
              <td><?= (int)$p->id ?></td>
              <td><?= htmlspecialchars($p->name) ?></td>
              <td class="small text-muted"><?= htmlspecialchars($p->descrip) ?></td>
              <td><span class="badge bg-primary"><?= (int)$p->users_count ?></span></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($permRows)): ?>
            <tr>
              <td colspan="4" class="text-center text-muted">
                لا توجد صلاحيات بعد — اضغط زرار «انشاء الصلاحيات» لإنشائها
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header"><strong>القوائم / Menu</strong></div>
    <div class="card-body">
      <p class="text-muted small">
        إضافة قائمة CRM (dropdown) بعناصرها من <code>sql/us_menu_items.sql</code> للمنيو اللي بتختارها.
        البرمشنز بتتحط ديناميك من الصلاحيات اللي اتعملت فوق (بدل الأرقام الثابتة في الملف).
        الزرار آمن للتكرار — العنصر الموجود مش هيتضاف تاني.
      </p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="create_menu">
        <div class="mb-3">
          <label class="form-label d-block mb-1"><strong>اختر المنيو:</strong></label>
          <?php foreach ($menus as $mn): ?>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="menu_ids[]" value="<?= (int)$mn->id ?>" id="menu_<?= (int)$mn->id ?>">
              <label class="form-check-label" for="menu_<?= (int)$mn->id ?>">
                #<?= (int)$mn->id ?> — <?= htmlspecialchars($mn->menu_name) ?>
              </label>
            </div>
          <?php endforeach; ?>
          <?php if (empty($menus)): ?>
            <div class="text-muted small">لا توجد منيو معرّفة في us_menus.</div>
          <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-list"></i>
          انشاء القوائم (Create Menu)
        </button>
      </form>
    </div>
  </div>
</div>