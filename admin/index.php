<?php
declare(strict_types=1);

Auth::requireLogin();
$pdo = Database::getInstance()->getConnection();

// Non-super admins only see their own church's posts (strict per-unit match).
$user = Auth::user();
$scopeClause = '';
$myUnitLabel = '';
if ($user && empty($user['is_super_admin'])) {
    $myUnitLabel = !empty($user['org_unit_id']) ? Unit::label((int) $user['org_unit_id']) : '';
    $scopeIds = !empty($user['org_unit_id']) ? [(int) $user['org_unit_id']] : [];
    $scopeClause = $scopeIds ? ' AND p.org_unit_id IN (' . implode(',', array_map('intval', $scopeIds)) . ')' : ' AND 1 = 0';
}

$stats = [
    'posts' => (int) $pdo->query('SELECT COUNT(*) FROM media_posts')->fetchColumn(),
    'events' => (int) $pdo->query('SELECT COUNT(*) FROM events WHERE start_at >= NOW()')->fetchColumn(),
    'sermons' => (int) $pdo->query('SELECT COUNT(*) FROM sermons')->fetchColumn(),
    'prayers_new' => (int) $pdo->query("SELECT COUNT(*) FROM prayer_requests WHERE status = 'new'")->fetchColumn(),
    'subscribers' => (int) $pdo->query('SELECT COUNT(*) FROM newsletter_subscribers WHERE is_active = 1')->fetchColumn(),
    'blocked_ips' => (int) $pdo->query("SELECT COUNT(*) FROM ip_rules WHERE type = 'blacklist'")->fetchColumn(),
];
if ($scopeClause !== '') {
    $stats['my_posts'] = (int) $pdo->query('SELECT COUNT(*) FROM media_posts p WHERE 1=1' . $scopeClause)->fetchColumn();
}

$recentPosts = $pdo->query('
    SELECT p.id, p.caption, p.post_type, p.likes_count, p.views_count, p.created_at, u.name AS author
    FROM media_posts p JOIN users u ON u.id = p.user_id
    WHERE 1=1' . $scopeClause . '
    ORDER BY p.created_at DESC LIMIT 6
')->fetchAll();

$recentSecurity = $pdo->query('
    SELECT ip_address, username_attempted, event_type, created_at
    FROM security_logs ORDER BY created_at DESC LIMIT 8
')->fetchAll();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/partials/layout-open.php';
?>

<?php if ($myUnitLabel !== ''): ?>
<div class="card" style="margin-bottom:18px;">
  <h2 style="margin:0 0 4px;">📍 My Unit</h2>
  <p style="margin:0;color:var(--ink-dim);"><?= e($myUnitLabel) ?></p>
  <p style="margin:6px 0 0;color:var(--ink-dim);"><strong><?= $stats['my_posts'] ?? 0 ?></strong> post(s) in your scope.</p>
</div>
<?php endif; ?>

<div class="grid cols-4" style="margin-bottom:22px;">
  <div class="stat"><div class="num"><?= $stats['posts'] ?></div><div class="label">Media Posts</div></div>
  <div class="stat"><div class="num"><?= $stats['events'] ?></div><div class="label">Upcoming Events</div></div>
  <div class="stat"><div class="num"><?= $stats['sermons'] ?></div><div class="label">Sermons</div></div>
  <div class="stat"><div class="num"><?= $stats['prayers_new'] ?></div><div class="label">New Prayer Requests</div></div>
  <div class="stat"><div class="num"><?= $stats['subscribers'] ?></div><div class="label">Newsletter Subscribers</div></div>
  <div class="stat"><div class="num" style="color:<?= $stats['blocked_ips'] ? 'var(--danger)' : 'var(--success)' ?>;"><?= $stats['blocked_ips'] ?></div><div class="label">Blocked IPs</div></div>
</div>

<div class="grid cols-2">
  <div class="card">
    <h2>Recent Media Posts</h2>
    <p class="sub">Latest uploads across the feed</p>
    <?php if (!$recentPosts): ?>
      <div class="empty">No posts yet — <a href="/admin/media" style="color:var(--gold-soft);">create your first one</a>.</div>
    <?php else: ?>
      <table>
        <tr><th>Caption</th><th>Type</th><th>Likes</th><th>Views</th><th>Posted</th></tr>
        <?php foreach ($recentPosts as $p): ?>
        <tr>
          <td><?= e(mb_strimwidth((string) $p['caption'], 0, 40, '…')) ?></td>
          <td><span class="badge info"><?= e(str_replace('_', ' ', $p['post_type'])) ?></span></td>
          <td><?= $p['likes_count'] ?></td>
          <td><?= $p['views_count'] ?></td>
          <td><?= e(timeAgo($p['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Recent Security Activity</h2>
    <p class="sub">Login attempts across the admin panel</p>
    <?php if (!$recentSecurity): ?>
      <div class="empty">No activity logged yet.</div>
    <?php else: ?>
      <table>
        <tr><th>Event</th><th>User</th><th>IP</th><th>When</th></tr>
        <?php foreach ($recentSecurity as $log): ?>
        <tr>
          <td>
            <?php if ($log['event_type'] === 'successful_login'): ?><span class="badge ok">success</span>
            <?php elseif ($log['event_type'] === 'failed_login'): ?><span class="badge fail">failed</span>
            <?php else: ?><span class="badge warn">blocked</span><?php endif; ?>
          </td>
          <td><?= e($log['username_attempted'] ?? '—') ?></td>
          <td><?= e($log['ip_address']) ?></td>
          <td><?= e(timeAgo($log['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/partials/layout-close.php'; ?>
