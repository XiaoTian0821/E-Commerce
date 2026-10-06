<?php
/**
 * Common helper functions
 */
defined('APP_START') or die();

/**
 * Escape output for safe HTML rendering
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Redirect to a URL with a flash message
 */
function redirect(string $url, ?string $message = null, string $type = 'success'): void
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }
    header('Location: ' . APP_URL . '/' . ltrim($url, '/'));
    exit;
}

/**
 * Get and clear the flash message
 */
function getFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Generate CSRF token and store in session
 */
function generateCSRF(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token from request
 */
function verifyCSRF(string $token): bool
{
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function csrf_verify(string $token): bool
{
    return verifyCSRF($token);
}

/**
 * Verify CSRF or redirect with error
 */
function csrf_verify_or_fail(string $redirect = null): void
{
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        redirect($redirect ?? 'public/shop.php', 'Invalid security token. Please try again.', 'danger');
    }
}

/**
 * Return CSRF input field HTML
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(generateCSRF()) . '">';
}

/**
 * Log an message to the application log
 */
function logMessage(string $level, string $message): void
{
    $line = sprintf(
        "[%s] [%s] %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message
    );
    @file_put_contents(LOG_PATH . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
}

/**
 * Upload an image file safely.
 * Returns the relative path (uploads/products/filename.ext) or false on failure.
 */
function uploadImage(array $file, string $subFolder): string|false
{
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    $allowedExts  = ['jpg', 'jpeg', 'png', 'webp'];
    $maxSize      = 5 * 1024 * 1024; // 5 MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    if ($file['size'] > $maxSize) {
        return false;
    }

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowedMimes, true)) {
        return false;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        return false;
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destDir  = UPLOAD_PATH . $subFolder . '/';
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $destDir . $filename)) {
        return 'uploads/' . $subFolder . '/' . $filename;
    }
    return false;
}

/**
 * Slugify a string
 */
function slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\-]/', '', $text);
    $text = preg_replace('/-+/', '-', $text);
    return $text ?: 'item-' . time();
}

/**
 * Format currency
 */
function formatCurrency(float $amount, ?string $currency = null): string
{
    $currency ??= defined('PAYPAL_CURRENCY') ? PAYPAL_CURRENCY : 'USD';
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Generate a unique order number
 */
function generateOrderNumber(): string
{
    $date = date('Ymd');
    $seq  = str_pad((int)date('u') % 10000, 4, '0', STR_PAD_LEFT);
    return 'ORD-' . $date . '-' . $seq;
}

/**
 * Get the current page URI path (without query string)
 */
function currentPage(): string
{
    return strtok($_SERVER['REQUEST_URI'], '?');
}
