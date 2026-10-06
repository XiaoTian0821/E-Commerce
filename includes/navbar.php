<?php
/**
 * Shared navbar – customer & seller
 * Includes once at the top of each page; echoes $navbar variable.
 */
defined('APP_START') or die();

$cartCount = 0;
if (isLoggedIn()) {
    $cnt = $pdo->prepare(
        "SELECT COALESCE(SUM(ci.quantity),0) AS cnt FROM cart_items ci " .
        "JOIN carts c ON ci.cart_id=c.id WHERE c.user_id=?"
    );
    $cnt->execute([$_SESSION['user_id']]);
    $cartCount = (int)$cnt->fetchColumn();
}

$navbar = '
<header class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="' . APP_URL . '/index.php">
      <i class="fas fa-store me-1"></i>' . e(APP_NAME) . '
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="' . APP_URL . '/index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="' . APP_URL . '/public/shop.php">Shop</a></li>
      </ul>
      <ul class="navbar-nav">
        ' . (isLoggedIn() ? '
          ' . ($_SESSION['user']['role'] === 'admin' ? '
            <li class="nav-item"><a class="nav-link" href="' . APP_URL . '/admin/dashboard.php">Admin</a></li>' : '') . '
          ' . ($_SESSION['user']['role'] === 'seller' ? '
            <li class="nav-item"><a class="nav-link" href="' . APP_URL . '/seller/dashboard.php">Seller Panel</a></li>' : '') . '
          <li class="nav-item">
            <a class="nav-link position-relative" href="' . APP_URL . '/public/wishlist.php">
              <i class="fas fa-heart"></i>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link position-relative" href="' . APP_URL . '/public/cart.php">
              <i class="fas fa-shopping-cart"></i>
              ' . ($cartCount > 0 ? '
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.65rem">' . $cartCount . '</span>' : '') . '
            </a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
              <i class="fas fa-user-circle me-1"></i>' . e($_SESSION['user']['name']) . '
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="' . APP_URL . '/public/profile.php">My Profile</a></li>
              <li><a class="dropdown-item" href="' . APP_URL . '/public/orders.php">My Orders</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="' . APP_URL . '/public/logout.php">Logout</a></li>
            </ul>
          </li>
        ' : '
          <li class="nav-item"><a class="nav-link" href="' . APP_URL . '/public/login.php">Login</a></li>
          <li class="nav-item"><a class="nav-link" href="' . APP_URL . '/public/register.php">Register</a></li>
        ') . '
      </ul>
    </div>
  </div>
</header>';
