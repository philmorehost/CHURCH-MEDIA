<?php
declare(strict_types=1);
$metaTitle = 'About Us';
$s = settings();
$team = Database::getInstance()->getConnection()
    ->query('SELECT * FROM team_members WHERE is_published = 1 ORDER BY sort_order ASC, name ASC')
    ->fetchAll();
?>

<section class="hero" style="min-height:56vh;">
  <div class="hero-content" style="padding-top:100px;">
    <span class="eyebrow">Our Story</span>
    <h1>About <?= e($s['site_title']) ?></h1>
    <p class="scripture"><?= e($s['footer_about_text'] ?? $s['site_tagline'] ?? '') ?></p>
  </div>
</section>

<section class="section reveal">
  <div class="container" style="max-width:760px;">
    <div class="grid grid-2" style="gap:36px;">
      <div class="glass-card" style="padding:28px;">
        <h3>Our Mission</h3>
        <p style="color:var(--ink-dim);">To lead people into a growing relationship with God, build authentic community, and serve our city with the love of Christ.</p>
      </div>
      <div class="glass-card" style="padding:28px;">
        <h3>Our Vision</h3>
        <p style="color:var(--ink-dim);">A church without walls — reaching every generation, in the room and online, with hope that lasts.</p>
      </div>
    </div>
  </div>
</section>

<?php if ($team): ?>
<section class="section reveal" style="background:var(--bg-1);">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">Meet the Team</span>
      <h2>Leadership</h2>
    </div>
    <div class="grid grid-4">
      <?php foreach ($team as $m): ?>
        <div class="glass-card" style="padding:20px; text-align:center;">
          <div style="width:88px; height:88px; border-radius:50%; margin:0 auto 14px; overflow:hidden; background:var(--bg-2);">
            <?php if ($m['photo']): ?><img src="<?= e(uploadUrl($m['photo'])) ?>" alt="<?= e($m['name']) ?>" style="width:100%; height:100%; object-fit:cover;"><?php endif; ?>
          </div>
          <h3 style="font-size:16px; margin-bottom:2px;"><?= e($m['name']) ?></h3>
          <?php if ($m['role_title']): ?><div style="color:var(--gold-soft); font-size:12.5px; margin-bottom:10px;"><?= e($m['role_title']) ?></div><?php endif; ?>
          <?php if ($m['bio']): ?><p style="color:var(--ink-dim); font-size:13px; margin:0;"><?= e($m['bio']) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section reveal">
  <div class="container" style="text-align:center;">
    <h2 style="font-size:clamp(24px,4vw,34px);">We'd love to meet you in person.</h2>
    <div class="hero-actions" style="margin-top:22px;">
      <a href="/contact" class="btn btn-gold">Plan a Visit</a>
      <a href="/events" class="btn btn-ghost">See What's Next</a>
    </div>
  </div>
</section>
