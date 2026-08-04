<?php
declare(strict_types=1);

/** GET /api/feed?page=1&per_page=10&category=worship — paginated Reels-style feed for web + apps. */

if (!RateLimiter::attemptConfigured('feed', Fingerprint::hash())) {
    jsonResponse(['status' => 'error', 'message' => 'Too many requests, slow down.'], 429);
}

$pdo = Database::getInstance()->getConnection();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(30, max(1, (int) ($_GET['per_page'] ?? 10)));
$offset = ($page - 1) * $perPage;
$categorySlug = trim((string) ($_GET['category'] ?? ''));

$where = 'p.is_published = 1';
$params = [];
if ($categorySlug !== '') {
    $where .= ' AND EXISTS (SELECT 1 FROM media_post_categories mpc JOIN media_categories c ON c.id = mpc.media_category_id WHERE mpc.media_post_id = p.id AND c.slug = :slug)';
    $params['slug'] = $categorySlug;
}

$stmt = $pdo->prepare("
    SELECT p.id, p.slug, p.caption, p.post_type, p.likes_count, p.views_count, p.created_at, u.name AS author_name
    FROM media_posts p JOIN users u ON u.id = p.user_id
    WHERE $where
    ORDER BY p.created_at DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue('limit', $perPage + 1, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

$hasMore = count($posts) > $perPage;
$posts = array_slice($posts, 0, $perPage);

$fingerprint = Fingerprint::hash();
$itemStmt = $pdo->prepare('SELECT type, file_path, thumbnail_path, alt_text, processing_status FROM media_post_items WHERE media_post_id = ? ORDER BY sort_order ASC');
$catStmt = $pdo->prepare('SELECT c.id, c.name, c.slug FROM media_categories c JOIN media_post_categories mpc ON mpc.media_category_id = c.id WHERE mpc.media_post_id = ?');
$likedStmt = $pdo->prepare('SELECT 1 FROM post_likes WHERE media_post_id = ? AND fingerprint_hash = ?');

foreach ($posts as &$post) {
    $itemStmt->execute([$post['id']]);
    $post['media_items'] = array_map(function ($item) {
        $item['file_url'] = uploadUrl($item['file_path']);
        $item['thumbnail_url'] = uploadUrl($item['thumbnail_path']);
        unset($item['file_path'], $item['thumbnail_path']);
        return $item;
    }, $itemStmt->fetchAll());

    $catStmt->execute([$post['id']]);
    $post['categories'] = $catStmt->fetchAll();

    $likedStmt->execute([$post['id'], $fingerprint]);
    $post['liked_by_viewer'] = (bool) $likedStmt->fetchColumn();

    $post['id'] = (int) $post['id'];
    $post['likes_count'] = (int) $post['likes_count'];
    $post['views_count'] = (int) $post['views_count'];
}
unset($post);

jsonResponse(['status' => 'success', 'page' => $page, 'has_more' => $hasMore, 'data' => $posts]);
