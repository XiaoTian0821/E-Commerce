<?php
/**
 * Admin: Coupon Management
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_navbar.php';
requireRole('admin');

$pageTitle = 'Coupons';

// ── Handle add/edit ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/csrf.php';
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        redirect('admin/coupons.php', 'Invalid token.', 'danger');
    }
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $code        = trim($_POST['code']        ?? '');
            $discType    = $_POST['discount_type']   ?? 'percentage';
            $discValue   = floatval($_POST['discount_value'] ?? 0);
            $minAmount   = floatval($_POST['minimum_amount'] ?? 0);
            $maxDiscount = $_POST['maximum_discount'] !== '' ? floatval($_POST['maximum_discount'] ?? 0) : null;
            $usageLimit  = $_POST['usage_limit'] !== '' ? (int)($_POST['usage_limit'] ?? null) : null;
            $startDate   = trim($_POST['start_date'] ?? '');
            $expiryDate  = trim($_POST['expiry_date'] ?? '');

            if (!$code) throw new Exception('Coupon code is required.');
            if ($discValue <= 0) throw new Exception('Discount value must be greater than 0.');
            if ($discType === 'percentage' && $discValue > 100) throw new Exception('Percentage discount cannot exceed 100%.');

            // Check unique code
            $chk = $pdo->prepare("SELECT id FROM coupons WHERE code=?");
            $chk->execute([$code]);
            if ($chk->fetch()) throw new Exception('This coupon code already exists.');

            $ins = $pdo->prepare("
                INSERT INTO coupons (code, discount_type, discount_value, minimum_amount, maximum_discount,
                                    usage_limit, used_count, start_date, expiry_date, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, 'active', NOW())
            ");
            $ins->execute([
                $code, $discType, $discValue, $minAmount, $maxDiscount,
                $usageLimit, $startDate ?: null, $expiryDate ?: null
            ]);
            logMessage('info', 'Admin added coupon: ' . $code . ' (ID:' . $pdo->lastInsertId() . ')');
            redirect('admin/coupons.php', 'Coupon created!', 'success');

        } elseif ($action === 'edit') {
            $id        = (int)($_POST['id']         ?? 0);
            $code      = trim($_POST['code']        ?? '');
            $discType  = $_POST['discount_type']   ?? 'percentage';
            $discValue = floatval($_POST['discount_value'] ?? 0);
            $minAmount = floatval($_POST['minimum_amount'] ?? 0);
            $maxDiscount = $_POST['maximum_discount'] !== '' ? floatval($_POST['maximum_discount'] ?? 0) : null;
            $usageLimit = $_POST['usage_limit'] !== '' ? (int)($_POST['usage_limit'] ?? null) : null;
            $startDate  = trim($_POST['start_date'] ?? '');
            $expiryDate = trim($_POST['expiry_date'] ?? '');

            if (!$id || !$code) throw new Exception('Invalid data.');
            $upd = $pdo->prepare("
                UPDATE coupons SET code=?, discount_type=?, discount_value=?, minimum_amount=?,
                                  maximum_discount=?, usage_limit=?, start_date=?, expiry_date=?, updated_at=NOW()
                WHERE id=?
            ");
            $upd->execute([
                $code, $discType, $discValue, $minAmount, $maxDiscount,
                $usageLimit, $startDate ?: null, $expiryDate ?: null, $id
            ]);
            redirect('admin/coupons.php', 'Coupon updated!', 'success');
        }
    } catch (Exception $e) {
        $_SESSION['flash'] = ['message' => $e->getMessage(), 'type' => 'danger'];
        redirect('admin/coupons.php');
    }
}

// ── Handle delete/toggle ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    require_once __DIR__ . '/includes/csrf.php';
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        redirect('admin/coupons.php', 'Invalid token.', 'danger');
    }
    $id = (int)($_POST['coupon_id'] ?? 0);
    if (!$id) redirect('admin/coupons.php', 'Invalid coupon.', 'danger');

    if ($_GET['action'] === 'delete') {
        $pdo->prepare("DELETE FROM coupons WHERE id=?")->execute([$id]);
        redirect('admin/coupons.php', 'Coupon deleted.', 'success');
    } elseif ($_GET['action'] === 'toggle') {
        $tog = $pdo->prepare("SELECT status FROM coupons WHERE id=?");
        $tog->execute([$id]);
        $current = $tog->fetchColumn();
        $new = $current === 'active' ? 'inactive' : 'active';
        $pdo->prepare("UPDATE coupons SET status=? WHERE id=?")->execute([$new, $id]);
        redirect('admin/coupons.php', 'Coupon status updated.', 'success');
    }
}

// ── Fetch coupons ───────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$wheres = [];
$params = [];
if ($search) {
    $wheres[] = "c.code LIKE ?";
    $params[] = '%' . $search . '%';
}
$whereSql = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

$coupons = $pdo->prepare("SELECT * FROM coupons $whereSql ORDER BY c.created_at DESC");
$coupons->execute($params);
$coupons = $coupons->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Coupons — Admin</title>
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
    <a class="nav-link active" href="<?= APP_URL ?>/admin/coupons.php"><i class="fas fa-ticket-alt"></i> Coupons</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/reviews.php"><i class="fas fa-star"></i> Reviews</a>
  </aside>

  <div class="admin-main">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <button class="btn btn-dark d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <h4 class="fw-bold mb-0"><i class="fas fa-ticket-alt me-2"></i>Coupon Management</h4>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fas fa-plus me-1"></i>Add Coupon
      </button>
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
          <label class="form-label small">Search Coupon</label>
          <input type="text" name="search" class="form-control form-control-sm"
                 placeholder="Coupon code…" value="<?= e($search) ?>">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary btn-sm w-100">
            <i class="fas fa-search me-1"></i>Search
          </button>
        </div>
        <?php if ($search): ?>
          <div class="col-md-2">
            <a href="<?= APP_URL ?>/admin/coupons.php" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
          </div>
        <?php endif; ?>
      </form>
    </div>

    <!-- Coupons Table -->
    <div class="admin-table">
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Code</th>
              <th>Type</th>
              <th>Value</th>
              <th>Min. Spend</th>
              <th>Max Discount</th>
              <th>Usage</th>
              <th>Valid</th>
              <th>Expiry</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($coupons)): ?>
              <tr><td colspan="10" class="text-center py-4 text-muted">No coupons found.</td></tr>
            <?php else: ?>
              <?php foreach ($coupons as $c):
                $statusClass = $c['status'] === 'active' ? 'badge-active' : 'badge-inactive';
                $typeLabel = $c['discount_type'] === 'percentage'
                  ? $c['discount_value'] . '%'
                  : formatCurrency((float)$c['discount_value']);
                $startDate = $c['start_date'] ? date('M j, Y', strtotime($c['start_date'])) : '—';
                $expiryDate = $c['expiry_date'] ? date('M j, Y', strtotime($c['expiry_date'])) : 'No expiry';
                $usageText = $c['usage_limit'] ? $c['used_count'] . '/' . $c['usage_limit'] : (int)$c['used_count'] . ' (unlimited)';
              ?>
                <tr>
                  <td class="fw-semibold"><code><?= e($c['code']) ?></code></td>
                  <td class="small"><?= ucfirst($c['discount_type']) ?></td>
                  <td class="fw-semibold small"><?= $typeLabel ?></td>
                  <td class="small"><?= formatCurrency((float)$c['minimum_amount']) ?></td>
                  <td class="small">
                    <?= $c['maximum_discount'] ? formatCurrency((float)$c['maximum_discount']) : '—' ?>
                  </td>
                  <td class="small"><?= $usageText ?></td>
                  <td class="small"><?= $startDate ?></td>
                  <td class="small"><?= $expiryDate ?></td>
                  <td><span class="badge-status <?= $statusClass ?>"><?= e(ucfirst($c['status'])) ?></span></td>
                  <td>
                    <div class="d-flex gap-1">
                      <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal"
                              data-id="<?= $c['id'] ?>"
                              data-code="<?= e($c['code']) ?>"
                              data-type="<?= e($c['discount_type']) ?>"
                              data-value="<?= htmlspecialchars($c['discount_value'], ENT_QUOTES) ?>"
                              data-min="<?= htmlspecialchars($c['minimum_amount'], ENT_QUOTES) ?>"
                              data-max="<?= htmlspecialchars($c['maximum_discount'] ?? '', ENT_QUOTES) ?>"
                              data-limit="<?= $c['usage_limit'] ?? '' ?>"
                              data-start="<?= $c['start_date'] ?? '' ?>"
                              data-expiry="<?= $c['expiry_date'] ?? '' ?>">
                        <i class="fas fa-edit"></i>
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-<?= $c['status']==='active'?'danger':'success' ?>"
                              data-bs-toggle="modal" data-bs-target="#toggleModal"
                              data-id="<?= $c['id'] ?>" data-code="<?= e($c['code']) ?>" data-status="<?= e($c['status']) ?>">
                        <i class="fas fa-<?= $c['status']==='active'?'ban':'check' ?>"></i>
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"
                              data-id="<?= $c['id'] ?>" data-code="<?= e($c['code']) ?>">
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
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
          <h5 class="modal-title">Add Coupon</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Coupon Code <span class="text-danger">*</span></label>
              <input type="text" name="code" class="form-control" required maxlength="50"
                     placeholder="e.g., SUMMER2024">
            </div>
            <div class="col-md-6">
              <label class="form-label">Discount Type <span class="text-danger">*</span></label>
              <select name="discount_type" class="form-select" required>
                <option value="percentage">Percentage (%)</option>
                <option value="fixed">Fixed Amount ($)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Discount Value <span class="text-danger">*</span></label>
              <input type="number" name="discount_value" class="form-control" step="0.01" min="0.01" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Minimum Amount ($)</label>
              <input type="number" name="minimum_amount" class="form-control" step="0.01" value="0">
            </div>
            <div class="col-md-6">
              <label class="form-label">Maximum Discount ($)</label>
              <input type="number" name="maximum_discount" class="form-control" step="0.01" value="">
              <div class="form-text">Leave empty for no limit (percentage type).</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Usage Limit</label>
              <input type="number" name="usage_limit" class="form-control" value="" placeholder="Empty = unlimited">
            </div>
            <div class="col-md-6">
              <label class="form-label">Start Date</label>
              <input type="date" name="start_date" class="form-control" value="">
            </div>
            <div class="col-md-6">
              <label class="form-label">Expiry Date</label>
              <input type="date" name="expiry_date" class="form-control" value="">
              <div class="form-text">Leave empty for no expiry.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Create Coupon</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="editId">
        <div class="modal-header">
          <h5 class="modal-title">Edit Coupon</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Coupon Code <span class="text-danger">*</span></label>
              <input type="text" name="code" id="editCode" class="form-control" required maxlength="50">
            </div>
            <div class="col-md-6">
              <label class="form-label">Discount Type <span class="text-danger">*</span></label>
              <select name="discount_type" id="editType" class="form-select" required>
                <option value="percentage">Percentage (%)</option>
                <option value="fixed">Fixed Amount ($)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Discount Value <span class="text-danger">*</span></label>
              <input type="number" name="discount_value" id="editValue" class="form-control" step="0.01" min="0.01" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Minimum Amount ($)</label>
              <input type="number" name="minimum_amount" id="editMin" class="form-control" step="0.01" value="0">
            </div>
            <div class="col-md-6">
              <label class="form-label">Maximum Discount ($)</label>
              <input type="number" name="maximum_discount" id="editMax" class="form-control" step="0.01" value="">
            </div>
            <div class="col-md-6">
              <label class="form-label">Usage Limit</label>
              <input type="number" name="usage_limit" id="editLimit" class="form-control" value="">
            </div>
            <div class="col-md-6">
              <label class="form-label">Start Date</label>
              <input type="date" name="start_date" id="editStart" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Expiry Date</label>
              <input type="date" name="expiry_date" id="editExpiry" class="form-control">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Toggle Modal -->
<div class="modal fade" id="toggleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="coupon_id" id="toggleId">
        <input type="hidden" name="action" value="toggle">
        <div class="modal-header">
          <h5 class="modal-title">Confirm</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Toggle status for "<span id="toggleCode"></span>"?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning">Confirm</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="coupon_id" id="deleteId">
        <input type="hidden" name="action" value="delete">
        <div class="modal-header">
          <h5 class="modal-title">Delete Coupon</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>
            Delete "<span id="deleteCode"></span>"? This cannot be undone.</p>
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
document.addEventListener('DOMContentLoaded', () => {
  const editModal = document.getElementById('editModal');
  editModal?.addEventListener('show.bs.modal', e => {
    const d = e.relatedTarget.dataset;
    document.getElementById('editId').value   = d.id;
    document.getElementById('editCode').value = d.code;
    document.getElementById('editType').value = d.type;
    document.getElementById('editValue').value = d.value;
    document.getElementById('editMin').value  = d.min || '0';
    document.getElementById('editMax').value  = d.max || '';
    document.getElementById('editLimit').value = d.limit || '';
    document.getElementById('editStart').value = d.start || '';
    document.getElementById('editExpiry').value = d.expiry || '';
  });
  document.getElementById('toggleModal')?.addEventListener('show.bs.modal', e => {
    document.getElementById('toggleId').value = e.relatedTarget.dataset.id;
    document.getElementById('toggleCode').textContent = e.relatedTarget.dataset.code;
  });
  document.getElementById('deleteModal')?.addEventListener('show.bs.modal', e => {
    document.getElementById('deleteId').value = e.relatedTarget.dataset.id;
    document.getElementById('deleteCode').textContent = e.relatedTarget.dataset.code;
  });
  document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('adminSidebar')?.classList.toggle('show');
  });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/admin.js"></script>
</body>
</html>
