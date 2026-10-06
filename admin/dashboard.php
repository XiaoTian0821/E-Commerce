<?php
/**
 * Admin Dashboard
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_navbar.php';
requireRole('admin');

$user = $_SESSION['user'];

// Stats
$stats = [];
$stats['total_users']    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$stats['total_sellers']  = $pdo->query("SELECT COUNT(*) FROM users WHERE role='seller'")->fetchColumn();
$stats['total_products'] = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$stats['total_orders']   = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$stats['pending_orders'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status='pending'")->fetchColumn();
$stats['completed_orders'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status='completed'")->fetchColumn();
$stats['total_revenue']  = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE payment_status='paid'")->fetchColumn();

// Sales by month (last 6 months)
$salesByMonth = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
           COUNT(*) AS order_count,
           COALESCE(SUM(total_amount),0) AS revenue
    FROM orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month DESC
    LIMIT 6
")->fetchAll();

// Orders by status
$ordersByStatus = $pdo->query("
    SELECT order_status, COUNT(*) AS cnt
    FROM orders
    GROUP BY order_status
    ORDER BY cnt DESC
")->fetchAll();

// Products by category
$productsByCat = $pdo->query("
    SELECT c.name, COUNT(p.id) AS cnt
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id
    ORDER BY cnt DESC
    LIMIT 8
")->fetchAll();

// Recent orders
$recentOrders = $pdo->query("
    SELECT o.*, u.name AS customer_name
    FROM orders o
    JOIN users u ON u.id=o.user_id
    ORDER BY o.created_at DESC
    LIMIT 10
")->fetchAll();

$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body class="bg-light">

<?= $adminNavbar ?>

<div class="admin-layout">
  <!-- Sidebar -->
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="brand"><i class="fas fa-shield-alt me-2"></i>Admin Panel</div>
    <div class="nav-section">Main</div>
    <a class="nav-link active" href="<?= APP_URL ?>/admin/dashboard.php">
      <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
    <div class="nav-section">Management</div>
    <a class="nav-link" href="<?= APP_URL ?>/admin/users.php">
      <i class="fas fa-users"></i> Users
    </a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/sellers.php">
      <i class="fas fa-store"></i> Sellers
    </a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/categories.php">
      <i class="fas fa-tags"></i> Categories
    </a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/products.php">
      <i class="fas fa-box"></i> Products
    </a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/orders.php">
      <i class="fas fa-clipboard-list"></i> Orders
    </a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/coupons.php">
      <i class="fas fa-ticket-alt"></i> Coupons
    </a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/reviews.php">
      <i class="fas fa-star"></i> Reviews
    </a>
    <div class="nav-section">Account</div>
    <a class="nav-link" href="<?= APP_URL ?>/public/shop.php" target="_blank">
      <i class="fas fa-external-link-alt"></i> View Site
    </a>
    <a class="nav-link text-danger" href="<?= APP_URL ?>/admin/logout.php">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </aside>

  <!-- Main Content -->
  <div class="admin-main">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <button class="btn btn-dark d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <h4 class="fw-bold mb-0"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h4>
      <span class="text-muted small">Welcome, <?= e($user['name']) ?></span>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon blue"><i class="fas fa-users"></i></div>
          <div>
            <div class="stat-value"><?= (int)$stats['total_users'] ?></div>
            <div class="stat-label">Customers</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon green"><i class="fas fa-store"></i></div>
          <div>
            <div class="stat-value"><?= (int)$stats['total_sellers'] ?></div>
            <div class="stat-label">Sellers</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon orange"><i class="fas fa-box"></i></div>
          <div>
            <div class="stat-value"><?= (int)$stats['total_products'] ?></div>
            <div class="stat-label">Products</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon purple"><i class="fas fa-clipboard-list"></i></div>
          <div>
            <div class="stat-value"><?= (int)$stats['total_orders'] ?></div>
            <div class="stat-label">Total Orders</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card">
          <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
          <div>
            <div class="stat-value"><?= (int)$stats['pending_orders'] ?></div>
            <div class="stat-label">Pending Orders</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card">
          <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
          <div>
            <div class="stat-value"><?= (int)$stats['completed_orders'] ?></div>
            <div class="stat-label">Completed</div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-6">
        <div class="stat-card">
          <div class="stat-icon green"><i class="fas fa-dollar-sign"></i></div>
          <div>
            <div class="stat-value"><?= formatCurrency((float)$stats['total_revenue']) ?></div>
            <div class="stat-label">Total Revenue</div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- Recent Orders -->
      <div class="col-lg-8">
        <div class="admin-table">
          <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
            <h6 class="fw-bold mb-0"><i class="fas fa-clock me-2"></i>Recent Orders</h6>
            <a href="<?= APP_URL ?>/admin/orders.php" class="btn btn-sm btn-outline-primary">View All</a>
          </div>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>Order #</th>
                  <th>Customer</th>
                  <th>Amount</th>
                  <th>Payment</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentOrders as $ro):
                  $sClass = match($ro['order_status']) {
                      'pending' => 'badge-pending', 'paid' => 'badge-paid',
                      'processing' => 'badge-processing', 'shipped' => 'badge-shipped',
                      'completed' => 'badge-completed', 'cancelled' => 'badge-cancelled'
                  };
                  $pClass = match($ro['payment_status']) {
                      'pending' => 'badge-pending', 'paid' => 'badge-paid',
                      'failed' => 'badge-cancelled', 'refunded' => 'badge-inactive'
                  };
                ?>
                  <tr>
                    <td class="fw-semibold small"><?= e($ro['order_number']) ?></td>
                    <td><?= e($ro['customer_name']) ?></td>
                    <td class="fw-semibold small"><?= formatCurrency((float)$ro['total_amount']) ?></td>
                    <td><span class="badge-status <?= $pClass ?>"><?= e(strtoupper($ro['payment_status'])) ?></span></td>
                    <td><span class="badge-status <?= $sClass ?>"><?= e(ucwords(str_replace('_',' ',$ro['order_status']))) ?></span></td>
                    <td class="small text-muted"><?= date('M j, Y', strtotime($ro['created_at'])) ?></td>
                    <td>
                      <a href="<?= APP_URL ?>/admin/orders.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($recentOrders)): ?>
                  <tr><td colspan="7" class="text-center py-4 text-muted">No orders yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Charts / Stats -->
      <div class="col-lg-4">
        <div class="admin-card mb-3">
          <h5><i class="fas fa-chart-bar me-2"></i>Orders by Status</h5>
          <div class="mt-3">
            <?php foreach ($ordersByStatus as $obs):
              $cls = match($obs['order_status']) {
                  'pending' => 'badge-pending', 'paid' => 'badge-paid',
                  'processing' => 'badge-processing', 'shipped' => 'badge-shipped',
                  'completed' => 'badge-completed', 'cancelled' => 'badge-cancelled'
              };
            ?>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge-status <?= $cls ?>"><?= e(ucwords(str_replace('_',' ',$obs['order_status']))) ?></span>
                <span class="fw-bold"><?= (int)$obs['cnt'] ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="admin-card">
          <h5><i class="fas fa-chart-pie me-2"></i>Products by Category</h5>
          <div class="mt-3">
            <?php foreach ($productsByCat as $pbc): ?>
              <div class="d-flex justify-content-between mb-1 small">
                <span><?= e($pbc['name']) ?></span>
                <span class="fw-bold"><?= (int)$pbc['cnt'] ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/admin.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  document.getElementById('adminSidebar')?.classList.toggle('show');
});
</script>
</body>
</html>
