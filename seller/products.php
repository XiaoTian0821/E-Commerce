<?php
/**
 * Seller Products Management
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
$pageTitle = 'My Products';

// ── Handle delete/archive ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    require_once __DIR__ . '/includes/csrf.php';
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        redirect('seller/products.php', 'Invalid token.', 'danger');
    }
    $pid = (int)($_POST['product_id'] ?? 0);
    if (!$pid) redirect('seller/products.php', 'Invalid product.', 'danger');
    $own = $pdo->prepare("SELECT id, name, status FROM products WHERE id=? AND seller_id=?");
    $own->execute([$pid, $userId]);
    $prod = $own->fetch();
    if (!$prod) redirect('seller/products.php', 'Product not found or you do not have permission.', 'danger');

    $action = $_GET['action'];
    if ($action === 'delete') {
        $pdo->prepare("UPDATE products SET status='inactive' WHERE id=?")->execute([$pid]);
        redirect('seller/products.php', '"' . e($prod['name']) . '" archived.', 'success');
    } elseif ($action === 'toggle') {
        $newStatus = $prod['status'] === 'active' ? 'inactive' : 'active';
        $pdo->prepare("UPDATE products SET status=? WHERE id=?")->execute([$newStatus, $pid]);
        redirect('seller/products.php', 'Product status updated.', 'success');
    }
}

// ── Fetch products ────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$wheres = ["p.seller_id = ?"];
$params = [$userId];
if ($search) {
    $wheres[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$whereSql = 'WHERE ' . implode(' AND ', $wheres);

$page     = max(1, (int)($_GET['page'] ?? 1));
$perPag   = 15;
$offset   = ($page - 1) * $perPag;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPag));

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name,
           (SELECT image_path FROM product_images pi WHERE pi.product_id=p.id ORDER BY display_order LIMIT 1) AS image
    FROM products p
    LEFT JOIN categories c ON c.id=p.category_id
    $whereSql
    ORDER BY p.created_at DESC
    LIMIT $perPag OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Products — <?= e(APP_NAME) ?></title>
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
      <a class="nav-link active" href="<?= APP_URL ?>/seller/products.php"><i class="fas fa-box"></i> My Products</a>
      <a class="nav-link" href="<?= APP_URL ?>/seller/orders.php"><i class="fas fa-clipboard-list"></i> Orders</a>
      <a class="nav-link" href="<?= APP_URL ?>/seller/profile.php"><i class="fas fa-user-circle"></i> Profile</a>
    </nav>
  </aside>

  <div class="seller-main">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <button class="btn btn-dark d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <h4 class="fw-bold mb-0"><i class="fas fa-box me-2"></i>My Products</h4>
      <a href="<?= APP_URL ?>/seller/product_add.php" class="btn btn-success">
        <i class="fas fa-plus me-1"></i>Add Product
      </a>
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
        <div class="col-md-5">
          <label class="form-label small">Search</label>
          <input type="text" name="search" class="form-control form-control-sm"
                 placeholder="Search products…" value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-success btn-sm w-100">
            <i class="fas fa-search me-1"></i>Search
          </button>
        </div>
        <?php if ($search): ?>
          <div class="col-md-2">
            <a href="<?= APP_URL ?>/seller/products.php" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
          </div>
        <?php endif; ?>
      </form>
    </div>

    <!-- Products Table -->
    <div class="seller-table">
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Image</th>
              <th>Name</th>
              <th>Category</th>
              <th>Price</th>
              <th>Stock</th>
              <th>Status</th>
              <th>Featured</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($products)): ?>
              <tr><td colspan="8" class="text-center py-4 text-muted">
                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>No products yet.
                <a href="<?= APP_URL ?>/seller/product_add.php">Add your first product</a>.
              </td></tr>
            <?php else: ?>
              <?php foreach ($products as $p):
                $statusClass = match($p['status']) {
                    'active' => 'badge-active', 'inactive' => 'badge-inactive', 'out_of_stock' => 'badge-cancelled'
                };
              ?>
                <tr>
                  <td>
                    <img src="<?= APP_URL ?>/<?= e($p['image'] ?? 'uploads/products/default-product.jpg') ?>"
                         alt="" class="rounded" style="width:50px;height:50px;object-fit:cover">
                  </td>
                  <td class="fw-semibold"><?= e($p['name']) ?></td>
                  <td><?= e($p['category_name'] ?? '—') ?></td>
                  <td class="fw-semibold"><?= formatCurrency((float)$p['price']) ?></td>
                  <td>
                    <?= (int)$p['stock'] ?>
                    <?php if ((int)$p['stock'] <= 5 && (int)$p['stock'] > 0): ?>
                      <span class="text-warning small"><i class="fas fa-exclamation-triangle"></i></span>
                    <?php elseif ((int)$p['stock'] === 0): ?>
                      <span class="text-danger small"><i class="fas fa-times-circle"></i></span>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge-status <?= $statusClass ?>"><?= e(ucfirst(str_replace('_',' ',$p['status']))) ?></span></td>
                  <td>
                    <?php if ($p['featured']): ?>
                      <i class="fas fa-star text-warning"></i>
                    <?php else: ?>
                      <i class="far fa-star text-muted"></i>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="d-flex gap-1">
                      <a href="<?= APP_URL ?>/seller/product_edit.php?id=<?= (int)$p['id'] ?>"
                         class="btn btn-sm btn-outline-primary" title="Edit">
                        <i class="fas fa-edit"></i>
                      </a>
                      <form method="POST" class="d-inline" onsubmit="return confirm('Archive this product? Order history will be preserved.')">
                        <?= csrfField() ?>
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <button type="submit" name="action" value="delete"
                                class="btn btn-sm btn-outline-danger" title="Archive">
                          <i class="fas fa-archive"></i>
                        </button>
                      </form>
                      <a href="<?= APP_URL ?>/public/product.php?slug=<?= e($p['slug']) ?>"
                         target="_blank" class="btn btn-sm btn-outline-secondary" title="View">
                        <i class="fas fa-eye"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
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
