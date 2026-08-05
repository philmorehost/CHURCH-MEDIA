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

// media.php is require()d inside Router::dispatchFlatFile(), so its top-level
// variables live in method scope — NOT the global scope. Functions in this file
// cannot read them via `global`; expose them as constants instead.
define('MEDIA_ALLOWED_IMAGE_MIME', $allowedImageMime);
define('MEDIA_ALLOWED_VIDEO_MIME', $allowedVideoMime);
define('MEDIA_MAX_UPLOAD_BYTES', $maxUploadBytes);

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

/** Normalizes $_FILES['media'] into ['kind'=>'image'|'video', 'tmp'=>..., 'ext'=>...] items, collecting any errors. */
function collectMediaFiles(?array $files, array &$errors): array
{
    $items = [];
    if (!$files || !is_array($files['tmp_name'])) {
        return $items;
    }
    $videoExt = [
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi',
        'video/x-matroska' => 'mkv',
        'video/webm' => 'webm',
    ];
    foreach ($files['tmp_name'] as $i => $tmpName) {
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            continue;
        }
        $name = $files['name'][$i] ?? 'file';
        if ((int) $files['size'][$i] > MEDIA_MAX_UPLOAD_BYTES) {
            $errors[] = $name . ' is too large (max ' . round(MEDIA_MAX_UPLOAD_BYTES / 1024 / 1024) . 'MB).';
            continue;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
        if (in_array($mime, MEDIA_ALLOWED_IMAGE_MIME, true)) {
            $items[] = ['kind' => 'image', 'tmp' => $tmpName];
        } elseif (in_array($mime, MEDIA_ALLOWED_VIDEO_MIME, true)) {
            $items[] = ['kind' => 'video', 'tmp' => $tmpName, 'ext' => $videoExt[$mime] ?? 'mp4'];
        } else {
            $errors[] = $name . ' has an unsupported file type.';
        }
    }
    return $items;
}

/**
 * Inserts media_post_items for a post. Uploaded videos are stored as their
 * original file and marked 'ready' so they play in the feed immediately —
 * uploads are never blocked on FFmpeg. If FFmpeg is available later, the
 * returned ids are queued for a background 9:16 crop via action=process.
 */
function storeMediaItems(PDO $pdo, int $postId, array $items, ?array $covers): array
{
    $itemStmt = $pdo->prepare('INSERT INTO media_post_items (media_post_id, type, source, file_path, thumbnail_path, processing_status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $convertible = [];

    foreach ($items as $order => $item) {
        $coverTmp = null;
        if ($covers && !empty($covers['tmp_name'][$order]) && is_uploaded_file($covers['tmp_name'][$order])) {
            $coverTmp = $covers['tmp_name'][$order];
        }
        // XHR flow sends a per-index cover as cover_<order> (client-captured frame).
        if (!empty($_FILES['cover_' . $order]['tmp_name']) && is_uploaded_file($_FILES['cover_' . $order]['tmp_name'])) {
            $coverTmp = $_FILES['cover_' . $order]['tmp_name'];
        }

        if ($item['kind'] === 'image') {
            $filename = MediaProcessor::processImage($item['tmp'], UPLOADS_WEBP_PATH);
            if (!$filename) {
                throw new RuntimeException('Could not process an uploaded image.');
            }
            $itemStmt->execute([$postId, 'image', 'upload', 'webp/' . $filename, null, 'ready', $order]);
        } elseif ($item['kind'] === 'youtube') {
            $thumb = $item['thumb'];
            if ($coverTmp) {
                $coverFile = MediaProcessor::processImage($coverTmp, UPLOADS_THUMBS_PATH, 82);
                if ($coverFile) {
                    $thumb = 'thumbs/' . $coverFile;
                }
            }
            $itemStmt->execute([$postId, 'video', 'youtube', $item['url'], $thumb, 'ready', $order]);
        } else { // video upload — keep the original so it is ready to play right away
            if (!is_dir(UPLOADS_ORIGINALS_PATH)) {
                mkdir(UPLOADS_ORIGINALS_PATH, 0775, true);
            }
            $origName = uniqid('orig_', true) . '.' . ($item['ext'] ?? 'mp4');
            if (!move_uploaded_file($item['tmp'], UPLOADS_ORIGINALS_PATH . '/' . $origName)) {
                throw new RuntimeException('Could not save the uploaded video.');
            }
            $thumb = null;
            if ($coverTmp) {
                $coverFile = MediaProcessor::processImage($coverTmp, UPLOADS_THUMBS_PATH, 82);
                if ($coverFile) {
                    $thumb = 'thumbs/' . $coverFile;
                }
            }
            $itemStmt->execute([$postId, 'video', 'upload', 'originals/' . $origName, $thumb, 'ready', $order]);
            $convertible[] = (int) $pdo->lastInsertId();
        }
    }
    return $convertible;
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

/** Shared by both the classic form POST and the instant XHR upload. */
function handleCreatePost(PDO $pdo, array $user): array
{
    $caption = trim($_POST['caption'] ?? '');
    $categoryIds = array_map('intval', $_POST['categories'] ?? []);
    $isPublished = isset($_POST['is_published']) ? 1 : 0;
    $errors = [];
    $youtubeUrl = trim((string) ($_POST['youtube_url'] ?? ''));
    $videoId = $youtubeUrl !== '' ? youtubeVideoId($youtubeUrl) : null;

    if ($videoId) {
        $youtubeCover = null;
        if (!empty($_FILES['youtube_cover']['tmp_name']) && is_uploaded_file($_FILES['youtube_cover']['tmp_name'])) {
            $youtubeCover = $_FILES['youtube_cover']['tmp_name'];
        }
        $thumb = youtubeThumbnailUrl($videoId);
        if ($youtubeCover) {
            $coverFile = MediaProcessor::processImage($youtubeCover, UPLOADS_THUMBS_PATH, 82);
            if ($coverFile) {
                $thumb = 'thumbs/' . $coverFile;
            }
        }
        $items = [['kind' => 'youtube', 'url' => 'https://www.youtube.com/embed/' . $videoId, 'thumb' => $thumb]];
        $covers = null;
    } else {
        $items = collectMediaFiles($_FILES['media'] ?? null, $errors);
        $covers = $_FILES['cover'] ?? null;
        if ($youtubeUrl !== '' && !$items) {
            $errors[] = "That YouTube link doesn't look valid.";
        }
    }

    if (!$items && !$errors) {
        $errors[] = 'Upload at least one photo or video, or paste a YouTube link.';
    }
    if ($errors) {
        return ['errors' => $errors];
    }

    $pdo->beginTransaction();
    try {
        $hasVideo = (bool) array_filter($items, fn ($i) => $i['kind'] !== 'image');
        $postType = $hasVideo ? 'vertical_reel' : (count($items) > 1 ? 'carousel' : 'single_image');

        $stmt = $pdo->prepare('INSERT INTO media_posts (user_id, slug, caption, post_type, is_published) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], mediaSlug($pdo, $caption), $caption, $postType, $isPublished]);
        $postId = (int) $pdo->lastInsertId();

        $pending = storeMediaItems($pdo, $postId, $items, $covers);

        if ($categoryIds) {
            $catStmt = $pdo->prepare('INSERT IGNORE INTO media_post_categories (media_post_id, media_category_id) VALUES (?, ?)');
            foreach ($categoryIds as $catId) {
                $catStmt->execute([$postId, $catId]);
            }
        }

        $pdo->commit();
        return ['post_id' => $postId, 'pending' => $pending, 'errors' => []];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['errors' => ['Failed to save the post: ' . $e->getMessage()]];
    }
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $result = handleCreatePost($pdo, $user);
    if ($result['errors']) {
        $errors = $result['errors'];
        keepOld(['caption' => trim($_POST['caption'] ?? '')]);
    } else {
        flash('success', 'Post published.');
        redirect('/admin/media');
    }
}

/** Instant XHR upload — saves originals immediately, converts in the background. */
if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $result = handleCreatePost($pdo, $user);
    if ($result['errors']) {
        jsonResponse(['status' => 'error', 'message' => implode(' ', $result['errors'])], 422);
    }
    jsonResponse(['status' => 'success', 'post_id' => $result['post_id'], 'pending' => $result['pending']]);
}

/** Background conversion of a pending video item (called via XHR/beacon after upload). */
if ($action === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['status' => 'error', 'message' => 'Missing item id.'], 400);
    }
    set_time_limit(600);
    @ignore_user_abort(true);
    $status = MediaProcessor::convertOriginalVideo($pdo, $id);
    jsonResponse(['status' => $status === 'ready' ? 'success' : 'error', 'processing_status' => $status]);
}

/** Reprocess every pending video on a post (from the list). */
if ($action === 'reprocess' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $id = (int) ($_POST['id'] ?? 0);
    set_time_limit(600);
    $stmt = $pdo->prepare("SELECT id FROM media_post_items WHERE media_post_id = ? AND type = 'video' AND source = 'upload' AND file_path LIKE 'originals/%'");
    $stmt->execute([$id]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $done = 0;
    foreach ($ids as $itemId) {
        if (MediaProcessor::convertOriginalVideo($pdo, (int) $itemId) === 'ready') {
            $done++;
        }
    }
    flash('success', $done > 0 ? "$done video(s) converted to reels." : 'Nothing pending to process.');
    redirect('/admin/media');
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
        if (str_starts_with((string) $item['file_path'], 'http')) {
            continue;
        }
        @unlink(UPLOADS_PATH . '/' . $item['file_path']);
        if ($item['thumbnail_path'] && !str_starts_with((string) $item['thumbnail_path'], 'http')) {
            @unlink(UPLOADS_PATH . '/' . $item['thumbnail_path']);
        }
    }
    $pdo->prepare('DELETE FROM media_posts WHERE id = ?')->execute([$id]);
    flash('success', 'Post deleted.');
    redirect('/admin/media');
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $id = (int) ($_POST['id'] ?? 0);
    $caption = trim($_POST['caption'] ?? '');
    $isPublished = isset($_POST['is_published']) ? 1 : 0;
    $categoryIds = array_map('intval', $_POST['categories'] ?? []);
    $pdo->prepare('UPDATE media_posts SET caption = ?, is_published = ? WHERE id = ?')->execute([$caption, $isPublished, $id]);
    $pdo->prepare('DELETE FROM media_post_categories WHERE media_post_id = ?')->execute([$id]);
    if ($categoryIds) {
        $catStmt = $pdo->prepare('INSERT IGNORE INTO media_post_categories (media_post_id, media_category_id) VALUES (?, ?)');
        foreach ($categoryIds as $catId) {
            $catStmt->execute([$id, $catId]);
        }
    }
    flash('success', 'Post updated.');
    redirect('/admin/media');
}

$editPost = null;
if ($action === 'edit') {
    $editPost = $pdo->prepare('SELECT * FROM media_posts WHERE id = ?');
    $editPost->execute([(int) ($_GET['id'] ?? 0)]);
    $editPost = $editPost->fetch() ?: null;
}

$posts = $action === 'list' ? $pdo->query('
    SELECT p.*, u.name AS author,
      (SELECT file_path FROM media_post_items WHERE media_post_id = p.id ORDER BY sort_order ASC LIMIT 1) AS cover,
      (SELECT thumbnail_path FROM media_post_items WHERE media_post_id = p.id ORDER BY sort_order ASC LIMIT 1) AS cover_thumb,
      (SELECT type FROM media_post_items WHERE media_post_id = p.id ORDER BY sort_order ASC LIMIT 1) AS cover_type,
      (SELECT IF(source = \'youtube\', thumbnail_path, NULL) FROM media_post_items WHERE media_post_id = p.id ORDER BY sort_order ASC LIMIT 1) AS cover_source,
      (SELECT COUNT(*) FROM media_post_items WHERE media_post_id = p.id AND processing_status = \'pending\') AS pending_count
    FROM media_posts p JOIN users u ON u.id = p.user_id
    ORDER BY p.created_at DESC LIMIT 60
')->fetchAll() : [];

$pageTitle = $action === 'create' ? 'New Post' : ($action === 'edit' ? 'Edit Post' : 'Media & Reels');
$activeNav = 'media';
require __DIR__ . '/partials/layout-open.php';
?>

<?php foreach ($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>
<?php if (isset($_GET['processed'])): ?><div class="alert success">Post published — videos are converting in the background.</div><?php endif; ?>

<?php if ($action === 'create'): ?>

  <link rel="stylesheet" href="<?= asset('css/admin-media.css') ?>">

  <div class="card composer">
    <h2>Create a Post</h2>
    <p class="sub">Paste a YouTube link, or upload photos/videos. Video covers are captured automatically from the first frame — you can override them.</p>

    <form id="mediaForm" method="post" action="/admin/media?action=create" enctype="multipart/form-data">
      <?= Csrf::field() ?>

      <div class="media-zone">
        <label for="media">Upload photos or video</label>
        <input type="file" id="media" name="media[]" multiple accept="image/*,video/*">
        <div class="media-preview" id="mediaPreview"></div>
        <p class="hint">Video covers are auto-captured as the default — tap a preview to replace it.</p>
      </div>

      <div class="media-zone">
        <label for="youtube_url">Or use a YouTube link <small style="font-weight:400;color:var(--ink-dim);">(a link is used instead of uploads)</small></label>
        <input type="url" id="youtube_url" name="youtube_url" placeholder="https://www.youtube.com/watch?v=… or a Shorts link">
        <div class="yt-preview" id="ytPreview"></div>
        <label for="youtube_cover">Cover image <small style="font-weight:400;color:var(--ink-dim);">(optional — defaults to the video's own thumbnail)</small></label>
        <input type="file" id="youtube_cover" name="youtube_cover" accept="image/*">
        <div class="yt-cover-preview" id="ytCoverPreview"></div>
      </div>

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

      <div class="progress-wrap" id="progressWrap" hidden>
        <div class="progress-track"><div class="progress-bar" id="progressBar"></div></div>
        <div class="progress-label" id="progressLabel">Saving…</div>
      </div>

      <div class="btn-row">
        <button class="btn" type="submit" id="publishBtn">Publish Post</button>
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

<?php elseif ($action === 'edit' && $editPost): ?>

  <div class="card" style="max-width:720px;">
    <h2>Edit Post #<?= (int) $editPost['id'] ?></h2>
    <form method="post" action="/admin/media?action=edit">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="<?= (int) $editPost['id'] ?>">
      <label for="edit_caption">Caption</label>
      <textarea id="edit_caption" name="caption" placeholder="Write a caption…"><?= e((string) $editPost['caption']) ?></textarea>

      <label>Categories</label>
      <div class="row three" style="margin-bottom:15px;">
        <?php
        $currentCats = $pdo->prepare('SELECT media_category_id FROM media_post_categories WHERE media_post_id = ?');
        $currentCats->execute([(int) $editPost['id']]);
        $selected = array_map('intval', $currentCats->fetchAll(PDO::FETCH_COLUMN));
        foreach ($categories as $cat):
        ?>
          <label style="font-weight:400;display:flex;align-items:center;gap:6px;margin-bottom:0;">
            <input type="checkbox" name="categories[]" value="<?= (int) $cat['id'] ?>" <?= in_array((int) $cat['id'], $selected, true) ? 'checked' : '' ?> style="width:auto;margin:0;"> <?= e($cat['name']) ?>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="checkbox-row">
        <input type="checkbox" id="is_published_edit" name="is_published" <?= $editPost['is_published'] ? 'checked' : '' ?>>
        <label for="is_published_edit" style="margin:0;">Publish</label>
      </div>

      <div class="btn-row">
        <button class="btn" type="submit">Save Changes</button>
        <a class="btn secondary" href="/admin/media">Cancel</a>
      </div>
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
        <tr><th>Cover</th><th>Caption</th><th>Type</th><th>Status</th><th>Likes</th><th>Views</th><th>Saves</th><th>Posted</th><th></th></tr>
        <?php foreach ($posts as $p): ?>
        <tr>
          <td>
            <?php
            $coverSrc = null;
            if ($p['cover']) {
                if ($p['cover_type'] === 'video') {
                    $coverSrc = $p['cover_source'] ?: $p['cover_thumb'];
                } else {
                    $coverSrc = $p['cover'];
                }
            }
            ?>
            <?php if ($coverSrc): ?>
              <img class="thumb" src="<?= e(uploadUrl($coverSrc)) ?>" alt="" onerror="this.style.visibility='hidden'">
            <?php else: ?><div class="thumb"></div><?php endif; ?>
          </td>
          <td><?= e(mb_strimwidth((string) $p['caption'], 0, 50, '…')) ?: '<em>No caption</em>' ?></td>
          <td>
            <span class="badge info"><?= e(str_replace('_', ' ', $p['post_type'])) ?></span>
            <?php if ((int) $p['pending_count'] > 0): ?>
              <span class="badge warn" title="Waiting for background conversion"><?= (int) $p['pending_count'] ?> pending</span>
            <?php endif; ?>
          </td>
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
          <td><?= (int) $p['saves_count'] ?></td>
          <td><?= e(timeAgo($p['created_at'])) ?></td>
          <td style="white-space:nowrap;">
            <?php if ((int) $p['pending_count'] > 0): ?>
              <form method="post" action="/admin/media?action=reprocess" style="display:inline;" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='…';">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button type="submit" class="btn sm">Process</button>
              </form>
            <?php endif; ?>
            <a class="btn sm secondary" href="/admin/media?action=edit&id=<?= (int) $p['id'] ?>">Edit</a>
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

<?php if ($action === 'create'): ?>
  <script src="<?= asset('js/admin-media.js') ?>"></script>
<?php endif; ?>

<?php require __DIR__ . '/partials/layout-close.php'; ?>
