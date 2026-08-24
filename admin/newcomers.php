<?php
declare(strict_types=1);

Auth::requireRole('admin', 'editor');
$pdo = Database::getInstance()->getConnection();
$user = Auth::user();
$scope = Unit::scopeClause($user, 'org_unit_id');
$scopeSql = $scope !== '' ? ' AND ' . $scope : '';
$action = $_GET['action'] ?? 'list';
$id = (int) ($_GET['id'] ?? 0);
$errors = [];
$statusFilter = $_GET['status'] ?? '';

if (in_array($action, ['create', 'edit'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    if ($action === 'edit' && !Unit::recordInScope($pdo, 'newcomers', $id, $user)) {
        flash('error', 'You can only manage newcomers for your own church.');
        redirect('/admin/newcomers');
    }
    $name = trim($_POST['name'] ?? '');
    $whatsapp = trim($_POST['whatsapp_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gender = in_array($_POST['gender'] ?? '', ['male', 'female', 'other'], true) ? $_POST['gender'] : null;
    $ageGroup = in_array($_POST['age_group'] ?? '', ['adult', 'children', 'youth'], true) ? $_POST['age_group'] : 'adult';
    $attendanceId = (int) ($_POST['attendance_id'] ?? 0);
    $visitDate = trim($_POST['visit_date'] ?? '') ?: null;
    $status = in_array($_POST['follow_up_status'] ?? '', ['new', 'contacted', 'followed_up', 'returned', 'inactive'], true) ? $_POST['follow_up_status'] : 'new';
    $notes = trim($_POST['notes'] ?? '');

    if ($name === '') {
        $errors[] = 'Name is required.';
    } else {
        if ($action === 'create') {
            $pdo->prepare('INSERT INTO newcomers (org_unit_id, name, whatsapp_phone, address, gender, age_group, attendance_id, visit_date, follow_up_status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$user['org_unit_id'] ?? null, $name, $whatsapp, $address, $gender, $ageGroup, $attendanceId > 0 ? $attendanceId : null, $visitDate, $status, $notes, $user['id'] ?? null]);
            flash('success', 'Newcomer added.');
        } else {
            $pdo->prepare('UPDATE newcomers SET name=?, whatsapp_phone=?, address=?, gender=?, age_group=?, attendance_id=?, visit_date=?, follow_up_status=?, notes=? WHERE id=?')
                ->execute([$name, $whatsapp, $address, $gender, $ageGroup, $attendanceId > 0 ? $attendanceId : null, $visitDate, $status, $notes, $id]);
            flash('success', 'Newcomer updated.');
        }
        redirect('/admin/newcomers');
    }
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $targetId = (int) ($_POST['id'] ?? 0);
    if (!Unit::recordInScope($pdo, 'newcomers', $targetId, $user)) {
        flash('error', 'You can only manage newcomers for your own church.');
        redirect('/admin/newcomers');
    }
    $pdo->prepare('DELETE FROM newcomers WHERE id = ?')->execute([$targetId]);
    flash('success', 'Newcomer removed.');
    redirect('/admin/newcomers');
}

$editing = null;
if ($action === 'edit') {
    $stmt = $pdo->prepare('SELECT * FROM newcomers WHERE id = ?');
    $stmt->execute([$id]);
    $editing = $stmt->fetch();
    if (!$editing || !Unit::recordInScope($pdo, 'newcomers', $id, $user)) {
        redirect('/admin/newcomers');
    }
}

$attendanceOptions = [];
$newcomers = [];
$statusCounts = ['new' => 0, 'contacted' => 0, 'followed_up' => 0, 'returned' => 0, 'inactive' => 0, 'total' => 0];
// Attendance options are needed by both the form (create/edit) and the list.
if (in_array($action, ['list', 'create', 'edit'], true)) {
    $attendanceOptions = $pdo->query('SELECT id, service_date, service_name, topic FROM attendance_records WHERE 1=1' . $scopeSql . ' ORDER BY service_date DESC, id DESC LIMIT 100')->fetchAll();
}
if ($action === 'list') {
    $statusWhere = '';
    $statusParams = [];
    if ($statusFilter !== '') {
        $statusWhere = ' AND n.follow_up_status = ?';
        $statusParams[] = $statusFilter;
    }
    $stmt = $pdo->prepare('SELECT n.*, a.service_date AS attended_on, a.service_name AS attended_service, u.name AS recorded_by FROM newcomers n LEFT JOIN attendance_records a ON a.id = n.attendance_id LEFT JOIN users u ON u.id = n.created_by WHERE 1=1' . $scopeSql . $statusWhere . ' ORDER BY n.created_at DESC, n.id DESC LIMIT 300');
    $stmt->execute($statusParams);
    $newcomers = $stmt->fetchAll();
    $agg = $pdo->query('SELECT follow_up_status, COUNT(*) AS c FROM newcomers WHERE 1=1' . $scopeSql . ' GROUP BY follow_up_status')->fetchAll();
    foreach ($agg as $row) {
        $statusCounts[$row['follow_up_status']] = (int) $row['c'];
        $statusCounts['total'] += (int) $row['c'];
    }
}

$pageTitle = 'Newcomers';
$activeNav = 'newcomers';
require __DIR__ . '/partials/layout-open.php';
?>

<?php foreach ($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>

<?php if (in_array($action, ['create', 'edit'], true)): ?>
  <div class="card" style="max-width:640px;">
    <h2><?= $action === 'create' ? 'Add Newcomer' : 'Edit Newcomer' ?></h2>
    <p class="sub">Capture the details of a first-time guest so you can follow up after the service.</p>
    <form method="post" action="/admin/newcomers?action=<?= $action ?><?= $editing ? '&id=' . (int) $editing['id'] : '' ?>">
      <?= Csrf::field() ?>
      <label for="name">Full Name</label>
      <input type="text" id="name" name="name" value="<?= e($editing['name'] ?? '') ?>" required placeholder="e.g. Sarah Johnson">
      <div class="row two">
        <div>
          <label for="whatsapp_phone">WhatsApp Phone Number</label>
          <input type="tel" id="whatsapp_phone" name="whatsapp_phone" value="<?= e($editing['whatsapp_phone'] ?? '') ?>" placeholder="+234 812 345 6789">
        </div>
        <div>
          <label for="gender">Gender</label>
          <select id="gender" name="gender">
            <option value="">— Select —</option>
            <option value="male" <?= ($editing['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
            <option value="female" <?= ($editing['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
            <option value="other" <?= ($editing['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
      </div>
      <div class="row two">
        <div>
          <label for="age_group">Age Group</label>
          <select id="age_group" name="age_group">
            <option value="adult" <?= ($editing['age_group'] ?? 'adult') === 'adult' ? 'selected' : '' ?>>Adult</option>
            <option value="children" <?= ($editing['age_group'] ?? '') === 'children' ? 'selected' : '' ?>>Children</option>
            <option value="youth" <?= ($editing['age_group'] ?? '') === 'youth' ? 'selected' : '' ?>>Youth</option>
          </select>
        </div>
        <div>
          <label for="visit_date">Visit Date</label>
          <input type="date" id="visit_date" name="visit_date" value="<?= e($editing['visit_date'] ?? date('Y-m-d')) ?>">
        </div>
      </div>
      <label for="address">Address</label>
      <input type="text" id="address" name="address" value="<?= e($editing['address'] ?? '') ?>" placeholder="Street, City">
      <label for="attendance_id">Attended Service</label>
      <select id="attendance_id" name="attendance_id">
        <option value="0">— None / Not logged —</option>
        <?php foreach ($attendanceOptions as $a): ?>
          <option value="<?= (int) $a['id'] ?>" <?= (int) ($editing['attendance_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>>
            <?= e(date('M j, Y', strtotime((string) $a['service_date']))) ?> · <?= e($a['service_name']) ?><?= $a['topic'] ? ' — ' . e((string) $a['topic']) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="row two">
        <div>
          <label for="follow_up_status">Follow-up Status</label>
          <select id="follow_up_status" name="follow_up_status">
            <option value="new" <?= ($editing['follow_up_status'] ?? 'new') === 'new' ? 'selected' : '' ?>>New (not yet contacted)</option>
            <option value="contacted" <?= ($editing['follow_up_status'] ?? '') === 'contacted' ? 'selected' : '' ?>>Contacted</option>
            <option value="followed_up" <?= ($editing['follow_up_status'] ?? '') === 'followed_up' ? 'selected' : '' ?>>Followed up</option>
            <option value="returned" <?= ($editing['follow_up_status'] ?? '') === 'returned' ? 'selected' : '' ?>>Returned (came back)</option>
            <option value="inactive" <?= ($editing['follow_up_status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive / Not interested</option>
          </select>
        </div>
        <div></div>
      </div>
      <label for="notes">Notes</label>
      <textarea id="notes" name="notes"><?= e($editing['notes'] ?? '') ?></textarea>
      <button class="btn" type="submit"><?= $action === 'create' ? 'Add Newcomer' : 'Save Changes' ?></button>
      <a href="/admin/newcomers" class="btn secondary">Cancel</a>
    </form>
  </div>
<?php else: ?>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
    <a href="/admin/newcomers?action=create" class="btn">+ Add Newcomer</a>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <a class="btn sm <?= $statusFilter === '' ? '' : 'secondary' ?>" href="/admin/newcomers">All (<?= (int) $statusCounts['total'] ?>)</a>
      <?php foreach (['new' => 'New', 'contacted' => 'Contacted', 'followed_up' => 'Followed Up', 'returned' => 'Returned', 'inactive' => 'Inactive'] as $key => $label): ?>
        <a class="btn sm <?= $statusFilter === $key ? '' : 'secondary' ?>" href="/admin/newcomers?status=<?= e($key) ?>"><?= e($label) ?> (<?= (int) $statusCounts[$key] ?>)</a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!$newcomers): ?>
    <div class="card empty">No newcomers yet. Click "+ Add Newcomer" to record a first-time guest.</div>
  <?php else: ?>
  <table>
    <tr>
      <th>Name</th>
      <th>WhatsApp</th>
      <th>Address</th>
      <th>Gender</th>
      <th>Age Group</th>
      <th>Visited</th>
      <th>Status</th>
      <th></th>
    </tr>
    <?php foreach ($newcomers as $n): ?>
      <tr>
        <td><strong><?= e($n['name']) ?></strong><?= $n['notes'] ? '<br><small style="color:var(--ink-faint);">' . e((string) $n['notes']) . '</small>' : '' ?></td>
        <td>
          <?php if ($n['whatsapp_phone']): ?>
            <a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', (string) $n['whatsapp_phone'])) ?>" target="_blank" rel="noopener" style="color:var(--gold);"><?= e($n['whatsapp_phone']) ?> ↗</a>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td><?= e((string) $n['address']) ?></td>
        <td><?= $n['gender'] ? e(ucfirst((string) $n['gender'])) : '—' ?></td>
        <td>
          <?php
            $ageBadge = match ($n['age_group'] ?? 'adult') {
              'children' => ['Children', 'info'],
              'youth' => ['Youth', 'warn'],
              default => ['Adult', 'ok'],
            };
          ?>
          <span class="badge <?= $ageBadge[1] ?>"><?= $ageBadge[0] ?></span>
        </td>
        <td>
          <?php if ($n['visit_date']): ?><?= e(date('M j, Y', strtotime((string) $n['visit_date']))) ?><?php else: ?>—<?php endif; ?>
          <?php if ($n['attended_on']): ?><br><small style="color:var(--ink-faint);"><?= e(date('M j', strtotime((string) $n['attended_on']))) ?> · <?= e((string) $n['attended_service']) ?></small><?php endif; ?>
        </td>
        <td>
          <?php
            $statusBadge = match ($n['follow_up_status'] ?? 'new') {
              'contacted' => ['Contacted', 'info'],
              'followed_up' => ['Followed Up', 'warn'],
              'returned' => ['Returned', 'ok'],
              'inactive' => ['Inactive', 'fail'],
              default => ['New', 'warn'],
            };
          ?>
          <span class="badge <?= $statusBadge[1] ?>"><?= $statusBadge[0] ?></span>
        </td>
        <td class="actions">
          <a class="btn sm secondary" href="/admin/newcomers?action=edit&id=<?= (int) $n['id'] ?>">Edit</a>
          <form method="post" action="/admin/newcomers?action=delete" style="display:inline;" onsubmit="return confirm('Remove this newcomer?');">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
            <button class="btn sm danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/layout-close.php'; ?>
