<?php
/**
 * Admin: Review Management
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_navbar.php';
requireRole('admin');

$pageTitle = 'Reviews';

$statusFilter = $_GET['status'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPag = ADMIN_ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPag;

$wheres = [];
$params = [];
if ($statusFilter && in_array($statusFilter, ['pending','approved','hidden'])) {
    $wheres[] = "pr.status = ?";
    $params[] = $statusFilter;
}
$whereSql = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM product_reviews pr $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPag));

$stmt = $pdo->prepare("
    SELECT pr.*, p.name AS product_name, p.slug AS product_slug,
           u.name AS reviewer_name, u.email AS reviewer_email
    FROM product_reviews pr
    JOIN products p ON p.id=pr.product_id
    JOIN users u ON u.id=pr.user_id
    $whereSql
    ORDER BY pr.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $perPag;
$params[] = $offset;
$stmt->execute($params);
$reviews = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reviews — Admin</title>
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
    <a class="nav-link" href="<?= APP_URL ?>/admin/products.php"><i class="fas fa-box"></i> Products</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/orders.php"><i class="fas fa-clipboard-list"></i> Orders</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/coupons.php"><i class="fas fa-ticket-alt"></i> Coupons</a>
    <a class="nav-link active" href="<?= APP_URL ?>/admin/reviews.php"><i class="fas fa-star"></i> Reviews</a>
  </aside>

  <div class="admin-main">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <button class="btn btn-dark d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <h4 class="fw-bold mb-0"><i class="fas fa-star me-2"></i>Review Management</h4>
    </div>

    <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Filter -->
    <div class="admin-card mb-4">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label small">Filter by Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="">All Reviews</option>
            <option value="pending"    <?= $statusFilter==='pending'?'selected':'' ?>>Pending</option>
            <option value="approved"   <?= $statusFilter==='approved'?'selected':'' ?>>Approved</option>
            <option value="hidden"     <?= $statusFilter==='hidden'?'selected':'' ?>>Hidden</option>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary btn-sm w-100">
            <i class="fas fa-filter me-1"></i>Filter
          </button>
        </div>
        <?php if ($statusFilter): ?>
          <div class="col-md-2">
            <a href="<?= APP_URL ?>/admin/reviews.php" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
          </div>
        <?php endif; ?>
      </form>
    </div>

    <!-- Reviews List -->
    <div class="admin-table">
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>ID</th>
              <th>Product</th>
              <th>Reviewer</th>
              <th>Rating</th>
              <th>Comment</th>
              <th>Status</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($reviews)): ?>
              <tr><td colspan="8" class="text-center py-4 text-muted">No reviews found.</td></tr>
            <?php else: ?>
              <?php foreach ($reviews as $rev):
                $statusClass = match($rev['status']) {
                    'pending' => 'badge-pending', 'approved' => 'badge-active', 'hidden' => 'badge-inactive'
                };
              ?>
                <tr>
                  <td class="text-muted small">#<?= (int)$rev['id'] ?></td>
                  <td>
                    <a href="<?= APP_URL ?>/public/product.php?slug=<?= e($rev['product_slug']) ?>"
                       target="_blank" class="fw-semibold small">
                      <?= e($rev['product_name']) ?>
                    </a>
                  </td>
                  <td class="small">
                    <div class="fw-semibold"><?= e($rev['reviewer_name']) ?></div>
                    <div class="text-muted"><?= e($rev['reviewer_email']) ?></div>
                  </td>
                  <td>
                    <div class="text-warning">
                      <?php for ($i=1;$i<=5;$i++):
                        echo $i<=(int)$rev['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-muted"></i>';
                      endfor; ?>
                    </div>
                  </td>
                  <td class="small" style="max-width:250px">
                    <?= nl2br(e(mb_substr($rev['comment'], 0, 100))) ?>
                    <?php if (mb_strlen($rev['comment']) > 100): ?>…<?php endif; ?>
                  </td>
                  <td><span class="badge-status <?= $statusClass ?>"><?= e(ucfirst($rev['status'])) ?></span></td>
                  <td class="small text-muted"><?= date('M j, Y', strtotime($rev['created_at'])) ?></td>
                  <td>
                    <div class="d-flex gap-1">
                      <?php if ($rev['status'] !== 'approved'): ?>
                        <button type="button" class="btn btn-sm btn-outline-success btn-status-toggle"
                                data-id="<?= $rev['id'] ?>" data-table="reviews" data-field="status"
                                data-current="<?= e($rev['status']) ?>" title="Approve">
                          <i class="fas fa-check"></i>
                        </button>
                      <?php endif; ?>
                      <?php if ($rev['status'] !== 'hidden'): ?>
                        <button type="button" class="btn btn-sm btn-outline-warning btn-status-toggle"
                                data-id="<?= $rev['id'] ?>" data-table="reviews" data-field="status"
                                data-current="<?= e($rev['status']) ?>" title="Hide">
                          <i class="fas fa-eye-slash"></i>
                        </button>
                      <?php endif; ?>
                      <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                              data-bs-target="#deleteModal" data-id="<?= $rev['id'] ?>"
                              data-product="<?= e($rev['product_name']) ?>" title="Delete">
                        <i class="fas fa-trash"></i>
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
            <a class="page-link" href="?page=<?= $page-1 ?><?= $statusFilter ? '&status='.urlencode($statusFilter) : '' ?>">Prev</a>
          </li>
          <?php for ($i=1;$i<=$totalPages;$i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
              <a class="page-link" href="?page=<?= $i ?><?= $statusFilter ? '&status='.urlencode($statusFilter) : '' ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
            <a class="page-link" href="?page=<?= $page+1 ?><?= $statusFilter ? '&status='.urlencode($statusFilter) : '' ?>">Next</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="<?= APP_URL ?>/admin/reviews.php">
        <?= csrfField() ?>
        <input type="hidden" name="review_id" id="deleteId">
        <div class="modal-header">
          <h5 class="modal-title">Delete Review</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete the review for "<span id="deleteProduct"></span>"? This cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('deleteModal')?.addEventListener('show.bs.modal', e => {
  document.getElementById('deleteId').value = e.relatedTarget.dataset.id;
  document.getElementById('deleteProduct').textContent = e.relatedTarget.dataset.product;
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
// Handle AJAX status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'toggle_status') {
    header('Content-Type: application/json');
    require_once __DIR__ . '/includes/csrf.php';
    $id   = (int)($_POST['id']    ?? 0);
    $status = $_POST['status'] ?? '';
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success'=>false,'message'=>'Security error.']); exit;
    }
    $valid = ['pending','approved','hidden'];
    if (!in_array($status, $valid, true) || !$id) {
        echo json_encode(['success'=>false,'message'=>'Invalid status.']); exit;
    }
    $upd = $pdo->prepare("UPDATE product_reviews SET status=?, updated_at=NOW() WHERE id=?");
    $upd->execute([$status, $id]);
    echo json_encode(['success'=>true,'message'=>'Review updated.','new_status'=>$status]);
    exit;
}
// Handle delete POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_id'])) {
    require_once __DIR__ . '/includes/csrf.php';
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        redirect('admin/reviews.php', 'Invalid token.', 'danger');
    }
    $rid = (int)$_POST['review_id'];
    $pdo->prepare("DELETE FROM product_reviews WHERE id=?")->execute([$rid]);
    redirect('admin/reviews.php', 'Review deleted.', 'success');
}
?>
