<?php
/**
 * Admin: Product Management
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_navbar.php';
requireRole('admin');

$pageTitle = 'Products';

$search   = trim($_GET['search'] ?? '');
$catFilter = (int)($_GET['category'] ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPag   = ADMIN_ITEMS_PER_PAGE;
$offset   = ($page - 1) * $perPag;

$wheres = [];
$params = [];
if ($search) {
    $wheres[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($catFilter > 0) {
    $wheres[] = "p.category_id = ?";
    $params[] = $catFilter;
}
$whereSql = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPag));

$stmt = $pdo->prepare("
    SELECT p.*, u.name AS seller_name, c.name AS category_name,
           (SELECT image_path FROM product_images pi WHERE pi.product_id=p.id ORDER BY display_order LIMIT 1) AS image
    FROM products p
    JOIN users u ON u.id=p.seller_id
    LEFT JOIN categories c ON c.id=p.category_id
    $whereSql
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $perPag;
$params[] = $offset;
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Products — Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="admin-layout">
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="brand"><i class="fas fa-shield-alt me-2"></i>Admin Panel</div>
    <div class="nav-section">Main</div>
    <a class="nav-link" href="<?= APP_URL ?>/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <div class="nav-section">Management</div>
    <a class="nav-link" href="<?= APP_URL ?>/admin/users.php"><i class="fas fa-users"></i> Users</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/sellers.php"><i class="fas fa-store"></i> Sellers</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/categories.php"><i class="fas fa-tags"></i> Categories</a>
    <a class="nav-link active" href="<?= APP_URL ?>/admin/products.php"><i class="fas fa-box"></i> Products</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/orders.php"><i class="fas fa-clipboard-list"></i> Orders</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/coupons.php"><i class="fas fa-ticket-alt"></i> Coupons</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/reviews.php"><i class="fas fa-star"></i> Reviews</a>
  </aside>

  <div class="admin-main">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <button class="btn btn-dark d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <h4 class="fw-bold mb-0"><i class="fas fa-box me-2"></i>Product Management</h4>
    </div>

    <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Search & Filter -->
    <div class="admin-card mb-4">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label small">Search</label>
          <input type="text" name="search" class="form-control form-control-sm"
                 placeholder="Product name or SKU…" value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label small">Category</label>
          <select name="category" class="form-select form-select-sm">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>" <?= $catFilter===(int)$cat['id']?'selected':'' ?>><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary btn-sm w-100">
            <i class="fas fa-search me-1"></i>Search
          </button>
        </div>
        <?php if ($search || $catFilter): ?>
          <div class="col-md-2">
            <a href="<?= APP_URL ?>/admin/products.php" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
          </div>
        <?php endif; ?>
      </form>
    </div>

    <!-- Products Table -->
    <div class="admin-table">
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Image</th>
              <th>Name</th>
              <th>Seller</th>
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
              <tr><td colspan="9" class="text-center py-4 text-muted">No products found.</td></tr>
            <?php else: ?>
              <?php foreach ($products as $p):
                $statusClass = match($p['status']) {
                    'active' => 'badge-active', 'inactive' => 'badge-inactive', 'out_of_stock' => 'badge-cancelled'
                };
              ?>
                <tr>
                  <td>
                    <img src="<?= APP_URL ?>/<?= e($p['image'] ?? 'uploads/products/default-product.jpg') ?>"
                         alt="" class="rounded" style="width:44px;height:44px;object-fit:cover">
                  </td>
                  <td class="fw-semibold small">
                    <a href="<?= APP_URL ?>/public/product.php?slug=<?= e($p['slug']) ?>" target="_blank"><?= e($p['name']) ?></a>
                  </td>
                  <td class="small"><?= e($p['seller_name']) ?></td>
                  <td class="small"><?= e($p['category_name'] ?? '—') ?></td>
                  <td class="fw-semibold small"><?= formatCurrency((float)$p['price']) ?></td>
                  <td><?= (int)$p['stock'] ?></td>
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
                      <a href="<?= APP_URL ?>/admin/product_edit.php?id=<?= (int)$p['id'] ?>"
                         class="btn btn-sm btn-outline-primary" title="Edit">
                        <i class="fas fa-edit"></i>
                      </a>
                      <button type="button" class="btn btn-sm btn-outline-<?= $p['status']==='active'?'danger':'success' ?>"
                              data-bs-toggle="modal" data-bs-target="#toggleModal"
                              data-id="<?= $p['id'] ?>" data-name="<?= e($p['name']) ?>" data-status="<?= e($p['status']) ?>">
                        <i class="fas fa-<?= $p['status']==='active'?'ban':'check' ?>"></i>
                      </button>
                    </div>
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
            <a class="page-link" href="?page=<?= $page-1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $catFilter ? '&category='.$catFilter : '' ?>">Prev</a>
          </li>
          <?php for ($i=1;$i<=$totalPages;$i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
              <a class="page-link" href="?page=<?= $i ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $catFilter ? '&category='.$catFilter : '' ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
            <a class="page-link" href="?page=<?= $page+1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $catFilter ? '&category='.$catFilter : '' ?>">Next</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>

<!-- Toggle Modal -->
<div class="modal fade" id="toggleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="<?= APP_URL ?>/admin/products.php">
        <?= csrfField() ?>
        <input type="hidden" name="toggle_id" id="toggleId">
        <div class="modal-header">
          <h5 class="modal-title">Confirm</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Toggle status for "<span id="toggleName"></span>"?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning">Confirm</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('toggleModal')?.addEventListener('show.bs.modal', e => {
  document.getElementById('toggleId').value = e.relatedTarget.dataset.id;
  document.getElementById('toggleName').textContent = e.relatedTarget.dataset.name;
});
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  document.getElementById('adminSidebar')?.classList.toggle('show');
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/admin.js"></script>
</body>
</html>
<?php
// Handle toggle status POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    require_once __DIR__ . '/includes/csrf.php';
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        redirect('admin/products.php', 'Invalid token.', 'danger');
    }
    $pid = (int)$_POST['toggle_id'];
    $tog = $pdo->prepare("SELECT status FROM products WHERE id=?");
    $tog->execute([$pid]);
    $current = $tog->fetchColumn();
    $new = $current === 'active' ? 'inactive' : ($current === 'inactive' ? 'active' : 'out_of_stock');
    // Simple toggle: active <-> inactive; out_of_stock -> active
    if ($current === 'out_of_stock') $new = 'active';
    $pdo->prepare("UPDATE products SET status=? WHERE id=?")->execute([$new, $pid]);
    redirect('admin/products.php', 'Product status updated.', 'success');
}
?>
