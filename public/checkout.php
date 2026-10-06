<?php
/**
 * Checkout Page
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/paypal.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Checkout';
requireLogin();

// ── AJAX: Validate coupon ─────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'validate_coupon' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../includes/csrf.php';
    $code = trim($_POST['code'] ?? '');
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success'=>false,'message'=>'Security error.']); exit;
    }
    if (!$code) {
        echo json_encode(['success'=>false,'message'=>'Please enter a coupon code.']); exit;
    }
    $cpStmt = $pdo->prepare("SELECT * FROM coupons WHERE code=? AND status='active'");
    $cpStmt->execute([$code]);
    $coupon = $cpStmt->fetch();
    if (!$coupon) {
        echo json_encode(['success'=>false,'message'=>'Invalid or inactive coupon code.']); exit;
    }
    $now = date('Y-m-d');
    $start = $coupon['start_date'] ?? null;
    $expiry = $coupon['expiry_date'] ?? null;
    if ($start && $now < $start) {
        echo json_encode(['success'=>false,'message'=>'This coupon is not yet active.']); exit;
    }
    if ($expiry && $now > $expiry) {
        echo json_encode(['success'=>false,'message'=>'This coupon has expired.']); exit;
    }
    if ($coupon['usage_limit'] !== null && (int)$coupon['used_count'] >= (int)$coupon['usage_limit']) {
        echo json_encode(['success'=>false,'message'=>'This coupon has reached its usage limit.']); exit;
    }
    echo json_encode(['success'=>true,'message'=>'Coupon applied!','code'=>$code,'coupon_id'=>$coupon['id']]);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'remove_coupon') {
    header('Content-Type: application/json');
    unset($_SESSION['cart_coupon_id']);
    echo json_encode(['success'=>true]);
    exit;
}

// ── Fetch cart ────────────────────────────────────────
$cartStmt = $pdo->prepare("SELECT id FROM carts WHERE user_id=?");
$cartStmt->execute([$_SESSION['user_id']]);
$cartRow = $cartStmt->fetch();
if (!$cartRow) { redirect('public/cart.php', 'Your cart is empty.', 'warning'); }
$cartId = (int)$cartRow['id'];

$itemsStmt = $pdo->prepare("
    SELECT ci.*, p.name, p.price AS db_price, p.stock, p.slug,
           (SELECT image_path FROM product_images pi WHERE pi.product_id=p.id ORDER BY display_order LIMIT 1) AS image
    FROM cart_items ci
    JOIN products p ON p.id=ci.product_id
    WHERE ci.cart_id=?
");
$itemsStmt->execute([$cartId]);
$cartItems = $itemsStmt->fetchAll();

$subtotal = 0.0;
$stockOk  = true;
$errors   = [];
foreach ($cartItems as &$item) {
    $item['subtotal'] = round((float)$item['db_price'] * (int)$item['quantity'], 2);
    $subtotal += $item['subtotal'];
    if ((int)$item['stock'] < (int)$item['quantity']) {
        $stockOk = false;
        $errors[] = $item['name'] . ' is out of stock or has insufficient quantity.';
    }
}

$sessionCouponId = $_SESSION['cart_coupon_id'] ?? null;
$discountAmount  = 0.0;
$couponData      = null;
if ($sessionCouponId) {
    $cpStmt = $pdo->prepare("SELECT * FROM coupons WHERE id=? AND status='active'");
    $cpStmt->execute([$sessionCouponId]);
    $couponData = $cpStmt->fetch();
    if ($couponData) {
        $now = date('Y-m-d');
        if (($couponData['start_date'] ?? null) && $now < $couponData['start_date']) {
            $couponData = null; unset($_SESSION['cart_coupon_id']);
        } elseif (($couponData['expiry_date'] ?? null) && $now > $couponData['expiry_date']) {
            $couponData = null; unset($_SESSION['cart_coupon_id']);
        } elseif ($couponData['usage_limit'] !== null && (int)$couponData['used_count'] >= (int)$couponData['usage_limit']) {
            $couponData = null; unset($_SESSION['cart_coupon_id']);
        } elseif ($subtotal < (float)($couponData['minimum_amount'] ?? 0)) {
            $couponData = null; unset($_SESSION['cart_coupon_id']);
        }
        if ($couponData) {
            if ($couponData['discount_type'] === 'percentage') {
                $discountAmount = min((float)$couponData['discount_value'] / 100 * $subtotal,
                                     (float)($couponData['maximum_discount'] ?? PHP_INT_MAX));
            } else {
                $discountAmount = min((float)$couponData['discount_value'], $subtotal);
            }
        }
    }
}

$shippingFee = $subtotal >= 100 ? 0.0 : 10.0;
$totalAmount = max(0, $subtotal - $discountAmount + $shippingFee);

// Current user shipping info
$userName    = $_SESSION['user']['name'] ?? '';
$userEmail   = $_SESSION['user']['email'] ?? '';
$userPhone   = $_SESSION['user']['phone'] ?? '';
$userAddress = $_SESSION['user']['address'] ?? '';

// ── Handle checkout POST ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'place_order') {
    require_once __DIR__ . '/../includes/csrf.php';
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        redirect('public/checkout.php', 'Invalid security token. Please try again.', 'danger');
    }

    // Re-validate cart
    $cartStmt2 = $pdo->prepare("SELECT id FROM carts WHERE user_id=?");
    $cartStmt2->execute([$_SESSION['user_id']]);
    $newCartId = $cartStmt2->fetchColumn();
    if (!$newCartId) { redirect('public/cart.php', 'Your cart is empty.', 'warning'); }

    $itemsStmt2 = $pdo->prepare("SELECT ci.*, p.price AS db_price, p.stock FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.cart_id=?");
    $itemsStmt2->execute([$newCartId]);
    $newItems = $itemsStmt2->fetchAll();
    if (empty($newItems)) { redirect('public/cart.php', 'Your cart is empty.', 'warning'); }

    $newSubtotal = 0.0;
    $newErrors   = [];
    foreach ($newItems as &$ni) {
        $ni['subtotal'] = round((float)$ni['db_price'] * (int)$ni['quantity'], 2);
        $newSubtotal += $ni['subtotal'];
        if ((int)$ni['stock'] < (int)$ni['quantity']) {
            $newErrors[] = $ni['db_price'] . ' insufficient stock.';
        }
    }
    if ($newErrors) {
        redirect('public/cart.php', 'Some items are out of stock. Please update your cart.', 'danger');
    }

    $paymentMethod = $_POST['payment_method'] ?? 'cod';
    $shippingName   = trim($_POST['shipping_name']   ?? '');
    $shippingPhone  = trim($_POST['shipping_phone']  ?? '');
    $shippingAddr   = trim($_POST['shipping_address'] ?? '');
    $couponId = $couponData ? (int)$couponData['id'] : null;

    if (!$shippingName || !$shippingPhone || !$shippingAddr) {
        redirect('public/checkout.php', 'Please fill in all shipping details.', 'warning');
    }

    $discountAmt = 0.0;
    if ($couponData) {
        if ($couponData['discount_type'] === 'percentage') {
            $discountAmt = min((float)$couponData['discount_value'] / 100 * $newSubtotal,
                               (float)($couponData['maximum_discount'] ?? PHP_INT_MAX));
        } else {
            $discountAmt = min((float)$couponData['discount_value'], $newSubtotal);
        }
    }
    $shipFee = $newSubtotal >= 100 ? 0.0 : 10.0;
    $totalAmt = max(0, $newSubtotal - $discountAmt + $shipFee);

    try {
        $pdo->beginTransaction();

        // Decrement stock with row-level lock to prevent overselling
        foreach ($newItems as $ni) {
            $upd = $pdo->prepare("UPDATE products SET stock=stock-? WHERE id=? AND stock>=?");
            $upd->execute([(int)$ni['quantity'], $ni['product_id'], (int)$ni['quantity']]);
            if ($upd->rowCount() === 0) {
                $pdo->rollBack();
                redirect('public/checkout.php', 'One or more products are no longer available. Please refresh your cart.', 'danger');
            }
        }

        // Create order
        $orderNumber = generateOrderNumber();
        $ordStmt = $pdo->prepare("
            INSERT INTO orders (order_number, user_id, subtotal, discount_amount, shipping_fee, total_amount,
                               coupon_id, payment_method, payment_status, order_status,
                               shipping_name, shipping_phone, shipping_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, ?)
        ");
        $ordStmt->execute([
            $orderNumber, $_SESSION['user_id'], $newSubtotal, $discountAmt, $shipFee, $totalAmt,
            $couponId, $paymentMethod,
            $shippingName, $shippingPhone, $shippingAddr
        ]);
        $orderId = (int)$pdo->lastInsertId();

        // Create order items
        $oiStmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, seller_id, product_name, quantity, unit_price, subtotal)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($newItems as $ni) {
            $oiStmt->execute([
                $orderId, $ni['product_id'], $ni['seller_id'] ?? 0,
                $ni['name'], (int)$ni['quantity'], $ni['db_price'], $ni['subtotal']
            ]);
        }

        // Create payment record
        $payStmt = $pdo->prepare("
            INSERT INTO payments (order_id, payment_method, amount, currency, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $payStmt->execute([$orderId, $paymentMethod, $totalAmt, PAYPAL_CURRENCY]);

        // Decrement coupon usage
        if ($couponId) {
            $pdo->prepare("UPDATE coupons SET used_count=used_count+1 WHERE id=?")->execute([$couponId]);
        }

        // Clear cart
        $pdo->prepare("DELETE FROM cart_items WHERE cart_id=?")->execute([$newCartId]);

        $pdo->commit();
        unset($_SESSION['cart_coupon_id']);

        if ($paymentMethod === 'paypal') {
            // Redirect to PayPal (handled via PayPal API below)
            $_SESSION['pending_order_id'] = $orderId;
            redirect('public/payment.php', 'Redirecting to PayPal...', 'info');
        } else {
            // COD — mark as paid
            $pdo->prepare("UPDATE orders SET payment_status='paid', order_status='processing' WHERE id=?")
                ->execute([$orderId]);
            redirect('public/payment_success.php?id=' . $orderId, 'Order placed successfully!', 'success');
        }
    } catch (PDOException $e) {
        $pdo?->rollBack();
        logMessage('error', 'Checkout error: ' . $e->getMessage());
        redirect('public/checkout.php', 'An error occurred. Please try again.', 'danger');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Checkout — <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
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

    <!-- Steps -->
    <nav aria-label="breadcrumb" class="mb-4">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/public/shop.php">Shop</a></li>
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/public/cart.php">Cart</a></li>
        <li class="breadcrumb-item active">Checkout</li>
      </ol>
    </nav>

    <h2 class="fw-bold mb-4"><i class="fas fa-lock me-2"></i>Checkout</h2>

    <?php if ($errors): ?>
      <div class="alert alert-warning">
        <?php foreach ($errors as $err): ?>
          <div><i class="fas fa-exclamation-triangle me-1"></i><?= e($err) ?></div>
        <?php endforeach; ?>
        <a href="<?= APP_URL ?>/public/cart.php" class="btn btn-sm btn-outline-warning mt-2">Go to Cart</a>
      </div>
    <?php endif; ?>

    <form method="POST" id="checkout-form" novalidate>
      <?= csrfField() ?>
      <input type="hidden" name="action" value="place_order">
      <input type="hidden" name="coupon_id" value="<?= $couponData ? (int)$couponData['id'] : '' ?>">

      <div class="row g-4">
        <!-- ── Shipping Info ── -->
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
              <h5 class="fw-bold mb-0"><i class="fas fa-shipping-fast me-2"></i>Shipping Information</h5>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Full Name <span class="text-danger">*</span></label>
                  <input type="text" name="shipping_name" class="form-control" required
                         value="<?= e($shippingName) ?>" placeholder="John Doe">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email <span class="text-danger">*</span></label>
                  <input type="email" name="email" class="form-control" required
                         value="<?= e($userEmail) ?>" placeholder="john@example.com">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone <span class="text-danger">*</span></label>
                  <input type="tel" name="shipping_phone" class="form-control" required
                         value="<?= e($shippingPhone) ?>" placeholder="+1 555 000 0000">
                </div>
                <div class="col-12">
                  <label class="form-label">Shipping Address <span class="text-danger">*</span></label>
                  <textarea name="shipping_address" class="form-control" rows="3" required
                            placeholder="123 Main St, Apt 4, City, State, ZIP"><?= e($shippingAddr) ?></textarea>
                </div>
              </div>
            </div>
          </div>

          <!-- ── Payment Method ── -->
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
              <h5 class="fw-bold mb-0"><i class="fas fa-credit-card me-2"></i>Payment Method</h5>
            </div>
            <div class="card-body">
              <div class="form-check mb-3">
                <input class="form-check-input" type="radio" name="payment_method" id="pay_cod" value="cod" checked>
                <label class="form-check-label" for="pay_cod">
                  <i class="fas fa-money-bill-wave me-1"></i>Cash on Delivery
                  <span class="text-muted small">— Pay when you receive your order</span>
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="payment_method" id="pay_paypal" value="paypal">
                <label class="form-check-label" for="pay_paypal">
                  <i class="fab fa-paypal me-1"></i>PayPal
                  <span class="text-muted small">— Securely pay with your PayPal account</span>
                </label>
              </div>
              <div class="form-check disabled mt-2">
                <input class="form-check-input" type="radio" name="payment_method" id="pay_bank" value="bank" disabled>
                <label class="form-check-label text-muted" for="pay_bank">
                  <i class="fas fa-university me-1"></i>Bank Transfer (Coming Soon)
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Order Summary ── -->
        <div class="col-lg-5">
          <div class="card border-0 shadow-sm mb-4" id="order-summary">
            <div class="card-header bg-white py-3">
              <h5 class="fw-bold mb-0"><i class="fas fa-receipt me-2"></i>Order Summary</h5>
            </div>
            <div class="card-body">
              <?php foreach ($cartItems as $item): ?>
                <div class="d-flex justify-content-between align-items-start mb-2 pb-2 border-bottom">
                  <div class="d-flex align-items-center gap-2" style="min-width:0">
                    <img src="<?= APP_URL ?>/<?= e($item['image'] ?? 'uploads/products/default-product.jpg') ?>"
                         alt="" class="rounded" style="width:48px;height:48px;object-fit:cover;flex-shrink:0">
                    <div style="min-width:0">
                      <div class="small fw-semibold text-truncate" style="max-width:160px">
                        <?= e($item['name']) ?>
                      </div>
                      <div class="small text-muted">Qty: <?= (int)$item['quantity'] ?></div>
                    </div>
                  </div>
                  <span class="small fw-semibold ms-2"><?= formatCurrency($item['subtotal']) ?></span>
                </div>
              <?php endforeach; ?>

              <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Subtotal</span>
                <span><?= formatCurrency($subtotal) ?></span>
              </div>
              <?php if ($discountAmount > 0): ?>
                <div class="d-flex justify-content-between mb-1 text-success">
                  <span>Discount</span>
                  <span>-<?= formatCurrency($discountAmount) ?></span>
                </div>
                <div class="small text-success mb-1">
                  <i class="fas fa-tag me-1"></i>
                  <?= e($couponData['code'] ?? '') ?>
                </div>
              <?php endif; ?>
              <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Shipping</span>
                <span>
                  <?= $shippingFee === 0
                      ? '<span class="text-success">FREE</span>'
                      : formatCurrency($shippingFee) ?>
                </span>
              </div>
              <hr>
              <div class="d-flex justify-content-between">
                <span class="fw-bold fs-5">Total</span>
                <span class="fw-bold fs-5 text-primary"><?= formatCurrency($totalAmount) ?></span>
              </div>
            </div>
          </div>

          <!-- ── Coupon ── -->
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
              <h6 class="fw-bold mb-3"><i class="fas fa-ticket-alt me-1"></i>Have a coupon?</h6>
              <div class="input-group">
                <input type="text" class="form-control" id="coupon_code"
                       placeholder="Enter coupon code"
                       value="<?= e($couponCode) ?>">
                <button type="button" class="btn btn-brand" id="btn-apply-coupon">Apply</button>
              </div>
              <div id="coupon-result" class="mt-2"></div>
              <?php if ($couponCode): ?>
                <div class="mt-2">
                  <span class="badge bg-success me-1"><?= e($couponCode) ?></span>
                  <button type="button" class="btn btn-sm btn-outline-danger btn-remove-coupon">
                    <i class="fas fa-times"></i> Remove
                  </button>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <button type="submit" class="btn btn-brand w-100 btn-lg">
            <i class="fas fa-check-circle me-2"></i>Place Order
          </button>
          <p class="small text-muted text-center mt-2">
            <i class="fas fa-shield-alt me-1"></i>Your information is secure and encrypted.
          </p>
        </div>
      </div>
    </form>
  </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/checkout.js"></script>
</body>
</html>
