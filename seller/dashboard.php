<?php
/**
 * Seller Dashboard
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seller_navbar.php';
requireRole('seller');

$user = $_SESSION['user'];
$userId = $user['id'];

// Dashboard stats
$statsStmt = $pdo->prepare("
    SELECT
      (SELECT COUNT(*) FROM products WHERE seller_id=?) AS total_products,
      (SELECT COUNT(*) FROM products WHERE seller_id=? AND status='active') AS active_products,
      (SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.seller_id=?) AS total_orders,
      (SELECT COALESCE(SUM(oi.subtotal),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.seller_id=? AND o.order_status IN ('completed','shipped','processing')) AS total_sales
");
$statsStmt->execute([$userId, $userId, $userId, $userId]);
$stats = $statsStmt->fetch();

$pendingOrders = $pdo->prepare("
    SELECT COUNT(*) FROM orders o JOIN order_items oi ON oi.order_id=o.id
    WHERE oi.seller_id=? AND o.order_status IN ('pending','paid')
");
$pendingOrders->execute([$userId]);
$pendingOrders = (int)$pendingOrders->fetchColumn();

$recentOrders = $pdo->prepare("
    SELECT o.* FROM orders o
    JOIN order_items oi ON oi.order_id=o.id
    WHERE oi.seller_id=?
    ORDER BY o.created_at DESC LIMIT 5
");
$recentOrders->execute([$userId]);
$recentOrders = $recentOrders->fetchAll();

$pageTitle = 'Seller Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Seller Dashboard — <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/seller.css" rel="stylesheet">
</head>
<body class="bg-light">

<?= $sellerNavbar ?>

<div class="seller-layout">
  <!-- Sidebar -->
  <aside class="seller-sidebar" id="sellerSidebar">
    <div class="brand"><i class="fas fa-store me-2"></i><?= e(APP_NAME) ?></div>
    <nav class="nav flex-column mt-2">
      <a class="nav-link active" href="<?= APP_URL ?>/seller/dashboard.php">
        <i class="fas fa-tachometer-alt"></i> Dashboard
      </a>
      <a class="nav-link" href="<?= APP_URL ?>/seller/products.php">
        <i class="fas fa-box"></i> My Products
      </a>
      <a class="nav-link" href="<?= APP_URL ?>/seller/orders.php">
        <i class="fas fa-clipboard-list"></i> Orders
      </a>
      <a class="nav-link" href="<?= APP_URL ?>/seller/profile.php">
        <i class="fas fa-user-circle"></i> Profile
      </a>
      <a class="nav-link text-warning" href="<?= APP_URL ?>/public/shop.php" target="_blank">
        <i class="fas fa-external-link-alt"></i> View Shop
      </a>
    </nav>
  </aside>

  <!-- Main Content -->
  <div class="seller-main">
    <!-- Top bar with mobile toggle -->
    <div class="d-flex align-items-center justify-content-between mb-4">
      <button class="btn btn-dark d-lg-none" id="sidebarToggle">
        <i class="fas fa-bars"></i>
      </button>
      <h4 class="fw-bold mb-0">Welcome, <?= e($user['name']) ?>!</h4>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="seller-stat">
          <div class="stat-icon si-green"><i class="fas fa-box"></i></div>
          <div>
            <div class="stat-value"><?= (int)$stats['total_products'] ?></div>
            <div class="stat-label">Total Products</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="seller-stat">
          <div class="stat-icon si-blue"><i class="fas fa-check-circle"></i></div>
          <div>
            <div class="stat-value"><?= (int)$stats['active_products'] ?></div>
            <div class="stat-label">Active Products</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="seller-stat">
          <div class="stat-icon si-orange"><i class="fas fa-clipboard-list"></i></div>
          <div>
            <div class="stat-value"><?= $pendingOrders ?></div>
            <div class="stat-label">Pending Orders</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="seller-stat">
          <div class="stat-icon si-red"><i class="fas fa-dollar-sign"></i></div>
          <div>
            <div class="stat-value"><?= formatCurrency((float)$stats['total_sales']) ?></div>
            <div class="stat-label">Total Sales</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <div class="seller-card">
          <h5><i class="fas fa-plus-circle me-2 text-success"></i>Quick Actions</h5>
          <div class="d-grid gap-2">
            <a href="<?= APP_URL ?>/seller/product_add.php" class="btn btn-success">
              <i class="fas fa-plus me-2"></i>Add New Product
            </a>
            <a href="<?= APP_URL ?>/seller/products.php" class="btn btn-outline-success">
              <i class="fas fa-box me-2"></i>Manage Products
            </a>
            <a href="<?= APP_URL ?>/seller/orders.php" class="btn btn-outline-success">
              <i class="fas fa-clipboard-list me-2"></i>View Orders
            </a>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="seller-card">
          <h5><i class="fas fa-clock me-2 text-success"></i>Recent Orders</h5>
          <?php if (empty($recentOrders)): ?>
            <p class="text-muted small">No orders yet.</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover">
                <thead><tr><th>Order #</th><th>Date</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach ($recentOrders as $ro):
                    $statusClass = match($ro['order_status']) {
                        'pending' => 'badge-pending', 'paid' => 'badge-paid',
                        'processing' => 'badge-processing', 'shipped' => 'badge-shipped',
                        'completed' => 'badge-completed', 'cancelled' => 'badge-cancelled',
                    };
                  ?>
                    <tr>
                      <td class="fw-semibold small"><?= e($ro['order_number']) ?></td>
                      <td class="small text-muted"><?= date('M j, Y', strtotime($ro['created_at'])) ?></td>
                      <td><span class="badge-status <?= $statusClass ?>"><?= e(ucwords(str_replace('_',' ',$ro['order_status']))) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <a href="<?= APP_URL ?>/seller/orders.php" class="btn btn-sm btn-outline-success mt-2">View All Orders</a>
          <?php endif; ?>
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
