<?php
/**
 * Admin: Order View
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin_navbar.php';
requireRole('admin');

$pageTitle = 'Order Detail';

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) redirect('admin/orders.php', 'Invalid order.', 'danger');

$ordStmt = $pdo->prepare("
    SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
           c.code AS coupon_code
    FROM orders o
    JOIN users u ON u.id=o.user_id
    LEFT JOIN coupons c ON c.id=o.coupon_id
    WHERE o.id=?
");
$ordStmt->execute([$orderId]);
$order = $ordStmt->fetch();
if (!$order) redirect('admin/orders.php', 'Order not found.', 'danger');

$itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
$itemsStmt->execute([$orderId]);
$orderItems = $itemsStmt->fetchAll();

// Handle status update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/csrf.php';
    if (csrf_verify($_POST['csrf_token'] ?? '')) {
        $newStatus = $_POST['order_status'] ?? '';
        $validStatuses = ['pending','paid','processing','shipped','completed','cancelled'];
        if (in_array($newStatus, $validStatuses, true)) {
            $pdo->prepare("UPDATE orders SET order_status=?, updated_at=NOW() WHERE id=?")
                ->execute([$newStatus, $orderId]);
            redirect('admin/order_view.php?id=' . $orderId, 'Order status updated.', 'success');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order <?= e($order['order_number']) ?> — Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body class="bg-light">
<?= $adminNavbar ?>

<div class="container py-4">
  <a href="<?= APP_URL ?>/admin/orders.php" class="btn btn-outline-secondary btn-sm mb-3">
    <i class="fas fa-arrow-left me-1"></i>Back to Orders
  </a>
  <h4 class="fw-bold mb-4">
    <i class="fas fa-receipt me-2"></i>Order
    <span class="text-primary"><?= e($order['order_number']) ?></span>
  </h4>

  <?php $flash = getFlash(); if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
      <?= e($flash['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="admin-card">
        <h5><i class="fas fa-box me-2"></i>Order Items</h5>
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
      <div class="admin-card mb-3">
        <h5>Customer</h5>
        <p class="mb-1 fw-semibold"><?= e($order['customer_name']) ?></p>
        <p class="small text-muted mb-1"><i class="fas fa-envelope me-1"></i><?= e($order['customer_email']) ?></p>
        <p class="small text-muted mb-3"><i class="fas fa-phone me-1"></i><?= e($order['customer_phone']) ?></p>
        <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-sm btn-outline-primary">View User</a>
      </div>
      <div class="admin-card mb-3">
        <h5>Shipping Address</h5>
        <p class="small mb-1 fw-semibold"><?= e($order['shipping_name']) ?></p>
        <p class="small text-muted mb-0"><?= nl2br(e($order['shipping_address'])) ?></p>
        <p class="small text-muted mb-0"><i class="fas fa-phone me-1"></i><?= e($order['shipping_phone']) ?></p>
      </div>
      <div class="admin-card">
        <h5>Order Summary</h5>
        <div class="d-flex justify-content-between mb-1">
          <span class="text-muted">Subtotal</span><span><?= formatCurrency((float)$order['subtotal']) ?></span>
        </div>
        <?php if ((float)$order['discount_amount'] > 0): ?>
          <div class="d-flex justify-content-between mb-1 text-success">
            <span>Discount</span><span>-<?= formatCurrency((float)$order['discount_amount']) ?></span>
          </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between mb-1">
          <span class="text-muted">Shipping</span>
          <span><?= (float)$order['shipping_fee']===0?'<span class="text-success">FREE</span>':formatCurrency((float)$order['shipping_fee']) ?></span>
        </div>
        <hr>
        <div class="d-flex justify-content-between">
          <span class="fw-bold">Total</span>
          <span class="fw-bold text-primary"><?= formatCurrency((float)$order['total_amount']) ?></span>
        </div>
        <div class="mt-3 small">
          <div class="mb-1">Status:
            <span class="badge badge-<?= $order['order_status'] ?>"><?= e(ucwords(str_replace('_',' ',$order['order_status']))) ?></span>
          </div>
          <div class="mb-1">Payment:
            <span class="badge badge-<?= $order['payment_status'] ?>"><?= e(strtoupper($order['payment_status'])) ?></span>
          </div>
          <?php if ($order['transaction_id']): ?>
            <div class="mb-1">Transaction: <code><?= e($order['transaction_id']) ?></code></div>
          <?php endif; ?>
          <?php if ($order['coupon_code']): ?>
            <div class="mb-1">Coupon: <span class="badge bg-success"><?= e($order['coupon_code']) ?></span></div>
          <?php endif; ?>
          <div class="mb-1">Date: <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></div>
        </div>
      </div>
      <!-- Update Status -->
      <?php if (!in_array($order['order_status'], ['completed','cancelled'])): ?>
        <div class="admin-card">
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
            <button type="submit" class="btn btn-primary w-100">
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
