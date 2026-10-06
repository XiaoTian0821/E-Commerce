<?php
/**
 * Cart Page
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Shopping Cart';

// ── AJAX actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    $action = $_GET['action'] ?? '';
    $isAjax = $action !== '';
    header('Content-Type: application/json');

    // AJAX: Add to cart
    if (($action === 'add' || (!$isAjax && $_SERVER['REQUEST_METHOD'] === 'POST')) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../includes/csrf.php';
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity  = max(1, (int)($_POST['quantity'] ?? 1));
        if (!$productId) { echo json_encode(['success'=>false,'message'=>'Invalid product.']); exit; }
        if (!csrf_verify($_POST['csrf_token'] ?? '')) { echo json_encode(['success'=>false,'message'=>'Security error.']); exit; }
        requireLogin();

        // Verify product
        $prod = $pdo->prepare("SELECT id, price, stock, status FROM products WHERE id=? AND status IN ('active','out_of_stock')");
        $prod->execute([$productId]);
        $p = $prod->fetch();
        if (!$p) { echo json_encode(['success'=>false,'message'=>'Product not found.']); exit; }
        if ((int)$p['stock'] < $quantity) { echo json_encode(['success'=>false,'message'=>'Not enough stock.']); exit; }

        // Get or create cart
        $cartStmt = $pdo->prepare("SELECT id FROM carts WHERE user_id=?");
        $cartStmt->execute([$_SESSION['user_id']]);
        $cartId = $cartStmt->fetchColumn();
        if (!$cartId) {
            $ins = $pdo->prepare("INSERT INTO carts (user_id) VALUES (?)");
            $ins->execute([$_SESSION['user_id']]);
            $cartId = (int)$pdo->lastInsertId();
        }

        // Upsert cart item
        $check = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE cart_id=? AND product_id=?");
        $check->execute([$cartId, $productId]);
        $existing = $check->fetch();
        if ($existing) {
            $newQty = min($existing['quantity'] + $quantity, (int)$p['stock']);
            $upd = $pdo->prepare("UPDATE cart_items SET quantity=?, price=? WHERE id=?");
            $upd->execute([$newQty, $p['price'], $existing['id']]);
        } else {
            $ins = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $ins->execute([$cartId, $productId, $quantity, $p['price']]);
        }

        // Count cart items
        $cnt = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM cart_items ci JOIN carts c ON ci.cart_id=c.id WHERE c.user_id=?");
        $cnt->execute([$_SESSION['user_id']]);
        if (!$isAjax) {
            redirect('public/cart.php', 'Added to cart!', 'success');
        }
        echo json_encode(['success'=>true,'message'=>'Added to cart!','cart_count'=>(int)$cnt->fetchColumn()]);
        exit;
    }

    // AJAX: Remove from cart
    if (( $_GET['action'] ?? '') === 'remove') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        if (!$itemId) { echo json_encode(['success'=>false]); exit; }
        $del = $pdo->prepare("DELETE ci FROM cart_items AS ci INNER JOIN carts AS c ON ci.cart_id=c.id WHERE ci.id=? AND c.user_id=?");
        $del->execute([$itemId, $_SESSION['user_id']]);
        echo json_encode(['success'=>true,'message'=>'Item removed.']);
        exit;
    }

    // AJAX: Update quantity
    if (( $_GET['action'] ?? '') === 'update') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $qty    = max(1, (int)($_POST['quantity'] ?? 1));
        if (!$itemId) { echo json_encode(['success'=>false]); exit; }
        $row = $pdo->prepare("SELECT ci.*, p.stock, p.price AS db_price FROM cart_items ci JOIN products p ON p.id=ci.product_id JOIN carts c ON c.id=ci.cart_id WHERE ci.id=? AND c.user_id=?");
        $row->execute([$itemId, $_SESSION['user_id']]);
        $item = $row->fetch();
        if (!$item) { echo json_encode(['success'=>false]); exit; }
        $newQty = min($qty, (int)$item['stock']);
        $upd = $pdo->prepare("UPDATE cart_items SET quantity=? WHERE id=?");
        $upd->execute([$newQty, $itemId]);
        $subtotal = round((float)$item['db_price'] * $newQty, 2);
        echo json_encode(['success'=>true,'quantity'=>$newQty,'subtotal'=>$subtotal,'old_quantity'=>$item['quantity']]);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action.']);
    exit;
}

// ── Page load: normal rendering ──
requireLogin();

// Fetch cart items
$cartStmt = $pdo->prepare("SELECT c.id AS cart_id FROM carts c WHERE c.user_id=?");
$cartStmt->execute([$_SESSION['user_id']]);
$cartRow = $cartStmt->fetch();
if (!$cartRow) {
    $ins = $pdo->prepare("INSERT INTO carts (user_id) VALUES (?)");
    $ins->execute([$_SESSION['user_id']]);
    $cartId = (int)$pdo->lastInsertId();
} else {
    $cartId = (int)$cartRow['cart_id'];
}

$itemsStmt = $pdo->prepare("
    SELECT ci.id, ci.product_id, ci.quantity, ci.price,
           p.name, p.slug, p.stock, p.sku,
           (SELECT image_path FROM product_images pi WHERE pi.product_id=p.id ORDER BY display_order LIMIT 1) AS image
    FROM cart_items ci
    JOIN products p ON p.id=ci.product_id
    WHERE ci.cart_id=?
    ORDER BY ci.created_at DESC
");
$itemsStmt->execute([$cartId]);
$cartItems = $itemsStmt->fetchAll();

$subtotal = 0.0;
foreach ($cartItems as &$item) {
    $item['subtotal'] = round((float)$item['price'] * (int)$item['quantity'], 2);
    $subtotal += $item['subtotal'];
}

$sessionCouponId = $_SESSION['cart_coupon_id'] ?? null;
$discountAmount  = 0.0;
$couponCode      = '';
if ($sessionCouponId) {
    $cpStmt = $pdo->prepare("SELECT * FROM coupons WHERE id=? AND status='active'");
    $cpStmt->execute([$sessionCouponId]);
    $coupon = $cpStmt->fetch();
    if ($coupon) {
        $couponCode = $coupon['code'];
        if ($coupon['discount_type'] === 'percentage') {
            $discountAmount = min((float)$coupon['discount_value'] / 100 * $subtotal,
                                 (float)($coupon['maximum_discount'] ?? PHP_INT_MAX));
        } else {
            $discountAmount = min((float)$coupon['discount_value'], $subtotal);
        }
    } else {
        unset($_SESSION['cart_coupon_id']);
        $sessionCouponId = null;
    }
}

$shippingFee = $subtotal >= 100 ? 0.0 : 10.0;
$totalAmount = max(0, $subtotal - $discountAmount + $shippingFee);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shopping Cart — <?= e(APP_NAME) ?></title>
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

    <h2 class="fw-bold mb-4"><i class="fas fa-shopping-cart me-2"></i>Shopping Cart</h2>

    <?php if (empty($cartItems)): ?>
      <div class="text-center py-5">
        <i class="fas fa-cart-arrow-down fa-4x text-muted mb-3"></i>
        <h5>Your cart is empty</h5>
        <p class="text-muted">Browse our shop and add items to your cart.</p>
        <a href="<?= APP_URL ?>/public/shop.php" class="btn btn-brand">
          <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
        </a>
      </div>
    <?php else: ?>
      <div class="row g-4">
        <!-- Cart Items -->
        <div class="col-lg-8">
          <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
              <table class="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($cartItems as $item): ?>
                    <tr data-item-id="<?= $item['id'] ?>">
                      <td>
                        <div class="d-flex align-items-center gap-3">
                          <img src="<?= APP_URL ?>/<?= e($item['image'] ?? 'uploads/products/default-product.jpg') ?>"
                               alt="<?= e($item['name']) ?>"
                               class="rounded" style="width:64px;height:64px;object-fit:cover">
                          <div>
                            <a href="<?= APP_URL ?>/public/product.php?slug=<?= e($item['slug']) ?>" class="fw-semibold text-dark">
                              <?= e($item['name']) ?>
                            </a>
                            <?php if ($item['sku']): ?>
                              <div class="small text-muted">SKU: <?= e($item['sku']) ?></div>
                            <?php endif; ?>
                            <div class="small <?= (int)$item['stock'] > 0 ? 'text-success' : 'text-danger' ?>">
                              <i class="fas fa-<?= (int)$item['stock'] > 0 ? 'check-circle' : 'times-circle' ?>"></i>
                              <?= (int)$item['stock'] > 0 ? 'In Stock' : 'Out of Stock' ?>
                            </div>
                          </div>
                        </div>
                      </td>
                      <td class="align-middle"><?= formatCurrency((float)$item['price']) ?></td>
                      <td class="align-middle">
                        <div class="d-flex align-items-center gap-1">
                          <input type="number" name="qty" value="<?= $item['quantity'] ?>" min="1"
                                 max="<?= (int)$item['stock'] ?>"
                                 class="form-control form-control-sm" style="width:60px">
                          <button type="button" class="btn btn-sm btn-outline-primary btn-update-cart"
                                  data-item-id="<?= $item['id'] ?>" title="Update">
                            <i class="fas fa-sync"></i>
                          </button>
                        </div>
                      </td>
                      <td class="align-middle cart-subtotal fw-bold"><?= formatCurrency($item['subtotal']) ?></td>
                      <td class="align-middle">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-cart"
                                data-item-id="<?= $item['id'] ?>" title="Remove">
                          <i class="fas fa-trash"></i>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="d-flex justify-content-between mt-3">
            <a href="<?= APP_URL ?>/public/shop.php" class="btn btn-outline-secondary">
              <i class="fas fa-arrow-left me-1"></i>Continue Shopping
            </a>
            <a href="<?= APP_URL ?>/public/wishlist.php" class="btn btn-outline-info">
              <i class="fas fa-heart me-1"></i>View Wishlist
            </a>
          </div>
        </div>

        <!-- Cart Summary -->
        <div class="col-lg-4">
          <div class="card border-0 shadow-sm" id="cart-summary">
            <div class="card-body">
              <h5 class="fw-bold mb-3">Order Summary</h5>

              <div class="d-flex justify-content-between mb-2">
                <span>Subtotal</span>
                <span class="fw-semibold"><?= formatCurrency($subtotal) ?></span>
              </div>
              <?php if ($discountAmount > 0): ?>
                <div class="d-flex justify-content-between mb-2 text-success">
                  <span>Discount</span>
                  <span class="fw-semibold">-<?= formatCurrency($discountAmount) ?></span>
                </div>
                <?php if ($couponCode): ?>
                  <div class="small text-success mb-2">
                    <i class="fas fa-tag me-1"></i>Coupon: <strong><?= e($couponCode) ?></strong>
                  </div>
                <?php endif; ?>
              <?php endif; ?>
              <div class="d-flex justify-content-between mb-2">
                <span>Shipping</span>
                <span class="fw-semibold">
                  <?= $shippingFee === 0 ? '<span class="text-success">FREE</span>' : formatCurrency($shippingFee) ?>
                </span>
              </div>
              <hr>
              <div class="d-flex justify-content-between mb-3">
                <span class="fw-bold">Total</span>
                <span class="fw-bold fs-5 text-primary"><?= formatCurrency($totalAmount) ?></span>
              </div>

              <!-- Coupon form -->
              <div class="mb-3">
                <label class="form-label small">Coupon Code</label>
                <div class="input-group">
                  <input type="text" class="form-control form-control-sm" id="coupon_code"
                         placeholder="Enter code" value="<?= e($couponCode) ?>">
                  <button type="button" class="btn btn-sm btn-brand" id="btn-apply-coupon">Apply</button>
                </div>
                <div id="coupon-result"></div>
              </div>

              <a href="<?= APP_URL ?>/public/checkout.php" class="btn btn-brand w-100 btn-lg">
                <i class="fas fa-lock me-2"></i>Proceed to Checkout
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/cart.js?v=<?= filemtime(__DIR__ . '/../assets/js/cart.js') ?>"></script>
<script src="<?= APP_URL ?>/assets/js/checkout.js?v=<?= filemtime(__DIR__ . '/../assets/js/checkout.js') ?>"></script>
</body>
</html>
