<?php
declare(strict_types=1);

Auth::requireRole('admin');

$pdo = Database::getInstance()->getConnection();
$user = Auth::user();
$action = $_GET['action'] ?? 'list';
$id = (int) ($_GET['id'] ?? 0);
$errors = [];

// Handle settings update
if ($action === 'settings_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    AdManager::updateSettings($_POST);
    flash('success', 'Ad settings and payment gateway updated successfully.');
    redirect('/admin/ads?action=settings');
}

// Handle ad approval
if ($action === 'approve' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $adId = (int) ($_POST['id'] ?? 0);
    if (AdManager::approveAd($adId)) {
        flash('success', 'Advertisement approved and activated immediately!');
    } else {
        flash('error', 'Could not approve advertisement.');
    }
    redirect('/admin/ads');
}

// Handle ad rejection
if ($action === 'reject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $adId = (int) ($_POST['id'] ?? 0);
    $reason = trim((string) ($_POST['reason'] ?? 'Did not meet ad placement guidelines.'));
    if (AdManager::rejectAd($adId, $reason)) {
        flash('success', 'Advertisement rejected.');
    } else {
        flash('error', 'Could not reject advertisement.');
    }
    redirect('/admin/ads');
}

// Handle toggle pause / resume
if ($action === 'toggle_pause' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $adId = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT status FROM advertisements WHERE id = ?');
    $stmt->execute([$adId]);
    $currentStatus = $stmt->fetchColumn();
    if ($currentStatus === 'approved') {
        $pdo->prepare('UPDATE advertisements SET status = "paused" WHERE id = ?')->execute([$adId]);
        flash('success', 'Ad paused.');
    } elseif ($currentStatus === 'paused') {
        $pdo->prepare('UPDATE advertisements SET status = "approved" WHERE id = ?')->execute([$adId]);
        flash('success', 'Ad resumed.');
    }
    redirect('/admin/ads');
}

// Handle edit request approval
if ($action === 'approve_edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $editId = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM ad_edit_requests WHERE id = ? AND status = "pending"');
    $stmt->execute([$editId]);
    $editReq = $stmt->fetch();
    if ($editReq) {
        $pdo->prepare('UPDATE advertisements SET title = ?, media_path = ?, target_url = ?, cta_label = ? WHERE id = ?')
            ->execute([$editReq['title'], $editReq['media_path'], $editReq['target_url'], $editReq['cta_label'], $editReq['ad_id']]);
        $pdo->prepare('UPDATE ad_edit_requests SET status = "approved" WHERE id = ?')->execute([$editId]);
        flash('success', 'Ad edit request approved.');
    }
    redirect('/admin/ads?action=edits');
}

// Handle edit request rejection
if ($action === 'reject_edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $editId = (int) ($_POST['id'] ?? 0);
    $pdo->prepare('UPDATE ad_edit_requests SET status = "rejected" WHERE id = ?')->execute([$editId]);
    flash('success', 'Ad edit request rejected.');
    redirect('/admin/ads?action=edits');
}

// Handle ad delete
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $adId = (int) ($_POST['id'] ?? 0);
    $pdo->prepare('DELETE FROM advertisements WHERE id = ?')->execute([$adId]);
    flash('success', 'Advertisement removed.');
    redirect('/admin/ads');
}

$settings = AdManager::getSettings();
$stats = AdManager::getAdminStats();

$pageTitle = 'Ads Management';
$activeNav = 'ads';
require __DIR__ . '/partials/layout-open.php';
?>

<div class="btn-row" style="margin-bottom:20px; flex-wrap:wrap; gap:10px;">
  <a class="btn <?= $action === 'list' ? '' : 'secondary' ?>" href="/admin/ads?action=list">📢 Advertisements</a>
  <a class="btn <?= $action === 'edits' ? '' : 'secondary' ?>" href="/admin/ads?action=edits">✏️ Edit Requests</a>
  <a class="btn <?= $action === 'analytics' ? '' : 'secondary' ?>" href="/admin/ads?action=analytics">📊 Performance & Revenue Stats</a>
  <a class="btn <?= $action === 'settings' ? '' : 'secondary' ?>" href="/admin/ads?action=settings">⚙️ Settings & Payhub Gateway</a>
  <a class="btn btn-gold" href="/advertise" target="_blank" style="margin-left:auto;">↗ View Public Ad Placement Form</a>
</div>

<?php if ($action === 'settings'): ?>
  <div class="card" style="max-width:800px;">
    <h2>Ad Placement & Payment Gateway Settings</h2>
    <p class="sub">Configure ad duration pricing, skip timer duration, Payhub integration keys, and Bank Transfer account details.</p>

    <form method="post" action="/admin/ads?action=settings_save">
      <?= Csrf::field() ?>
      <h3 style="margin-top:20px; color:var(--gold-soft);">Duration Pricing (NGN ₦)</h3>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
        <div>
          <label for="price_7_days">7 Days Preset Price (₦)</label>
          <input type="number" step="0.01" id="price_7_days" name="price_7_days" value="<?= e((string) $settings['price_7_days']) ?>" required>
        </div>
        <div>
          <label for="price_14_days">14 Days Preset Price (₦)</label>
          <input type="number" step="0.01" id="price_14_days" name="price_14_days" value="<?= e((string) $settings['price_14_days']) ?>" required>
        </div>
        <div>
          <label for="price_30_days">30 Days Preset Price (₦)</label>
          <input type="number" step="0.01" id="price_30_days" name="price_30_days" value="<?= e((string) $settings['price_30_days']) ?>" required>
        </div>
        <div>
          <label for="price_90_days">90 Days Preset Price (₦)</label>
          <input type="number" step="0.01" id="price_90_days" name="price_90_days" value="<?= e((string) $settings['price_90_days']) ?>" required>
        </div>
        <div>
          <label for="price_per_custom_day">Custom Per Day Rate (₦)</label>
          <input type="number" step="0.01" id="price_per_custom_day" name="price_per_custom_day" value="<?= e((string) $settings['price_per_custom_day']) ?>" required>
        </div>
        <div>
          <label for="price_per_custom_hour">Custom Per Hour Rate (₦)</label>
          <input type="number" step="0.01" id="price_per_custom_hour" name="price_per_custom_hour" value="<?= e((string) $settings['price_per_custom_hour']) ?>" required>
        </div>
      </div>

      <h3 style="margin-top:24px; color:var(--gold-soft);">Reels Ad Timer</h3>
      <div>
        <label for="skip_timer_seconds">Skip Timer Countdown (Seconds before skip button enables)</label>
        <input type="number" id="skip_timer_seconds" name="skip_timer_seconds" value="<?= e((string) $settings['skip_timer_seconds']) ?>" min="1" max="30" required>
        <p class="sub">Displays a round SVG clock timer counting down (default 7 seconds) like Instagram and Facebook Reels.</p>
      </div>

      <h3 style="margin-top:24px; color:var(--gold-soft);">Payhub Gateway Credentials (merchant.payhub.com.ng)</h3>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
        <div>
          <label for="payhub_public_key">Payhub Public Key</label>
          <input type="text" id="payhub_public_key" name="payhub_public_key" value="<?= e($settings['payhub_public_key'] ?? '') ?>" placeholder="pk_live_...">
        </div>
        <div>
          <label for="payhub_secret_key">Payhub Secret Key</label>
          <input type="text" id="payhub_secret_key" name="payhub_secret_key" value="<?= e($settings['payhub_secret_key'] ?? '') ?>" placeholder="sk_live_...">
        </div>
      </div>

      <h3 style="margin-top:24px; color:var(--gold-soft);">Manual Bank Transfer Details</h3>
      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">
        <div>
          <label for="bank_name">Bank Name</label>
          <input type="text" id="bank_name" name="bank_name" value="<?= e($settings['bank_name'] ?? '') ?>" placeholder="e.g. Zenith Bank">
        </div>
        <div>
          <label for="bank_account_number">Account Number</label>
          <input type="text" id="bank_account_number" name="bank_account_number" value="<?= e($settings['bank_account_number'] ?? '') ?>" placeholder="0123456789">
        </div>
        <div>
          <label for="bank_account_name">Account Name</label>
          <input type="text" id="bank_account_name" name="bank_account_name" value="<?= e($settings['bank_account_name'] ?? '') ?>" placeholder="Church Media Account">
        </div>
      </div>

      <div class="btn-row" style="margin-top:24px;">
        <button type="submit" class="btn">Save Ad Settings</button>
      </div>
    </form>
  </div>

<?php elseif ($action === 'edits'): ?>
  <?php
  $stmt = $pdo->query("SELECT e.*, a.title as orig_title, a.media_path as orig_media_path, p.name as publisher_name, p.email as publisher_email FROM ad_edit_requests e JOIN advertisements a ON e.ad_id = a.id JOIN ad_publishers p ON a.publisher_id = p.id ORDER BY e.created_at DESC");
  $editReqs = $stmt->fetchAll();
  ?>
  <div class="card">
    <h2>Publisher Edit Requests</h2>
    <p class="sub">When ad publishers update their active advertisements, requests require admin review before taking effect.</p>
    <?php if (!$editReqs): ?>
      <div class="empty">No edit requests.</div>
    <?php else: ?>
      <table>
        <tr>
          <th>Ad ID</th>
          <th>Publisher</th>
          <th>Original Title</th>
          <th>Requested Changes</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
        <?php foreach ($editReqs as $r): ?>
          <tr>
            <td>#<?= (int) $r['ad_id'] ?></td>
            <td><strong><?= e($r['publisher_name']) ?></strong><br><small><?= e($r['publisher_email']) ?></small></td>
            <td><?= e($r['orig_title']) ?></td>
            <td>
              <strong>Title:</strong> <?= e($r['title']) ?><br>
              <strong>Target URL:</strong> <a href="<?= e($r['target_url']) ?>" target="_blank"><?= e($r['target_url']) ?></a><br>
              <strong>CTA Label:</strong> <?= e($r['cta_label']) ?>
            </td>
            <td>
              <?php if ($r['status'] === 'pending'): ?><span class="badge warn">pending</span>
              <?php elseif ($r['status'] === 'approved'): ?><span class="badge ok">approved</span>
              <?php else: ?><span class="badge fail">rejected</span><?php endif; ?>
            </td>
            <td><?= e(date('M j, Y H:i', strtotime($r['created_at']))) ?></td>
            <td>
              <?php if ($r['status'] === 'pending'): ?>
                <form method="post" action="/admin/ads?action=approve_edit" style="display:inline;">
                  <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="btn sm">Approve</button>
                </form>
                <form method="post" action="/admin/ads?action=reject_edit" style="display:inline;">
                  <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="btn sm danger">Reject</button>
                </form>
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>

<?php elseif ($action === 'analytics'): ?>
  <?php $topAds = AdManager::getTopPerformingAds(20); ?>
  <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:20px;">
    <div class="card" style="text-align:center;">
      <small style="color:var(--ink-faint); font-weight:600;">TOTAL REVENUE GENERATED</small>
      <div style="font-size:24px; font-weight:800; color:var(--gold-soft); margin-top:5px;">₦<?= number_format($stats['total_revenue'], 2) ?></div>
    </div>
    <div class="card" style="text-align:center;">
      <small style="color:var(--ink-faint); font-weight:600;">ACTIVE ADS</small>
      <div style="font-size:24px; font-weight:800; margin-top:5px;"><?= $stats['active_ads'] ?> / <?= $stats['total_ads'] ?></div>
    </div>
    <div class="card" style="text-align:center;">
      <small style="color:var(--ink-faint); font-weight:600;">TOTAL IMPRESSIONS</small>
      <div style="font-size:24px; font-weight:800; margin-top:5px;"><?= number_format($stats['total_impressions']) ?></div>
    </div>
    <div class="card" style="text-align:center;">
      <small style="color:var(--ink-faint); font-weight:600;">TOTAL CLICKS</small>
      <div style="font-size:24px; font-weight:800; margin-top:5px;"><?= number_format($stats['total_clicks']) ?></div>
    </div>
    <div class="card" style="text-align:center;">
      <small style="color:var(--ink-faint); font-weight:600;">AVG CLICK-THROUGH RATE</small>
      <div style="font-size:24px; font-weight:800; color:var(--ok); margin-top:5px;"><?= $stats['ctr'] ?>%</div>
    </div>
  </div>

  <div class="card">
    <h2>Ad Revenue & Performance Leaderboard</h2>
    <p class="sub">Compare performance metrics across video and image advertisements to identify top revenue generators and highest user engagement.</p>
    <?php if (!$topAds): ?>
      <div class="empty">No advertisement data yet.</div>
    <?php else: ?>
      <table>
        <tr>
          <th>Ad Title & Type</th>
          <th>Publisher</th>
          <th>Amount Paid</th>
          <th>Impressions</th>
          <th>Clicks</th>
          <th>CTR (%)</th>
          <th>Status</th>
        </tr>
        <?php foreach ($topAds as $ad): ?>
          <tr>
            <td>
              <strong><?= e($ad['title']) ?></strong><br>
              <span class="badge info"><?= strtoupper(e($ad['media_type'])) ?></span>
            </td>
            <td><?= e($ad['publisher_name']) ?><br><small style="color:var(--ink-faint);"><?= e($ad['publisher_email']) ?></small></td>
            <td><strong style="color:var(--gold-soft);">₦<?= number_format((float) $ad['amount'], 2) ?></strong></td>
            <td><?= number_format((int) $ad['impressions_count']) ?></td>
            <td><?= number_format((int) $ad['clicks_count']) ?></td>
            <td><strong><?= $ad['ctr'] ?>%</strong></td>
            <td>
              <?php if ($ad['status'] === 'approved'): ?><span class="badge ok">active</span>
              <?php elseif ($ad['status'] === 'pending'): ?><span class="badge warn">pending</span>
              <?php elseif ($ad['status'] === 'paused'): ?><span class="badge info">paused</span>
              <?php else: ?><span class="badge fail"><?= e($ad['status']) ?></span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>

<?php else: ?>
  <?php
  $stmt = $pdo->query("SELECT a.*, p.name as publisher_name, p.email as publisher_email FROM advertisements a JOIN ad_publishers p ON a.publisher_id = p.id ORDER BY CASE a.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END, a.created_at DESC");
  $ads = $stmt->fetchAll();
  ?>
  <div class="card">
    <h2>All Advertisement Orders</h2>
    <p class="sub">Review ad placement orders, check bank receipts or Payhub transactions, and approve or reject submissions.</p>
    <?php if (!$ads): ?>
      <div class="empty">No advertisement placements submitted yet.</div>
    <?php else: ?>
      <table>
        <tr>
          <th>Ad Details</th>
          <th>Publisher</th>
          <th>Duration & Price</th>
          <th>Payment Method</th>
          <th>Status</th>
          <th>Stats</th>
          <th>Actions</th>
        </tr>
        <?php foreach ($ads as $ad): ?>
          <tr>
            <td>
              <strong><?= e($ad['title']) ?></strong><br>
              <small><span class="badge info"><?= strtoupper(e($ad['media_type'])) ?></span> <a href="<?= e($ad['target_url']) ?>" target="_blank">↗ Target Link</a></small><br>
              <?php if ($ad['receipt_path']): ?>
                <a class="btn sm secondary" href="<?= e(uploadUrl($ad['receipt_path'])) ?>" target="_blank" style="margin-top:4px;">📄 View Receipt</a>
              <?php endif; ?>
            </td>
            <td><?= e($ad['publisher_name']) ?><br><small style="color:var(--ink-faint);"><?= e($ad['publisher_email']) ?></small></td>
            <td>
              <strong>₦<?= number_format((float) $ad['amount'], 2) ?></strong><br>
              <small style="color:var(--ink-faint);"><?= e(str_replace('_', ' ', $ad['duration_type'])) ?></small>
            </td>
            <td>
              <span class="badge info"><?= strtoupper(e($ad['payment_method'])) ?></span><br>
              <small style="color:var(--ink-faint);"><?= e($ad['payment_status']) ?></small>
            </td>
            <td>
              <?php if ($ad['status'] === 'pending'): ?><span class="badge warn">pending review</span>
              <?php elseif ($ad['status'] === 'approved'): ?><span class="badge ok">active</span>
              <?php elseif ($ad['status'] === 'paused'): ?><span class="badge info">paused</span>
              <?php elseif ($ad['status'] === 'expired'): ?><span class="badge fail">expired</span>
              <?php else: ?><span class="badge fail">rejected</span><?php endif; ?>
            </td>
            <td>
              <small>👁 <?= number_format((int) $ad['impressions_count']) ?> views</small><br>
              <small>🖱 <?= number_format((int) $ad['clicks_count']) ?> clicks</small>
            </td>
            <td>
              <?php if ($ad['status'] === 'pending'): ?>
                <form method="post" action="/admin/ads?action=approve" style="display:inline;">
                  <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $ad['id'] ?>">
                  <button type="submit" class="btn sm">Approve & Activate</button>
                </form>
                <button type="button" class="btn sm danger" onclick="var r=prompt('Reason for rejection:'); if(r){ var f=document.createElement('form'); f.method='POST'; f.action='/admin/ads?action=reject'; f.innerHTML='<?= Csrf::field() ?><input type=\'hidden\' name=\'id\' value=\'<?= (int)$ad['id'] ?>\'><input type=\'hidden\' name=\'reason\' value=\''+r+'\'>'; document.body.appendChild(f); f.submit(); }">Reject</button>
              <?php elseif ($ad['status'] === 'approved' || $ad['status'] === 'paused'): ?>
                <form method="post" action="/admin/ads?action=toggle_pause" style="display:inline;">
                  <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $ad['id'] ?>">
                  <button type="submit" class="btn sm secondary"><?= $ad['status'] === 'paused' ? 'Resume' : 'Pause' ?></button>
                </form>
              <?php endif; ?>
              <form method="post" action="/admin/ads?action=delete" style="display:inline;" onsubmit="return confirm('Delete this advertisement permanently?');">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $ad['id'] ?>">
                <button type="submit" class="btn sm danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/partials/layout-close.php'; ?>
