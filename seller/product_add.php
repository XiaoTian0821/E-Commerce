<?php
/**
 * Seller: Add Product
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
$pageTitle = 'Add Product';

// Fetch categories for dropdown
$categories = $pdo->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name")->fetchAll();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/csrf.php';
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $name      = trim($_POST['name']      ?? '');
        $desc      = trim($_POST['description'] ?? '');
        $price     = floatval($_POST['price'] ?? 0);
        $stock     = (int)($_POST['stock']    ?? 0);
        $sku       = trim($_POST['sku']       ?? '');
        $catId     = (int)($_POST['category_id'] ?? 0);
        $featured  = isset($_POST['featured']);

        if (!$name) $errors[] = 'Product name is required.';
        if ($price <= 0) $errors[] = 'Price must be greater than 0.';
        if ($stock < 0) $errors[] = 'Stock cannot be negative.';
        if (!$catId) $errors[] = 'Please select a category.';
        if (strlen($sku) > 0 && strlen($sku) < 3) $errors[] = 'SKU must be at least 3 characters.';

        // Check unique SKU
        if (strlen($sku) > 0) {
            $skuCheck = $pdo->prepare("SELECT id FROM products WHERE sku=? AND seller_id=?");
            $skuCheck->execute([$sku, $userId]);
            if ($skuCheck->fetch()) $errors[] = 'This SKU is already in use.';
        }

        // Handle image uploads
        $imagePaths = [];
        if (!empty($_FILES['images']['name'][0] ?? null)) {
            foreach ($_FILES['images']['name'] as $key => $fname) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $result = uploadImage($_FILES['images'], 'products');
                    if ($result) $imagePaths[] = $result;
                }
            }
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                $slug = slugify($name);
                // Ensure unique slug
                $origSlug = $slug;
                $dup = $pdo->prepare("SELECT id FROM products WHERE slug=? AND seller_id=?");
                $dup->execute([$slug, $userId]);
                $i = 1;
                while ($dup->fetch()) {
                    $slug = $origSlug . '-' . $i++;
                    $dup->execute([$slug, $userId]);
                }

                $ins = $pdo->prepare("
                    INSERT INTO products (seller_id, category_id, name, slug, description, price, stock, sku, status, featured, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
                ");
                $ins->execute([$userId, $catId, $name, $slug, $desc, $price, $stock, $sku, $featured ? 1 : 0]);
                $productId = (int)$pdo->lastInsertId();

                foreach ($imagePaths as $idx => $path) {
                    $imgIns = $pdo->prepare("INSERT INTO product_images (product_id, image_path, display_order) VALUES (?, ?, ?)");
                    $imgIns->execute([$productId, $path, $idx]);
                }
                // Always ensure at least one image entry
                if (empty($imagePaths)) {
                    $imgIns = $pdo->prepare("INSERT INTO product_images (product_id, image_path, display_order) VALUES (?, ?, ?)");
                    $imgIns->execute([$productId, 'uploads/products/default-product.jpg', 0]);
                }

                $pdo->commit();
                logMessage('info', 'Seller ' . $userId . ' added product ID ' . $productId . ': ' . $name);
                redirect('seller/products.php', 'Product added successfully!', 'success');
            } catch (PDOException $e) {
                $pdo?->rollBack();
                logMessage('error', 'Product add error: ' . $e->getMessage());
                $errors[] = 'Could not add product. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Product — <?= e(APP_NAME) ?></title>
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
      <h4 class="fw-bold mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Product</h4>
      <a href="<?= APP_URL ?>/seller/products.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Back
      </a>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <?php foreach ($errors as $err): ?>
          <div><i class="fas fa-exclamation-circle me-1"></i><?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-8">
        <div class="seller-card">
          <h5><i class="fas fa-info-circle me-2"></i>Product Details</h5>
          <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required maxlength="200"
                       placeholder="e.g., Wireless Bluetooth Headphones">
              </div>
              <div class="col-md-6">
                <label class="form-label">Category <span class="text-danger">*</span></label>
                <select name="category_id" class="form-select" required>
                  <option value="">— Select Category —</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">SKU</label>
                <input type="text" name="sku" class="form-control" placeholder="e.g., WBH-001" maxlength="50">
              </div>
              <div class="col-md-3">
                <label class="form-label">Price ($) <span class="text-danger">*</span></label>
                <input type="number" name="price" class="form-control" step="0.01" min="0.01" required
                       placeholder="0.00">
              </div>
              <div class="col-md-3">
                <label class="form-label">Stock <span class="text-danger">*</span></label>
                <input type="number" name="stock" class="form-control" min="0" value="0" required>
              </div>
              <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="4"
                          placeholder="Describe your product in detail…"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Product Images</label>
                <input type="file" name="images[]" class="form-control" multiple accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPG, PNG, WEBP. Max 5MB each. Up to 5 images.</div>
              </div>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="featured" id="featured" value="1">
                  <label class="form-check-label" for="featured">
                    <i class="fas fa-star text-warning me-1"></i>Feature this product on the homepage
                  </label>
                </div>
              </div>
              <div class="col-12 mt-4">
                <button type="submit" class="btn btn-success btn-lg">
                  <i class="fas fa-save me-2"></i>Add Product
                </button>
                <a href="<?= APP_URL ?>/seller/products.php" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Sidebar Help -->
      <div class="col-lg-4">
        <div class="seller-card">
          <h5><i class="fas fa-lightbulb me-2 text-warning"></i>Tips</h5>
          <ul class="small text-muted mb-0">
            <li>Use clear, descriptive product names.</li>
            <li>Write a detailed description to help customers.</li>
            <li>Upload high-quality images (at least 800×600px).</li>
            <li>Set realistic stock quantities.</li>
            <li>Feature your best sellers for more visibility.</li>
          </ul>
        </div>
      </div>
    </div>
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
