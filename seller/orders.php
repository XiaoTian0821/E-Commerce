<?php
/**
 * Seller Orders Management
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
$pageTitle = 'My Orders';

$orderId = (int)($_GET['id'] ?? 0);

if ($orderId) {
    // Single order view
    $ordStmt = $pdo->prepare("
        SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
               c.code AS coupon_code
        FROM orders o
        JOIN users u ON u.id=o.user_id
        LEFT JOIN coupons c ON c.id=o.coupon_id
        WHERE o.id=? AND EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id=o.id AND oi.seller_id=?)
    ");
    $ordStmt->execute([$orderId, $userId]);
    $order = $ordStmt->fetch();
    if (!$order) redirect('seller/orders.php', 'Order not found.', 'danger');

    $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
    $itemsStmt->execute([$orderId]);
    $orderItems = $itemsStmt->fetchAll();

    // Update order status
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/includes/csrf.php';
        if (csrf_verify($_POST['csrf_token'] ?? '')) {
            $newStatus = $_POST['order_status'] ?? '';
            $validStatuses = ['pending','paid','processing','shipped','completed','cancelled'];
            if (in_array($newStatus, $validStatuses, true)) {
                $pdo->prepare("UPDATE orders SET order_status=?, updated_at=NOW() WHERE id=? AND EXISTS (SELECT 1 FROM order_items WHERE order_id=orders.id AND seller_id=?)")
                    ->execute([$newStatus, $orderId, $userId]);
                redirect('seller/orders.php?id=' . $orderId, 'Order status updated.', 'success');
            }
        }
    }

    // Render order detail
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Order <?= e($order['order_number']) ?> — Seller</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
      <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
      <link href="<?= APP_URL ?>/assets/css/seller.css" rel="stylesheet">
    </head>
    <body class="bg-light">
    <?= $sellerNavbar ?>
    <div class="container py-4">
      <a href="<?= APP_URL ?>/seller/orders.php" class="btn btn-outline-secondary btn-sm mb-3">
        <i class="fas fa-arrow-left me-1"></i>Back to Orders
      </a>
      <h4 class="fw-bold mb-4">
        <i class="fas fa-receipt me-2"></i>Order <?= e($order['order_number']) ?>
      </h4>

      <div class="row g-4">
        <div class="col-lg-8">
          <div class="seller-card">
            <h5>Order Items</h5>
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($orderItems as $oi): ?>
                    <tr>
                      <td class="fw-semibold"><?= e($oi['product_name']) ?></td>
                      <td><?= (int)$oi['quantity'] ?></td>
                      <td><?= formatCurrency((float)$oi['unit_price']) ?></td>
                      <td class="fw-semibold"><?= formatCurrency((float)$oi['subtotal']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="seller-card mb-3">
            <h5>Customer Info</h5>
            <p class="mb-1"><strong><?= e($order['customer_name']) ?></strong></p>
            <p class="small text-muted mb-1"><i class="fas fa-envelope me-1"></i><?= e($order['customer_email']) ?></p>
            <p class="small text-muted mb-0"><i class="fas fa-phone me-1"></i><?= e($order['customer_phone']) ?></p>
          </div>
          <div class="seller-card mb-3">
            <h5>Shipping Address</h5>
            <p class="small mb-0"><?= nl2br(e($order['shipping_address'])) ?></p>
            <p class="small text-muted mb-0"><i class="fas fa-phone me-1"></i><?= e($order['shipping_phone']) ?></p>
          </div>
          <div class="seller-card">
            <h5>Order Summary</h5>
            <div class="d-flex justify-content-between mb-1"><span class="text-muted">Subtotal</span><span><?= formatCurrency((float)$order['subtotal']) ?></span></div>
            <?php if ((float)$order['discount_amount'] > 0): ?>
              <div class="d-flex justify-content-between mb-1 text-success"><span>Discount</span><span>-<?= formatCurrency((float)$order['discount_amount']) ?></span></div>
            <?php endif; ?>
            <div class="d-flex justify-content-between mb-1"><span class="text-muted">Shipping</span><span><?= (float)$order['shipping_fee']===0?'<span class="text-success">FREE</span>':formatCurrency((float)$order['shipping_fee']) ?></span></div>
            <hr>
            <div class="d-flex justify-content-between"><span class="fw-bold">Total</span><span class="fw-bold text-primary"><?= formatCurrency((float)$order['total_amount']) ?></span></div>
            <div class="mt-3">
              <div class="small text-muted mb-1">Status: <span class="badge badge-<?= $order['order_status'] ?>"><?= e(ucwords(str_replace('_',' ',$order['order_status']))) ?></span></div>
              <div class="small text-muted mb-1">Payment: <span class="badge badge-<?= $order['payment_status'] ?>"><?= e(strtoupper($order['payment_status'])) ?></span></div>
              <?php if ($order['transaction_id']): ?>
                <div class="small text-muted">Transaction: <code><?= e($order['transaction_id']) ?></code></div>
              <?php endif; ?>
            </div>
          </div>
          <!-- Update Status -->
          <?php if (!in_array($order['order_status'], ['completed','cancelled'])): ?>
            <div class="seller-card">
              <h5>Update Status</h5>
              <form method="POST">
                <?= csrfField() ?>
                <select name="order_status" class="form-select mb-2">
                  <option value="pending"    <?= $order['order_status']==='pending'?'selected':'' ?>>Pending</option>
                  <option value="paid"       <?= $order['order_status']==='paid'?'selected':'' ?>>Paid</option>
                  <option value="processing" <?= $order['order_status']==='processing'?'selected':'' ?>>Processing</option>
                  <option value="shipped"    <?= $order['order_status']==='shipped'?'selected':'' ?>>Shipped</option>
                  <option value="completed"  <?= $order['order_status']==='completed'?'selected':'' ?>>Completed</option>
                  <option value="cancelled"  <?= $order['order_status']==='cancelled'?'selected':'' ?>>Cancelled</option>
                </select>
                <button type="submit" class="btn btn-success w-100">
                  <i class="fas fa-save me-1"></i>Update Status
                </button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// ── Orders list ────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$wheres = ["EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id=o.id AND oi.seller_id=?)"];
$params = [$userId];
if ($search) {
    $wheres[] = "(o.order_number LIKE ? OR o.shipping_name LIKE ? OR o.shipping_phone LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$whereSql = 'WHERE ' . implode(' AND ', $wheres);

$page     = max(1, (int)($_GET['page'] ?? 1));
$perPag   = 15;
$offset   = ($page - 1) * $perPag;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPag));

$stmt = $pdo->prepare("
    SELECT o.*, u.name AS customer_name
    FROM orders o
    JOIN users u ON u.id=o.user_id
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
  <title>My Orders — <?= e(APP_NAME) ?></title>
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
      <a class="nav-link active" href="<?= APP_URL ?>/seller/orders.php"><i class="fas fa-clipboard-list"></i> Orders</a>
      <a class="nav-link" href="<?= APP_URL ?>/seller/profile.php"><i class="fas fa-user-circle"></i> Profile</a>
    </nav>
  </aside>

  <div class="seller-main">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <button class="btn btn-dark d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <h4 class="fw-bold mb-0"><i class="fas fa-clipboard-list me-2"></i>My Orders</h4>
    </div>

    <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Search -->
    <div class="seller-card mb-4">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-6">
          <label class="form-label small">Search Orders</label>
          <input type="text" name="search" class="form-control form-control-sm"
                 placeholder="Order #, customer name…" value="<?= e($search) ?>">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-success btn-sm w-100">
            <i class="fas fa-search me-1"></i>Search
          </button>
        </div>
        <?php if ($search): ?>
          <div class="col-md-2">
            <a href="<?= APP_URL ?>/seller/orders.php" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
          </div>
        <?php endif; ?>
      </form>
    </div>

    <!-- Orders Table -->
    <div class="seller-table">
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
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($orders)): ?>
              <tr><td colspan="8" class="text-center py-4 text-muted">No orders found.</td></tr>
            <?php else: ?>
              <?php foreach ($orders as $o):
                $statusClass = match($o['order_status']) {
                    'pending' => 'badge-pending', 'paid' => 'badge-paid',
                    'processing' => 'badge-processing', 'shipped' => 'badge-shipped',
                    'completed' => 'badge-completed', 'cancelled' => 'badge-cancelled',
                };
                $payClass = match($o['payment_status']) {
                    'pending' => 'badge-pending', 'paid' => 'badge-paid',
                    'failed' => 'badge-cancelled', 'refunded' => 'badge-inactive',
                };
              ?>
                <tr>
                  <td class="fw-semibold small"><?= e($o['order_number']) ?></td>
                  <td><?= e($o['customer_name']) ?></td>
                  <td class="small text-muted"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                  <td>
                    <?php
                      $ic = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id=?");
                      $ic->execute([$o['id']]);
                      echo $ic->fetchColumn() . ' item(s)';
                    ?>
                  </td>
                  <td class="fw-semibold"><?= formatCurrency((float)$o['total_amount']) ?></td>
                  <td><span class="badge-status <?= $payClass ?>"><?= e(strtoupper($o['payment_status'])) ?></span></td>
                  <td><span class="badge-status <?= $statusClass ?>"><?= e(ucwords(str_replace('_',' ',$o['order_status']))) ?></span></td>
                  <td>
                    <a href="?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-primary">
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
            <a class="page-link" href="?page=<?= $page-1 ?><?php if($search): ?>&search=<?= urlencode($search) ?><?php endif; ?>">Prev</a>
          </li>
          <?php for ($i=1;$i<=$totalPages;$i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
              <a class="page-link" href="?page=<?= $i ?><?php if($search): ?>&search=<?= urlencode($search) ?><?php endif; ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
            <a class="page-link" href="?page=<?= $page+1 ?><?php if($search): ?>&search=<?= urlencode($search) ?><?php endif; ?>">Next</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
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
