<?php
/**
 * Authentication helpers
 */
defined('APP_START') or die();

/**
 * Check if the user is logged in
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Get the current user's data from the database (cached in session on login)
 */
function getCurrentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }
    // Re-fetch from session cache set at login time
    return $_SESSION['user'] ?? null;
}

/**
 * Require login; redirect to login page if not authenticated
 */
function requireLogin(string $redirect = 'public/login.php'): void
{
    if (!isLoggedIn()) {
        redirect($redirect);
    }
}

/**
 * Require a specific role; redirect if role does not match
 */
function requireRole(string ...$roles): void
{
    requireLogin();
    $user = getCurrentUser();
    if (!$user || !in_array($user['role'], $roles, true)) {
        redirect('public/shop.php', 'You do not have permission to access this page.', 'danger');
    }
}

/**
 * Hash a password
 */
function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify a password against a hash
 */
function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Check password strength – must be ≥ 8 chars, at least one letter and one digit
 */
function validatePassword(string $password): array
{
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Za-z]/', $password)) {
        $errors[] = 'Password must contain at least one letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one digit.';
    }
    return $errors;
}

/**
 * Send a password-reset token and return the token string
 */
function requestPasswordReset(PDO $pdo, int $userId, string $email): ?string
{
    // Delete any existing unused tokens
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $stmt->execute([$userId]);

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $pdo->prepare(
        "INSERT INTO password_resets (user_id, email, token, expires_at, created_at) VALUES (?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$userId, $email, $token, $expires]);

    return $token;
}

/**
 * Verify a password-reset token and return the user_id, or false
 */
function verifyPasswordResetToken(PDO $pdo, string $token): int|false
{
    $stmt = $pdo->prepare(
        "SELECT user_id, expires_at FROM password_resets WHERE token = ? AND used_at IS NULL AND expires_at > NOW()"
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    return (int)$row['user_id'];
}

/**
 * Mark a password-reset token as used
 */
function markPasswordResetUsed(PDO $pdo, string $token): void
{
    $stmt = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE token = ?");
    $stmt->execute([$token]);
}
