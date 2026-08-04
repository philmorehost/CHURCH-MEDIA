<?php
declare(strict_types=1);

Auth::requireRole('admin', 'editor');
$pdo = Database::getInstance()->getConnection();

if (($_GET['action'] ?? '') === 'export') {
    $rows = $pdo->query('SELECT email, subscribed_at FROM newsletter_subscribers WHERE is_active = 1 ORDER BY subscribed_at DESC')->fetchAll();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="subscribers.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['email', 'subscribed_at']);
    foreach ($rows as $row) {
        fputcsv($out, [$row['email'], $row['subscribed_at']]);
    }
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $id = (int) ($_POST['id'] ?? 0);
    $pdo->prepare('DELETE FROM newsletter_subscribers WHERE id = ?')->execute([$id]);
    redirect('/admin/newsletter');
}

$subscribers = $pdo->query('SELECT * FROM newsletter_subscribers ORDER BY subscribed_at DESC LIMIT 300')->fetchAll();

$pageTitle = 'Newsletter';
$activeNav = 'newsletter';
require __DIR__ . '/partials/layout-open.php';
?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <div>
      <h2>Newsletter Subscribers</h2>
      <p class="sub"><?= count($subscribers) ?> total</p>
    </div>
    <a class="btn secondary sm" href="/admin/newsletter?action=export">Export CSV</a>
  </div>
  <?php if (!$subscribers): ?>
    <div class="empty">No subscribers yet.</div>
  <?php else: ?>
    <table>
      <tr><th>Email</th><th>Status</th><th>Subscribed</th><th></th></tr>
      <?php foreach ($subscribers as $s): ?>
      <tr>
        <td><?= e($s['email']) ?></td>
        <td><?= $s['is_active'] ? '<span class="badge ok">active</span>' : '<span class="badge">unsubscribed</span>' ?></td>
        <td><?= e(timeAgo($s['subscribed_at'])) ?></td>
        <td>
          <form method="post" onsubmit="return confirm('Remove this subscriber?');" style="display:inline;">
            <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <button type="submit" class="btn danger sm">Remove</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/layout-close.php'; ?>
