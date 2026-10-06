<?php
/**
 * Admin: Category Management
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_navbar.php';
requireRole('admin');

$pageTitle = 'Categories';

// ── Handle add/edit ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/csrf.php';
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        redirect('admin/categories.php', 'Invalid token.', 'danger');
    }
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $name     = trim($_POST['name']     ?? '');
            $desc     = trim($_POST['description'] ?? '');
            if (!$name) throw new Exception('Category name is required.');
            $ins = $pdo->prepare("INSERT INTO categories (name, description, status, created_at) VALUES (?, ?, 'active', NOW())");
            $ins->execute([$name, $desc]);
            logMessage('info', 'Admin added category: ' . $name . ' (ID:' . $pdo->lastInsertId() . ')');
            redirect('admin/categories.php', 'Category added!', 'success');
        } elseif ($action === 'edit') {
            $id       = (int)($_POST['id']       ?? 0);
            $name     = trim($_POST['name']     ?? '');
            $desc     = trim($_POST['description'] ?? '');
            if (!$id || !$name) throw new Exception('Invalid data.');
            $upd = $pdo->prepare("UPDATE categories SET name=?, description=? WHERE id=?");
            $upd->execute([$name, $desc, $id]);
            redirect('admin/categories.php', 'Category updated!', 'success');
        }
    } catch (Exception $e) {
        $_SESSION['flash'] = ['message' => $e->getMessage(), 'type' => 'danger'];
        redirect('admin/categories.php');
    }
}

// ── Handle delete/toggle ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    require_once __DIR__ . '/includes/csrf.php';
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        redirect('admin/categories.php', 'Invalid token.', 'danger');
    }
    $id = (int)($_POST['cat_id'] ?? 0);
    if (!$id) redirect('admin/categories.php', 'Invalid category.', 'danger');

    if ($_GET['action'] === 'delete') {
        // Check for products in this category
        $prodCount = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id=?");
        $prodCount->execute([$id]);
        $prodCount = (int)$prodCount->fetchColumn();
        if ($prodCount > 0) {
            redirect('admin/categories.php', "Cannot delete: $prodCount product(s) are in this category. Delete or reassign products first.", 'warning');
        }
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
        redirect('admin/categories.php', 'Category deleted.', 'success');
    } elseif ($_GET['action'] === 'toggle') {
        $tog = $pdo->prepare("SELECT status FROM categories WHERE id=?");
        $tog->execute([$id]);
        $current = $tog->fetchColumn();
        $new = $current === 'active' ? 'inactive' : 'active';
        $pdo->prepare("UPDATE categories SET status=? WHERE id=?")->execute([$new, $id]);
        redirect('admin/categories.php', 'Category status updated.', 'success');
    }
}

// ── Fetch categories ────────────────────────────────────
$categories = $pdo->query("
    SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) AS product_count
    FROM categories c
    ORDER BY c.name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Categories — Admin</title>
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
    <a class="nav-link active" href="<?= APP_URL ?>/admin/categories.php"><i class="fas fa-tags"></i> Categories</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/products.php"><i class="fas fa-box"></i> Products</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/orders.php"><i class="fas fa-clipboard-list"></i> Orders</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/coupons.php"><i class="fas fa-ticket-alt"></i> Coupons</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/reviews.php"><i class="fas fa-star"></i> Reviews</a>
  </aside>

  <div class="admin-main">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <button class="btn btn-dark d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <h4 class="fw-bold mb-0"><i class="fas fa-tags me-2"></i>Categories</h4>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fas fa-plus me-1"></i>Add Category
      </button>
    </div>

    <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="admin-table">
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Description</th>
              <th>Products</th>
              <th>Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $cat):
              $statusClass = $cat['status'] === 'active' ? 'badge-active' : 'badge-inactive';
            ?>
              <tr>
                <td class="text-muted small">#<?= (int)$cat['id'] ?></td>
                <td class="fw-semibold"><?= e($cat['name']) ?></td>
                <td class="small text-muted"><?= e(mb_substr($cat['description'], 0, 60)) ?></td>
                <td><?= (int)$cat['product_count'] ?></td>
                <td><span class="badge-status <?= $statusClass ?>"><?= e(ucfirst($cat['status'])) ?></span></td>
                <td class="small text-muted"><?= date('M j, Y', strtotime($cat['created_at'])) ?></td>
                <td>
                  <div class="d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal"
                            data-id="<?= $cat['id'] ?>" data-name="<?= e($cat['name']) ?>" data-desc="<?= e($cat['description']) ?>">
                      <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-<?= $cat['status']==='active'?'danger':'success' ?>"
                            data-bs-toggle="modal" data-bs-target="#toggleModal"
                            data-id="<?= $cat['id'] ?>" data-name="<?= e($cat['name']) ?>" data-status="<?= e($cat['status']) ?>">
                      <i class="fas fa-<?= $cat['status']==='active'?'ban':'check' ?>"></i>
                    </button>
                    <?php if ((int)$cat['product_count'] === 0): ?>
                      <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"
                              data-id="<?= $cat['id'] ?>" data-name="<?= e($cat['name']) ?>">
                        <i class="fas fa-trash"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
          <h5 class="modal-title">Add Category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required maxlength="100">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="editId">
        <div class="modal-header">
          <h5 class="modal-title">Edit Category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="editName" class="form-control" required maxlength="100">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" id="editDesc" class="form-control" rows="3"></textarea>
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
        <input type="hidden" name="cat_id" id="toggleId">
        <input type="hidden" name="action" value="toggle">
        <div class="modal-header">
          <h5 class="modal-title">Confirm</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to <span id="toggleAction"></span> "<span id="toggleName"></span>"?</p>
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
        <input type="hidden" name="cat_id" id="deleteId">
        <input type="hidden" name="action" value="delete">
        <div class="modal-header">
          <h5 class="modal-title">Delete Category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>
            Are you sure you want to delete "<span id="deleteName"></span>"? This cannot be undone.</p>
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
    document.getElementById('editId').value = e.relatedTarget.dataset.id;
    document.getElementById('editName').value = e.relatedTarget.dataset.name;
    document.getElementById('editDesc').value = e.relatedTarget.dataset.desc;
  });
  const toggleModal = document.getElementById('toggleModal');
  toggleModal?.addEventListener('show.bs.modal', e => {
    document.getElementById('toggleId').value = e.relatedTarget.dataset.id;
    document.getElementById('toggleName').value = e.relatedTarget.dataset.name;
    document.getElementById('toggleAction').textContent =
      e.relatedTarget.dataset.status === 'active' ? 'suspend' : 'activate';
  });
  const deleteModal = document.getElementById('deleteModal');
  deleteModal?.addEventListener('show.bs.modal', e => {
    document.getElementById('deleteId').value = e.relatedTarget.dataset.id;
    document.getElementById('deleteName').value = e.relatedTarget.dataset.name;
  });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/admin.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  document.getElementById('adminSidebar')?.classList.toggle('show');
});
</script>
</body>
</html>
