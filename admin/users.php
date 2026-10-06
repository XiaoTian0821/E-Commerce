<?php
/**
 * Admin: User Management
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pageTitle = 'Manage Users';

// ── AJAX toggle status ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'toggle_status') {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../includes/csrf.php';
    $id     = (int)($_POST['id']      ?? 0);
    $status = $_POST['status'] ?? '';
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success'=>false,'message'=>'Security error.']); exit;
    }
    // Protect last admin
    if ($status === 'suspended') {
        $adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='active'")->fetchColumn();
        $currentUser = $pdo->prepare("SELECT status FROM users WHERE id=?");
        $currentUser->execute([$id]);
        $target = $currentUser->fetch();
        if ($target && $target['role'] === 'admin' && $adminCount <= 1) {
            echo json_encode(['success'=>false,'message'=>'Cannot suspend the last active administrator.']); exit;
        }
    }
    $upd = $pdo->prepare("UPDATE users SET status=? WHERE id=?");
    $upd->execute([$status, $id]);
    echo json_encode(['success'=>true,'message'=>'User status updated.','new_status'=>$status]);
    exit;
}

// ── Pagination & Search ────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$role    = $_GET['role']    ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPag  = ADMIN_ITEMS_PER_PAGE;
$offset  = ($page - 1) * $perPag;

$wheres = [];
$params = [];
if ($search) {
    $wheres[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($role && in_array($role, ['customer','seller','admin'])) {
    $wheres[] = "u.role = ?";
    $params[] = $role;
}
$whereSql = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPag));

$stmt = $pdo->prepare("
    SELECT u.* FROM users u $whereSql ORDER BY u.created_at DESC LIMIT ? OFFSET ?
");
$params[] = $perPag;
$params[] = $offset;
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Users — Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require __DIR__ . '/../includes/admin_navbar.php'; ?>
<div class="admin-layout">
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="brand"><i class="fas fa-shield-alt me-2"></i>Admin Panel</div>
    <div class="nav-section">Main</div>
    <a class="nav-link" href="<?= APP_URL ?>/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <div class="nav-section">Management</div>
    <a class="nav-link active" href="<?= APP_URL ?>/admin/users.php"><i class="fas fa-users"></i> Users</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/sellers.php"><i class="fas fa-store"></i> Sellers</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/categories.php"><i class="fas fa-tags"></i> Categories</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/products.php"><i class="fas fa-box"></i> Products</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/orders.php"><i class="fas fa-clipboard-list"></i> Orders</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/coupons.php"><i class="fas fa-ticket-alt"></i> Coupons</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/reviews.php"><i class="fas fa-star"></i> Reviews</a>
  </aside>

  <div class="admin-main">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <button class="btn btn-dark d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <h4 class="fw-bold mb-0"><i class="fas fa-users me-2"></i>User Management</h4>
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
                 placeholder="Name or email…" value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label small">Role</label>
          <select name="role" class="form-select form-select-sm">
            <option value="">All Roles</option>
            <option value="customer" <?= $role==='customer'?'selected':'' ?>>Customer</option>
            <option value="seller"   <?= $role==='seller'?'selected':'' ?>>Seller</option>
            <option value="admin"    <?= $role==='admin'?'selected':'' ?>>Admin</option>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary btn-sm w-100">
            <i class="fas fa-search me-1"></i>Search
          </button>
        </div>
        <?php if ($search || $role): ?>
          <div class="col-md-2">
            <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
          </div>
        <?php endif; ?>
      </form>
    </div>

    <!-- Users Table -->
    <div class="admin-table">
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Registered</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr><td colspan="7" class="text-center py-4 text-muted">No users found.</td></tr>
            <?php else: ?>
              <?php foreach ($users as $u):
                $roleBadge = match($u['role']) {
                    'admin' => 'bg-danger', 'seller' => 'bg-success', default => 'bg-primary'
                };
                $statusClass = $u['status'] === 'active' ? 'badge-active' : 'badge-suspended';
              ?>
                <tr>
                  <td class="text-muted small">#<?= (int)$u['id'] ?></td>
                  <td class="fw-semibold"><?= e($u['name']) ?></td>
                  <td class="small"><?= e($u['email']) ?></td>
                  <td><span class="badge <?= $roleBadge ?>"><?= e(ucfirst($u['role'])) ?></span></td>
                  <td><span class="badge-status <?= $statusClass ?>"><?= e(ucfirst($u['status'])) ?></span></td>
                  <td class="small text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                  <td>
                    <div class="d-flex gap-1">
                      <button type="button" class="btn btn-sm btn-outline-<?= $u['status']==='active'?'danger':'success' ?> btn-status-toggle"
                              data-id="<?= $u['id'] ?>" data-table="users" data-field="status"
                              data-current="<?= e($u['status']) ?>">
                        <i class="fas fa-<?= $u['status']==='active'?'ban':'check' ?>"></i>
                        <?= $u['status']==='active' ? 'Suspend' : 'Activate' ?>
                      </button>
                      <a href="<?= APP_URL ?>/admin/user_view.php?id=<?= (int)$u['id'] ?>"
                         class="btn btn-sm btn-outline-primary" title="View details">
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

    <?php if ($totalPages > 1): ?>
      <nav class="mt-3 d-flex justify-content-center">
        <ul class="pagination">
          <li class="page-item <?= $page<=1?'disabled':'' ?>">
            <a class="page-link" href="?page=<?= $page-1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $role ? '&role='.urlencode($role) : '' ?>">Prev</a>
          </li>
          <?php for ($i=1;$i<=$totalPages;$i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
              <a class="page-link" href="?page=<?= $i ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $role ? '&role='.urlencode($role) : '' ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
            <a class="page-link" href="?page=<?= $page+1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $role ? '&role='.urlencode($role) : '' ?>">Next</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/admin.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  document.getElementById('adminSidebar')?.classList.toggle('show');
});
</script>
</body>
</html>
