<?php
declare(strict_types=1);

/**
 * Bible API Handler
 * Handles requests for scripture and switches between providers based on admin settings.
 */

header('Content-Type: application/json');

// 1. Load Settings
$pdo = Database::getInstance()->getConnection();
$row = $pdo->query('SELECT * FROM settings ORDER BY id ASC LIMIT 1')->fetch();
$source = $row['bible_source'] ?? 'keyless';
$apiKey = $row['bible_api_key'] ?? null;

// 2. Get Parameters
$book = $_GET['book'] ?? '';
$chapter = $_GET['chapter'] ?? '';
$verse = $_GET['verse'] ?? ''; // Optional: for specific verse or whole chapter
$version = $_GET['version'] ?? 'KJV';
$lang = $_GET['lang'] ?? 'en';

if (!$book || !$chapter) {
    http_response_code(400);
    echo json_encode(['error' => 'Book and chapter are required.']);
    exit;
}

// 3. Fetch from Provider
if ($source === 'api_bible' && $apiKey) {
    // API.Bible Implementation
    // Note: API.Bible requires specific Bible IDs for versions.
    // Mapping for the free tier versions:
    $versionMapping = [
        'NIV'  => 'de4e12af bec3-4da3-8d3d-4e8e12afbec3', // Example IDs, would need dynamic lookup
        'NLT'  => '...', 
        'NKJV' => '...',
        'KJV'  => '...',
    ];
    
    $bibleId = $versionMapping[$version] ?? $versionMapping['KJV'];
    $url = "https://api.scripture.api.bible/v1/bibles/{$bibleId}/chapters/{$book}.{$chapter}";
    
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer {$apiKey}\r\n"
        ]
    ];
    
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch from API.Bible']);
        exit;
    }
    
    echo $response;
} else {
    // Key-less implementation (Bible-Api.com)
    // format: https://bible-api.com/john 3:16
    $query = "{$book} {$chapter}";
    if ($verse) {
        $query .= ":{$verse}";
    }
    
    $url = "https://bible-api.com/" . urlencode($query);
    if ($lang !== 'en') {
        $url .= "?translation=" . ($lang === 'es' ? 'rvr1960' : 'web'); 
    }
    $response = @file_get_contents($url);
    
    if ($response === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch from Bible-Api.com']);
        exit;
    }
    
    echo $response;
}
