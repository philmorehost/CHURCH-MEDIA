<?php
declare(strict_types=1);

Auth::requireRole('admin');
if (!Auth::isSuperAdmin()) {
    http_response_code(403);
    exit('Only the super admin can manage units.');
}

$pdo = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? 'list';
$id = (int) ($_GET['id'] ?? 0);
$errors = [];

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $type = (string) ($_POST['type'] ?? 'province');
    $parentId = (string) ($_POST['parent_id'] ?? '') !== '' ? (int) $_POST['parent_id'] : null;
    $name = trim((string) ($_POST['name'] ?? ''));
    $result = Unit::create($type, $parentId, $name);
    if (!empty($result['errors'])) {
        $errors = $result['errors'];
    } else {
        flash('success', 'Unit added.');
        redirect('/admin/units');
    }
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $id = (int) ($_POST['id'] ?? $id);
    $type = (string) ($_POST['type'] ?? '');
    $parentId = (string) ($_POST['parent_id'] ?? '') !== '' ? (int) $_POST['parent_id'] : null;
    $name = trim((string) ($_POST['name'] ?? ''));
    $result = Unit::update($id, $type, $parentId, $name);
    if (!empty($result['errors'])) {
        $errors = $result['errors'];
    } else {
        flash('success', 'Unit updated.');
        redirect('/admin/units');
    }
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    Unit::delete((int) ($_POST['id'] ?? 0));
    flash('success', 'Unit removed.');
    redirect('/admin/units');
}

$editUnit = null;
if ($action === 'edit') {
    $editUnit = Unit::find($id);
    if (!$editUnit) {
        redirect('/admin/units');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors) {
        $editUnit['type'] = (string) ($_POST['type'] ?? $editUnit['type']);
        $editUnit['parent_id'] = (string) ($_POST['parent_id'] ?? '') !== '' ? (int) $_POST['parent_id'] : null;
        $editUnit['name'] = trim((string) ($_POST['name'] ?? $editUnit['name']));
    }
}

$tree = Unit::tree();
$unitOptions = array_map(fn (array $u): array => ['id' => (int) $u['id'], 'type' => $u['type'], 'label' => Unit::label((int) $u['id'])], Unit::all());

$pageTitle = 'Units';
$activeNav = 'units';
require __DIR__ . '/partials/layout-open.php';
?>

<?php foreach ($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>

<?php if ($action === 'create' || $action === 'edit'): ?>
  <div class="card" style="max-width:560px;">
    <h2><?= $action === 'edit' ? 'Edit Unit' : 'Add Unit' ?></h2>
    <form method="post" action="/admin/units?action=<?= $action ?><?= $action === 'edit' ? '&id=' . (int) $id : '' ?>">
      <?= Csrf::field() ?>
      <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>
      <label for="type">Type</label>
      <select id="type" name="type">
        <?php foreach (Unit::types() as $t): ?>
          <option value="<?= e($t) ?>" <?= ($editUnit['type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst(e($t)) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="parent_id">Parent unit</label>
      <select id="parent_id" name="parent_id">
        <option value="">— none (top level) —</option>
      </select>
      <label for="name">Name</label>
      <input type="text" id="name" name="name" value="<?= e($editUnit['name'] ?? '') ?>" required>
      <div class="btn-row">
        <button class="btn" type="submit"><?= $action === 'edit' ? 'Save Changes' : 'Add Unit' ?></button>
        <a class="btn secondary" href="/admin/units">Cancel</a>
      </div>
    </form>
  </div>
  <script>
    var unitOptions = <?= json_encode($unitOptions, JSON_UNESCAPED_UNICODE) ?>;
    var parentTypes = { 'zone': 'province', 'area': 'zone', 'parish': 'area' };
    function updateParentPicker() {
      var type = document.getElementById('type').value;
      var want = parentTypes[type] || '';
      var sel = document.getElementById('parent_id');
      var current = sel.value;
      sel.innerHTML = '<option value="">— none (top level) —</option>';
      unitOptions.forEach(function (u) {
        if (want === '' || u.type === want) {
          var o = document.createElement('option');
          o.value = String(u.id);
          o.textContent = u.label;
          sel.appendChild(o);
        }
      });
      for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === current) { sel.value = current; break; }
      }
    }
    document.getElementById('type').addEventListener('change', updateParentPicker);
    updateParentPicker();
  </script>
<?php else: ?>
  <div class="btn-row" style="margin-bottom:20px;"><a class="btn" href="/admin/units?action=create">+ Add Unit</a></div>
  <?php if (!$tree): ?>
    <div class="card"><p style="color:var(--ink-faint);">No units yet — start by adding a Province.</p></div>
  <?php else: ?>
  <div class="card">
    <table>
      <tr><th>Unit</th><th>Type</th><th></th></tr>
      <?php
      $renderNode = function (array $node, int $depth = 0) use (&$renderNode): void {
          $pad = str_repeat('&nbsp;&nbsp;', $depth);
          echo '<tr>';
          echo '<td>' . $pad . e($node['name']) . ' <small style="color:var(--ink-faint);">/' . e($node['slug']) . '</small></td>';
          echo '<td><span class="badge info">' . e($node['type']) . '</span></td>';
          echo '<td>';
          echo '<a class="btn sm" href="/admin/units?action=edit&id=' . (int) $node['id'] . '">Edit</a> ';
          echo '<form method="post" action="/admin/units?action=delete" onsubmit="return confirm(\'Delete this unit and everything under it? Posts/users will be unassigned.\');" style="display:inline;">';
          echo Csrf::field();
          echo '<input type="hidden" name="id" value="' . (int) $node['id'] . '">';
          echo '<button type="submit" class="btn danger sm">Delete</button>';
          echo '</form>';
          echo '</td></tr>';
          foreach ($node['children'] ?? [] as $child) {
              $renderNode($child, $depth + 1);
          }
      };
      foreach ($tree as $node) {
          $renderNode($node);
      }
      ?>
    </table>
  </div>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/layout-close.php'; ?>
