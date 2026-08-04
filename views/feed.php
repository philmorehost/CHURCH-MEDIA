<?php
declare(strict_types=1);
$metaTitle = 'Feed';
$categories = Database::getInstance()->getConnection()
    ->query('SELECT c.slug, c.name FROM media_categories c WHERE EXISTS (SELECT 1 FROM media_post_categories mpc WHERE mpc.media_category_id = c.id) ORDER BY c.name ASC')
    ->fetchAll();
?>
<link rel="stylesheet" href="<?= asset('css/feed.css') ?>">

<div class="feed-page">
  <div class="feed-chip-row">
    <div class="chip-row" style="margin:0;">
      <button class="chip active" data-category="">All</button>
      <?php foreach ($categories as $cat): ?>
        <button class="chip" data-category="<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></button>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="feed-scroller" id="feedScroller" data-endpoint="/api/feed">
    <div class="feed-loading" id="feedLoading">Loading the feed…</div>
  </div>
</div>

<template id="feedSlideTemplate">
  <section class="feed-slide">
    <div class="feed-media"></div>
    <div class="feed-scrim"></div>
    <div class="feed-topbar">
      <span class="feed-type-badge"></span>
    </div>
    <div class="feed-caption">
      <div class="feed-author"></div>
      <div class="feed-text"></div>
    </div>
    <div class="feed-actions">
      <button class="feed-action feed-like" aria-label="Like">
        <span class="icon">♥</span>
        <span class="count like-count">0</span>
      </button>
      <div class="feed-action feed-views">
        <span class="icon">◉</span>
        <span class="count view-count">0</span>
      </div>
      <button class="feed-action feed-share" aria-label="Share">
        <span class="icon">↗</span>
        <span class="count">Share</span>
      </button>
    </div>
    <div class="feed-mute-hint">Tap to unmute</div>
  </section>
</template>

<script src="<?= asset('js/feed.js') ?>"></script>
