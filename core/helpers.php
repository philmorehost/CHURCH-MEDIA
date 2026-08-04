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
