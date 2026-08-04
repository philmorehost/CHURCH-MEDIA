<?php
declare(strict_types=1);

Auth::requireRole('admin', 'editor', 'media_team');
$pdo = Database::getInstance()->getConnection();
$user = Auth::user();
$action = $_GET['action'] ?? 'list';
$errors = [];

$categories = $pdo->query('SELECT * FROM media_categories ORDER BY name ASC')->fetchAll();

$allowedImageMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
$allowedVideoMime = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska', 'video/webm'];
$maxUploadBytes = 200 * 1024 * 1024; // 200MB per file — raise upload_max_filesize/post_max_size in php.ini to match

function mediaSlug(PDO $pdo, string $caption): string
{
    $base = slugify($caption !== '' ? mb_substr($caption, 0, 60) : 'post');
    $slug = $base;
    $i = 1;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM media_posts WHERE slug = ?');
    while (true) {
        $stmt->execute([$slug]);
        if (!$stmt->fetchColumn()) {
            return $slug;
        }
        $slug = $base . '-' . (++$i);
    }
}

if ($action === 'category_create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $name = trim($_POST['name'] ?? '');
    if ($name !== '') {
        $pdo->prepare('INSERT IGNORE INTO media_categories (name, slug) VALUES (?, ?)')->execute([$name, slugify($name)]);
        flash('success', 'Category added.');
    }
    redirect('/admin/media?action=create');
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $caption = trim($_POST['caption'] ?? '');
    $categoryIds = array_map('intval', $_POST['categories'] ?? []);
    $isPublished = isset($_POST['is_published']) ? 1 : 0;

    $files = $_FILES['media'] ?? null;
    $items = [];
    if ($files && is_array($files['tmp_name'])) {
        foreach ($files['tmp_name'] as $i => $tmpName) {
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                continue;
            }
            if ($files['size'][$i] > $maxUploadBytes) {
                $errors[] = $files['name'][$i] . ' is too large (max ' . round($maxUploadBytes / 1024 / 1024) . 'MB).';
                continue;
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
            if (in_array($mime, $allowedImageMime, true)) {
                $items[] = ['kind' => 'image', 'tmp' => $tmpName];
            } elseif (in_array($mime, $allowedVideoMime, true)) {
                $items[] = ['kind' => 'video', 'tmp' => $tmpName];
            } else {
                $errors[] = $files['name'][$i] . ' has an unsupported file type.';
            }
        }
    }

    if (!$items && !$errors) {
        $errors[] = 'Upload at least one photo or video.';
    }

    if (!$errors) {
        $hasVideo = (bool) array_filter($items, fn ($i) => $i['kind'] === 'video');
        $postType = $hasVideo ? 'vertical_reel' : (count($items) > 1 ? 'carousel' : 'single_image');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO media_posts (user_id, slug, caption, post_type, is_published) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$user['id'], mediaSlug($pdo, $caption), $caption, $postType, $isPublished]);
            $postId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare('INSERT INTO media_post_items (media_post_id, type, file_path, thumbnail_path, processing_status, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($items as $order => $item) {
                if ($item['kind'] === 'image') {
                    $filename = MediaProcessor::processImage($item['tmp'], UPLOADS_WEBP_PATH);
                    if (!$filename) {
                        throw new RuntimeException('Could not process an uploaded image.');
                    }
                    $itemStmt->execute([$postId, 'image', 'webp/' . $filename, null, 'ready', $order]);
                } else {
                    $result = MediaProcessor::processVideoToReel($item['tmp'], UPLOADS_REELS_PATH, UPLOADS_THUMBS_PATH);
                    $itemStmt->execute([
                        $postId, 'video', 'reels/' . $result['file'],
                        $result['thumbnail'] ? 'thumbs/' . $result['thumbnail'] : null,
                        $result['status'], $order,
                    ]);
                }
            }

            if ($categoryIds) {
                $catStmt = $pdo->prepare('INSERT IGNORE INTO media_post_categories (media_post_id, media_category_id) VALUES (?, ?)');
                foreach ($categoryIds as $catId) {
                    $catStmt->execute([$postId, $catId]);
                }
            }

            $pdo->commit();
            flash('success', 'Post published.');
            redirect('/admin/media');
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Failed to save the post: ' . $e->getMessage();
        }
    }
}

if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $id = (int) ($_POST['id'] ?? 0);
    $pdo->prepare('UPDATE media_posts SET is_published = NOT is_published WHERE id = ?')->execute([$id]);
    redirect('/admin/media');
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $id = (int) ($_POST['id'] ?? 0);
    $itemStmt = $pdo->prepare('SELECT type, file_path, thumbnail_path FROM media_post_items WHERE media_post_id = ?');
    $itemStmt->execute([$id]);
    foreach ($itemStmt->fetchAll() as $item) {
        @unlink(UPLOADS_PATH . '/' . $item['file_path']);
        if ($item['thumbnail_path']) {
            @unlink(UPLOADS_PATH . '/' . $item['thumbnail_path']);
        }
    }
    $pdo->prepare('DELETE FROM media_posts WHERE id = ?')->execute([$id]);
    flash('success', 'Post deleted.');
    redirect('/admin/media');
}

$posts = $action === 'list' ? $pdo->query('
    SELECT p.*, u.name AS author,
      (SELECT file_path FROM media_post_items WHERE media_post_id = p.id ORDER BY sort_order ASC LIMIT 1) AS cover,
      (SELECT thumbnail_path FROM media_post_items WHERE media_post_id = p.id ORDER BY sort_order ASC LIMIT 1) AS cover_thumb,
      (SELECT type FROM media_post_items WHERE media_post_id = p.id ORDER BY sort_order ASC LIMIT 1) AS cover_type
    FROM media_posts p JOIN users u ON u.id = p.user_id
    ORDER BY p.created_at DESC LIMIT 60
')->fetchAll() : [];

$pageTitle = $action === 'create' ? 'New Post' : 'Media & Reels';
$activeNav = 'media';
require __DIR__ . '/partials/layout-open.php';
?>

<?php foreach ($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>

<?php if ($action === 'create'): ?>

  <div class="card" style="max-width:720px;">
    <h2>Create a Post</h2>
    <p class="sub">Upload photos for a single/carousel post, or a video to auto-convert into a vertical 9:16 reel.</p>
    <form method="post" action="/admin/media?action=create" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <label for="media">Photos or Video</label>
      <input type="file" id="media" name="media[]" multiple accept="image/*,video/*" required>
      <label for="caption">Caption</label>
      <textarea id="caption" name="caption" placeholder="Write a caption…"><?= old('caption') ?></textarea>

      <label>Categories</label>
      <div class="row three" style="margin-bottom:15px;">
        <?php foreach ($categories as $cat): ?>
          <label style="font-weight:400;display:flex;align-items:center;gap:6px;margin-bottom:0;">
            <input type="checkbox" name="categories[]" value="<?= (int) $cat['id'] ?>" style="width:auto;margin:0;"> <?= e($cat['name']) ?>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="checkbox-row">
        <input type="checkbox" id="is_published" name="is_published" checked>
        <label for="is_published" style="margin:0;">Publish immediately</label>
      </div>

      <div class="btn-row">
        <button class="btn" type="submit">Publish Post</button>
        <a class="btn secondary" href="/admin/media">Cancel</a>
      </div>
    </form>
  </div>

  <div class="card" style="max-width:720px;">
    <h2>Add Category</h2>
    <form method="post" action="/admin/media?action=category_create" style="display:flex;gap:10px;align-items:flex-start;">
      <?= Csrf::field() ?>
      <div style="flex:1;"><input type="text" name="name" placeholder="e.g. Missions" required></div>
      <button class="btn secondary" type="submit">Add</button>
    </form>
  </div>

<?php else: ?>

  <div class="btn-row" style="margin-bottom:20px;">
    <a class="btn" href="/admin/media?action=create">+ New Post</a>
  </div>

  <div class="card">
    <?php if (!$posts): ?>
      <div class="empty">No posts yet. Create your first one above.</div>
    <?php else: ?>
      <table>
        <tr><th>Cover</th><th>Caption</th><th>Type</th><th>Status</th><th>Likes</th><th>Views</th><th>Posted</th><th></th></tr>
        <?php foreach ($posts as $p): ?>
        <tr>
          <td>
            <?php if ($p['cover']): ?>
              <img class="thumb" src="<?= e(uploadUrl($p['cover_type'] === 'video' ? $p['cover_thumb'] : $p['cover'])) ?>" alt="" onerror="this.style.visibility='hidden'">
            <?php else: ?><div class="thumb"></div><?php endif; ?>
          </td>
          <td><?= e(mb_strimwidth((string) $p['caption'], 0, 50, '…')) ?: '<em>No caption</em>' ?></td>
          <td><span class="badge info"><?= e(str_replace('_', ' ', $p['post_type'])) ?></span></td>
          <td>
            <form method="post" action="/admin/media?action=toggle" style="display:inline;">
              <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="badge <?= $p['is_published'] ? 'ok' : 'warn' ?>" style="border:none;cursor:pointer;">
                <?= $p['is_published'] ? 'published' : 'draft' ?>
              </button>
            </form>
          </td>
          <td><?= (int) $p['likes_count'] ?></td>
          <td><?= (int) $p['views_count'] ?></td>
          <td><?= e(timeAgo($p['created_at'])) ?></td>
          <td>
            <form method="post" action="/admin/media?action=delete" onsubmit="return confirm('Delete this post permanently?');" style="display:inline;">
              <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="btn danger sm">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/partials/layout-close.php'; ?>
