<?php
/**
 * Customer Login Page
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Login';

// Already logged in? Redirect
if (isLoggedIn()) {
    $role = $_SESSION['user']['role'];
    if ($role === 'admin') redirect('admin/dashboard.php');
    if ($role === 'seller') redirect('seller/dashboard.php');
    redirect('public/shop.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/csrf.php';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } elseif (!$email || !$password) {
        $errors[] = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email, password, role, status FROM users WHERE email=?");
        $stmt->execute([ $email]);
        $user = $stmt->fetch();
        if (!$user) {
            $errors[] = 'Invalid email or password.';
        } elseif ($user['status'] === 'suspended') {
            $errors[] = 'Your account has been suspended. Contact support.';
        } elseif (!password_verify($password, $user['password'])) {
            $errors[] = 'Invalid email or password.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['user']     = [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ];
            if ($remember) {
                $dur = 30 * 86400;
                setcookie(session_name(), session_id(), time() + $dur, '/', '', false, true);
            }
            logMessage('info', 'User logged in: ' . $user['email'] . ' (ID:' . $user['id'] . ')');
            if ($user['role'] === 'admin') {
                redirect('admin/dashboard.php', 'Welcome back, Admin!', 'success');
            } elseif ($user['role'] === 'seller') {
                redirect('seller/dashboard.php', 'Welcome back!', 'success');
            } else {
                redirect('public/shop.php', 'Welcome back, ' . e($user['name']) . '!', 'success');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
  <style>
    .login-wrap { min-height:100vh;display:flex;align-items:center;background:linear-gradient(135deg,#2563EB 0%,#7C3AED 100%); }
    .login-card { max-width:420px;margin:auto; }
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
            <p class="text-muted">Sign in to your account</p>
          </div>

          <?php if ($errors): ?>
            <div class="alert alert-danger py-2">
              <?php foreach ($errors as $err): ?>
                <div><i class="fas fa-exclamation-circle me-1"></i><?= e($err) ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="POST">
            <?= csrfField() ?>
            <div class="mb-3">
              <label class="form-label">Email Address</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                <input type="email" name="email" class="form-control" value="<?= e($email) ?>" required autofocus>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" name="password" class="form-control" required>
              </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-4">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label small" for="remember">Remember me</label>
              </div>
              <a href="<?= APP_URL ?>/public/forgot_password.php" class="small">Forgot password?</a>
            </div>
            <button type="submit" class="btn btn-brand w-100 btn-lg">
              <i class="fas fa-sign-in-alt me-2"></i>Sign In
            </button>
          </form>

          <div class="text-center mt-4">
            <p class="small text-muted">
              Don't have an account?
              <a href="<?= APP_URL ?>/public/register.php" class="fw-bold">Register</a>
            </p>
          </div>

          <hr class="my-4">
          <div class="small text-muted text-center">
            <p class="mb-1"><strong>Test Accounts:</strong></p>
            <p>Admin: <code>admin@novamart.com</code> / <code Admin@123</code></p>
            <p>Customer: <code>alice@novamart.com</code> / <code Admin@123</code></p>
            <p>Seller: <code>john@novamart.com</code> / <code Admin@123</code></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
