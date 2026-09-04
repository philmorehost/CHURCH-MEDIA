<?php
declare(strict_types=1);

/** GET /api/ads — Retrieves active sponsored ads for web & mobile app.
 *  POST /api/ads — Records impression or click tracking.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$fingerprint = Fingerprint::hash();

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $action = $data['action'] ?? 'impression';
    $adId = (int) ($data['ad_id'] ?? 0);

    if ($adId <= 0) {
        jsonResponse(['status' => 'error', 'message' => 'Invalid ad_id'], 400);
    }

    if ($action === 'click') {
        AdManager::recordClick($adId, $fingerprint, clientIp());
        jsonResponse(['status' => 'success', 'message' => 'Click recorded']);
    } else {
        AdManager::recordImpression($adId, $fingerprint, clientIp());
        jsonResponse(['status' => 'success', 'message' => 'Impression recorded']);
    }
}

// GET active sponsored ads
$activeAds = AdManager::getActiveAds();
$settings = AdManager::getSettings();
$skipTimerSeconds = (int) ($settings['skip_timer_seconds'] ?? 7);

$out = [];
foreach ($activeAds as $ad) {
    $out[] = [
        'id' => (int) $ad['id'],
        'title' => $ad['title'],
        'media_type' => $ad['media_type'],
        'media_url' => uploadUrl($ad['media_path']),
        'thumbnail_url' => uploadUrl($ad['thumbnail_path'] ?: $ad['media_path']),
        'target_url' => $ad['target_url'],
        'cta_label' => $ad['cta_label'],
        'skip_timer_seconds' => $skipTimerSeconds,
        'is_sponsored' => true,
    ];
}

jsonResponse(['status' => 'success', 'skip_timer_seconds' => $skipTimerSeconds, 'data' => $out]);
