<?php
/**
 * Wishlist Page
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navbar.php';

$pageTitle = 'My Wishlist';
requireLogin();

// Handle AJAX remove
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'remove') {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../includes/csrf.php';
    $wishId = (int)($_POST['wish_id'] ?? 0);
    if (!$wishId || !csrf_verify($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success'=>false]); exit;
    }
    $del = $pdo->prepare("DELETE FROM wishlists WHERE id=? AND user_id=?");
    $del->execute([$wishId, $_SESSION['user_id']]);
    echo json_encode(['success'=>true]);
    exit;
}

// Handle move to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'move_to_cart') {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../includes/csrf.php';
    $wishId = (int)($_POST['wish_id'] ?? 0);
    if (!$wishId || !csrf_verify($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit;
    }
    $row = $pdo->prepare("SELECT w.*, p.stock, p.price FROM wishlists w JOIN products p ON p.id=w.product_id WHERE w.id=? AND w.user_id=?");
    $row->execute([$wishId, $_SESSION['user_id']]);
    $item = $row->fetch();
    if (!$item) { echo json_encode(['success'=>false,'message'=>'Item not found.']); exit; }
    if ((int)$item['stock'] < 1) { echo json_encode(['success'=>false,'message'=>'Out of stock.']); exit; }
    // Add to cart
    $cartStmt = $pdo->prepare("SELECT id FROM carts WHERE user_id=?");
    $cartStmt->execute([$_SESSION['user_id']]);
    $cartId = $cartStmt->fetchColumn();
    if (!$cartId) {
        $ins = $pdo->prepare("INSERT INTO carts (user_id) VALUES (?)");
        $ins->execute([$_SESSION['user_id']]);
        $cartId = (int)$pdo->lastInsertId();
    }
    $check = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE cart_id=? AND product_id=?");
    $check->execute([$cartId, $item['product_id']]);
    $existing = $check->fetch();
    if ($existing) {
        $newQty = min($existing['quantity'] + 1, (int)$item['stock']);
        $upd = $pdo->prepare("UPDATE cart_items SET quantity=?, price=? WHERE id=?");
        $upd->execute([$newQty, $item['price'], $existing['id']]);
    } else {
        $ins = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity, price) VALUES (?, ?, 1, ?)");
        $ins->execute([$cartId, $item['product_id'], $item['price']]);
    }
    // Remove from wishlist
    $pdo->prepare("DELETE FROM wishlists WHERE id=?")->execute([$wishId]);
    echo json_encode(['success'=>true,'message'=>'Moved to cart!']);
    exit;
}

// Fetch wishlist items
$wishStmt = $pdo->prepare("
    SELECT w.id AS wish_id, w.product_id, p.name, p.slug, p.price, p.stock, p.sku,
           (SELECT image_path FROM product_images pi WHERE pi.product_id=p.id ORDER BY display_order LIMIT 1) AS image
    FROM wishlists w
    JOIN products p ON p.id=w.product_id
    WHERE w.user_id=?
    ORDER BY w.created_at DESC
");
$wishStmt->execute([$_SESSION['user_id']]);
$wishlist = $wishStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Wishlist — <?= e(APP_NAME) ?></title>
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

    <h2 class="fw-bold mb-4"><i class="fas fa-heart text-danger me-2"></i>My Wishlist</h2>

    <?php if (empty($wishlist)): ?>
      <div class="text-center py-5">
        <i class="fas fa-heart-broken fa-3x text-muted mb-3"></i>
        <h5>Your wishlist is empty</h5>
        <p class="text-muted">Browse products and save your favorites!</p>
        <a href="<?= APP_URL ?>/public/shop.php" class="btn btn-brand">
          <i class="fas fa-shopping-bag me-2"></i>Browse Shop
        </a>
      </div>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($wishlist as $item):
          $inStock = (int)$item['stock'] > 0;
        ?>
          <div class="col-6 col-md-4 col-lg-3">
            <div class="product-card">
              <div class="card-img-wrap">
                <a href="<?= APP_URL ?>/public/product.php?slug=<?= e($item['slug']) ?>">
                  <img src="<?= APP_URL ?>/<?= e($item['image'] ?? 'uploads/products/default-product.jpg') ?>"
                       alt="<?= e($item['name']) ?>" loading="lazy">
                </a>
              </div>
              <div class="card-body">
                <div class="product-name">
                  <a href="<?= APP_URL ?>/public/product.php?slug=<?= e($item['slug']) ?>"><?= e($item['name']) ?></a>
                </div>
                <div class="product-price"><?= formatCurrency((float)$item['price']) ?></div>
                <div class="product-stock <?= $inStock ? 'stock-in' : 'stock-out' ?>">
                  <i class="fas fa-<?= $inStock ? 'check-circle' : 'times-circle' ?>"></i>
                  <?= $inStock ? 'In Stock' : 'Out of Stock' ?>
                </div>
                <div class="card-actions">
                  <?php if ($inStock): ?>
                    <form method="POST" action="<?= APP_URL ?>/public/wishlist.php?action=move_to_cart" class="d-inline">
                      <?= csrfField() ?>
                      <input type="hidden" name="wish_id" value="<?= $item['wish_id'] ?>">
                      <button type="submit" class="btn btn-sm btn-brand w-100">
                        <i class="fas fa-cart-plus me-1"></i>Move to Cart
                      </button>
                    </form>
                  <?php else: ?>
                    <button class="btn btn-sm btn-secondary w-100" disabled>Out of Stock</button>
                  <?php endif; ?>
                  <button type="button" class="btn btn-sm btn-outline-danger btn-wishlist-remove"
                          data-id="<?= $item['wish_id'] ?>" title="Remove">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.btn-wishlist-remove').forEach(btn => {
    btn.addEventListener('click', function() {
      if (!confirm('Remove this item from your wishlist?')) return;
      const fd = new FormData();
      fd.append('wish_id', this.dataset.id);
      fd.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
      fetch('<?= APP_URL ?>/public/wishlist.php?action=remove', {
        method: 'POST', body: fd, credentials: 'same-origin'
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) this.closest('.col-6,.col-md-4,.col-lg-3').remove();
      });
    });
  });
});
</script>
</body>
</html>
