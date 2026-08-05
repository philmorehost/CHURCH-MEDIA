<?php
declare(strict_types=1);

/**
 * Single init chain shared by public/index.php (web requests) and
 * cli/media_worker.php (background job runner) — keeps config loading,
 * autoloading, and error handling identical in both contexts.
 */

require_once __DIR__ . '/config/paths.php';

$siteConfig = require CONFIG_PATH . '/site.php';
$isLocal = (getenv('APP_ENV') ?: ($siteConfig['app_env'] ?? 'production')) === 'local';

error_reporting(E_ALL);
ini_set('display_errors', $isLocal ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

date_default_timezone_set($siteConfig['timezone'] ?? 'UTC');

spl_autoload_register(function (string $class): void {
    // core/SecurityGuard.php <- SecurityGuard, etc. Flat namespace, one class per file.
    $path = CORE_PATH . '/' . $class . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

require_once CORE_PATH . '/helpers.php';

define('ASSET_VERSION', $isLocal ? (string) time() : '1.0.0');

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    if (!$isLocal) {
        header('Content-Security-Policy: default-src \'self\'; img-src \'self\' data: https:; media-src \'self\' https:; style-src \'self\' \'unsafe-inline\'; script-src \'self\'; frame-src https:; connect-src \'self\'');
    }
}

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !$isLocal,
    ]);
    session_start();
}

define('APP_IS_LOCAL', $isLocal);
// A valid install requires BOTH the lock file AND a reachable database using
// the persisted config. A lock file alone is not proof: config/database.php may
// have been copied from another environment (or credentials rotated), in which
// case a bare lock would let the app boot and then crash with a raw PDOException
// on the first page load. When the database can't be reached we treat this as a
// new environment — drop the stale lock so the installer runs fresh — and only
// fall back to the existing lock once the DB is genuinely reachable.
$lockExists = is_file(INSTALL_LOCK_FILE);
if ($lockExists && !Database::isReachable()) {
    @unlink(INSTALL_LOCK_FILE);
    $lockExists = false;
}
define('APP_IS_INSTALLED', $lockExists);

// Bring already-installed databases up to date with the latest schema
// (feature columns/tables added after first install). Stamped, idempotent.
if (APP_IS_INSTALLED) {
    Database::migrate();
}

// Site-wide IP/country gate — runs before any route handles the request.
// Fails open (logs and continues) if the DB isn't reachable, rather than
// taking the whole site down on a transient connection issue.
if (PHP_SAPI !== 'cli' && APP_IS_INSTALLED) {
    try {
        $guard = new SecurityGuard(Database::getInstance()->getConnection());
        $guard->inspectRequest(clientIp(), SecurityGuard::resolveCountryCode());
    } catch (Throwable $e) {
        error_log('SecurityGuard inspectRequest skipped: ' . $e->getMessage());
    }
}
