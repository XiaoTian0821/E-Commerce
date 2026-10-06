<?php
/**
 * Admin: View User Details
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_navbar.php';
requireRole('admin');

$pageTitle = 'View User';

$userId = (int)($_GET['id'] ?? 0);
if (!$userId) redirect('admin/users.php', 'Invalid user ID.', 'danger');

$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) redirect('admin/users.php', 'User not found.', 'danger');

// Order count
$orderCount = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id=?");
$orderCount->execute([$userId]);
$totalOrders = (int)$orderCount->fetchColumn();

// Total spent
$spentStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id=? AND payment_status='paid'");
$spentStmt->execute([$userId]);
$totalSpent = (float)$spentStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User #<?= $userId ?> — Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4">
  <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline-secondary btn-sm mb-3">
    <i class="fas fa-arrow-left me-1"></i>Back to Users
  </a>
  <h4 class="fw-bold mb-4"><i class="fas fa-user me-2"></i>User Details</h4>

  <div class="row g-4">
    <div class="col-lg-4">
      <div class="admin-card text-center">
        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3"
             style="width:80px;height:80px;font-size:2rem">
          <i class="fas fa-user"></i>
        </div>
        <h5 class="fw-bold"><?= e($user['name']) ?></h5>
        <p class="text-muted small mb-1"><i class="fas fa-envelope me-1"></i><?= e($user['email']) ?></p>
        <p class="mb-2">
          <span class="badge bg-<?= $user['role']==='admin'?'danger':($user['role']==='seller'?'success':'primary') ?>">
            <?= e(ucfirst($user['role'])) ?>
          </span>
          <span class="badge bg-<?= $user['status']==='active'?'success':'secondary' ?> ms-1">
            <?= e(ucfirst($user['status'])) ?>
          </span>
        </p>
        <p class="small text-muted mb-3">
          <i class="fas fa-calendar me-1"></i>Joined
          <?= date('M j, Y', strtotime($user['created_at'])) ?>
        </p>
        <?php if ($user['phone']): ?>
          <p class="small text-muted mb-1"><i class="fas fa-phone me-1"></i><?= e($user['phone']) ?></p>
        <?php endif; ?>
        <?php if ($user['address']): ?>
          <p class="small text-muted mb-3"><i class="fas fa-map-marker-alt me-1"></i><?= nl2br(e($user['address'])) ?></p>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline-secondary w-100">
          <i class="fas fa-arrow-left me-1"></i>Back
        </a>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="admin-card text-center">
            <div class="fs-3 fw-bold text-primary"><?= $totalOrders ?></div>
            <div class="small text-muted">Total Orders</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="admin-card text-center">
            <div class="fs-3 fw-bold text-success"><?= formatCurrency($totalSpent) ?></div>
            <div class="small text-muted">Total Spent</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="admin-card text-center">
            <div class="fs-3 fw-bold text-warning"><?= $user['role'] === 'seller' ? 'Seller' : ($user['role'] === 'admin' ? 'Admin' : 'Customer') ?></div>
            <div class="small text-muted">Role</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
