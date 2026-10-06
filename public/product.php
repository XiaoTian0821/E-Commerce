<?php
/**
 * Product Detail Page
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navbar.php';

$pageTitle = 'Product Details';

$slug     = trim($_GET['slug'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 5;
$offset   = ($page - 1) * $perPage;

if (!$slug) { redirect('public/shop.php', 'Invalid product.', 'danger'); }

// ── Handle POST actions before any output ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/csrf.php';
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        redirect('public/product.php?slug=' . urlencode($slug), 'Invalid security token.', 'danger');
    }

    // Wishlist toggle
    if (isset($_POST['wishlist_action']) && isLoggedIn()) {
        if ($_POST['wishlist_action'] === 'add') {
            try {
                $pdo->prepare("INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?,?)")
                    ->execute([$_SESSION['user_id'], (int)$_POST['product_id'] ?? 0]);
                redirect('public/product.php?slug=' . $slug, 'Added to wishlist!', 'success');
            } catch (PDOException $e) { logMessage('error', $e->getMessage()); }
        } elseif ($_POST['wishlist_action'] === 'remove') {
            $pdo->prepare("DELETE FROM wishlists WHERE user_id=? AND product_id=?")
                ->execute([$_SESSION['user_id'], (int)$_POST['product_id'] ?? 0]);
            redirect('public/product.php?slug=' . $slug, 'Removed from wishlist.', 'info');
        }
    }

    // Submit review
    if (isset($_POST['review_product_id']) && isLoggedIn()) {
        $pid = (int)$_POST['review_product_id'];
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($rating < 1 || $rating > 5) {
            redirect('public/product.php?slug=' . urlencode($slug), 'Please select a rating.', 'warning');
        }
        try {
            $pdo->beginTransaction();
            $ordStmt = $pdo->prepare("
                SELECT o.id FROM orders o
                JOIN order_items oi ON oi.order_id=o.id
                WHERE o.user_id=? AND oi.product_id=? AND o.order_status IN ('completed','shipped')
                ORDER BY o.created_at DESC LIMIT 1
            ");
            $ordStmt->execute([$_SESSION['user_id'], $pid]);
            $orderId = $ordStmt->fetchColumn();
            if (!$orderId) {
                $pdo->rollBack();
                redirect('public/product.php?slug=' . urlencode($slug), 'You must have purchased this product to review it.', 'warning');
            }
            $upd = $pdo->prepare("
                UPDATE product_reviews SET rating=?, comment=?, status='approved', updated_at=NOW()
                WHERE user_id=? AND product_id=?
            ");
            $upd->execute([$rating, $comment, $_SESSION['user_id'], $pid]);
            if ($upd->rowCount() === 0) {
                $ins = $pdo->prepare("
                    INSERT INTO product_reviews (product_id, user_id, order_id, rating, comment, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'approved', NOW())
                ");
                $ins->execute([$pid, $_SESSION['user_id'], $orderId, $rating, $comment]);
            }
            $pdo->commit();
            redirect('public/product.php?slug=' . urlencode($slug), 'Review submitted! Thank you.', 'success');
        } catch (PDOException $e) {
            $pdo?->rollBack();
            logMessage('error', 'Review insert error: ' . $e->getMessage());
            redirect('public/product.php?slug=' . urlencode($slug), 'Could not submit review. Please try again.', 'danger');
        }
    }

    // Edit review
    if (isset($_POST['edit_review_id']) && isLoggedIn()) {
        $revId = (int)$_POST['edit_review_id'];
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($rating < 1 || $rating > 5) {
            redirect('public/product.php?slug=' . urlencode($slug), 'Please select a rating.', 'warning');
        }
        try {
            $upd = $pdo->prepare("
                UPDATE product_reviews SET rating=?, comment=?, updated_at=NOW()
                WHERE id=? AND user_id=?
            ");
            $upd->execute([$rating, $comment, $revId, $_SESSION['user_id']]);
            redirect('public/product.php?slug=' . urlencode($slug), 'Review updated!', 'success');
        } catch (PDOException $e) {
            logMessage('error', 'Review update error: ' . $e->getMessage());
            redirect('public/product.php?slug=' . urlencode($slug), 'Could not update review.', 'danger');
        }
    }
}

// Fetch product with seller info
$productStmt = $pdo->prepare("
    SELECT p.*, u.name AS seller_name, u.id AS seller_id,
           c.name AS category_name, c.id AS category_id,
           (SELECT COUNT(*) FROM product_reviews pr WHERE pr.product_id=p.id AND pr.status='approved') AS review_count,
           COALESCE(AVG(pr.rating),0) AS avg_rating
    FROM products p
    JOIN users u ON u.id=p.seller_id
    JOIN categories c ON c.id=p.category_id
    LEFT JOIN product_reviews pr ON pr.product_id=p.id AND pr.status='approved'
    WHERE p.slug = ? AND p.status IN ('active','out_of_stock')
    GROUP BY p.id
");
$productStmt->execute([$slug]);
$product = $productStmt->fetch();

if (!$product) { redirect('public/shop.php', 'Product not found.', 'danger'); }

// Fetch images
$imgStmt = $pdo->prepare("SELECT image_path, display_order FROM product_images WHERE product_id=? ORDER BY display_order");
$imgStmt->execute([$product['id']]);
$images = $imgStmt->fetchAll();
$mainImage = $images ? $images[0]['image_path'] : 'uploads/products/default-product.jpg';

// Fetch reviews
$revCountStmt = $pdo->prepare("SELECT COUNT(*) FROM product_reviews WHERE product_id=? AND status='approved'");
$revCountStmt->execute([$product['id']]);
$totalReviews = (int)$revCountStmt->fetchColumn();
$totalReviewPages = max(1, (int)ceil($totalReviews / $perPage));
if ($page > $totalReviewPages) $page = $totalReviewPages;

$reviewStmt = $pdo->prepare("
    SELECT pr.*, u.name AS reviewer_name, u.id AS reviewer_id
    FROM product_reviews pr
    JOIN users u ON u.id=pr.user_id
    WHERE pr.product_id=? AND pr.status='approved'
    ORDER BY pr.created_at DESC
    LIMIT ? OFFSET ?
");
$reviewStmt->execute([$product['id'], $perPage, $offset]);
$reviews = $reviewStmt->fetchAll();

// Rating distribution
$dist = [1=>0,2=>0,3=>0,4=>0,5=>0];
$distStmt = $pdo->prepare("SELECT rating, COUNT(*) AS cnt FROM product_reviews WHERE product_id=? AND status='approved' GROUP BY rating");
$distStmt->execute([$product['id']]);
while ($r = $distStmt->fetch()) $dist[(int)$r['rating']] = (int)$r['cnt'];

// Related products
$relStmt = $pdo->prepare("
    SELECT p.*, u.name AS seller_name,
           (SELECT image_path FROM product_images pi WHERE pi.product_id=p.id ORDER BY display_order LIMIT 1) AS image
    FROM products p
    JOIN users u ON u.id=p.seller_id
    WHERE p.category_id=? AND p.id!=? AND p.status='active'
    ORDER BY RAND() LIMIT 4
");
$relStmt->execute([$product['category_id'], $product['id']]);
$relatedProducts = $relStmt->fetchAll();

// Check if user is in wishlist
$isWished = false;
if (isLoggedIn()) {
    $wishStmt = $pdo->prepare("SELECT 1 FROM wishlists WHERE user_id=? AND product_id=?");
    $wishStmt->execute([$_SESSION['user_id'], $product['id']]);
    $isWished = (bool)$wishStmt->fetch();
}

// Check purchase for review
$hasPurchased = false;
$existingReview = null;
if (isLoggedIn()) {
    $purchasedStmt = $pdo->prepare("
        SELECT 1 FROM orders o JOIN order_items oi ON oi.order_id=o.id
        WHERE o.user_id=? AND oi.product_id=? AND o.order_status IN ('completed','shipped')
        LIMIT 1
    ");
    $purchasedStmt->execute([$_SESSION['user_id'], $product['id']]);
    $hasPurchased = (bool)$purchasedStmt->fetch();
    if ($hasPurchased) {
        $revStmt2 = $pdo->prepare("SELECT * FROM product_reviews WHERE user_id=? AND product_id=? LIMIT 1");
        $revStmt2->execute([$_SESSION['user_id'], $product['id']]);
        $existingReview = $revStmt2->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($product['name']) ?> — <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
<?= $navbar ?>

<main class="flex-grow-1 py-4">
  <div class="container">

    <?php $flash = getFlash(); if ($flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/public/shop.php">Shop</a></li>
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/public/shop.php?category=<?= (int)$product['category_id'] ?>"><?= e($product['category_name']) ?></a></li>
        <li class="breadcrumb-item active"><?= e($product['name']) ?></li>
      </ol>
    </nav>

    <div class="row g-4">
      <!-- ── Image Gallery ── -->
      <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-2">
          <img src="<?= APP_URL ?>/<?= e($mainImage) ?>"
               id="mainProductImage"
               class="card-img-top" alt="<?= e($product['name']) ?>"
               style="aspect-ratio:4/3;object-fit:cover;max-height:480px">
        </div>
        <?php if (count($images) > 1): ?>
          <div class="d-flex gap-2 flex-wrap">
            <?php foreach ($images as $idx => $img): ?>
              <img src="<?= APP_URL ?>/<?= e($img['image_path']) ?>"
                   class="border rounded"
                   alt="Product image <?= $idx+1 ?>"
                   style="width:72px;height:72px;object-fit:cover;cursor:pointer;opacity:<?= $idx===0?1:.6 ?>"
                   onclick="document.getElementById('mainProductImage').src=this.src;this.parentElement.querySelectorAll('img').forEach(i=>i.style.opacity='.6');this.style.opacity='1'">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- ── Product Info ── -->
      <div class="col-lg-7">
        <h1 class="fw-bold mb-2"><?= e($product['name']) ?></h1>
        <p class="text-muted mb-1">
          <i class="fas fa-store me-1"></i>Sold by
          <a href="<?= APP_URL ?>/seller/profile.php?id=<?= (int)$product['seller_id'] ?>"><?= e($product['seller_name']) ?></a>
          &nbsp;·&nbsp;
          <i class="fas fa-tag me-1"></i><?= e($product['category_name']) ?>
        </p>
        <?php if ($product['sku']): ?>
          <p class="small text-muted mb-3">SKU: <code><?= e($product['sku']) ?></code></p>
        <?php endif; ?>

        <div class="d-flex align-items-baseline gap-3 mb-3">
          <span class="fs-3 fw-bold text-primary"><?= formatCurrency((float)$product['price']) ?></span>
        </div>

        <div class="mb-3">
          <div class="stars fs-5">
            <?php
            $r = round((float)$product['avg_rating']);
            for ($i=1;$i<=5;$i++):
              echo $i<=$r ? '<i class="fas fa-star text-warning"></i>' : '<i class="far fa-star text-muted"></i>';
            endfor;
            ?>
          </div>
          <a href="#reviews" class="small text-muted"><?= $product['review_count'] ?> review(s)</a>
        </div>

        <div class="mb-4">
          <?php if ((int)$product['stock'] > 0): ?>
            <span class="badge bg-success fs-6 py-2 px-3">
              <i class="fas fa-check-circle me-1"></i>In Stock (<?= (int)$product['stock'] ?> available)
            </span>
          <?php else: ?>
            <span class="badge bg-danger fs-6 py-2 px-3">
              <i class="fas fa-times-circle me-1"></i>Out of Stock
            </span>
          <?php endif; ?>
        </div>

        <div class="mb-4">
          <h5>Description</h5>
          <p class="text-muted"><?= nl2br(e($product['description'])) ?></p>
        </div>

        <!-- Add to cart / Wishlist -->
        <div class="d-flex gap-2 flex-wrap mb-4">
          <?php if (isLoggedIn() && (int)$product['stock'] > 0): ?>
            <form method="POST" action="<?= APP_URL ?>/public/cart.php" class="d-flex gap-2 align-items-center">
              <?= csrfField() ?>
              <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
              <input type="number" name="quantity" value="1" min="1" max="<?= (int)$product['stock'] ?>"
                     class="form-control form-control-sm" style="width:70px" id="qtyInput">
              <button type="submit" class="btn btn-brand btn-lg">
                <i class="fas fa-cart-plus me-2"></i>Add to Cart
              </button>
              <a href="<?= APP_URL ?>/public/checkout.php" class="btn btn-accent btn-lg">
                <i class="fas fa-bolt me-2"></i>Buy Now
              </a>
            </form>
          <?php elseif (!isLoggedIn()): ?>
            <a href="<?= APP_URL ?>/public/login.php" class="btn btn-brand btn-lg">
              <i class="fas fa-sign-in-alt me-2"></i>Login to Buy
            </a>
          <?php else: ?>
            <button class="btn btn-secondary btn-lg" disabled>
              <i class="fas fa-times-circle me-2"></i>Out of Stock
            </button>
          <?php endif; ?>

          <?php if (isLoggedIn()): ?>
            <form method="POST" class="ms-2">
              <?= csrfField() ?>
              <input type="hidden" name="wishlist_action" value="<?= $isWished ? 'remove' : 'add' ?>">
              <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
              <button type="submit" class="btn btn-lg <?= $isWished ? 'btn-outline-danger' : 'btn-outline-secondary' ?>"
                      title="<?= $isWished ? 'Remove from wishlist' : 'Add to wishlist' ?>">
                <i class="fas fa-heart"></i>
                <?= $isWished ? 'Wished' : 'Wishlist' ?>
              </button>
            </form>
          <?php else: ?>
            <a href="<?= APP_URL ?>/public/login.php" class="btn btn-outline-secondary btn-lg ms-2">
              <i class="fas fa-heart me-2"></i>Wishlist
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── Reviews Section ── -->
    <section id="reviews" class="mt-5">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="fw-bold mb-0">Customer Reviews</h4>
      </div>

      <!-- Rating distribution -->
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="card border-0 shadow-sm p-3 text-center">
            <div class="fs-1 fw-bold"><?= number_format((float)$product['avg_rating'], 1) ?></div>
            <div class="stars text-warning">
              <?php $r=round((float)$product['avg_rating']); for($i=1;$i<=5;$i++):echo $i<=$r?'<i class="fas fa-star"></i>':'<i class="far fa-star text-muted"></i>';endfor;?>
            </div>
            <div class="small text-muted"><?= (int)$product['review_count'] ?> reviews</div>
          </div>
        </div>
        <div class="col-md-8">
          <?php foreach ([5,4,3,2,1] as $n): ?>
            <div class="d-flex align-items-center gap-2 mb-1">
              <span class="small fw-bold" style="width:20px"><?= $n ?></span>
              <i class="fas fa-star text-warning" style="width:16px"></i>
              <div class="progress flex-grow-1" style="height:8px;border-radius:4px">
                <div class="progress-bar bg-warning" style="width:<?= $totalReviews ? round($dist[$n]/$totalReviews*100) : 0 ?>%"></div>
              </div>
              <span class="small text-muted" style="width:30px"><?= $dist[$n] ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Review form -->
      <?php if ($hasPurchased && !$existingReview): ?>
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-body p-4">
            <h5 class="fw-bold mb-3">Write a Review</h5>
            <form method="POST" action="<?= APP_URL ?>/public/product.php">
              <?= csrfField() ?>
              <input type="hidden" name="review_product_id" value="<?= $product['id'] ?>">
              <div class="mb-3">
                <label class="form-label">Rating <span class="text-danger">*</span></label>
                <div class="rating-group d-flex gap-1" id="reviewStars">
                  <?php for ($i=1;$i<=5;$i++): ?>
                    <i class="fas fa-star star-btn text-muted" data-val="<?= $i ?>"
                       style="font-size:1.8rem;cursor:pointer;transition:color .15s"></i>
                  <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" value="0" id="reviewRatingInput">
              </div>
              <div class="mb-3">
                <label class="form-label">Comment</label>
                <textarea name="comment" class="form-control" rows="3"
                          placeholder="Share your experience with this product…" maxlength="1000"></textarea>
              </div>
              <button type="submit" class="btn btn-brand">Submit Review</button>
            </form>
          </div>
        </div>
      <?php elseif ($existingReview): ?>
        <div class="alert alert-info mb-4">
          <i class="fas fa-check-circle me-2"></i>
          You have already reviewed this product.
          <a href="#" class="fw-bold" data-bs-toggle="modal" data-bs-target="#editReviewModal">Edit review</a>
        </div>
      <?php elseif (!isLoggedIn()): ?>
        <div class="alert alert-warning mb-4">
          <a href="<?= APP_URL ?>/public/login.php">Login</a> to write a review.
        </div>
      <?php endif; ?>

      <!-- Reviews list -->
      <?php if (empty($reviews)): ?>
        <div class="text-center py-4 text-muted">
          <i class="fas fa-comment-dots fa-2x mb-2"></i>
          <p>No reviews yet. Be the first to review!</p>
        </div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($reviews as $rev):
            $canEdit = isLoggedIn() && (int)$rev['user_id'] === (int)$_SESSION['user_id'];
          ?>
            <div class="col-12">
              <div class="card border-0 shadow-sm mb-2">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <span class="fw-bold"><?= e($rev['reviewer_name']) ?></span>
                      <span class="text-muted small ms-2"><?= date('M j, Y', strtotime($rev['created_at'])) ?></span>
                    </div>
                    <?php if ($canEdit): ?>
                      <button class="btn btn-sm btn-link text-muted" data-bs-toggle="modal" data-bs-target="#editReviewModal"
                              data-id="<?= $rev['id'] ?>" data-rating="<?= $rev['rating'] ?>" data-comment="<?= e($rev['comment']) ?>">
                        <i class="fas fa-edit"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                  <div class="stars text-warning my-1">
                    <?php for ($i=1;$i<=5;$i++): echo $i<=(int)$rev['rating']?'<i class="fas fa-star"></i>':'<i class="far fa-star text-muted"></i>';endfor; ?>
                  </div>
                  <p class="mb-0 text-muted"><?= nl2br(e($rev['comment'])) ?></p>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($totalReviewPages > 1): ?>
          <nav class="mt-3 d-flex justify-content-center">
            <ul class="pagination">
              <li class="page-item <?= $page<=1?'disabled':'' ?>">
                <a class="page-link" href="?slug=<?= e($slug) ?>&page=<?= $page-1 ?>">Prev</a>
              </li>
              <?php for ($i=1;$i<=$totalReviewPages;$i++): ?>
                <li class="page-item <?= $i===$page?'active':'' ?>">
                  <a class="page-link" href="?slug=<?= e($slug) ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?= $page>=$totalReviewPages?'disabled':'' ?>">
                <a class="page-link" href="?slug=<?= e($slug) ?>&page=<?= $page+1 ?>">Next</a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <!-- ── Edit Review Modal ── -->
    <div class="modal fade" id="editReviewModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Edit Review</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form method="POST" action="<?= APP_URL ?>/public/product.php">
              <?= csrfField() ?>
              <input type="hidden" name="edit_review_id" value="">
              <input type="hidden" name="review_product_id" value="<?= $product['id'] ?>">
              <div class="mb-3">
                <label class="form-label">Rating</label>
                <div class="rating-group d-flex gap-1" id="editReviewStars">
                  <?php for ($i=1;$i<=5;$i++): ?>
                    <i class="fas fa-star star-btn text-muted" data-val="<?= $i ?>" style="font-size:1.5rem;cursor:pointer"></i>
                  <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" value="0" id="editReviewRating">
              </div>
              <div class="mb-3">
                <label class="form-label">Comment</label>
                <textarea name="comment" class="form-control" rows="3" maxlength="1000"></textarea>
              </div>
              <button type="submit" class="btn btn-brand">Save Changes</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Related Products ── -->
    <?php if (!empty($relatedProducts)): ?>
      <section class="mt-5">
        <h3 class="section-title">Related Products</h3>
        <div class="row g-4">
          <?php foreach ($relatedProducts as $rp): ?>
            <div class="col-6 col-md-3">
              <div class="product-card">
                <div class="card-img-wrap">
                  <a href="<?= APP_URL ?>/public/product.php?slug=<?= e($rp['slug']) ?>">
                    <img src="<?= APP_URL ?>/<?= e($rp['image'] ?? 'uploads/products/default-product.jpg') ?>"
                         alt="<?= e($rp['name']) ?>" loading="lazy">
                  </a>
                </div>
                <div class="card-body">
                  <div class="product-name">
                    <a href="<?= APP_URL ?>/public/product.php?slug=<?= e($rp['slug']) ?>"><?= e($rp['name']) ?></a>
                  </div>
                  <div class="product-price"><?= formatCurrency((float)$rp['price']) ?></div>
                  <div class="product-stock stock-in"><i class="fas fa-check-circle"></i> In Stock</div>
                  <div class="card-actions">
                    <a href="<?= APP_URL ?>/public/product.php?slug=<?= e($rp['slug']) ?>"
                       class="btn btn-sm btn-outline-secondary w-100"><i class="fas fa-eye me-1"></i>View</a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

  </div>
</main>

<!-- Edit Review modal form data -->
<script>
document.getElementById('editReviewModal').addEventListener('show.bs.modal', function (e) {
  const btn = e.relatedTarget;
  const modal = this;
  modal.querySelector('[name="edit_review_id"]').value = btn.dataset.id;
  modal.querySelector('[name="rating"]').value = btn.dataset.rating;
  modal.querySelector('[name="comment"]').value = btn.dataset.comment;
  modal.querySelectorAll('#editReviewStars .star-btn').forEach(s => {
    s.classList.toggle('text-warning', parseInt(s.dataset.val) <= parseInt(btn.dataset.rating));
    s.classList.toggle('text-muted', parseInt(s.dataset.val) > parseInt(btn.dataset.rating));
  });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
