<?php
/**
 * Seller Profile Page
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seller_navbar.php';
requireRole('seller');

$user = $_SESSION['user'];
$pageTitle = 'My Profile';
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/csrf.php';
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $name     = trim($_POST['name']      ?? '');
        $phone    = trim($_POST['phone']     ?? '');
        $address  = trim($_POST['address']   ?? '');
        $newPass  = trim($_POST['new_password'] ?? '');
        $confirm  = trim($_POST['confirm']    ?? '');

        if (!$name) $errors[] = 'Name is required.';
        if ($newPass && $newPass !== $confirm) $errors[] = 'Passwords do not match.';
        if ($newPass && strlen($newPass) < 8) $errors[] = 'Password must be at least 8 characters.';

        if (empty($errors)) {
            try {
                $fields = ['name' => $name, 'phone' => $phone, 'address' => $address];
                if ($newPass) $fields['password'] = password_hash($newPass, PASSWORD_DEFAULT);
                $setClause = implode(', ', array_map(fn($k) => "$k=?", $fields));
                $vals = array_values($fields);
                $vals[] = $_SESSION['user_id'];
                $stmt = $pdo->prepare("UPDATE users SET $setClause WHERE id=?");
                $stmt->execute($vals);
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['phone'] = $phone;
                $_SESSION['user']['address'] = $address;
                $success = 'Profile updated successfully!';
            } catch (PDOException $e) {
                logMessage('error', 'Seller profile update error: ' . $e->getMessage());
                $errors[] = 'Could not update profile.';
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
  <title>My Profile — <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/seller.css" rel="stylesheet">
</head>
<body class="bg-light">
<?= $sellerNavbar ?>

<div class="seller-layout">
  <aside class="seller-sidebar" id="sellerSidebar">
    <div class="brand"><i class="fas fa-store me-2"></i><?= e(APP_NAME) ?></div>
    <nav class="nav flex-column mt-2">
      <a class="nav-link" href="<?= APP_URL ?>/seller/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a class="nav-link" href="<?= APP_URL ?>/seller/products.php"><i class="fas fa-box"></i> My Products</a>
      <a class="nav-link" href="<?= APP_URL ?>/seller/orders.php"><i class="fas fa-clipboard-list"></i> Orders</a>
      <a class="nav-link active" href="<?= APP_URL ?>/seller/profile.php"><i class="fas fa-user-circle"></i> Profile</a>
    </nav>
  </aside>

  <div class="seller-main">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <button class="btn btn-dark d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <h4 class="fw-bold mb-0"><i class="fas fa-user-circle me-2"></i>My Profile</h4>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <?php foreach ($errors as $err): ?>
          <div><i class="fas fa-exclamation-circle me-1"></i><?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="seller-card">
      <form method="POST">
        <?= csrfField() ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
            <div class="form-text">Email cannot be changed.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input type="tel" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Address</label>
            <textarea name="address" class="form-control" rows="2"><?= e($user['address'] ?? '') ?></textarea>
          </div>
          <div class="col-12"><hr></div>
          <div class="col-md-6">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current">
          </div>
          <div class="col-md-6">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm" class="form-control">
          </div>
          <div class="col-12 mt-3">
            <button type="submit" class="btn btn-success">
              <i class="fas fa-save me-1"></i>Save Changes
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Stats -->
    <div class="row g-3 mt-2">
      <div class="col-md-4">
        <div class="seller-card text-center">
          <div class="stat-icon si-green d-inline-flex rounded-circle mb-2" style="width:48px;height:48px">
            <i class="fas fa-box"></i>
          </div>
          <div class="stat-value">
            <?php
            $pc = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id=?");
            $pc->execute([$user['id']]);
            echo $pc->fetchColumn();
            ?>
          </div>
          <div class="stat-label">Total Products</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="seller-card text-center">
          <div class="stat-icon si-blue d-inline-flex rounded-circle mb-2" style="width:48px;height:48px">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="stat-value">
            <?php
            $ac = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id=? AND status='active'");
            $ac->execute([$user['id']]);
            echo $ac->fetchColumn();
            ?>
          </div>
          <div class="stat-label">Active Products</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="seller-card text-center">
          <div class="stat-icon si-orange d-inline-flex rounded-circle mb-2" style="width:48px;height:48px">
            <i class="fas fa-clipboard-list"></i>
          </div>
          <div class="stat-value">
            <?php
            $oc = $pdo->prepare("SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.seller_id=?");
            $oc->execute([$user['id']]);
            echo $oc->fetchColumn();
            ?>
          </div>
          <div class="stat-label">Total Orders</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  document.getElementById('sellerSidebar')?.classList.toggle('show');
});
</script>
</body>
</html>
