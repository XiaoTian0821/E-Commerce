<?php
/**
 * Customer Registration Page
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Register';

// Already logged in?
if (isLoggedIn()) {
    $role = $_SESSION['user']['role'];
    if ($role === 'admin') redirect('admin/dashboard.php');
    if ($role === 'seller') redirect('seller/dashboard.php');
    redirect('public/shop.php');
}

$errors = [];
$old = ['name'=>'','email'=>'','phone'=>'','address'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/csrf.php';
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';
    $phone    = trim($_POST['phone']    ?? '');
    $address  = trim($_POST['address']  ?? '');

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    }
    if (!$name) $errors[] = 'Name is required.';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!$password) $errors[] = 'Password is required.';
    $pwErrors = validatePassword($password);
    $errors = array_merge($errors, $pwErrors);
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        // Check unique email
        $chk = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $errors[] = 'This email is already registered.';
        }
    }

    if (empty($errors)) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare("
                INSERT INTO users (name, email, password, phone, address, role, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'customer', 'active', NOW())
            ");
            $ins->execute([$name, $email, $hash, $phone, $address]);
            $userId = (int)$pdo->lastInsertId();

            // Create empty cart
            $pdo->prepare("INSERT INTO carts (user_id) VALUES (?)")->execute([$userId]);

            logMessage('info', 'New customer registered: ' . $email . ' (ID:' . $userId . ')');
            redirect('public/login.php', 'Account created! Please sign in.', 'success');
        } catch (PDOException $e) {
            logMessage('error', 'Registration error: ' . $e->getMessage());
            $errors[] = 'Could not create account. Please try again.';
        }
    }
    $old = compact('name','email','phone','address');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
  <style>
    .login-wrap { min-height:100vh;display:flex;align-items:center;background:linear-gradient(135deg,#2563EB 0%,#7C3AED 100%); }
    .login-card { max-width:480px;margin:auto; }
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
            <p class="text-muted">Create your account</p>
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
              <label class="form-label">Full Name</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-user"></i></span>
                <input type="text" name="name" class="form-control" value="<?= e($old['name']) ?>" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Email Address</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                <input type="email" name="email" class="form-control" value="<?= e($old['email']) ?>" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" name="password" class="form-control" required>
              </div>
              <div class="form-text">Min 8 chars, at least 1 letter and 1 digit.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Confirm Password</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" name="confirm" class="form-control" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Phone</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                <input type="tel" name="phone" class="form-control" value="<?= e($old['phone']) ?>">
              </div>
            </div>
            <div class="mb-4">
              <label class="form-label">Address</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                <textarea name="address" class="form-control" rows="2"><?= e($old['address']) ?></textarea>
              </div>
            </div>
            <button type="submit" class="btn btn-brand w-100 btn-lg">
              <i class="fas fa-user-plus me-2"></i>Create Account
            </button>
          </form>

          <div class="text-center mt-4">
            <p class="small text-muted">
              Already have an account?
              <a href="<?= APP_URL ?>/public/login.php" class="fw-bold">Sign In</a>
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
