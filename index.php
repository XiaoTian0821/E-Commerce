<?php
/**
 * Home Page — NovaMart
 */
define('APP_START', true);
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/navbar.php';

$pageTitle = 'Home';
$featuredProducts = [];
$categories = [];
$newArrivals = [];

try {
    $cats = $pdo->query("SELECT * FROM categories WHERE status='active' ORDER BY id LIMIT 10");
    $categories = $cats->fetchAll();

    $feat = $pdo->query("
        SELECT p.*, u.name AS seller_name,
               (SELECT image_path FROM product_images pi WHERE pi.product_id=p.id ORDER BY display_order LIMIT 1) AS image
        FROM products p
        JOIN users u ON u.id=p.seller_id
        WHERE p.featured=1 AND p.status='active'
        ORDER BY p.created_at DESC LIMIT 8
    ");
    $featuredProducts = $feat->fetchAll();

    $new = $pdo->query("
        SELECT p.*, u.name AS seller_name,
               (SELECT image_path FROM product_images pi WHERE pi.product_id=p.id ORDER BY display_order LIMIT 1) AS image
        FROM products p
        JOIN users u ON u.id=p.seller_id
        WHERE p.status='active'
        ORDER BY p.created_at DESC LIMIT 8
    ");
    $newArrivals = $new->fetchAll();
} catch (PDOException $e) {
    logMessage('error', 'Home page DB error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(APP_NAME) ?> — <?= e(APP_TAGLINE) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

<?= $navbar ?>

<!-- ── Hero ── -->
<section class="hero-section">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6">
        <h1>Discover Amazing Products at Unbeatable Prices</h1>
        <p class="mb-4">Shop thousands of items from trusted sellers. Fast shipping, secure payments, and great deals every day.</p>
        <a href="<?= APP_URL ?>/public/shop.php" class="btn btn-light btn-lg px-4 fw-bold text-primary">
          <i class="fas fa-shopping-bag me-2"></i>Start Shopping
        </a>
      </div>
      <div class="col-lg-6 text-center d-none d-lg-block">
        <i class="fas fa-shopping-cart" style="font-size:12rem;opacity:.15;color:#fff"></i>
      </div>
    </div>
  </div>
</section>

<!-- ── Categories ── -->
<section class="py-5">
  <div class="container">
    <h2 class="section-title">Shop by Category</h2>
    <div class="row g-3">
      <?php foreach ($categories as $cat): ?>
        <div class="col-6 col-md-4 col-lg-2">
          <a href="<?= APP_URL ?>/public/shop.php?category=<?= (int)$cat['id'] ?>" class="category-card">
            <div class="cat-icon">
              <?php
                $icons = ['Electronics'=>'fa-bolt','Fashion'=>'fa-tshirt','Home & Kitchen'=>'fa-kitchen-set',
                          'Sports & Outdoors'=>'fa-person-running','Books'=>'fa-book','Beauty & Health'=>'fa-spa',
                          'Toys & Games'=>'fa-puzzle-piece','Automotive'=>'fa-car','Garden'=>'fa-seedling','Office'=>'fa-briefcase'];
                echo '<i class="fas ' . ($icons[$cat['name']] ?? 'fa-tag') . '"></i>';
              ?>
            </div>
            <div class="cat-name"><?= e($cat['name']) ?></div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── Featured Products ── -->
<?php if (!empty($featuredProducts)): ?>
<section class="py-5 bg-white">
  <div class="container">
    <h2 class="section-title">Featured Products</h2>
    <div class="row g-4">
      <?php foreach ($featuredProducts as $p): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="product-card">
            <div class="card-img-wrap">
              <a href="<?= APP_URL ?>/public/product.php?slug=<?= e($p['slug']) ?>">
                <img src="<?= APP_URL ?>/<?= e($p['image'] ?? 'uploads/products/default-product.jpg') ?>"
                     alt="<?= e($p['name']) ?>" loading="lazy">
              </a>
              <?php if ($p['featured']): ?>
                <span class="badge-featured">Featured</span>
              <?php endif; ?>
            </div>
            <div class="card-body">
              <div class="product-name">
                <a href="<?= APP_URL ?>/public/product.php?slug=<?= e($p['slug']) ?>"><?= e($p['name']) ?></a>
              </div>
              <div class="product-seller">by <?= e($p['seller_name']) ?></div>
              <div class="product-price"><?= formatCurrency((float)$p['price']) ?></div>
              <div class="product-stock <?= (int)$p['stock'] > 0 ? 'stock-in' : 'stock-out' ?>">
                <i class="fas fa-<?= (int)$p['stock'] > 0 ? 'check-circle' : 'times-circle' ?>"></i>
                <?= (int)$p['stock'] > 0 ? 'In Stock' : 'Out of Stock' ?>
              </div>
              <div class="card-actions">
                <a href="<?= APP_URL ?>/public/product.php?slug=<?= e($p['slug']) ?>" class="btn btn-sm btn-outline-secondary">
                  <i class="fas fa-eye me-1"></i>View
                </a>
                <?php if (isLoggedIn()): ?>
                  <form method="POST" action="<?= APP_URL ?>/public/cart.php" class="d-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" class="btn btn-sm btn-brand" title="Add to cart">
                      <i class="fas fa-cart-plus"></i>
                    </button>
                  </form>
                <?php else: ?>
                  <a href="<?= APP_URL ?>/public/login.php" class="btn btn-sm btn-brand" title="Login to add to cart">
                    <i class="fas fa-cart-plus"></i>
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-4">
      <a href="<?= APP_URL ?>/public/shop.php" class="btn btn-brand-outline">
        View All Products <i class="fas fa-arrow-right ms-1"></i>
      </a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── New Arrivals ── -->
<?php if (!empty($newArrivals)): ?>
<section class="py-5">
  <div class="container">
    <h2 class="section-title">New Arrivals</h2>
    <div class="row g-4">
      <?php foreach (array_slice($newArrivals, 0, 4) as $p): ?>
        <div class="col-6 col-md-3">
          <div class="product-card">
            <div class="card-img-wrap">
              <a href="<?= APP_URL ?>/public/product.php?slug=<?= e($p['slug']) ?>">
                <img src="<?= APP_URL ?>/<?= e($p['image'] ?? 'uploads/products/default-product.jpg') ?>"
                     alt="<?= e($p['name']) ?>" loading="lazy">
              </a>
            </div>
            <div class="card-body">
              <div class="product-name">
                <a href="<?= APP_URL ?>/public/product.php?slug=<?= e($p['slug']) ?>"><?= e($p['name']) ?></a>
              </div>
              <div class="product-price"><?= formatCurrency((float)$p['price']) ?></div>
              <div class="product-stock stock-in">
                <i class="fas fa-check-circle"></i> In Stock
              </div>
              <div class="card-actions">
                <a href="<?= APP_URL ?>/public/product.php?slug=<?= e($p['slug']) ?>" class="btn btn-sm btn-outline-secondary w-100">
                  <i class="fas fa-eye me-1"></i>Details
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── Promo Banner ── -->
<section class="py-5" style="background:linear-gradient(135deg,#1E293B 0%,#334155 100%);color:#fff">
  <div class="container text-center">
    <h2 class="fw-bold mb-3"><i class="fas fa-bolt text-warning me-2"></i>Use Code <span class="text-warning">WELCOME10</span> for 10% Off</h2>
    <p class="mb-4 opacity-75">New customers get an exclusive discount on orders over $50. Shop now!</p>
    <a href="<?= APP_URL ?>/public/register.php" class="btn btn-accent btn-lg px-5">
      <i class="fas fa-user-plus me-2"></i>Create Account
    </a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
