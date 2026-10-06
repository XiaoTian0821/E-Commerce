<?php
/**
 * Customer Orders Page
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'My Orders';
requireLogin();

$page     = max(1, (int)($_GET['page'] ?? 1));
$perPag   = 10;
$offset   = ($page - 1) * $perPag;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id=?");
$countStmt->execute([$_SESSION['user_id']]);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPag));

$ordersStmt = $pdo->prepare("
    SELECT o.*, c.code AS coupon_code
    FROM orders o
    LEFT JOIN coupons c ON c.id=o.coupon_id
    WHERE o.user_id=?
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
");
$ordersStmt->execute([$_SESSION['user_id'], $perPag, $offset]);
$orders = $ordersStmt->fetchAll();
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
  <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
<?php require __DIR__ . '/../includes/navbar.php'; ?>

<main class="flex-grow-1 py-4">
  <div class="container">
    <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <h2 class="fw-bold mb-4"><i class="fas fa-box-open me-2"></i>My Orders</h2>

    <?php if (empty($orders)): ?>
      <div class="text-center py-5">
        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
        <h5>No orders yet</h5>
        <p class="text-muted">You haven't placed any orders yet. Start shopping!</p>
        <a href="<?= APP_URL ?>/public/shop.php" class="btn btn-brand">
          <i class="fas fa-shopping-bag me-2"></i>Browse Products
        </a>
      </div>
    <?php else: ?>
      <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Order #</th>
                  <th>Date</th>
                  <th>Items</th>
                  <th>Total</th>
                  <th>Payment</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orders as $o):
                  $statusClass = match($o['order_status']) {
                      'pending'    => 'badge-pending',
                      'paid'       => 'badge-paid',
                      'processing' => 'badge-processing',
                      'shipped'    => 'badge-shipped',
                      'completed'  => 'badge-completed',
                      'cancelled'  => 'badge-cancelled',
                      default      => 'badge-inactive'
                  };
                  $payClass = match($o['payment_status']) {
                      'pending' => 'badge-pending',
                      'paid'    => 'badge-paid',
                      'failed'  => 'badge-cancelled',
                      default   => 'badge-inactive'
                  };
                ?>
                  <tr>
                    <td class="fw-semibold"><?= e($o['order_number']) ?></td>
                    <td class="text-muted small"><?= date('M j, Y g:i A', strtotime($o['created_at'])) ?></td>
                    <td>
                      <?php
                        $itemCnt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id=?");
                        $itemCnt->execute([$o['id']]);
                        echo $itemCnt->fetchColumn() . ' item(s)';
                      ?>
                    </td>
                    <td class="fw-semibold"><?= formatCurrency((float)$o['total_amount']) ?></td>
                    <td>
                      <span class="badge-status <?= $payClass ?>">
                        <?= e(strtoupper($o['payment_status'])) ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge-status <?= $statusClass ?>">
                        <?= e(ucwords(str_replace('_',' ',$o['order_status']))) ?>
                      </span>
                    </td>
                    <td>
                      <a href="<?= APP_URL ?>/public/order_detail.php?id=<?= (int)$o['id'] ?>"
                         class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye"></i> View
                      </a>
                      <?php if (in_array($o['order_status'], ['pending','paid']) && $o['payment_status'] !== 'refunded'): ?>
                        <a href="<?= APP_URL ?>/public/order_detail.php?id=<?= (int)$o['id'] ?>&cancel=1"
                           class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Cancel this order? Stock will be restored.')">
                          <i class="fas fa-times"></i> Cancel
                        </a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <?php if ($totalPages > 1): ?>
        <nav class="mt-3 d-flex justify-content-center">
          <ul class="pagination">
            <li class="page-item <?= $page<=1?'disabled':'' ?>">
              <a class="page-link" href="?page=<?= $page-1 ?>">Prev</a>
            </li>
            <?php for ($i=1;$i<=$totalPages;$i++): ?>
              <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
              <a class="page-link" href="?page=<?= $page+1 ?>">Next</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
