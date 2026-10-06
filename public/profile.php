<?php
/**
 * Customer Profile Page
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/navbar.php';

$pageTitle = 'My Profile';
requireLogin();

$user = $_SESSION['user'];
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/csrf.php';
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $name     = trim($_POST['name']     ?? '');
        $phone    = trim($_POST['phone']    ?? '');
        $address  = trim($_POST['address']  ?? '');
        $newPass  = trim($_POST['new_password']  ?? '');
        $confirm  = trim($_POST['confirm_password'] ?? '');

        if (!$name) { $errors[] = 'Name is required.'; }
        if ($newPass && $newPass !== $confirm) { $errors[] = 'Passwords do not match.'; }
        if ($newPass && strlen($newPass) < 8) { $errors[] = 'Password must be at least 8 characters.'; }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                $fields = ['name' => $name, 'phone' => $phone, 'address' => $address];
                if ($newPass) {
                    $fields['password'] = password_hash($newPass, PASSWORD_DEFAULT);
                }
                $setClause = implode(', ', array_map(fn($k) => "$k=?",$fields));
                $vals = array_values($fields);
                $vals[] = $_SESSION['user_id'];
                $stmt = $pdo->prepare("UPDATE users SET $setClause WHERE id=?");
                $stmt->execute($vals);
                $pdo->commit();
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['phone'] = $phone;
                $_SESSION['user']['address'] = $address;
                $success = 'Profile updated successfully!';
            } catch (PDOException $e) {
                $pdo?->rollBack();
                logMessage('error', 'Profile update error: ' . $e->getMessage());
                $errors[] = 'Could not update profile. Please try again.';
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
  <title>My Profile — <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
<?= $navbar ?>

<main class="flex-grow-1 py-4">
  <div class="container">
    <?php if ($success): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <?php foreach ($errors as $err): ?>
          <div><i class="fas fa-exclamation-circle me-1"></i><?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body text-center p-4">
            <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3"
                 style="width:80px;height:80px;font-size:2rem">
              <i class="fas fa-user"></i>
            </div>
            <h5 class="fw-bold"><?= e($user['name']) ?></h5>
            <p class="text-muted small mb-1"><i class="fas fa-envelope me-1"></i><?= e($user['email']) ?></p>
            <p class="text-muted small mb-1">
              <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'seller' ? 'success' : 'primary') ?>">
                <?= e(ucfirst($user['role'])) ?>
              </span>
              <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?> ms-1">
                <?= e(ucfirst($user['status'])) ?>
              </span>
            </p>
            <p class="text-muted small mb-3">
              <i class="fas fa-calendar me-1"></i>Member since
              <?= date('M j, Y', strtotime($user['created_at'])) ?>
            </p>
            <a href="<?= APP_URL ?>/public/orders.php" class="btn btn-outline-primary w-100">
              <i class="fas fa-box me-1"></i>My Orders
            </a>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0"><i class="fas fa-user-edit me-2"></i>Edit Profile</h5>
          </div>
          <div class="card-body">
            <form method="POST">
              <?= csrfField() ?>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Full Name</label>
                  <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
                  <div class="form-text">Email cannot be changed.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone</label>
                  <input type="tel" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
                </div>
                <div class="col-12">
                  <label class="form-label">Address</label>
                  <textarea name="address" class="form-control" rows="2"><?= e($user['address'] ?? '') ?></textarea>
                </div>
                <div class="col-12"><hr></div>
                <div class="col-md-6">
                  <label class="form-label">New Password</label>
                  <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Confirm New Password</label>
                  <input type="password" name="confirm_password" class="form-control">
                </div>
              </div>
              <button type="submit" class="btn btn-brand mt-3">
                <i class="fas fa-save me-1"></i>Save Changes
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
