<?php
/**
 * Admin navbar
 */
defined('APP_START') or die();
?>
<nav class="navbar navbar-dark bg-dark sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="<?= APP_URL ?>/admin/dashboard.php">
      <i class="fas fa-shield-alt me-1"></i><?= e(APP_NAME) ?> <span class="text-warning">Admin</span>
    </a>
    <div class="d-flex align-items-center gap-3">
      <span class="text-light small d-none d-md-inline">
        <i class="fas fa-user-shield me-1"></i><?= e($_SESSION['user']['name']) ?>
      </span>
      <a href="<?= APP_URL ?>/public/shop.php" class="btn btn-sm btn-outline-light" target="_blank">
        <i class="fas fa-external-link-alt me-1"></i>View Site
      </a>
      <a href="<?= APP_URL ?>/admin/logout.php" class="btn btn-sm btn-danger">
        <i class="fas fa-sign-out-alt me-1"></i>Logout
      </a>
    </div>
  </div>
</nav>
