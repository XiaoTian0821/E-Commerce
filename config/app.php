<?php
/**
 * Application Configuration
 *
 * Centralised paths and constants so the app can be placed in any folder.
 */

// Prevent direct access
defined('APP_START') or define('APP_START', true);

// ── Base URL & Path ──────────────────────────────────────────────────────────
// Resolve the project root on disk using this file's location (bulletproof).
$projectRoot = str_replace('\\', '/', dirname(__DIR__));

// Resolve the web-accessible URL prefix.
// Strategy: use DOCUMENT_ROOT when available (real web request), fall back to
// SCRIPT_NAME, and for CLI test with a known base path.
$documentRoot    = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$scriptFilename  = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
$scriptDir       = dirname($scriptFilename);

if ($documentRoot !== '' && str_starts_with($projectRoot, $documentRoot)) {
    // Project sits inside (or at) the document root — compute relative prefix.
    $relative = ltrim(substr($projectRoot, strlen($documentRoot)), '/');
    $urlPrefix = $relative === '' ? '' : '/' . $relative;
} elseif ($scriptDir === $projectRoot) {
    // SCRIPT_FILENAME happens to sit exactly at the project root (rare but safe).
    $urlPrefix = '';
} else {
    // Fallback: guess from SCRIPT_NAME.  e.g. /E-Commerce/index.php → /E-Commerce.
    $urlPrefix = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    if ($urlPrefix === '.') $urlPrefix = '';
}

define('APP_URL',    'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $urlPrefix);
define('APP_PATH',   $projectRoot);
define('UPLOAD_PATH', $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);

// ── App Settings ──────────────────────────────────────────────────────────────
define('APP_NAME',     'NovaMart');
define('APP_TAGLINE',  'Your One-Stop Online Marketplace');
define('ITEMS_PER_PAGE', 12);
define('ADMIN_ITEMS_PER_PAGE', 20);

// ── Session ───────────────────────────────────────────────────────────────────
@ini_set('session.cookie_httponly', 1);
@ini_set('session.use_only_cookies', 1);
@ini_set('session.cookie_secure', 0);        // Set to 1 on HTTPS in production
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Misc ──────────────────────────────────────────────────────────────────────
define('TIMEZONE', 'UTC');
date_default_timezone_set(TIMEZONE);

define('LOG_PATH', $projectRoot . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR);
if (!is_dir(LOG_PATH)) {
    @mkdir(LOG_PATH, 0755, true);
}
