<?php
/**
 * Reset Password Page
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Reset Password';

$token = trim($_GET['token'] ?? '');
$error = '';
$validToken = false;
$userId = null;

if ($token) {
    $userId = verifyPasswordResetToken($pdo, $token);
    $validToken = $userId !== false;
} else {
    $error = 'Invalid reset link.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    require_once __DIR__ . '/../includes/csrf.php';
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    }
    $pwErrors = validatePassword($password);
    $errors = array_merge($pwErrors, [$error]);
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    $errors = array_filter($errors);

    if (empty($errors)) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
            $upd->execute([$hash, $userId]);
            markPasswordResetUsed($pdo, $token);
            logMessage('info', 'Password reset for user ID: ' . $userId);
            redirect('public/login.php', 'Password reset successful! You can now sign in.', 'success');
        } catch (PDOException $e) {
            logMessage('error', 'Password reset error: ' . $e->getMessage());
            $error = 'Could not reset password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password — <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
  <style>
    .login-wrap { min-height:100vh;display:flex;align-items:center;background:linear-gradient(135deg,#2563EB 0%,#7C3AED 100%); }
    .login-card { max-width:440px;margin:auto; }
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="container">
    <div class="login-card">
      <div class="card border-0 shadow-lg">
        <div class="card-body p-4">
          <div class="text-center mb-4">
            <h3 class="fw-bold text-primary"><i class="fas fa-store me-2"></i><?= e(APP_NAME) ?></h3>
            <p class="text-muted">Set your new password</p>
          </div>

          <?php if (!$validToken): ?>
            <div class="alert alert-danger">
              <i class="fas fa-exclamation-triangle me-2"></i>
              <?= e($error ?: 'Invalid or expired reset link.') ?>
            </div>
            <div class="text-center">
              <a href="<?= APP_URL ?>/public/forgot_password.php" class="btn btn-outline-primary btn-sm">
                Request a new link
              </a>
            </div>
          <?php else: ?>
            <?php if ($error): ?>
              <div class="alert alert-danger py-2"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="POST">
              <?= csrfField() ?>
              <input type="hidden" name="token" value="<?= e($token) ?>">
              <div class="mb-3">
                <label class="form-label">New Password</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-lock"></i></span>
                  <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-text">Min 8 chars, 1 letter, 1 digit.</div>
              </div>
              <div class="mb-4">
                <label class="form-label">Confirm Password</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-lock"></i></span>
                  <input type="password" name="confirm" class="form-control" required>
                </div>
              </div>
              <button type="submit" class="btn btn-brand w-100 btn-lg">
                <i class="fas fa-key me-2"></i>Reset Password
              </button>
            </form>
          <?php endif; ?>

          <div class="text-center mt-3">
            <a href="<?= APP_URL ?>/public/login.php" class="small">
              <i class="fas fa-arrow-left me-1"></i>Back to Login
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
