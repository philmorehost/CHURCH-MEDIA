<?php
declare(strict_types=1);
/** @var string $slug */

$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->prepare('SELECT * FROM org_units WHERE slug = ? LIMIT 1');
$stmt->execute([$slug]);
$unit = $stmt->fetch();
if (!$unit) {
    http_response_code(404);
    render('404', [], false);
    return;
}

$path = Unit::path((int) $unit['id']);
$metaTitle = $unit['name'] . ' · Media';
$metaDescription = 'Browse all media from ' . $unit['name'] . '.';
?>
<link rel="stylesheet" href="<?= asset('css/unit.css') ?>">

<div class="unit-page">
  <header class="unit-hero">
    <a class="unit-back" href="/">&larr; Home</a>
    <p class="unit-eyebrow"><?= e($unit['type']) ?></p>
    <h1 class="unit-name"><?= e($unit['name']) ?></h1>
    <nav class="unit-breadcrumb" aria-label="Breadcrumb">
      <?php foreach ($path as $i => $u): ?>
        <a href="/unit/<?= e($u['slug']) ?>"><?= e($u['name']) ?></a>
        <?php if ($i < count($path) - 1): ?><span>/</span><?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <div class="unit-controls">
      <button type="button" class="btn" id="unitShuffle">🔀 Shuffle: On</button>
      <span class="unit-count" id="unitCount"></span>
    </div>
  </header>
  <div class="unit-grid" id="unitGrid" data-slug="<?= e($unit['slug']) ?>">
    <div class="unit-loading">Loading media…</div>
  </div>
</div>

<script src="<?= asset('js/unit.js') ?>"></script>
