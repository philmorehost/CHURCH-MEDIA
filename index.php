<?php
declare(strict_types=1);

/**
 * Root entry point for web servers whose DocumentRoot points to the repository root.
 * Forwards execution to public/index.php.
 */

require_once __DIR__ . '/public/index.php';
