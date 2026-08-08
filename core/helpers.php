<?php
declare(strict_types=1);

/**
 * Small stateless helpers shared across public views, admin views, and API
 * endpoints. Loaded once from bootstrap.php. Kept framework-free on purpose.
 */

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function clientIp(): string
{
    // Trust X-Forwarded-For only if a trusted proxy config says so; kept simple
    // (direct REMOTE_ADDR) since this ships without a known reverse-proxy setup.
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function baseUrl(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return '/assets/' . ltrim($path, '/') . '?v=' . ASSET_VERSION;
}

function uploadUrl(?string $path): ?string
{
    if (!$path) {
        return null;
    }
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    return baseUrl('uploads/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function slugify(string $text): string
{
    $text = trim($text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: bin2hex(random_bytes(4));
}

/** Lazily loads the single settings row and caches it for the request. */
function settings(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $defaults = require CONFIG_PATH . '/site.php';
    if (!defined('APP_IS_INSTALLED') || !APP_IS_INSTALLED) {
        return $cache = $defaults;
    }
    try {
        $row = Database::getInstance()->getConnection()
            ->query('SELECT * FROM settings ORDER BY id ASC LIMIT 1')
            ->fetch();
        $cache = $row ? array_merge($defaults, array_filter($row, fn ($v) => $v !== null)) : $defaults;
    } catch (Throwable) {
        $cache = $defaults;
    }
    return $cache;
}

function setting(string $key, mixed $default = null): mixed
{
    return settings()[$key] ?? $default;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function old(string $key, string $default = ''): string
{
    $value = $_SESSION['_old'][$key] ?? $default;
    return e((string) $value);
}

function keepOld(array $input): void
{
    $_SESSION['_old'] = $input;
}

function clearOld(): void
{
    unset($_SESSION['_old']);
}

/**
 * Renders a view file with $data extracted into scope, optionally inside the
 * site layout. The view runs *before* the layout's <head> is emitted (via an
 * output buffer) so a view can set $metaTitle/$metaDescription for its own
 * page — those locals are still in scope when layout-open.php requires next.
 */
function render(string $view, array $data = [], bool $layout = true): void
{
    extract($data, EXTR_SKIP);

    if (!$layout) {
        require VIEWS_PATH . '/' . $view . '.php';
        return;
    }

    ob_start();
    require VIEWS_PATH . '/' . $view . '.php';
    $content = ob_get_clean();

    require VIEWS_PATH . '/partials/layout-open.php';
    echo $content;
    require VIEWS_PATH . '/partials/layout-close.php';
}

function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'just now';
    }
    $units = [31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($units as $seconds => $label) {
        $count = intdiv($diff, $seconds);
        if ($count >= 1) {
            return $count . ' ' . $label . ($count > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

/** Converts a YouTube watch/share URL to an embeddable one; passes through anything else (Vimeo, already-embed links). */
function embedUrl(?string $url): ?string
{
    if (!$url) {
        return null;
    }
    if (preg_match('#youtu\.be/([a-zA-Z0-9_-]+)#', $url, $m) || preg_match('#youtube\.com/watch\?v=([a-zA-Z0-9_-]+)#', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    return $url;
}

/** Extracts a YouTube video id from watch/shorts/embed/live/share URLs, or null. */
function youtubeVideoId(?string $url): ?string
{
    if (!$url) {
        return null;
    }
    $patterns = [
        '#youtube\.com/watch\?[^&\s]*&?v=([a-zA-Z0-9_-]{6,})#',
        '#youtube\.com/(?:embed|shorts|live|v)/([a-zA-Z0-9_-]{6,})#',
        '#youtu\.be/([a-zA-Z0-9_-]{6,})#',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, trim($url), $m)) {
            return $m[1];
        }
    }
    return null;
}

/** Public thumbnail URL for a YouTube video id. */
function youtubeThumbnailUrl(?string $videoId): string
{
    return $videoId ? 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg' : '';
}

function formatCount(int $count): string
{
    if ($count >= 1000000) {
        return round($count / 1000000, 1) . 'M';
    }
    if ($count >= 1000) {
        return round($count / 1000, 1) . 'K';
    }
    return (string) $count;
}

/** Parses the "one option per line" textarea into a clean list (select/radio/checkbox). */
function formFieldOptions(array $field): array
{
    $options = array_filter(array_map('trim', explode("\n", (string) ($field['options'] ?? ''))));
    return array_values($options);
}

/** True when a form has an end date that has already passed (validity window closed). */
function formsExpired(array $form): bool
{
    if (empty($form['end_at'])) {
        return false;
    }
    return strtotime((string) $form['end_at']) <= time();
}

/** True when a form is currently accepting responses (active + not past its end date). */
function formsAccepting(array $form): bool
{
    return !empty($form['is_active']) && !formsExpired($form);
}

/** Stashes the raw POST payload so the public form can repopulate inputs after a validation error. */
function keepFormOld(array $input): void
{
    $_SESSION['_form_old'] = $input;
}

/** Returns the previously submitted value for a form input (string for scalar fields, array for checkbox). */
function formOld(string $key, mixed $default = ''): mixed
{
    $old = $_SESSION['_form_old'] ?? [];
    return array_key_exists($key, $old) ? $old[$key] : $default;
}

function clearFormOld(): void
{
    unset($_SESSION['_form_old']);
}

/** Normalizes PHP's $_FILES shape (single vs. multiple) into a flat per-key list of file arrays. */
function normalizeUploadedFiles(array $files): array
{
    $out = [];
    foreach ($files as $key => $file) {
        if (!is_array($file['name'] ?? null)) {
            $out[$key][] = $file;
            continue;
        }
        foreach ($file['name'] as $i => $_) {
            $out[$key][] = [
                'name' => $file['name'][$i],
                'type' => $file['type'][$i] ?? '',
                'tmp_name' => $file['tmp_name'][$i] ?? '',
                'error' => $file['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $file['size'][$i] ?? 0,
            ];
        }
    }
    return $out;
}

/**
 * Validates + compresses one uploaded image for a form field. Accepts any image
 * format (JPG/PNG/GIF/WebP/BMP/AVIF), auto-shrinks it, and returns the stored
 * relative path ('form-files/xxx') or null when the file isn't a usable image.
 * Throws RuntimeException for a recoverable violation (too large).
 */
function storeFormImageUpload(array $file): ?string
{
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    $maxBytes = 8 * 1024 * 1024;
    if ((int) ($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('Image "' . $file['name'] . '" is too large — max 8MB per file.');
    }
    $name = MediaProcessor::compressImage($file['tmp_name'], UPLOADS_FORM_PATH);
    if (!$name) {
        return null;
    }
    return 'form-files/' . $name;
}

/**
 * Conversion state of one media item row (media_post_items).
 * 'converted'  — a real 9:16 crop finished (converted_at set)
 * 'pending'    — uploaded original waiting to be processed (no crop yet)
 * 'original'   — plays the original as-is (crop unavailable/failed); never converted
 * 'youtube'    — a YouTube embed, no conversion involved
 * 'image'      — a photo, no conversion involved
 */
function videoConversionStatus(array $item): string
{
    if (($item['type'] ?? '') === 'image') {
        return 'image';
    }
    if (($item['source'] ?? '') === 'youtube') {
        return 'youtube';
    }
    if (!empty($item['converted_at'])) {
        return 'converted';
    }
    if (str_starts_with((string) ($item['file_path'] ?? ''), 'originals/')) {
        return 'pending';
    }
    return 'original';
}

/**
 * Renders a page's content sections into the public design templates. Each
 * section is one block in the JSON stored on `pages.content`:
 * hero / text / columns / image / quote / cta.
 */
function renderPageSections(array $sections): void
{
    foreach ($sections as $section) {
        if (!is_array($section)) {
            continue;
        }
        switch ($section['type'] ?? 'text') {
            case 'hero':
                $img = !empty($section['image']) ? uploadUrl((string) $section['image']) : null;
                echo '<section class="page-hero' . ($img ? ' has-img' : '') . '">';
                if ($img) {
                    echo '<img src="' . e($img) . '" alt="' . e((string) ($section['alt'] ?? '')) . '" loading="eager">';
                    echo '<div class="page-hero-shade"></div>';
                }
                echo '<div class="page-hero-inner">';
                if (!empty($section['eyebrow'])) {
                    echo '<span class="eyebrow">' . e((string) $section['eyebrow']) . '</span>';
                }
                if (!empty($section['title'])) {
                    echo '<h1>' . e((string) $section['title']) . '</h1>';
                }
                if (!empty($section['subtitle'])) {
                    echo '<p class="page-hero-sub">' . e((string) $section['subtitle']) . '</p>';
                }
                echo '</div></section>';
                break;

            case 'text':
                $center = ($section['align'] ?? '') === 'center' ? ' center' : '';
                echo '<section class="section page-text' . $center . '"><div class="container" style="max-width:780px;">';
                if (!empty($section['heading'])) {
                    echo '<h2 class="page-heading">' . e((string) $section['heading']) . '</h2>';
                }
                foreach (preg_split('/\n{2,}/', trim((string) ($section['body'] ?? ''))) ?: [] as $para) {
                    if (trim($para) !== '') {
                        echo '<p class="page-body">' . nl2br(e(trim($para))) . '</p>';
                    }
                }
                echo '</div></section>';
                break;

            case 'columns':
                $cols = array_values(array_filter($section['columns'] ?? [], 'is_array'));
                echo '<section class="section"><div class="container">';
                if (!empty($section['heading'])) {
                    echo '<div class="section-head"><span class="eyebrow">' . e((string) ($section['eyebrow'] ?? '')) . '</span><h2>' . e((string) $section['heading']) . '</h2></div>';
                }
                $n = min(4, max(1, count($cols)));
                echo '<div class="grid grid-' . $n . '">';
                foreach ($cols as $col) {
                    echo '<div class="glass-card" style="padding:26px;">';
                    if (!empty($col['heading'])) {
                        echo '<h3 style="margin:0 0 10px;">' . e((string) $col['heading']) . '</h3>';
                    }
                    if (!empty($col['body'])) {
                        echo '<p style="color:var(--ink-dim); margin:0;">' . nl2br(e((string) $col['body'])) . '</p>';
                    }
                    echo '</div>';
                }
                echo '</div></div></section>';
                break;

            case 'image':
                if (empty($section['image'])) {
                    break;
                }
                echo '<section class="section"><div class="container">';
                echo '<figure class="page-figure"><img src="' . e(uploadUrl((string) $section['image'])) . '" alt="' . e((string) ($section['alt'] ?? '')) . '" loading="lazy">';
                if (!empty($section['caption'])) {
                    echo '<figcaption>' . e((string) $section['caption']) . '</figcaption>';
                }
                echo '</figure></div></section>';
                break;

            case 'quote':
                if (empty($section['quote'])) {
                    break;
                }
                echo '<section class="section"><div class="container">';
                echo '<blockquote class="page-quote">';
                echo '<span class="q-mark">”</span><p>' . e((string) $section['quote']) . '</p>';
                if (!empty($section['source'])) {
                    echo '<footer>— ' . e((string) $section['source']) . '</footer>';
                }
                echo '</blockquote></div></section>';
                break;

            case 'cta':
                echo '<section class="section"><div class="container" style="text-align:center;">';
                if (!empty($section['title'])) {
                    echo '<h2 class="page-cta-title">' . e((string) $section['title']) . '</h2>';
                }
                if (!empty($section['subtitle'])) {
                    echo '<p class="page-cta-sub">' . e((string) $section['subtitle']) . '</p>';
                }
                if (!empty($section['label'])) {
                    $url = (string) ($section['url'] ?? '#');
                    if (!preg_match('#^https?://#', $url) && !str_starts_with($url, '/')) {
                        $url = '/' . $url;
                    }
                    echo '<div class="hero-actions" style="margin-top:24px;"><a class="btn btn-gold" href="' . e($url) . '">' . e((string) $section['label']) . '</a></div>';
                }
                echo '</div></section>';
                break;
        }
    }
}
