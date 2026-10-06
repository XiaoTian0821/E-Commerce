<?php
/**
 * Admin: Product Add / Edit
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin_navbar.php';
requireRole('admin');

$pageTitle = 'Product Management';
$isEdit = isset($_GET['id']) && (int)$_GET['id'] > 0;
$productId = $isEdit ? (int)$_GET['id'] : 0;

if ($isEdit) {
    $prodStmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $prodStmt->execute([$productId]);
    $product = $prodStmt->fetch();
    if (!$product) redirect('admin/products.php', 'Product not found.', 'danger');
}

$sellers = $pdo->query("SELECT id, name FROM users WHERE role='seller' AND status='active' ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name")->fetchAll();

// Existing images
$existingImages = [];
if ($isEdit) {
    $imgStmt = $pdo->prepare("SELECT id, image_path, display_order FROM product_images WHERE product_id=? ORDER BY display_order");
    $imgStmt->execute([$productId]);
    $existingImages = $imgStmt->fetchAll();
}

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
        $sellerId  = (int)($_POST['seller_id'] ?? 0);
        $featured  = isset($_POST['featured']);
        $status    = $_POST['status'] ?? 'active';

        if (!$name) $errors[] = 'Product name is required.';
        if ($price <= 0) $errors[] = 'Price must be greater than 0.';
        if ($stock < 0) $errors[] = 'Stock cannot be negative.';
        if (!$catId) $errors[] = 'Please select a category.';
        if (!$sellerId) $errors[] = 'Please select a seller.';
        if (strlen($sku) > 0 && strlen($sku) < 3) $errors[] = 'SKU must be at least 3 characters.';

        // Check unique SKU (excluding this product if editing)
        if (strlen($sku) > 0) {
            $skuCheck = $pdo->prepare("SELECT id FROM products WHERE sku=? AND id!=?");
            $skuCheck->execute([$sku, $productId]);
            if ($skuCheck->fetch()) $errors[] = 'This SKU is already in use.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                if ($isEdit) {
                    $slug = slugify($name);
                    // Ensure unique slug
                    $origSlug = $slug;
                    $dup = $pdo->prepare("SELECT id FROM products WHERE slug=? AND id!=?");
                    $dup->execute([$slug, $productId]);
                    $i = 1;
                    while ($dup->fetch()) {
                        $slug = $origSlug . '-' . $i++;
                        $dup->execute([$slug, $productId]);
                    }
                    $upd = $pdo->prepare("
                        UPDATE products SET seller_id=?, category_id=?, name=?, slug=?, description=?,
                                           price=?, stock=?, sku=?, status=?, featured=?, updated_at=NOW()
                        WHERE id=?
                    ");
                    $upd->execute([$sellerId, $catId, $name, $slug, $desc, $price, $stock, $sku, $status, $featured ? 1 : 0, $productId]);
                } else {
                    $slug = slugify($name);
                    $origSlug = $slug;
                    $dup = $pdo->prepare("SELECT id FROM products WHERE slug=?");
                    $dup->execute([$slug]);
                    $i = 1;
                    while ($dup->fetch()) {
                        $slug = $origSlug . '-' . $i++;
                        $dup->execute([$slug]);
                    }
                    $ins = $pdo->prepare("
                        INSERT INTO products (seller_id, category_id, name, slug, description, price, stock, sku, status, featured, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $ins->execute([$sellerId, $catId, $name, $slug, $desc, $price, $stock, $sku, $status, $featured ? 1 : 0]);
                    $productId = (int)$pdo->lastInsertId();
                }

                // Handle image uploads
                $newPaths = [];
                if (!empty($_FILES['images']['name'][0] ?? null)) {
                    foreach ($_FILES['images']['name'] as $key => $fname) {
                        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                            $result = uploadImage($_FILES['images'], 'products');
                            if ($result) $newPaths[] = $result;
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
                foreach ($newPaths as $idx => $path) {
                    $insImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, display_order) VALUES (?, ?, ?)");
                    $insImg->execute([$productId, $path, count($existingImages) + $idx]);
                }
                if (empty($newPaths) && empty($existingImages) && !$isEdit) {
                    $insImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, display_order) VALUES (?, ?, ?)");
                    $insImg->execute([$productId, 'uploads/products/default-product.jpg', 0]);
                }

                $pdo->commit();
                logMessage('info', 'Admin ' . ($_SESSION['user_id'] ?? 'unknown') . ($isEdit ? ' edited' : ' added') . ' product ID ' . $productId);
                redirect('admin/products.php', $isEdit ? 'Product updated!' : 'Product added!', 'success');
            } catch (PDOException $e) {
                $pdo?->rollBack();
                logMessage('error', 'Admin product error: ' . $e->getMessage());
                $errors[] = 'Could not save product. Please try again.';
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
  <title><?= $isEdit ? 'Edit' : 'Add' ?> Product — Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body class="bg-light">
<?= $adminNavbar ?>
<div class="container py-4">
  <a href="<?= APP_URL ?>/admin/products.php" class="btn btn-outline-secondary btn-sm mb-3">
    <i class="fas fa-arrow-left me-1"></i>Back to Products
  </a>
  <h4 class="fw-bold mb-4">
    <i class="fas fa-<?= $isEdit ? 'edit' : 'plus-circle' ?> me-2"></i>
    <?= $isEdit ? 'Edit' : 'Add' ?> Product
  </h4>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $err): ?>
        <div><i class="fas fa-exclamation-circle me-1"></i><?= e($err) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="admin-card">
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Product Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" required maxlength="200"
                 value="<?= e($product['name'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Seller <span class="text-danger">*</span></label>
          <select name="seller_id" class="form-select" required>
            <option value="">— Select Seller —</option>
            <?php foreach ($sellers as $s): ?>
              <option value="<?= $s['id'] ?>" <?= isset($product) && (int)$product['seller_id']===(int)$s['id']?'selected':'' ?>>
                <?= e($s['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Category <span class="text-danger">*</span></label>
          <select name="category_id" class="form-select" required>
            <option value="">— Select Category —</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>" <?= isset($product) && (int)$product['category_id']===(int)$cat['id']?'selected':'' ?>>
                <?= e($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">SKU</label>
          <input type="text" name="sku" class="form-control" maxlength="50"
                 value="<?= e($product['sku'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Price ($) <span class="text-danger">*</span></label>
          <input type="number" name="price" class="form-control" step="0.01" min="0.01" required
                 value="<?= htmlspecialchars($product['price'] ?? '', ENT_QUOTES) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Stock <span class="text-danger">*</span></label>
          <input type="number" name="stock" class="form-control" min="0" value="<?= (int)($product['stock'] ?? 0) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="active" <?= ($product['status'] ?? 'active')==='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= ($product['status'] ?? '')==='inactive'?'selected':'' ?>>Inactive</option>
            <option value="out_of_stock" <?= ($product['status'] ?? '')==='out_of_stock'?'selected':'' ?>>Out of Stock</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="4"><?= e($product['description'] ?? '') ?></textarea>
        </div>
        <div class="col-md-4">
          <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" name="featured" id="featured" value="1"
                   <?= ($product['featured'] ?? false) ? 'checked' : '' ?>>
            <label class="form-check-label" for="featured">
              <i class="fas fa-star text-warning me-1"></i>Feature on homepage
            </label>
          </div>
        </div>
        <div class="col-12"><hr></div>
        <div class="col-12">
          <label class="form-label">Images</label>
          <?php if (!empty($existingImages)): ?>
            <div class="row g-2 mb-2">
              <?php foreach ($existingImages as $img): ?>
                <div class="col-3">
                  <div class="position-relative">
                    <img src="<?= APP_URL ?>/<?= e($img['image_path']) ?>" alt=""
                         class="img-fluid rounded" style="height:80px;object-fit:cover;width:100%">
                    <input type="checkbox" name="delete_images[]" value="<?= $img['id'] ?>"
                           class="form-check-input position-absolute top-0 end-0 m-1" style="background:#fff">
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <input type="file" name="images[]" class="form-control" multiple accept="image/jpeg,image/png,image/webp">
          <div class="form-text">JPG, PNG, WEBP. Max 5MB each.</div>
        </div>
        <div class="col-12 mt-3">
          <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save me-1"></i>
            <?= $isEdit ? 'Update Product' : 'Add Product' ?>
          </button>
          <a href="<?= APP_URL ?>/admin/products.php" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
