<?php
/**
 * Admin: Orders Management
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_navbar.php';
requireRole('admin');

$pageTitle = 'Orders';

$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPag = ADMIN_ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPag;

$wheres = [];
$params = [];
if ($statusFilter && in_array($statusFilter, ['pending','paid','processing','shipped','completed','cancelled'])) {
    $wheres[] = "o.order_status = ?";
    $params[] = $statusFilter;
}
if ($search) {
    $wheres[] = "(o.order_number LIKE ? OR o.shipping_name LIKE ? OR u.name LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$whereSql = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON u.id=o.user_id $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPag));

$stmt = $pdo->prepare("
    SELECT o.*, u.name AS customer_name, u.email AS customer_email, c.code AS coupon_code
    FROM orders o
    JOIN users u ON u.id=o.user_id
    LEFT JOIN coupons c ON c.id=o.coupon_id
    $whereSql
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $perPag;
$params[] = $offset;
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Orders — Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="admin-layout">
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="brand"><i class="fas fa-shield-alt me-2"></i>Admin Panel</div>
    <div class="nav-section">Main</div>
    <a class="nav-link" href="<?= APP_URL ?>/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <div class="nav-section">Management</div>
    <a class="nav-link" href="<?= APP_URL ?>/admin/users.php"><i class="fas fa-users"></i> Users</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/sellers.php"><i class="fas fa-store"></i> Sellers</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/categories.php"><i class="fas fa-tags"></i> Categories</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/products.php"><i class="fas fa-box"></i> Products</a>
    <a class="nav-link active" href="<?= APP_URL ?>/admin/orders.php"><i class="fas fa-clipboard-list"></i> Orders</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/coupons.php"><i class="fas fa-ticket-alt"></i> Coupons</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/reviews.php"><i class="fas fa-star"></i> Reviews</a>
  </aside>

  <div class="admin-main">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <button class="btn btn-dark d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <h4 class="fw-bold mb-0"><i class="fas fa-clipboard-list me-2"></i>Order Management</h4>
    </div>

    <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="admin-card mb-4">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label small">Search</label>
          <input type="text" name="search" class="form-control form-control-sm"
                 placeholder="Order #, customer…" value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label small">Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="">All Statuses</option>
            <option value="pending"    <?= $statusFilter==='pending'?'selected':'' ?>>Pending</option>
            <option value="paid"       <?= $statusFilter==='paid'?'selected':'' ?>>Paid</option>
            <option value="processing" <?= $statusFilter==='processing'?'selected':'' ?>>Processing</option>
            <option value="shipped"    <?= $statusFilter==='shipped'?'selected':'' ?>>Shipped</option>
            <option value="completed"  <?= $statusFilter==='completed'?'selected':'' ?>>Completed</option>
            <option value="cancelled"  <?= $statusFilter==='cancelled'?'selected':'' ?>>Cancelled</option>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary btn-sm w-100">
            <i class="fas fa-search me-1"></i>Search
          </button>
        </div>
        <?php if ($search || $statusFilter): ?>
          <div class="col-md-2">
            <a href="<?= APP_URL ?>/admin/orders.php" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
          </div>
        <?php endif; ?>
      </form>
    </div>

    <!-- Orders Table -->
    <div class="admin-table">
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Order #</th>
              <th>Customer</th>
              <th>Date</th>
              <th>Items</th>
              <th>Total</th>
              <th>Payment</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($orders)): ?>
              <tr><td colspan="8" class="text-center py-4 text-muted">No orders found.</td></tr>
            <?php else: ?>
              <?php foreach ($orders as $o):
                $sClass = match($o['order_status']) {
                    'pending' => 'badge-pending', 'paid' => 'badge-paid',
                    'processing' => 'badge-processing', 'shipped' => 'badge-shipped',
                    'completed' => 'badge-completed', 'cancelled' => 'badge-cancelled'
                };
                $pClass = match($o['payment_status']) {
                    'pending' => 'badge-pending', 'paid' => 'badge-paid',
                    'failed' => 'badge-cancelled', 'refunded' => 'badge-inactive'
                };
              ?>
                <tr>
                  <td class="fw-semibold small"><?= e($o['order_number']) ?></td>
                  <td>
                    <div class="fw-semibold small"><?= e($o['customer_name']) ?></div>
                    <div class="small text-muted"><?= e($o['customer_email']) ?></div>
                  </td>
                  <td class="small text-muted"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                  <td class="small">
                    <?php
                      $ic = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id=?");
                      $ic->execute([$o['id']]);
                      echo $ic->fetchColumn() . ' item(s)';
                    ?>
                  </td>
                  <td class="fw-semibold small"><?= formatCurrency((float)$o['total_amount']) ?></td>
                  <td><span class="badge-status <?= $pClass ?>"><?= e(strtoupper($o['payment_status'])) ?></span></td>
                  <td><span class="badge-status <?= $sClass ?>"><?= e(ucwords(str_replace('_',' ',$o['order_status']))) ?></span></td>
                  <td>
                    <a href="<?= APP_URL ?>/admin/order_view.php?id=<?= (int)$o['id'] ?>"
                       class="btn btn-sm btn-outline-primary">
                      <i class="fas fa-eye"></i> View
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($totalPages > 1): ?>
      <nav class="mt-3 d-flex justify-content-center">
        <ul class="pagination">
          <li class="page-item <?= $page<=1?'disabled':'' ?>">
            <a class="page-link" href="?page=<?= $page-1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $statusFilter ? '&status='.urlencode($statusFilter) : '' ?>">Prev</a>
          </li>
          <?php for ($i=1;$i<=$totalPages;$i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
              <a class="page-link" href="?page=<?= $i ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $statusFilter ? '&status='.urlencode($statusFilter) : '' ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
            <a class="page-link" href="?page=<?= $page+1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $statusFilter ? '&status='.urlencode($statusFilter) : '' ?>">Next</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
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
