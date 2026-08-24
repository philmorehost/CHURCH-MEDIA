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

if (in_array($action, ['create', 'edit'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    if ($action === 'edit' && !Unit::recordInScope($pdo, 'attendance_records', $id, $user)) {
        flash('error', 'You can only manage attendance for your own church.');
        redirect('/admin/attendance');
    }
    $serviceDate = trim($_POST['service_date'] ?? '');
    $serviceName = trim($_POST['service_name'] ?? '');
    $topic = trim($_POST['topic'] ?? '');
    $bibleText = trim($_POST['bible_text'] ?? '');
    $adult = max(0, (int) ($_POST['adult_count'] ?? 0));
    $children = max(0, (int) ($_POST['children_count'] ?? 0));
    $youth = max(0, (int) ($_POST['youth_count'] ?? 0));
    $notes = trim($_POST['notes'] ?? '');

    if ($serviceDate === '' || $serviceName === '') {
        $errors[] = 'Date and service name are required.';
    } else {
        if ($action === 'create') {
            $pdo->prepare('INSERT INTO attendance_records (org_unit_id, service_date, service_name, topic, bible_text, adult_count, children_count, youth_count, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$user['org_unit_id'] ?? null, $serviceDate, $serviceName, $topic, $bibleText, $adult, $children, $youth, $notes, $user['id'] ?? null]);
            flash('success', 'Attendance added.');
        } else {
            $pdo->prepare('UPDATE attendance_records SET service_date=?, service_name=?, topic=?, bible_text=?, adult_count=?, children_count=?, youth_count=?, notes=? WHERE id=?')
                ->execute([$serviceDate, $serviceName, $topic, $bibleText, $adult, $children, $youth, $notes, $id]);
            flash('success', 'Attendance updated.');
        }
        redirect('/admin/attendance');
    }
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $targetId = (int) ($_POST['id'] ?? 0);
    if (!Unit::recordInScope($pdo, 'attendance_records', $targetId, $user)) {
        flash('error', 'You can only manage attendance for your own church.');
        redirect('/admin/attendance');
    }
    $pdo->prepare('DELETE FROM attendance_records WHERE id = ?')->execute([$targetId]);
    flash('success', 'Attendance record removed.');
    redirect('/admin/attendance');
}

$editing = null;
if ($action === 'edit') {
    $stmt = $pdo->prepare('SELECT * FROM attendance_records WHERE id = ?');
    $stmt->execute([$id]);
    $editing = $stmt->fetch();
    if (!$editing || !Unit::recordInScope($pdo, 'attendance_records', $id, $user)) {
        redirect('/admin/attendance');
    }
}

$records = [];
$summary = ['services' => 0, 'adult' => 0, 'children' => 0, 'youth' => 0, 'total' => 0];
$trend = [];
if ($action === 'list') {
    $records = $pdo->query('SELECT a.*, u.name AS recorded_by FROM attendance_records a LEFT JOIN users u ON u.id = a.created_by WHERE 1=1' . $scopeSql . ' ORDER BY a.service_date DESC, a.id DESC LIMIT 200')->fetchAll();
    $agg = $pdo->query('SELECT COUNT(*) AS services, COALESCE(SUM(adult_count),0) AS adult, COALESCE(SUM(children_count),0) AS children, COALESCE(SUM(youth_count),0) AS youth, COALESCE(SUM(adult_count + children_count + youth_count),0) AS total FROM attendance_records WHERE 1=1' . $scopeSql)->fetch();
    $summary = $agg ?: $summary;

    // Weekly growth trend for the last 12 weeks (from the earliest day of the
    // window, so the bars read left-to-right oldest → newest).
    $trendStmt = $pdo->query('
        SELECT MIN(service_date) AS week_start,
               SUM(adult_count + children_count + youth_count) AS total
        FROM attendance_records
        WHERE 1=1' . $scopeSql . '
          AND service_date >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
        GROUP BY YEARWEEK(service_date, 1)
        ORDER BY week_start ASC');
    foreach ($trendStmt->fetchAll() as $row) {
        $trend[] = ['label' => date('M j', strtotime((string) $row['week_start'])), 'total' => (int) $row['total']];
    }
    // Fill gaps so the chart always spans 12 bars (weeks with no record = 0).
    $trendMap = [];
    foreach ($trend as $t) {
        $trendMap[$t['label']] = $t['total'];
    }
    $filled = [];
    $day = new DateTimeImmutable('today');
    for ($i = 11; $i >= 0; $i--) {
        $d = $day->modify('-' . $i . ' weeks');
        $label = $d->format('M j');
        $filled[] = ['label' => $label, 'total' => $trendMap[$label] ?? 0];
    }
    $trend = $filled;
}

$pageTitle = 'Attendance';
$activeNav = 'attendance';
require __DIR__ . '/partials/layout-open.php';
?>

<?php foreach ($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>

<?php if (in_array($action, ['create', 'edit'], true)): ?>
  <div class="card" style="max-width:640px;">
    <h2><?= $action === 'create' ? 'Add Attendance Record' : 'Edit Attendance Record' ?></h2>
    <form method="post" action="/admin/attendance?action=<?= $action ?><?= $editing ? '&id=' . (int) $editing['id'] : '' ?>">
      <?= Csrf::field() ?>
      <div class="row two">
        <div>
          <label for="service_date">Date</label>
          <input type="date" id="service_date" name="service_date" value="<?= e($editing['service_date'] ?? date('Y-m-d')) ?>" required>
        </div>
        <div>
          <label for="service_name">Service</label>
          <input type="text" id="service_name" name="service_name" value="<?= e($editing['service_name'] ?? '') ?>" placeholder="Sunday Worship" list="service-names" required>
          <datalist id="service-names">
            <option value="Sunday Worship">
            <option value="Sunday School">
            <option value="Midweek Service">
            <option value="Prayer Meeting">
            <option value="Youth Service">
            <option value="Choir Rehearsal">
            <option value="Special Service">
          </datalist>
        </div>
      </div>
      <label for="topic">Topic / Message Title</label>
      <input type="text" id="topic" name="topic" value="<?= e($editing['topic'] ?? '') ?>" placeholder="Faith in Action">
      <label for="bible_text">Bible Text / Scripture</label>
      <input type="text" id="bible_text" name="bible_text" value="<?= e($editing['bible_text'] ?? '') ?>" placeholder="James 2:14-26">
      <div class="row three">
        <div>
          <label for="adult_count">Adults</label>
          <input type="number" id="adult_count" name="adult_count" value="<?= (int) ($editing['adult_count'] ?? 0) ?>" min="0" required>
        </div>
        <div>
          <label for="children_count">Children</label>
          <input type="number" id="children_count" name="children_count" value="<?= (int) ($editing['children_count'] ?? 0) ?>" min="0" required>
        </div>
        <div>
          <label for="youth_count">Youth</label>
          <input type="number" id="youth_count" name="youth_count" value="<?= (int) ($editing['youth_count'] ?? 0) ?>" min="0" required>
        </div>
      </div>
      <label for="notes">Notes</label>
      <textarea id="notes" name="notes"><?= e($editing['notes'] ?? '') ?></textarea>
      <button class="btn" type="submit"><?= $action === 'create' ? 'Add Record' : 'Save Changes' ?></button>
      <a href="/admin/attendance" class="btn secondary">Cancel</a>
    </form>
  </div>
<?php else: ?>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
    <a href="/admin/attendance?action=create" class="btn">+ Add Attendance</a>
  </div>

  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:20px;">
    <div class="card" style="padding:16px;">
      <div style="color:var(--ink-faint);font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Services Logged</div>
      <div style="font-size:26px;font-weight:700;color:var(--ink);margin-top:4px;"><?= (int) $summary['services'] ?></div>
    </div>
    <div class="card" style="padding:16px;">
      <div style="color:var(--ink-faint);font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Total Attendance</div>
      <div style="font-size:26px;font-weight:700;color:var(--ink);margin-top:4px;"><?= (int) $summary['total'] ?></div>
    </div>
    <div class="card" style="padding:16px;">
      <div style="color:var(--ink-faint);font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Adults</div>
      <div style="font-size:26px;font-weight:700;color:var(--gold);margin-top:4px;"><?= (int) $summary['adult'] ?></div>
    </div>
    <div class="card" style="padding:16px;">
      <div style="color:var(--ink-faint);font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Children</div>
      <div style="font-size:26px;font-weight:700;color:var(--ink-dim);margin-top:4px;"><?= (int) $summary['children'] ?></div>
    </div>
    <div class="card" style="padding:16px;">
      <div style="color:var(--ink-faint);font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Youth</div>
      <div style="font-size:26px;font-weight:700;color:var(--ink-dim);margin-top:4px;"><?= (int) $summary['youth'] ?></div>
    </div>
  </div>

  <?php if ($trend): ?>
  <div class="card" style="padding:20px;margin-bottom:20px;">
    <h2 style="margin-bottom:4px;">Growth Trend <span style="color:var(--ink-faint);font-size:12px;font-weight:400;">— last 12 weeks</span></h2>
    <p class="sub" style="margin-bottom:18px;">Total attendance (adults + children + youth) per week. Weeks with no record are shown as zero.</p>
    <div class="trend-chart">
      <?php $trendMax = max(array_column($trend, 'total')); $trendMax = $trendMax > 0 ? $trendMax : 1; ?>
      <?php foreach ($trend as $t): ?>
        <div class="trend-col">
          <div class="trend-value"><?= (int) $t['total'] ?></div>
          <div class="trend-bar-wrap">
            <div class="trend-bar" style="height:<?= max(2, round(((int) $t['total'] / $trendMax) * 100)) ?>%;"></div>
          </div>
          <div class="trend-label"><?= e($t['label']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$records): ?>
    <div class="card empty">No attendance records yet. Click "+ Add Attendance" to record your first service.</div>
  <?php else: ?>
  <table>
    <tr>
      <th>Date</th>
      <th>Service</th>
      <th>Topic</th>
      <th>Bible Text</th>
      <th>Adults</th>
      <th>Children</th>
      <th>Youth</th>
      <th>Total</th>
      <th>Recorded By</th>
      <th></th>
    </tr>
    <?php foreach ($records as $r): ?>
      <tr>
        <td><?= e(date('M j, Y', strtotime((string) $r['service_date']))) ?></td>
        <td><?= e($r['service_name']) ?></td>
        <td><?= e((string) $r['topic']) ?></td>
        <td><?= e((string) $r['bible_text']) ?></td>
        <td><?= (int) $r['adult_count'] ?></td>
        <td><?= (int) $r['children_count'] ?></td>
        <td><?= (int) $r['youth_count'] ?></td>
        <td><strong><?= (int) $r['adult_count'] + (int) $r['children_count'] + (int) $r['youth_count'] ?></strong></td>
        <td><?= e((string) ($r['recorded_by'] ?? '')) ?></td>
        <td class="actions">
          <a class="btn sm secondary" href="/admin/newcomers?action=create&attendance_id=<?= (int) $r['id'] ?>" title="Quick-add a newcomer who attended this service">+ Newcomer</a>
          <a class="btn sm secondary" href="/admin/attendance?action=edit&id=<?= (int) $r['id'] ?>">Edit</a>
          <form method="post" action="/admin/attendance?action=delete" style="display:inline;" onsubmit="return confirm('Delete this attendance record?');">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button class="btn sm danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/layout-close.php'; ?>
