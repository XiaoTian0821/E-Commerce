<?php
/**
 * Shop Page — Product listing with search, filter, sort & pagination
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navbar.php';
$pageTitle = 'Shop';

// ── Pagination ─────────────────────────────────────────
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPag = ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPag;

// ── Filters ────────────────────────────────────────────
$search   = trim($_GET['search']   ?? '');
$category = (int)($_GET['category'] ?? 0);
$minPrice = floatval($_GET['min_price'] ?? 0);
$maxPrice = floatval($_GET['max_price'] ?? PHP_INT_MAX);
$inStock  = $_GET['in_stock'] ?? null;
$sort     = $_GET['sort'] ?? 'newest';

// Build conditions
$wheres = ["p.status = 'active'"];
$params = [];

if ($search) {
    $wheres[] = "(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($category > 0) {
    $wheres[] = "p.category_id = ?";
    $params[] = $category;
}
if ($minPrice > 0) {
    $wheres[] = "p.price >= ?";
    $params[] = $minPrice;
}
if ($maxPrice < PHP_INT_MAX) {
    $wheres[] = "p.price <= ?";
    $params[] = $maxPrice;
}
if ($inStock !== null) {
    $wheres[] = "p.stock > 0";
}

$whereSql = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

// Sorting
$sortMap = [
    'newest'    => 'p.created_at DESC',
    'price_asc' => 'p.price ASC',
    'price_desc'=> 'p.price DESC',
    'rating'    => 'avg_rating DESC',
    'popular'   => 'order_count DESC',
];
$orderBy = $sortMap[$sort] ?? $sortMap['newest'];

// Count total
$countSql = "SELECT COUNT(*) FROM products p $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalProducts / $perPag));

// Fetch products
$sql = "
    SELECT p.*, u.name AS seller_name,
           COALESCE(AVG(pr.rating),0) AS avg_rating,
           COUNT(DISTINCT pri.id) AS review_count,
           (SELECT image_path FROM product_images pi WHERE pi.product_id=p.id ORDER BY display_order LIMIT 1) AS image
    FROM products p
    JOIN users u ON u.id=p.seller_id
    LEFT JOIN product_reviews pr ON pr.product_id=p.id AND pr.status='approved'
    LEFT JOIN product_images pri ON pri.product_id=p.id
    $whereSql
    GROUP BY p.id
    ORDER BY $orderBy
    LIMIT $perPag OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Categories for filter
$categories = $pdo->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shop — <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
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

    <!-- ── Search & Filters Bar ── -->
    <div class="card mb-4 shadow-sm">
      <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label small">Search</label>
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search products…" value="<?= e($search) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label small">Category</label>
            <select name="category" class="form-select form-select-sm">
              <option value="0">All Categories</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $category === (int)$cat['id'] ? 'selected' : '' ?>>
                  <?= e($cat['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small">Max Price</label>
            <input type="number" name="max_price" class="form-control form-control-sm"
                   placeholder="Any" step="0.01" value="<?= htmlspecialchars($maxPrice, ENT_QUOTES) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label small">Sort By</label>
            <select name="sort" class="form-select form-select-sm">
              <option value="newest"    <?= $sort==='newest'    ? 'selected' : '' ?>>Newest</option>
              <option value="price_asc" <?= $sort==='price_asc' ? 'selected' : '' ?>>Price: Low → High</option>
              <option value="price_desc"><?= $sort==='price_desc' ? 'selected' : '' ?>>Price: High → Low</option>
              <option value="rating"    <?= $sort==='rating'    ? 'selected' : '' ?>>Best Rated</option>
              <option value="popular"   <?= $sort==='popular'   ? 'selected' : '' ?>>Most Popular</option>
            </select>
          </div>
          <div class="col-md-1">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="in_stock" value="1"
                     <?= $inStock ? 'checked' : '' ?>>
              <label class="form-check-label small">In Stock</label>
            </div>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-brand btn-sm w-100">
              <i class="fas fa-search me-1"></i>Apply
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ── Results count ── -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <span class="text-muted small">
        <?= $totalProducts ?> product<?= $totalProducts !== 1 ? 's' : '' ?> found
        <?php if ($search): ?>
          for "<strong><?= e($search) ?></strong>"
        <?php endif; ?>
      </span>
    </div>

    <!-- ── Product Grid ── -->
    <?php if (empty($products)): ?>
      <div class="text-center py-5">
        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
        <h5>No products found</h5>
        <p class="text-muted">Try adjusting your filters or <a href="<?= APP_URL ?>/public/shop.php">view all products</a>.</p>
      </div>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($products as $p):
          $rating   = (float)$p['avg_rating'];
          $stars    = round($rating);
          $stockTxt = (int)$p['stock'] > 0
            ? '<span class="stock-in"><i class="fas fa-check-circle"></i> In Stock (' . (int)$p['stock'] . ')</span>'
            : '<span class="stock-out"><i class="fas fa-times-circle"></i> Out of Stock</span>';
          $starHtml = '';
          for ($i = 1; $i <= 5; $i++) {
            $starHtml .= $i <= $stars ? '<i class="fas fa-star"></i>' : '<i class="far fa-star empty"></i>';
          }
        ?>
          <div class="col-6 col-md-4 col-lg-3">
            <div class="product-card">
              <div class="card-img-wrap">
                <a href="<?= APP_URL ?>/public/product.php?slug=<?= e($p['slug']) ?>">
                  <img src="<?= APP_URL ?>/<?= e($p['image'] ?? 'uploads/products/default-product.jpg') ?>"
                       alt="<?= e($p['name']) ?>" loading="lazy">
                </a>
                <?php if ($p['featured']): ?>
                  <span class="badge-discount">★ Featured</span>
                <?php endif; ?>
              </div>
              <div class="card-body">
                <div class="product-name">
                  <a href="<?= APP_URL ?>/public/product.php?slug=<?= e($p['slug']) ?>"><?= e($p['name']) ?></a>
                </div>
                <div class="product-seller">by <?= e($p['seller_name']) ?></div>
                <div class="product-rating stars"><?= $starHtml ?>
                  <span class="text-muted small">(<?= (int)$p['review_count'] ?>)</span>
                </div>
                <div class="product-price"><?= formatCurrency((float)$p['price']) ?></div>
                <div class="product-stock small"><?= $stockTxt ?></div>
                <div class="card-actions">
                  <a href="<?= APP_URL ?>/public/product.php?slug=<?= e($p['slug']) ?>"
                     class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-eye"></i>
                  </a>
                  <?php if (isLoggedIn() && (int)$p['stock'] > 0): ?>
                    <form method="POST" action="<?= APP_URL ?>/public/cart.php" class="d-inline">
                      <?= csrfField() ?>
                      <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                      <input type="hidden" name="quantity" value="1">
                      <button type="submit" class="btn btn-sm btn-brand" title="Add to cart">
                        <i class="fas fa-cart-plus"></i>
                      </button>
                    </form>
                  <?php elseif (!isLoggedIn()): ?>
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

      <!-- ── Pagination ── -->
      <?php if ($totalPages > 1): ?>
        <nav class="mt-4 d-flex justify-content-center">
          <ul class="pagination">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>">Prev</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <?php if ($i === 1 || $i === $totalPages || abs($i - $page) <= 2): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                  <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a>
                </li>
              <?php elseif (abs($i - $page) === 3): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
              <?php endif; ?>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>">Next</a>
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
