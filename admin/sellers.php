<?php
/**
 * Admin: Seller Management
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_navbar.php';
requireRole('admin');

$pageTitle = 'Sellers';

$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPag = ADMIN_ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPag;

$wheres = [];
$params = [];
if ($search) {
    $wheres[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$whereSql = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

// Only sellers
$whereSql .= ($wheres ? ' AND ' : 'WHERE ') . "u.role='seller'";

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPag));

$stmt = $pdo->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM products p WHERE p.seller_id=u.id) AS product_count,
           (SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.seller_id=u.id) AS order_count,
           (SELECT COALESCE(SUM(oi.subtotal),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.seller_id=u.id AND o.order_status IN ('completed','shipped','processing')) AS total_sales
    FROM users u $whereSql
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $perPag;
$params[] = $offset;
$stmt->execute($params);
$sellers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sellers — Admin</title>
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
    <a class="nav-link active" href="<?= APP_URL ?>/admin/sellers.php"><i class="fas fa-store"></i> Sellers</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/categories.php"><i class="fas fa-tags"></i> Categories</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/products.php"><i class="fas fa-box"></i> Products</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/orders.php"><i class="fas fa-clipboard-list"></i> Orders</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/coupons.php"><i class="fas fa-ticket-alt"></i> Coupons</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/reviews.php"><i class="fas fa-star"></i> Reviews</a>
  </aside>

  <div class="admin-main">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <button class="btn btn-dark d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <h4 class="fw-bold mb-0"><i class="fas fa-store me-2"></i>Seller Management</h4>
    </div>

    <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Search -->
    <div class="admin-card mb-4">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-5">
          <label class="form-label small">Search Sellers</label>
          <input type="text" name="search" class="form-control form-control-sm"
                 placeholder="Name or email…" value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-primary btn-sm w-100">
            <i class="fas fa-search me-1"></i>Search
          </button>
        </div>
        <?php if ($search): ?>
          <div class="col-md-2">
            <a href="<?= APP_URL ?>/admin/sellers.php" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
          </div>
        <?php endif; ?>
      </form>
    </div>

    <!-- Sellers Table -->
    <div class="admin-table">
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Products</th>
              <th>Orders</th>
              <th>Sales</th>
              <th>Status</th>
              <th>Registered</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($sellers)): ?>
              <tr><td colspan="9" class="text-center py-4 text-muted">No sellers found.</td></tr>
            <?php else: ?>
              <?php foreach ($sellers as $s):
                $statusClass = $s['status'] === 'active' ? 'badge-active' : 'badge-suspended';
              ?>
                <tr>
                  <td class="text-muted small">#<?= (int)$s['id'] ?></td>
                  <td class="fw-semibold"><?= e($s['name']) ?></td>
                  <td class="small"><?= e($s['email']) ?></td>
                  <td><?= (int)$s['product_count'] ?></td>
                  <td><?= (int)$s['order_count'] ?></td>
                  <td class="fw-semibold small"><?= formatCurrency((float)$s['total_sales']) ?></td>
                  <td><span class="badge-status <?= $statusClass ?>"><?= e(ucfirst($s['status'])) ?></span></td>
                  <td class="small text-muted"><?= date('M j, Y', strtotime($s['created_at'])) ?></td>
                  <td>
                    <div class="d-flex gap-1">
                      <a href="<?= APP_URL ?>/public/shop.php?seller=<?= (int)$s['id'] ?>"
                         class="btn btn-sm btn-outline-info" title="View Shop">
                        <i class="fas fa-store"></i>
                      </a>
                      <button type="button" class="btn btn-sm btn-outline-<?= $s['status']==='active'?'danger':'success' ?>"
                              data-bs-toggle="modal" data-bs-target="#toggleModal"
                              data-id="<?= $s['id'] ?>" data-name="<?= e($s['name']) ?>" data-status="<?= e($s['status']) ?>">
                        <i class="fas fa-<?= $s['status']==='active'?'ban':'check' ?>"></i>
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
            <a class="page-link" href="?page=<?= $page-1 ?><?= $search ? '&search='.urlencode($search) : '' ?>">Prev</a>
          </li>
          <?php for ($i=1;$i<=$totalPages;$i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
              <a class="page-link" href="?page=<?= $i ?><?= $search ? '&search='.urlencode($search) : '' ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
            <a class="page-link" href="?page=<?= $page+1 ?><?= $search ? '&search='.urlencode($search) : '' ?>">Next</a>
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
      <form method="POST" action="<?= APP_URL ?>/admin/sellers.php">
        <?= csrfField() ?>
        <input type="hidden" name="seller_id" id="toggleId">
        <div class="modal-header">
          <h5 class="modal-title">Confirm</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to <span id="toggleAction"></span> seller "<span id="toggleName"></span>"?</p>
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
  document.getElementById('toggleAction').textContent =
    e.relatedTarget.dataset.status === 'active' ? 'suspend' : 'activate';
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
// Handle toggle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seller_id'])) {
    require_once __DIR__ . '/includes/csrf.php';
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        redirect('admin/sellers.php', 'Invalid token.', 'danger');
    }
    $sid = (int)$_POST['seller_id'];
    $tog = $pdo->prepare("SELECT status FROM users WHERE id=? AND role='seller'");
    $tog->execute([$sid]);
    $current = $tog->fetchColumn();
    if ($current) {
        $new = $current === 'active' ? 'suspended' : 'active';
        $pdo->prepare("UPDATE users SET status=? WHERE id=? AND role='seller'")->execute([$new, $sid]);
    }
    redirect('admin/sellers.php', 'Seller status updated.', 'success');
}
?>
