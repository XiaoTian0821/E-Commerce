    <main class="flex-grow-1">
      <?php $flash = getFlash(); if ($flash): ?>
        <div class="container mt-3">
          <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        </div>
      <?php endif; ?>
