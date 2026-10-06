<?php
/**
 * Forgot Password Page
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail_service.php';

$pageTitle = 'Forgot Password';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/csrf.php';
    $email = trim($_POST['email'] ?? '');
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } elseif (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email=? AND status='active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $token = requestPasswordReset($pdo, $user['id'], $user['email']);
            // Try to send email (silent if SMTP not configured)
            $mailService = new MailService();
            $resetLink = APP_URL . '/public/reset_password.php?token=' . urlencode($token);
            $subject = 'Password Reset — ' . APP_NAME;
            $body = "Hello {$user['name']},\n\n"
                  . "Click the link below to reset your password:\n"
                  . $resetLink . "\n\n"
                  . "This link expires in 1 hour.\n\n"
                  . "If you didn't request this, ignore this email.";
            $mailService->send($user['email'], $subject, $body);
            logMessage('info', 'Password reset requested for: ' . $email);
            $success = true;
        } else {
            // Don't reveal whether the email exists
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password — <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
  <style>
    .login-wrap { min-height:100vh;display:flex;align-items:center;background:linear-gradient(135deg,#2563EB 0%,#7C3AED 100%); }
    .login-card { max-width:460px;margin:auto; }
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="container">
    <div class="login-card">
      <div class="card border-0 shadow-lg">
        <div class="card-body p-4">
          <div class="text-center mb-4">
            <a href="<?= APP_URL ?>/index.php" class="text-decoration-none">
              <h3 class="fw-bold text-primary"><i class="fas fa-store me-2"></i><?= e(APP_NAME) ?></h3>
            </a>
            <p class="text-muted">Reset your password</p>
          </div>

          <?php if ($success): ?>
            <div class="alert alert-success">
              <i class="fas fa-check-circle me-2"></i>
              If an account exists with that email, a reset link has been sent.
              Check your inbox and spam folder.
            </div>
            <div class="text-center">
              <a href="<?= APP_URL ?>/public/login.php" class="small">
                <i class="fas fa-arrow-left me-1"></i>Back to Login
              </a>
            </div>
          <?php else: ?>
            <?php if ($error): ?>
              <div class="alert alert-danger py-2"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="POST">
              <?= csrfField() ?>
              <div class="mb-4">
                <label class="form-label">Email Address</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                  <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                </div>
              </div>
              <button type="submit" class="btn btn-brand w-100 btn-lg">
                <i class="fas fa-paper-plane me-2"></i>Send Reset Link
              </button>
            </form>
            <div class="text-center mt-3">
              <a href="<?= APP_URL ?>/public/login.php" class="small">
                <i class="fas fa-arrow-left me-1"></i>Back to Login
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
