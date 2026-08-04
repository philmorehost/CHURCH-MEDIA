<?php
declare(strict_types=1);
$s = settings();
$serviceTimes = $s['service_times'] ? (is_array($s['service_times']) ? $s['service_times'] : (json_decode((string) $s['service_times'], true) ?: [])) : [];
$socials = [
    'Facebook' => $s['facebook_url'] ?? null,
    'Instagram' => $s['instagram_url'] ?? null,
    'YouTube' => $s['youtube_url'] ?? null,
    'TikTok' => $s['tiktok_url'] ?? null,
];
?>
</main>

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="footer-brand">
          <span class="mark"><?php if ($s['logo_path'] ?? null): ?><img src="<?= e(uploadUrl($s['logo_path'])) ?>" alt=""><?php else: ?><?= e(mb_substr($s['site_title'], 0, 1)) ?><?php endif; ?></span>
          <?= e($s['site_title']) ?>
        </div>
        <p class="footer-about"><?= e($s['footer_about_text'] ?? $s['site_tagline'] ?? '') ?></p>
        <div class="footer-social">
          <?php foreach ($socials as $label => $url): ?>
            <?php if ($url): ?><a href="<?= e($url) ?>" target="_blank" rel="noopener" title="<?= e($label) ?>"><?= e(mb_substr($label, 0, 1)) ?></a><?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <h4>Explore</h4>
        <a href="/feed">Media Feed</a>
        <a href="/events">Events</a>
        <a href="/sermons">Sermons</a>
        <a href="/live">Watch Live</a>
        <a href="/prayer">Prayer Wall</a>
      </div>
      <div>
        <h4>Connect</h4>
        <a href="/about">About Us</a>
        <a href="/contact">Contact</a>
        <a href="/give">Give</a>
        <?php if ($s['contact_email'] ?? null): ?><a href="mailto:<?= e($s['contact_email']) ?>"><?= e($s['contact_email']) ?></a><?php endif; ?>
        <?php if ($s['contact_phone'] ?? null): ?><a href="tel:<?= e($s['contact_phone']) ?>"><?= e($s['contact_phone']) ?></a><?php endif; ?>
      </div>
      <div>
        <h4>Service Times</h4>
        <?php if (!$serviceTimes): ?><p class="footer-about">Check back soon for our schedule.</p><?php endif; ?>
        <?php foreach ($serviceTimes as $st): ?>
          <div style="margin-bottom:10px;">
            <div style="color:var(--ink); font-size:13.5px; font-weight:600;"><?= e($st['label']) ?></div>
            <div style="color:var(--ink-faint); font-size:12.5px;"><?= e($st['time']) ?></div>
          </div>
        <?php endforeach; ?>
        <form data-remote-form="/api/newsletter" style="margin-top:14px;">
          <label style="font-size:12px; color:var(--ink-dim); display:block; margin-bottom:8px;">Get updates by email</label>
          <div style="display:flex; gap:8px;">
            <input type="email" name="email" required placeholder="you@example.com" style="flex:1; padding:10px 12px; border-radius:10px; border:1px solid var(--border-soft); background:#ffffff08; color:var(--ink); font-size:13px;">
            <button class="btn btn-gold btn-sm" type="submit">Join</button>
          </div>
          <div data-form-message class="form-message"></div>
        </form>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> <?= e($s['site_title']) ?>. All rights reserved.</span>
      <div class="legal-links">
        <a href="/prayer">Prayer Wall</a>
        <a href="/search">Search</a>
        <a href="/admin">Admin</a>
      </div>
    </div>
  </div>
</footer>

<script src="<?= asset('js/site.js') ?>"></script>
</body>
</html>
