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
        'hero_eyebrow' => trim($_POST['hero_eyebrow'] ?? ''),
        'hero_cta_primary_label' => trim($_POST['hero_cta_primary_label'] ?? ''),
        'hero_cta_primary_url' => trim($_POST['hero_cta_primary_url'] ?? ''),
        'hero_cta_secondary_label' => trim($_POST['hero_cta_secondary_label'] ?? ''),
        'hero_cta_secondary_url' => trim($_POST['hero_cta_secondary_url'] ?? ''),
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
        'bible_source' => trim($_POST['bible_source'] ?? 'keyless'),
        'bible_api_key' => trim($_POST['bible_api_key'] ?? ''),
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
        $imageErrors = [];
        if (!empty($_FILES['logo']['name'])) {
            if (($_FILES['logo']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $imageErrors[] = 'Logo upload failed — the file may be too large for the server.';
            } elseif (!is_uploaded_file($_FILES['logo']['tmp_name'] ?? '') || !($filename = MediaProcessor::processImage($_FILES['logo']['tmp_name'], UPLOADS_WEBP_PATH))) {
                $imageErrors[] = 'Logo could not be processed — use JPG, PNG, GIF, WebP, BMP, or AVIF.';
            } else {
                $fields['logo_path'] = 'webp/' . $filename;
            }
        }
        if (!empty($_FILES['favicon']['name'])) {
            if (($_FILES['favicon']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $imageErrors[] = 'Favicon upload failed — the file may be too large for the server.';
            } elseif (!is_uploaded_file($_FILES['favicon']['tmp_name'] ?? '') || !($filename = MediaProcessor::processImage($_FILES['favicon']['tmp_name'], UPLOADS_WEBP_PATH))) {
                $imageErrors[] = 'Favicon could not be processed — use JPG, PNG, GIF, WebP, BMP, or AVIF.';
            } else {
                $fields['favicon_path'] = 'webp/' . $filename;
            }
        }
        if (isset($_POST['remove_hero_image'])) {
            $fields['hero_image_path'] = null;
        } elseif (!empty($_FILES['hero_image']['name'])) {
            if (($_FILES['hero_image']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $imageErrors[] = 'Hero image upload failed — the file may be too large for the server (increase upload_max_filesize/post_max_size in php.ini).';
            } elseif (!is_uploaded_file($_FILES['hero_image']['tmp_name'] ?? '') || !($filename = MediaProcessor::processImage($_FILES['hero_image']['tmp_name'], UPLOADS_WEBP_PATH, 80))) {
                $imageErrors[] = 'Hero image could not be processed — use JPG, PNG, GIF, WebP, BMP, or AVIF (iPhone HEIC files are not supported).';
            } else {
                $fields['hero_image_path'] = 'webp/' . $filename;
            }
        }

        $setSql = implode(', ', array_map(fn ($k) => "$k = :$k", array_keys($fields)));
        $pdo->prepare("UPDATE settings SET $setSql WHERE id = :id")->execute([...$fields, 'id' => $row['id']]);
        if ($imageErrors) {
            flash('error', implode(' ', $imageErrors) . ' Other settings were still saved.');
        } else {
            flash('success', 'Settings saved.');
        }
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
    <h2>Homepage Hero</h2>
    <p class="sub">The large banner at the top of the homepage. Upload a background image (compressed to WebP automatically) and edit the text that sits on top of it.</p>
    <label for="hero_image">Hero Background Image <?= $row['hero_image_path'] ? '(currently set)' : '' ?></label>
    <input type="file" id="hero_image" name="hero_image" accept="image/*">
    <?php if ($row['hero_image_path']): ?>
      <div style="display:flex; align-items:center; gap:14px; margin:10px 0;">
        <img src="<?= e(uploadUrl($row['hero_image_path'])) ?>" class="thumb" alt="" style="width:120px; height:68px; object-fit:cover; border-radius:10px;">
        <label class="checkbox-row" style="margin:0;">
          <input type="checkbox" id="remove_hero_image" name="remove_hero_image">
          <label for="remove_hero_image" style="margin:0;">Remove current image (back to the animated gradient)</label>
        </label>
      </div>
    <?php endif; ?>
    <label for="hero_eyebrow">Eyebrow Text <small>(small label above the headline)</small></label>
    <input type="text" id="hero_eyebrow" name="hero_eyebrow" value="<?= e((string) $row['hero_eyebrow']) ?>" placeholder="Welcome Home">
    <label for="hero_tagline2">Headline (Hero Tagline)</label>
    <input type="text" id="hero_tagline2" name="hero_tagline" value="<?= e((string) $row['hero_tagline']) ?>" placeholder="Where Faith Comes Alive">
    <label for="hero_scripture2">Scripture Line</label>
    <input type="text" id="hero_scripture2" name="hero_scripture" value="<?= e((string) $row['hero_scripture']) ?>">
    <div class="row two">
      <div>
        <label for="hero_cta_primary_label">Primary Button Label</label>
        <input type="text" id="hero_cta_primary_label" name="hero_cta_primary_label" value="<?= e((string) $row['hero_cta_primary_label']) ?>" placeholder="Plan Your Visit">
      </div>
      <div>
        <label for="hero_cta_primary_url">Primary Button Link</label>
        <input type="text" id="hero_cta_primary_url" name="hero_cta_primary_url" value="<?= e((string) $row['hero_cta_primary_url']) ?>" placeholder="/about or https://…">
      </div>
    </div>
    <div class="row two">
      <div>
        <label for="hero_cta_secondary_label">Secondary Button Label</label>
        <input type="text" id="hero_cta_secondary_label" name="hero_cta_secondary_label" value="<?= e((string) $row['hero_cta_secondary_label']) ?>" placeholder="Watch the Feed">
      </div>
      <div>
        <label for="hero_cta_secondary_url">Secondary Button Link</label>
        <input type="text" id="hero_cta_secondary_url" name="hero_cta_secondary_url" value="<?= e((string) $row['hero_cta_secondary_url']) ?>" placeholder="/feed or https://…">
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
    <label for="livestream_embed_url">Livestream YouTube Link (paste your channel's live video URL)</label>
    <input type="url" id="livestream_embed_url" name="livestream_embed_url" value="<?= e((string) $row['livestream_embed_url']) ?>" placeholder="https://www.youtube.com/watch?v=... or https://www.youtube.com/live/...">
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

  <div class="card">
    <h2>Bible</h2>
    <p class="sub">Choose the source that powers the Bible page (/bible) and the mobile app's Bible screen. The key-less option works with no signup but only provides public-domain translations (KJV/WEB). API.Bible (scripture.api.bible) adds modern translations like NIV, NLT, and NKJV — you'll need to register a free API key at <a href="https://scripture.api.bible/" target="_blank" rel="noopener">scripture.api.bible</a>.</p>
    <label for="bible_source">Bible Source</label>
    <select id="bible_source" name="bible_source">
      <option value="keyless" <?= ($row['bible_source'] ?? 'keyless') === 'keyless' ? 'selected' : '' ?>>Key-less (free — public domain translations)</option>
      <option value="api_bible" <?= ($row['bible_source'] ?? '') === 'api_bible' ? 'selected' : '' ?>>API.Bible (NIV, NLT, NKJV — requires API key)</option>
    </select>
    <div id="bible-api-key-wrap" style="<?= ($row['bible_source'] ?? 'keyless') === 'api_bible' ? '' : 'display:none;' ?>">
      <label for="bible_api_key">API.Bible API Key</label>
      <input type="text" id="bible_api_key" name="bible_api_key" value="<?= e((string) ($row['bible_api_key'] ?? '')) ?>" placeholder="Paste your api.bible access token" autocomplete="off">
    </div>
  </div>

  <button class="btn" type="submit">Save Settings</button>
</form>

<script>
(function () {
  const source = document.getElementById('bible_source');
  const keyWrap = document.getElementById('bible-api-key-wrap');
  if (!source || !keyWrap) return;
  const toggle = () => { keyWrap.style.display = source.value === 'api_bible' ? '' : 'none'; };
  source.addEventListener('change', toggle);
})();
</script>

<div class="card">
  <h2>Video Conversion (Cron Job)</h2>
  <p class="sub">Uploaded videos play instantly in the feed. If FFmpeg is installed, a background job crops them into the vertical 9:16 reel format — this runs automatically right after you publish, and the cron below is the safety net that guarantees every video is processed even if a browser closes mid-upload.</p>

  <h3>Set it up in cPanel</h3>
  <ol style="margin:0 0 14px 1.2em;line-height:1.7;">
    <li>Log in to cPanel and open <strong>Advanced &rarr; Cron Jobs</strong>.</li>
    <li>Under "Add New Cron Job", set the interval to <strong>every 5 minutes</strong>:
      <code>Minute: */5 &nbsp;Hour: * &nbsp;Day: * &nbsp;Month: * &nbsp;Weekday: *</code>
    </li>
    <li>Paste this as the command (path shown is for this server):</li>
  </ol>
  <pre style="background:#1a1530;color:#e8e4f0;padding:12px;border-radius:8px;overflow-x:auto;line-height:1.6;"><code>/usr/bin/php <?= e((string) realpath(__DIR__ . '/../cli/media_worker.php')) ?> &gt;&gt; <?= e(STORAGE_PATH . '/logs/media_worker.log') ?> 2&gt;&amp;1</code></pre>
  <p class="hint">
    If <code>/usr/bin/php</code> isn't found on your host, run <code>which php</code> in cPanel's Terminal to find it (often <code>/usr/local/bin/php</code>). Each run converts whatever originals are still waiting and stops after ~4 minutes; it is safe to run more often. Progress is logged to <code><?= e(STORAGE_PATH . '/logs/media_worker.log') ?></code>.
  </p>
</div>

<?php require __DIR__ . '/partials/layout-close.php'; ?>
