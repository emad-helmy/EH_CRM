<?php
/*
 =============================================================================
 EH_CRM plugin — eh_configure.php  (إعدادات البلجن: برمشنز + قائمة)
 =============================================================================
 */
require_once __DIR__ . '/../users/init.php';

if (!securePage(Server::get('PHP_SELF'))) { die(); }

$db = DB::getInstance();
require_once $abs_us_root . $us_url_root . 'usersc/plugins/eh_crm/lib/crm_func.php';

/* فقط الأدمن أو المشرف */
if (!crm_is_admin() && !crm_is_supervisor()) {
    Redirect::to($us_url_root . 'usersc/eh_no_access.php');
    die();
}

/* ---------- AJAX endpoint (self-contained) ---------- */
$ehCrmCfgAjax = (isset($_POST['action']) && $_POST['action'] !== '');
if ($ehCrmCfgAjax) {
    header('Content-Type: application/json; charset=utf-8');
    $action = (string) $_POST['action'];
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (!Token::check($csrf)) {
        echo json_encode(['success' => false, 'msg' => 'CSRF invalid']); exit;
    }

    $crmNames = [CRM_PERM_ENTRY_NAME, CRM_PERM_SALES_NAME, CRM_PERM_MGR_NAME, CRM_PERM_SUPERVISOR_NAME];

    if ($action === 'create_perm') {
        $name = trim((string)($_POST['name'] ?? ''));
        if (!in_array($name, $crmNames, true)) {
            echo json_encode(['success' => false, 'msg' => 'اسم غير مسموح']); exit;
        }
        $exists = $db->query("SELECT id FROM permissions WHERE name = ?", [$name])->first();
        if ($exists) {
            echo json_encode(['success' => true, 'msg' => 'موجود مسبقًا', 'id' => (int)$exists->id]); exit;
        }
        $db->insert('permissions', ['name' => $name, 'descrip' => $name]);
        $newId = (int) $db->lastId();
        echo json_encode(['success' => true, 'msg' => 'تم إنشاء البرمشن', 'id' => $newId]); exit;
    }

    if ($action === 'create_menu') {
        // Create CRM dropdown + items in menu 1 (idempotent)
        $db->query("INSERT INTO `us_menu_items` (`menu`,`type`,`label`,`link`,`icon_class`,`link_target`,`parent`,`display_order`,`disabled`,`permissions`,`tags`)
            SELECT 1, 'dropdown', 'CRM', '', 'fas fa-address-book', '_self', 0, 2, 0, '[2,3,4,5]', NULL
            FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `us_menu_items` WHERE `menu`=1 AND `type`='dropdown' AND `label`='CRM')");
        $menuCrm = (int) $db->query("SELECT id FROM `us_menu_items` WHERE `menu`=1 AND `type`='dropdown' AND `label`='CRM' LIMIT 1")->first()->id;
        $items = [
            ['CRM_MENU_HOME', 'usersc/eh_index.php', 'fas fa-gauge-high', 1, '[2,3,4,5]'],
            ['CRM_NAV_IMPORT', 'usersc/eh_lead_import.php', 'fas fa-file-csv', 2, '[2,3]'],
            ['CRM_LEADS_TITLE', 'usersc/eh_leads.php', 'fas fa-users', 3, '[2,5]'],
            ['CRM_NAV_MY_LEADS', 'usersc/eh_my_leads.php', 'fas fa-phone-volume', 4, '[2,4]'],
            ['CRM_NAV_MY_CALLS', 'usersc/eh_my_calls.php', 'fas fa-clipboard-list', 5, '[2,4]'],
            ['CRM_NAV_DISTRIBUTE', 'usersc/eh_distribute.php', 'fas fa-shuffle', 6, '[2,5]'],
            ['CRM_NAV_BATCHES', 'usersc/eh_batches.php', 'fas fa-layer-group', 7, '[2,3]'],
            ['CRM_NAV_REPORTS', 'usersc/eh_reports.php', 'fas fa-chart-line', 8, '[2,5]'],
            ['CRM_NAV_SETTINGS', 'usersc/eh_settings.php', 'fas fa-sliders', 9, '[2,5]'],
            ['CRM_NAV_CEO_CONTROL', 'usersc/eh_ceo_control.php', 'fas fa-user-shield', 10, '[2,5]'],
        ];
        foreach ($items as $it) {
            $db->query("INSERT INTO `us_menu_items` (`menu`,`type`,`label`,`link`,`icon_class`,`link_target`,`parent`,`display_order`,`disabled`,`permissions`,`tags`)
                SELECT 1, 'link', ?, ?, ?, '_self', ?, ?, 0, ?, NULL FROM DUAL
                WHERE NOT EXISTS (SELECT 1 FROM `us_menu_items` WHERE `menu`=1 AND `parent`=? AND `link`=?)",
                [$it[0], $it[1], $it[2], $menuCrm, $it[3], $it[4], $menuCrm, $it[1]]);
        }
        echo json_encode(['success' => true, 'msg' => 'تم إنشاء قائمة CRM']); exit;
    }

    echo json_encode(['success' => false, 'msg' => 'عملية غير معروفة']); exit;
}

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

$crmPermNames = [CRM_PERM_ENTRY_NAME, CRM_PERM_SALES_NAME, CRM_PERM_MGR_NAME, CRM_PERM_SUPERVISOR_NAME];
$permStatus = [];
foreach ($crmPermNames as $nm) {
    $r = $db->query("SELECT id FROM permissions WHERE name = ?", [$nm])->first();
    $permStatus[$nm] = $r ? (int)$r->id : 0;
}
?>

<div class="container-fluid">
  <h3 class="mb-3"><i class="fas fa-cog"></i> إعدادات بلجن CRM</h3>

  <div class="card mb-3">
    <div class="card-header"><strong>البرمشنات (Permissions)</strong></div>
    <div class="card-body">
      <p class="text-muted small">أنشئ برمشنات CRM (أو اختر موجودة وأعد تسميتها). الـ CEO_Control will show only these.</p>
      <table class="table table-sm">
        <thead><tr><th>اسم البرمشن</th><th>الحالة</th><th>إجراء</th></tr></thead>
        <tbody>
          <?php foreach ($permStatus as $nm => $pid): ?>
            <tr>
              <td><?= htmlspecialchars($nm) ?></td>
              <td>
                <?php if ($pid > 0): ?>
                  <span class="badge bg-success">موجود (ID <?= $pid ?>)</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">غير موجود</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($pid === 0): ?>
                  <button class="btn btn-sm btn-primary btn-create-perm" data-name="<?= htmlspecialchars($nm) ?>">إنشاء</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header"><strong>قائمة CRM (Menu 1)</strong></div>
    <div class="card-body">
      <p class="text-muted small">أنشئ قائمة CRM (dropdown + 10 عناصر) في Menu 1. Idempotent — آمنة للتكرار.</p>
      <button class="btn btn-primary" id="btnCreateMenu">إنشاء قائمة CRM</button>
    </div>
  </div>
</div>

<script>
(function () {
  var csrf = '<?= Token::generate() ?>';
  document.querySelectorAll('.btn-create-perm').forEach(function (b) {
    b.addEventListener('click', function () {
      var name = this.dataset.name;
      fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=create_perm&name=' + encodeURIComponent(name) + '&csrf=' + encodeURIComponent(csrf)
      }).then(r => r.json()).then(function (res) {
        if (res.success) { alert(res.msg + (res.id ? ' (ID ' + res.id + ')' : '')); location.reload(); }
        else { alert(res.msg); }
      });
    });
  });
  document.getElementById('btnCreateMenu').addEventListener('click', function () {
    fetch(window.location.href, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'action=create_menu&csrf=' + encodeURIComponent(csrf)
    }).then(r => r.json()).then(function (res) {
      alert(res.msg || res.error || 'done');
    });
  });
})();
</script>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
