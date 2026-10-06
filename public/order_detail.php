<?php
/**
 * Order Detail Page
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navbar.php';

$pageTitle = 'Order Details';
requireLogin();

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) { redirect('public/orders.php', 'Invalid order.', 'danger'); }

// Handle cancellation
if (($_GET['cancel'] ?? '') === '1' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/csrf.php';
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        redirect('public/order_detail.php?id=' . $orderId, 'Invalid token.', 'danger');
    }
    $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id=? AND user_id=?");
    $orderStmt->execute([$orderId, $_SESSION['user_id']]);
    $order = $orderStmt->fetch();
    if ($order && in_array($order['order_status'], ['pending','paid']) && $order['payment_status'] !== 'refunded') {
        try {
            $pdo->beginTransaction();
            // Restore stock
            $items = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id=?");
            $items->execute([$orderId]);
            foreach ($items->fetchAll() as $oi) {
                $pdo->prepare("UPDATE products SET stock=stock+? WHERE id=?")
                    ->execute([(int)$oi['quantity'], $oi['product_id']]);
            }
            $pdo->prepare("UPDATE orders SET order_status='cancelled', payment_status='refunded', updated_at=NOW() WHERE id=?")
                ->execute([$orderId]);
            $pdo->prepare("UPDATE payments SET status='refunded', updated_at=NOW() WHERE order_id=?")
                ->execute([$orderId]);
            $pdo->commit();
            redirect('public/orders.php', 'Order cancelled. Stock has been restored.', 'success');
        } catch (PDOException $e) {
            $pdo?->rollBack();
            logMessage('error', 'Order cancellation error: ' . $e->getMessage());
            redirect('public/order_detail.php?id=' . $orderId, 'Could not cancel order. Please contact support.', 'danger');
        }
    } else {
        redirect('public/orders.php', 'This order cannot be cancelled.', 'warning');
    }
}

// Fetch order
$orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id=? AND user_id=?");
$orderStmt->execute([$orderId, $_SESSION['user_id']]);
$order = $orderStmt->fetch();
if (!$order) { redirect('public/orders.php', 'Order not found.', 'danger'); }

$itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id=? ORDER BY id");
$itemsStmt->execute([$orderId]);
$orderItems = $itemsStmt->fetchAll();

$statusTimeline = [
    'pending'    => ['label'=>'Order Placed',     'icon'=>'fa-clipboard',    'done'=>true],
    'paid'       => ['label'=>'Payment Confirmed','icon'=>'fa-credit-card','done'=>in_array($order['order_status'],['paid','processing','shipped','completed'])],
    'processing' => ['label'=>'Processing',       'icon'=>'fa-cog',        'done'=>in_array($order['order_status'],['processing','shipped','completed'])],
    'shipped'    => ['label'=>'Shipped',          'icon'=>'fa-truck',      'done'=>in_array($order['order_status'],['shipped','completed'])],
    'completed'  => ['label'=>'Completed',        'icon'=>'fa-check-circle','done'=>$order['order_status']==='completed'],
    'cancelled'  => ['label'=>'Cancelled',         'icon'=>'fa-times-circle','done'=>$order['order_status']==='cancelled'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order <?= e($order['order_number']) ?> — <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
<?= $navbar ?>

<main class="flex-grow-1 py-4">
  <div class="container">
    <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <nav aria-label="breadcrumb" class="mb-4">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/public/orders.php">My Orders</a></li>
        <li class="breadcrumb-item active">Order Detail</li>
      </ol>
    </nav>

    <div class="d-flex align-items-center justify-content-between mb-4">
      <h2 class="fw-bold mb-0">
        <i class="fas fa-receipt me-2"></i>Order
        <span class="text-primary"><?= e($order['order_number']) ?></span>
      </h2>
      <a href="<?= APP_URL ?>/public/orders.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Back to Orders
      </a>
    </div>

    <!-- Order Timeline -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <h5 class="fw-bold mb-3">Order Status</h5>
        <div class="d-flex flex-wrap justify-content-center">
          <?php
          $statuses = ['pending','paid','processing','shipped','completed','cancelled'];
          $currentIdx = array_search($order['order_status'], $statuses);
          foreach ($statuses as $i => $s):
            $active = $i <= $currentIdx && $s !== $order['order_status'];
            $done   = $s === $order['order_status'];
            $disabled = $s === $order['order_status'] ? '' : 'text-muted';
          ?>
            <div class="text-center px-3 <?= $disabled ?>">
              <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-1"
                   style="width:40px;height:40px;
                   background:<?= $done ? 'var(--brand-primary)' : ($active ? 'var(--gray-200)' : 'var(--gray-100)') ?>;
                   color:<?= $done ? '#fff' : 'var(--gray-600)' ?>">
                <i class="fas <?= $done ? 'fa-check' : 'fa-clipboard' ?>"></i>
              </div>
              <div class="small fw-semibold"><?= e(ucwords(str_replace('_',' ',$s))) ?></div>
            </div>
            <?php if ($i < count($statuses) - 1): ?>
              <div class="d-flex align-items-center mx-1">
                <div style="width:30px;height:2px;background:<?= $i < $currentIdx ? 'var(--brand-primary)' : 'var(--gray-200)' ?>"></div>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- Order Items -->
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0"><i class="fas fa-box me-2"></i>Order Items</h5>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Subtotal</th>
                  </tr>
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

        <!-- Cancellation (if eligible) -->
        <?php if (in_array($order['order_status'], ['pending','paid']) && $order['payment_status'] !== 'refunded'): ?>
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
              <h5 class="fw-bold text-danger mb-3"><i class="fas fa-times-circle me-2"></i>Cancel Order</h5>
              <p class="text-muted small">You can cancel this order if it is still pending or unpaid. Stock will be restored.</p>
              <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this order?')">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-danger">
                  <i class="fas fa-times me-1"></i>Cancel Order
                </button>
              </form>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Order Summary -->
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0"><i class="fas fa-file-invoice me-2"></i>Order Summary</h5>
          </div>
          <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">Order Date</span>
              <span class="fw-semibold"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">Payment Method</span>
              <span><?= e(ucwords(str_replace('_',' ',$order['payment_method']))) ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">Payment Status</span>
              <span class="badge badge-<?= $order['payment_status'] ?>">
                <?= e(strtoupper($order['payment_status'])) ?>
              </span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">Order Status</span>
              <span class="badge badge-<?= $order['order_status'] ?>">
                <?= e(ucwords(str_replace('_',' ',$order['order_status']))) ?>
              </span>
            </div>
            <?php if ($order['transaction_id']): ?>
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Transaction ID</span>
                <code class="small"><?= e($order['transaction_id']) ?></code>
              </div>
            <?php endif; ?>
            <?php if ($order['coupon_code']): ?>
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Coupon</span>
                <span class="badge bg-success"><?= e($order['coupon_code']) ?></span>
              </div>
            <?php endif; ?>
            <hr>
            <div class="d-flex justify-content-between mb-1">
              <span>Subtotal</span><span><?= formatCurrency((float)$order['subtotal']) ?></span>
            </div>
            <?php if ((float)$order['discount_amount'] > 0): ?>
              <div class="d-flex justify-content-between mb-1 text-success">
                <span>Discount</span><span>-<?= formatCurrency((float)$order['discount_amount']) ?></span>
              </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between mb-1">
              <span>Shipping</span>
              <span><?= (float)$order['shipping_fee'] === 0 ? '<span class="text-success">FREE</span>' : formatCurrency((float)$order['shipping_fee']) ?></span>
            </div>
            <hr>
            <div class="d-flex justify-content-between">
              <span class="fw-bold fs-5">Total</span>
              <span class="fw-bold fs-5 text-primary"><?= formatCurrency((float)$order['total_amount']) ?></span>
            </div>
          </div>
        </div>

        <!-- Shipping Info -->
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0"><i class="fas fa-map-marker-alt me-2"></i>Shipping Address</h5>
          </div>
          <div class="card-body">
            <p class="mb-1 fw-semibold"><?= e($order['shipping_name']) ?></p>
            <p class="mb-1 text-muted small"><?= nl2br(e($order['shipping_address'])) ?></p>
            <p class="mb-0 text-muted small"><i class="fas fa-phone me-1"></i><?= e($order['shipping_phone']) ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
