<?php
/**
 * Seller: Edit Product
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
$pageTitle = 'Edit Product';

$productId = (int)($_GET['id'] ?? 0);
if (!$productId) redirect('seller/products.php', 'Invalid product.', 'danger');

// Verify ownership
$prodStmt = $pdo->prepare("SELECT * FROM products WHERE id=? AND seller_id=?");
$prodStmt->execute([$productId, $userId]);
$product = $prodStmt->fetch();
if (!$product) redirect('seller/products.php', 'Product not found or you do not have permission.', 'danger');

$categories = $pdo->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name")->fetchAll();

// Fetch existing images
$imgStmt = $pdo->prepare("SELECT id, image_path, display_order FROM product_images WHERE product_id=? ORDER BY display_order");
$imgStmt->execute([$productId]);
$existingImages = $imgStmt->fetchAll();

$errors = [];

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

        // Check unique SKU (excluding this product)
        if (strlen($sku) > 0) {
            $skuCheck = $pdo->prepare("SELECT id FROM products WHERE sku=? AND seller_id=? AND id!=?");
            $skuCheck->execute([$sku, $userId, $productId]);
            if ($skuCheck->fetch()) $errors[] = 'This SKU is already used by another product.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Update product
                $upd = $pdo->prepare("
                    UPDATE products SET name=?, description=?, price=?, stock=?, sku=?,
                                       category_id=?, featured=?, updated_at=NOW()
                    WHERE id=? AND seller_id=?
                ");
                $upd->execute([$name, $desc, $price, $stock, $sku, $catId, $featured ? 1 : 0, $productId, $userId]);

                // Update slug if name changed
                if ($product['name'] !== $name) {
                    $newSlug = slugify($name);
                    $origSlug = $newSlug;
                    $dup = $pdo->prepare("SELECT id FROM products WHERE slug=? AND id!=?");
                    $dup->execute([$newSlug, $productId]);
                    $i = 1;
                    while ($dup->fetch()) {
                        $newSlug = $origSlug . '-' . $i++;
                        $dup->execute([$newSlug, $productId]);
                    }
                    $pdo->prepare("UPDATE products SET slug=? WHERE id=?")->execute([$newSlug, $productId]);
                }

                // Handle new image uploads
                $newImagePaths = [];
                if (!empty($_FILES['images']['name'][0] ?? null)) {
                    foreach ($_FILES['images']['name'] as $key => $fname) {
                        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                            $result = uploadImage($_FILES['images'], 'products');
                            if ($result) $newImagePaths[] = $result;
                        }
                    }
                }

                // Handle image deletion
                $deleteImages = $_POST['delete_images'] ?? [];
                if (!empty($deleteImages)) {
                    $delStmt = $pdo->prepare("DELETE FROM product_images WHERE id=? AND product_id=?");
                    foreach ($deleteImages as $imgId) {
                        $delStmt->execute([(int)$imgId, $productId]);
                    }
                }

                // Insert new images
                foreach ($newImagePaths as $idx => $path) {
                    $insImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, display_order) VALUES (?, ?, ?)");
                    $insImg->execute([$productId, $path, count($existingImages) + $idx]);
                }

                $pdo->commit();
                logMessage('info', 'Seller ' . $userId . ' updated product ID ' . $productId);
                redirect('seller/products.php', 'Product updated successfully!', 'success');
            } catch (PDOException $e) {
                $pdo?->rollBack();
                logMessage('error', 'Product update error: ' . $e->getMessage());
                $errors[] = 'Could not update product. Please try again.';
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
  <title>Edit Product — <?= e(APP_NAME) ?></title>
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
      <h4 class="fw-bold mb-0"><i class="fas fa-edit me-2"></i>Edit Product</h4>
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
                       value="<?= e($product['name']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Category <span class="text-danger">*</span></label>
                <select name="category_id" class="form-select" required>
                  <option value="">— Select —</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= (int)$cat['id']===(int)$product['category_id']?'selected':'' ?>>
                      <?= e($cat['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">SKU</label>
                <input type="text" name="sku" class="form-control" maxlength="50"
                       value="<?= e($product['sku'] ?? '') ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Price ($) <span class="text-danger">*</span></label>
                <input type="number" name="price" class="form-control" step="0.01" min="0.01" required
                       value="<?= htmlspecialchars($product['price'], ENT_QUOTES) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Stock <span class="text-danger">*</span></label>
                <input type="number" name="stock" class="form-control" min="0" value="<?= (int)$product['stock'] ?>" required>
              </div>
              <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="4"><?= e($product['description']) ?></textarea>
              </div>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="featured" id="featured" value="1"
                         <?= $product['featured'] ? 'checked' : '' ?>>
                  <label class="form-check-label" for="featured">
                    <i class="fas fa-star text-warning me-1"></i>Feature this product
                  </label>
                </div>
              </div>
              <div class="col-12 mt-4">
                <button type="submit" class="btn btn-success btn-lg">
                  <i class="fas fa-save me-2"></i>Save Changes
                </button>
                <a href="<?= APP_URL ?>/seller/products.php" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Images -->
      <div class="col-lg-4">
        <div class="seller-card">
          <h5><i class="fas fa-images me-2"></i>Product Images</h5>
          <?php if (!empty($existingImages)): ?>
            <div class="row g-2 mb-3">
              <?php foreach ($existingImages as $img): ?>
                <div class="col-6">
                  <div class="position-relative">
                    <img src="<?= APP_URL ?>/<?= e($img['image_path']) ?>" alt=""
                         class="img-fluid rounded" style="height:100px;object-fit:cover;width:100%">
                    <input type="checkbox" name="delete_images[]" value="<?= $img['id'] ?>"
                           class="form-check-input position-absolute top-0 end-0 m-1" style="background:#fff">
                  </div>
                  <div class="small text-muted text-center mt-1">Order: <?= (int)$img['display_order'] ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="small text-muted">No images uploaded yet.</p>
          <?php endif; ?>

          <label class="form-label">Add New Images</label>
          <input type="file" name="images[]" class="form-control mb-3" multiple accept="image/jpeg,image/png,image/webp">
          <div class="form-text small">JPG, PNG, WEBP. Max 5MB each. Up to 5 additional images.</div>
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
