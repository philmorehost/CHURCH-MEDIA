<?php
declare(strict_types=1);

if (empty($_SESSION['publisher_id'])) {
    redirect('/ads/login');
}

$publisherId = (int) $_SESSION['publisher_id'];
$pdo = Database::getInstance()->getConnection();

$stmt = $pdo->prepare('SELECT * FROM ad_publishers WHERE id = ?');
$stmt->execute([$publisherId]);
$publisher = $stmt->fetch();

if (!$publisher) {
    unset($_SESSION['publisher_id']);
    redirect('/ads/login');
}

$action = $_GET['action'] ?? 'dashboard';

// Handle publisher pause / resume
if ($action === 'toggle_pause' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $adId = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT status FROM advertisements WHERE id = ? AND publisher_id = ?');
    $stmt->execute([$adId, $publisherId]);
    $status = $stmt->fetchColumn();
    if ($status === 'approved') {
        $pdo->prepare('UPDATE advertisements SET status = "paused" WHERE id = ? AND publisher_id = ?')->execute([$adId, $publisherId]);
        flash('success', 'Ad paused.');
    } elseif ($status === 'paused') {
        $pdo->prepare('UPDATE advertisements SET status = "approved" WHERE id = ? AND publisher_id = ?')->execute([$adId, $publisherId]);
        flash('success', 'Ad resumed.');
    }
    redirect('/ads/manager');
}

// Handle publisher edit request
if ($action === 'request_edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $adId = (int) ($_POST['id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $targetUrl = trim((string) ($_POST['target_url'] ?? ''));
    $ctaLabel = trim((string) ($_POST['cta_label'] ?? 'Learn More'));

    $stmt = $pdo->prepare('SELECT * FROM advertisements WHERE id = ? AND publisher_id = ?');
    $stmt->execute([$adId, $publisherId]);
    $ad = $stmt->fetch();

    if ($ad && $title !== '' && filter_var($targetUrl, FILTER_VALIDATE_URL)) {
        $mediaPath = $ad['media_path'];
        // Check if a new media file was uploaded in edit
        if (!empty($_FILES['edit_media']['tmp_name']) && ($_FILES['edit_media']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmpFile = $_FILES['edit_media']['tmp_name'];
            $mime = mime_content_type($tmpFile) ?: '';
            if (str_starts_with($mime, 'video/')) {
                $res = MediaProcessor::processVideoToReel($tmpFile, UPLOADS_REELS_PATH, UPLOADS_THUMBS_PATH);
                $mediaPath = 'reels/' . $res['file'];
            } elseif (str_starts_with($mime, 'image/')) {
                $croppedFile = AdManager::processAdImage($tmpFile, UPLOADS_PATH . '/ads');
                if ($croppedFile) {
                    $mediaPath = 'ads/' . $croppedFile;
                }
            }
        }

        $stmt = $pdo->prepare('INSERT INTO ad_edit_requests (ad_id, title, media_type, media_path, target_url, cta_label, status) VALUES (?, ?, ?, ?, ?, ?, "pending")');
        $stmt->execute([$adId, $title, $ad['media_type'], $mediaPath, $targetUrl, $ctaLabel]);
        $editReqId = (int) $pdo->lastInsertId();

        $editReq = [
            'id' => $editReqId,
            'title' => $title,
            'target_url' => $targetUrl,
            'cta_label' => $ctaLabel,
        ];
        AdManager::notifySuperAdminEditRequest($editReq, $ad, $publisher);

        flash('success', 'Your edit request has been submitted to the admin team for review.');
    } else {
        flash('error', 'Please provide a valid title and target URL.');
    }
    redirect('/ads/manager');
}

// Fetch publisher's ads
$stmt = $pdo->prepare("SELECT a.*,
    (CASE WHEN a.impressions_count > 0 THEN ROUND((a.clicks_count / a.impressions_count) * 100, 2) ELSE 0 END) as ctr
    FROM advertisements a
    WHERE a.publisher_id = ?
    ORDER BY a.created_at DESC");
$stmt->execute([$publisherId]);
$ads = $stmt->fetchAll();

// Auto expire active ads past end date
$pdo->prepare("UPDATE advertisements SET status = 'expired' WHERE publisher_id = ? AND status = 'approved' AND expires_at <= NOW()")->execute([$publisherId]);

$totalImpressions = array_sum(array_column($ads, 'impressions_count'));
$totalClicks = array_sum(array_column($ads, 'clicks_count'));
$avgCtr = $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0.0;
$activeCount = count(array_filter($ads, fn($a) => $a['status'] === 'approved'));

$metaTitle = 'Publisher Ad Manager';
$metaDescription = 'Monitor your live ad performance metrics, impressions, clicks, CTR, and time remaining.';
?>

<div class="container section" style="max-width:1100px; margin-top:20px;">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:24px;">
    <div>
      <h1 style="font-size:28px; margin:0 0 4px;">Welcome, <?= e($publisher['name']) ?></h1>
      <p style="color:var(--ink-dim); margin:0; font-size:14px;"><?= e($publisher['email']) ?> · Ad Manager Account</p>
    </div>
    <div class="btn-row">
      <a class="btn btn-gold" href="/advertise">+ Create New Advertisement</a>
      <a class="btn secondary" href="/ads/logout">Log Out</a>
    </div>
  </div>

  <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:24px;">
    <div class="card" style="text-align:center;">
      <small style="color:var(--ink-faint); font-weight:600;">ACTIVE ADS</small>
      <div style="font-size:28px; font-weight:800; color:var(--gold-soft); margin-top:4px;"><?= $activeCount ?></div>
    </div>
    <div class="card" style="text-align:center;">
      <small style="color:var(--ink-faint); font-weight:600;">TOTAL IMPRESSIONS</small>
      <div style="font-size:28px; font-weight:800; margin-top:4px;"><?= number_format($totalImpressions) ?></div>
    </div>
    <div class="card" style="text-align:center;">
      <small style="color:var(--ink-faint); font-weight:600;">TOTAL CLICKS</small>
      <div style="font-size:28px; font-weight:800; margin-top:4px;"><?= number_format($totalClicks) ?></div>
    </div>
    <div class="card" style="text-align:center;">
      <small style="color:var(--ink-faint); font-weight:600;">AVG CLICK-THROUGH RATE (CTR)</small>
      <div style="font-size:28px; font-weight:800; color:var(--ok); margin-top:4px;"><?= $avgCtr ?>%</div>
    </div>
  </div>

  <div class="card">
    <h2 style="font-size:20px; margin-bottom:16px;">Your Advertisements</h2>
    <?php if (!$ads): ?>
      <div class="empty">You have no advertisements yet. <a href="/advertise" style="color:var(--gold-soft);">Click here to place your first ad!</a></div>
    <?php else: ?>
      <table>
        <tr>
          <th>Ad Details</th>
          <th>Type & Target</th>
          <th>Duration & Time Left</th>
          <th>Views</th>
          <th>Clicks</th>
          <th>CTR (%)</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
        <?php foreach ($ads as $ad): ?>
          <?php
          $timeLeft = '—';
          if ($ad['status'] === 'approved' && !empty($ad['expires_at'])) {
              $diff = strtotime($ad['expires_at']) - time();
              if ($diff > 0) {
                  $days = intdiv($diff, 86400);
                  $hours = intdiv($diff % 86400, 3600);
                  $timeLeft = ($days > 0 ? $days . 'd ' : '') . $hours . 'h remaining';
              } else {
                  $timeLeft = 'Expired';
              }
          }
          ?>
          <tr>
            <td>
              <strong><?= e($ad['title']) ?></strong><br>
              <small style="color:var(--ink-faint);">Order #<?= (int) $ad['id'] ?> · ₦<?= number_format((float) $ad['amount'], 2) ?></small>
            </td>
            <td>
              <span class="badge info"><?= strtoupper(e($ad['media_type'])) ?></span><br>
              <small><a href="<?= e($ad['target_url']) ?>" target="_blank">↗ <?= e($ad['cta_label']) ?></a></small>
            </td>
            <td>
              <small style="color:var(--gold-soft); font-weight:700;"><?= e($timeLeft) ?></small><br>
              <small style="color:var(--ink-faint);"><?= e(str_replace('_', ' ', $ad['duration_type'])) ?></small>
            </td>
            <td>👁 <?= number_format((int) $ad['impressions_count']) ?></td>
            <td>🖱 <?= number_format((int) $ad['clicks_count']) ?></td>
            <td><strong><?= $ad['ctr'] ?>%</strong></td>
            <td>
              <?php if ($ad['status'] === 'approved'): ?><span class="badge ok">active</span>
              <?php elseif ($ad['status'] === 'pending'): ?><span class="badge warn">under review</span>
              <?php elseif ($ad['status'] === 'paused'): ?><span class="badge info">paused</span>
              <?php elseif ($ad['status'] === 'expired'): ?><span class="badge fail">expired</span>
              <?php else: ?><span class="badge fail">rejected</span><?php endif; ?>
            </td>
            <td>
              <?php if ($ad['status'] === 'approved' || $ad['status'] === 'paused'): ?>
                <form method="post" action="/ads/manager?action=toggle_pause" style="display:inline;">
                  <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $ad['id'] ?>">
                  <button type="submit" class="btn sm secondary"><?= $ad['status'] === 'paused' ? 'Resume' : 'Pause' ?></button>
                </form>
              <?php endif; ?>

              <button class="btn sm" onclick="openEditModal(<?= (int) $ad['id'] ?>, <?= json_encode($ad['title']) ?>, <?= json_encode($ad['target_url']) ?>, <?= json_encode($ad['cta_label']) ?>)">Edit</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
</div>

<!-- Edit Ad Modal -->
<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center;">
  <div class="card" style="width:100%; max-width:500px; padding:28px; background:var(--bg-card); position:relative;">
    <h3 style="margin-top:0;">Request Ad Edit</h3>
    <p class="sub">Note: Editing an active ad submits a change request for super admin review before updating live.</p>

    <form method="post" action="/ads/manager?action=request_edit" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <input type="hidden" id="edit_ad_id" name="id" value="">

      <label for="edit_title">Ad Title</label>
      <input type="text" id="edit_title" name="title" required>

      <label for="edit_target_url">Target URL</label>
      <input type="url" id="edit_target_url" name="target_url" required>

      <label for="edit_cta_label">CTA Label</label>
      <select id="edit_cta_label" name="cta_label">
        <option value="Learn More">Learn More</option>
        <option value="Register Now">Register Now</option>
        <option value="Shop Now">Shop Now</option>
        <option value="Get Started">Get Started</option>
        <option value="Watch Video">Watch Video</option>
        <option value="Contact Us">Contact Us</option>
      </select>

      <label for="edit_media">Replace Ad Media (Optional)</label>
      <input type="file" id="edit_media" name="edit_media" accept="image/*,video/*">

      <div class="btn-row" style="margin-top:20px;">
        <button type="submit" class="btn btn-gold">Submit Edit Request</button>
        <button type="button" class="btn secondary" onclick="closeEditModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(id, title, targetUrl, ctaLabel) {
  document.getElementById('edit_ad_id').value = id;
  document.getElementById('edit_title').value = title;
  document.getElementById('edit_target_url').value = targetUrl;
  document.getElementById('edit_cta_label').value = ctaLabel;
  document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
}
</script>
