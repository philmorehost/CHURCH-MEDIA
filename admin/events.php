<?php
declare(strict_types=1);

Auth::requireRole('admin', 'editor');
$pdo = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? 'list';
$id = (int) ($_GET['id'] ?? 0);
$errors = [];

function eventSlug(PDO $pdo, string $title, int $ignoreId = 0): string
{
    $base = slugify($title);
    $slug = $base;
    $i = 1;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM events WHERE slug = ? AND id != ?');
    while (true) {
        $stmt->execute([$slug, $ignoreId]);
        if (!$stmt->fetchColumn()) {
            return $slug;
        }
        $slug = $base . '-' . (++$i);
    }
}

if (in_array($action, ['create', 'edit'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $startAt = $_POST['start_at'] ?? '';
    $endAt = trim($_POST['end_at'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $rsvpEnabled = isset($_POST['rsvp_enabled']) ? 1 : 0;
    $rsvpUrl = trim($_POST['rsvp_url'] ?? '');
    $isPublished = isset($_POST['is_published']) ? 1 : 0;

    if ($title === '' || $startAt === '') {
        $errors[] = 'Title and start date/time are required.';
    } else {
        $coverPath = null;
        if (!empty($_FILES['cover_image']['tmp_name']) && is_uploaded_file($_FILES['cover_image']['tmp_name'])) {
            $filename = MediaProcessor::processImage($_FILES['cover_image']['tmp_name'], UPLOADS_WEBP_PATH);
            $coverPath = $filename ? 'webp/' . $filename : null;
        }

        if ($action === 'create') {
            $stmt = $pdo->prepare('INSERT INTO events (title, slug, description, cover_image, start_at, end_at, location, rsvp_enabled, rsvp_url, is_published) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$title, eventSlug($pdo, $title), $description, $coverPath, $startAt, $endAt ?: null, $location, $rsvpEnabled, $rsvpUrl ?: null, $isPublished]);
            flash('success', 'Event created.');
        } else {
            $sql = 'UPDATE events SET title=?, slug=?, description=?, start_at=?, end_at=?, location=?, rsvp_enabled=?, rsvp_url=?, is_published=?';
            $params = [$title, eventSlug($pdo, $title, $id), $description, $startAt, $endAt ?: null, $location, $rsvpEnabled, $rsvpUrl ?: null, $isPublished];
            if ($coverPath) {
                $sql .= ', cover_image=?';
                $params[] = $coverPath;
            }
            $sql .= ' WHERE id=?';
            $params[] = $id;
            $pdo->prepare($sql)->execute($params);
            flash('success', 'Event updated.');
        }
        redirect('/admin/events');
    }
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
    flash('success', 'Event deleted.');
    redirect('/admin/events');
}

$editing = null;
if ($action === 'edit') {
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$id]);
    $editing = $stmt->fetch();
    if (!$editing) {
        redirect('/admin/events');
    }
}

$events = $action === 'list' ? $pdo->query('SELECT * FROM events ORDER BY start_at DESC LIMIT 100')->fetchAll() : [];

$pageTitle = 'Events';
$activeNav = 'events';
require __DIR__ . '/partials/layout-open.php';
?>

<?php foreach ($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>

<?php if (in_array($action, ['create', 'edit'], true)): ?>
  <div class="card" style="max-width:640px;">
    <h2><?= $action === 'create' ? 'New Event' : 'Edit Event' ?></h2>
    <form method="post" action="/admin/events?action=<?= $action ?><?= $editing ? '&id=' . (int) $editing['id'] : '' ?>" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <label for="title">Title</label>
      <input type="text" id="title" name="title" value="<?= e($editing['title'] ?? '') ?>" required>
      <label for="description">Description</label>
      <textarea id="description" name="description"><?= e($editing['description'] ?? '') ?></textarea>
      <div class="row two">
        <div>
          <label for="start_at">Starts</label>
          <input type="datetime-local" id="start_at" name="start_at" value="<?= e($editing ? str_replace(' ', 'T', substr((string) $editing['start_at'], 0, 16)) : '') ?>" required>
        </div>
        <div>
          <label for="end_at">Ends (optional)</label>
          <input type="datetime-local" id="end_at" name="end_at" value="<?= e($editing && $editing['end_at'] ? str_replace(' ', 'T', substr((string) $editing['end_at'], 0, 16)) : '') ?>">
        </div>
      </div>
      <label for="location">Location</label>
      <input type="text" id="location" name="location" value="<?= e($editing['location'] ?? '') ?>" placeholder="Main Auditorium">
      <label for="cover_image">Cover Image</label>
      <input type="file" id="cover_image" name="cover_image" accept="image/*">
      <div class="checkbox-row">
        <input type="checkbox" id="rsvp_enabled" name="rsvp_enabled" <?= !empty($editing['rsvp_enabled']) ? 'checked' : '' ?>>
        <label for="rsvp_enabled" style="margin:0;">Enable RSVP link</label>
      </div>
      <label for="rsvp_url">RSVP URL</label>
      <input type="url" id="rsvp_url" name="rsvp_url" value="<?= e($editing['rsvp_url'] ?? '') ?>" placeholder="https://...">
      <div class="checkbox-row">
        <input type="checkbox" id="is_published" name="is_published" <?= $editing === null || !empty($editing['is_published']) ? 'checked' : '' ?>>
        <label for="is_published" style="margin:0;">Published</label>
      </div>
      <div class="btn-row">
        <button class="btn" type="submit"><?= $action === 'create' ? 'Create Event' : 'Save Changes' ?></button>
        <a class="btn secondary" href="/admin/events">Cancel</a>
      </div>
    </form>
  </div>
<?php else: ?>
  <div class="btn-row" style="margin-bottom:20px;"><a class="btn" href="/admin/events?action=create">+ New Event</a></div>
  <div class="card">
    <?php if (!$events): ?>
      <div class="empty">No events yet.</div>
    <?php else: ?>
      <table>
        <tr><th>Title</th><th>Starts</th><th>Location</th><th>RSVP</th><th>Status</th><th></th></tr>
        <?php foreach ($events as $ev): ?>
        <tr>
          <td><?= e($ev['title']) ?></td>
          <td><?= e(date('M j, Y g:i A', strtotime($ev['start_at']))) ?></td>
          <td><?= e($ev['location'] ?: '—') ?></td>
          <td><?= $ev['rsvp_enabled'] ? '<span class="badge ok">yes</span>' : '<span class="badge">no</span>' ?></td>
          <td><?= $ev['is_published'] ? '<span class="badge ok">published</span>' : '<span class="badge warn">draft</span>' ?></td>
          <td>
            <a class="btn secondary sm" href="/admin/events?action=edit&id=<?= (int) $ev['id'] ?>">Edit</a>
            <form method="post" action="/admin/events?action=delete" onsubmit="return confirm('Delete this event?');" style="display:inline;">
              <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
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
