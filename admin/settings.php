<?php
declare(strict_types=1);

Auth::requireRole('admin');
$pdo = Database::getInstance()->getConnection();
$errors = [];

$row = $pdo->query('SELECT * FROM settings ORDER BY id ASC LIMIT 1')->fetch();
$serviceTimes = $row && $row['service_times'] ? (json_decode($row['service_times'], true) ?: []) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();

    $fields = [
        'site_title' => trim($_POST['site_title'] ?? ''),
        'site_tagline' => trim($_POST['site_tagline'] ?? ''),
        'hero_tagline' => trim($_POST['hero_tagline'] ?? ''),
        'hero_scripture' => trim($_POST['hero_scripture'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'contact_phone' => trim($_POST['contact_phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'facebook_url' => trim($_POST['facebook_url'] ?? ''),
        'instagram_url' => trim($_POST['instagram_url'] ?? ''),
        'youtube_url' => trim($_POST['youtube_url'] ?? ''),
        'tiktok_url' => trim($_POST['tiktok_url'] ?? ''),
        'livestream_embed_url' => trim($_POST['livestream_embed_url'] ?? ''),
        'livestream_is_live' => isset($_POST['livestream_is_live']) ? 1 : 0,
        'giving_url' => trim($_POST['giving_url'] ?? ''),
        'footer_about_text' => trim($_POST['footer_about_text'] ?? ''),
        'meta_description' => trim($_POST['meta_description'] ?? ''),
    ];

    $labels = $_POST['service_label'] ?? [];
    $times = $_POST['service_time'] ?? [];
    $newServiceTimes = [];
    foreach ($labels as $i => $label) {
        $label = trim($label);
        $time = trim($times[$i] ?? '');
        if ($label !== '' && $time !== '') {
            $newServiceTimes[] = ['label' => $label, 'time' => $time];
        }
    }
    $fields['service_times'] = json_encode($newServiceTimes);

    if ($fields['site_title'] === '') {
        $errors[] = 'Site title is required.';
    } else {
        if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $filename = MediaProcessor::processImage($_FILES['logo']['tmp_name'], UPLOADS_WEBP_PATH);
            if ($filename) {
                $fields['logo_path'] = 'webp/' . $filename;
            }
        }
        if (!empty($_FILES['favicon']['tmp_name']) && is_uploaded_file($_FILES['favicon']['tmp_name'])) {
            $filename = MediaProcessor::processImage($_FILES['favicon']['tmp_name'], UPLOADS_WEBP_PATH);
            if ($filename) {
                $fields['favicon_path'] = 'webp/' . $filename;
            }
        }

        $setSql = implode(', ', array_map(fn ($k) => "$k = :$k", array_keys($fields)));
        $pdo->prepare("UPDATE settings SET $setSql WHERE id = :id")->execute([...$fields, 'id' => $row['id']]);
        flash('success', 'Settings saved.');
        redirect('/admin/settings');
    }
}

$row = $pdo->query('SELECT * FROM settings ORDER BY id ASC LIMIT 1')->fetch();
$serviceTimes = $row['service_times'] ? (json_decode($row['service_times'], true) ?: []) : [];
while (count($serviceTimes) < 4) {
    $serviceTimes[] = ['label' => '', 'time' => ''];
}

$pageTitle = 'Settings';
$activeNav = 'settings';
require __DIR__ . '/partials/layout-open.php';
?>

<?php foreach ($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>

<form method="post" enctype="multipart/form-data">
  <?= Csrf::field() ?>

  <div class="card">
    <h2>Branding</h2>
    <div class="row two">
      <div>
        <label for="site_title">Site / Church Name</label>
        <input type="text" id="site_title" name="site_title" value="<?= e($row['site_title']) ?>" required>
      </div>
      <div>
        <label for="site_tagline">Tagline</label>
        <input type="text" id="site_tagline" name="site_tagline" value="<?= e((string) $row['site_tagline']) ?>">
      </div>
    </div>
    <label for="hero_tagline">Homepage Hero Tagline</label>
    <input type="text" id="hero_tagline" name="hero_tagline" value="<?= e((string) $row['hero_tagline']) ?>">
    <label for="hero_scripture">Homepage Hero Scripture</label>
    <input type="text" id="hero_scripture" name="hero_scripture" value="<?= e((string) $row['hero_scripture']) ?>">
    <div class="row two">
      <div>
        <label for="logo">Logo <?= $row['logo_path'] ? '(currently set)' : '' ?></label>
        <input type="file" id="logo" name="logo" accept="image/*">
        <?php if ($row['logo_path']): ?><img src="<?= e(uploadUrl($row['logo_path'])) ?>" class="thumb" alt=""><?php endif; ?>
      </div>
      <div>
        <label for="favicon">Favicon <?= $row['favicon_path'] ? '(currently set)' : '' ?></label>
        <input type="file" id="favicon" name="favicon" accept="image/*">
        <?php if ($row['favicon_path']): ?><img src="<?= e(uploadUrl($row['favicon_path'])) ?>" class="thumb" alt=""><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <h2>Contact &amp; Service Times</h2>
    <div class="row two">
      <div><label for="contact_email">Contact Email</label><input type="email" id="contact_email" name="contact_email" value="<?= e((string) $row['contact_email']) ?>"></div>
      <div><label for="contact_phone">Contact Phone</label><input type="text" id="contact_phone" name="contact_phone" value="<?= e((string) $row['contact_phone']) ?>"></div>
    </div>
    <label for="address">Address</label>
    <input type="text" id="address" name="address" value="<?= e((string) $row['address']) ?>">
    <label>Service Times</label>
    <?php foreach ($serviceTimes as $st): ?>
      <div class="row two">
        <input type="text" name="service_label[]" value="<?= e($st['label']) ?>" placeholder="Sunday Worship">
        <input type="text" name="service_time[]" value="<?= e($st['time']) ?>" placeholder="9:00 AM & 11:00 AM">
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2>Social &amp; Live</h2>
    <div class="row two">
      <div><label for="facebook_url">Facebook URL</label><input type="url" id="facebook_url" name="facebook_url" value="<?= e((string) $row['facebook_url']) ?>"></div>
      <div><label for="instagram_url">Instagram URL</label><input type="url" id="instagram_url" name="instagram_url" value="<?= e((string) $row['instagram_url']) ?>"></div>
    </div>
    <div class="row two">
      <div><label for="youtube_url">YouTube URL</label><input type="url" id="youtube_url" name="youtube_url" value="<?= e((string) $row['youtube_url']) ?>"></div>
      <div><label for="tiktok_url">TikTok URL</label><input type="url" id="tiktok_url" name="tiktok_url" value="<?= e((string) $row['tiktok_url']) ?>"></div>
    </div>
    <label for="livestream_embed_url">Livestream Embed URL (YouTube/Facebook Live)</label>
    <input type="url" id="livestream_embed_url" name="livestream_embed_url" value="<?= e((string) $row['livestream_embed_url']) ?>">
    <div class="checkbox-row">
      <input type="checkbox" id="livestream_is_live" name="livestream_is_live" <?= !empty($row['livestream_is_live']) ? 'checked' : '' ?>>
      <label for="livestream_is_live" style="margin:0;">We are live right now (shows "LIVE" badge site-wide)</label>
    </div>
    <label for="giving_url">Giving / Donation URL</label>
    <input type="url" id="giving_url" name="giving_url" value="<?= e((string) $row['giving_url']) ?>" placeholder="https://giving-platform.com/your-church">
  </div>

  <div class="card">
    <h2>Footer &amp; SEO</h2>
    <label for="footer_about_text">Footer About Text</label>
    <textarea id="footer_about_text" name="footer_about_text"><?= e((string) $row['footer_about_text']) ?></textarea>
    <label for="meta_description">Meta Description (SEO)</label>
    <textarea id="meta_description" name="meta_description"><?= e((string) $row['meta_description']) ?></textarea>
  </div>

  <button class="btn" type="submit">Save Settings</button>
</form>

<?php require __DIR__ . '/partials/layout-close.php'; ?>
