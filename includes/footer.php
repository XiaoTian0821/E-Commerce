<?php
/**
 * Shared footer
 */
?>
<footer class="bg-dark text-light py-4 mt-5">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        <h5 class="fw-bold"><i class="fas fa-store me-1"></i><?= e(APP_NAME) ?></h5>
        <p class="small text-white-50"><?= e(APP_TAGLINE) ?></p>
      </div>
      <div class="col-md-4">
        <h6>Quick Links</h6>
        <ul class="list-unstyled small">
          <li><a href="<?= APP_URL ?>/index.php" class="text-white-50 text-decoration-none">Home</a></li>
          <li><a href="<?= APP_URL ?>/public/shop.php" class="text-white-50 text-decoration-none">Shop</a></li>
          <li><a href="<?= APP_URL ?>/public/orders.php" class="text-white-50 text-decoration-none">My Orders</a></li>
        </ul>
      </div>
      <div class="col-md-4">
        <h6>Contact</h6>
        <p class="small text-white-50 mb-0">
          <i class="fas fa-envelope me-1"></i>support@novamart.local<br>
          <i class="fas fa-phone me-1"></i>+1 (555) 000-0000
        </p>
      </div>
    </div>
    <hr class="border-secondary">
    <p class="text-center small text-white-50 mb-0">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</p>
  </div>
</footer>
