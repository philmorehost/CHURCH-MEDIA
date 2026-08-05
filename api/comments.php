<?php
declare(strict_types=1);

/**
 * GET  /api/comments?post_id=5 — published comments for a post.
 * POST /api/comments {post_id, name?, message} — adds an anonymous comment.
 */

$pdo = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $postId = (int) ($_GET['post_id'] ?? 0);
    if ($postId <= 0) {
        jsonResponse(['status' => 'error', 'message' => 'post_id is required.'], 400);
    }
    $stmt = $pdo->prepare('SELECT id, name, message, created_at FROM post_comments WHERE media_post_id = ? AND is_published = 1 ORDER BY created_at ASC LIMIT 200');
    $stmt->execute([$postId]);
    $comments = array_map(function (array $c) {
        $c['id'] = (int) $c['id'];
        return $c;
    }, $stmt->fetchAll());
    jsonResponse(['status' => 'success', 'data' => $comments]);
}

if ($method === 'POST') {
    $fingerprint = Fingerprint::hash();
    if (!RateLimiter::attemptConfigured('comments', $fingerprint)) {
        jsonResponse(['status' => 'error', 'message' => 'Too many requests, slow down.'], 429);
    }

    $input = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $postId = (int) ($input['post_id'] ?? 0);
    $name = trim((string) ($input['name'] ?? ''));
    $message = trim((string) ($input['message'] ?? ''));

    if ($postId <= 0) {
        jsonResponse(['status' => 'error', 'message' => 'post_id is required.'], 400);
    }
    if ($message === '' || mb_strlen($message) > 1000) {
        jsonResponse(['status' => 'error', 'message' => 'Comment must be between 1 and 1000 characters.'], 422);
    }
    if ($name !== '' && mb_strlen($name) > 100) {
        jsonResponse(['status' => 'error', 'message' => 'Name is too long.'], 422);
    }

    $exists = $pdo->prepare('SELECT id FROM media_posts WHERE id = ? AND is_published = 1');
    $exists->execute([$postId]);
    if (!$exists->fetchColumn()) {
        jsonResponse(['status' => 'error', 'message' => 'Post not found.'], 404);
    }

    $stmt = $pdo->prepare('INSERT INTO post_comments (media_post_id, name, message, fingerprint_hash) VALUES (?, ?, ?, ?)');
    $stmt->execute([$postId, $name !== '' ? $name : null, $message, $fingerprint]);
    $commentId = (int) $pdo->lastInsertId();

    jsonResponse(['status' => 'success', 'data' => [
        'id' => $commentId,
        'name' => $name !== '' ? $name : null,
        'message' => $message,
        'created_at' => date('Y-m-d H:i:s'),
    ]]);
}

jsonResponse(['status' => 'error', 'message' => 'Method not allowed.'], 405);
