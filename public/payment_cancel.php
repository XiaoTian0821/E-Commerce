<?php
/**
 * Payment Cancel Page
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Cancelled — <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center min-vh-100 bg-light">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-6 text-center">
        <div class="card border-0 shadow-lg">
          <div class="card-body p-5">
            <i class="fas fa-times-circle fa-4x text-danger mb-3"></i>
            <h3 class="fw-bold">Payment Cancelled</h3>
            <p class="text-muted">Your payment was not completed. No charges have been made to your account.</p>
            <div class="d-grid gap-2 mt-4">
              <a href="<?= APP_URL ?>/public/checkout.php" class="btn btn-brand btn-lg">
                <i class="fas fa-arrow-left me-2"></i>Back to Checkout
              </a>
              <a href="<?= APP_URL ?>/public/shop.php" class="btn btn-outline-secondary">
                <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
