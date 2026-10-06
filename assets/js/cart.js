/* ─── NovaMart — Cart JavaScript ────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  const appUrl = new URL('../', window.location.href).pathname.replace(/\/$/, '');
  const parseResponse = response => response.text().then(text => {
    if (!response.ok) {
      throw new Error(`Server returned HTTP ${response.status}`);
    }
    try {
      return JSON.parse(text);
    } catch {
      throw new Error('Server returned invalid JSON');
    }
  });

  // ── AJAX: Add to cart ──────────────────────────────
  document.querySelectorAll('.btn-add-cart').forEach(btn => {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      const form = this.closest('form');
      if (!form) return;
      const productId = form.querySelector('input[name="product_id"]')?.value;
      const quantity  = form.querySelector('input[name="quantity"]')?.value || 1;
      const csrf      = form.querySelector('input[name="csrf_token"]')?.value;

      const fd = new FormData();
      fd.append('product_id', productId);
      fd.append('quantity',   quantity);
      fd.append('csrf_token', csrf);

      fetch(appUrl + '/public/cart.php?action=add', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      })
      .then(parseResponse)
      .then(data => {
        if (data.success) {
          showToast(data.message || 'Added to cart!', 'success');
          // Update cart badge if present
          const badge = document.querySelector('.cart-badge-count');
          if (badge && data.cart_count !== undefined) badge.textContent = data.cart_count;
        } else {
          showToast(data.message || 'Could not add to cart', 'danger');
        }
      })
      .catch(error => showToast(error.message || 'Network error. Please try again.', 'danger'));
    });
  });

  // ── AJAX: Remove from cart ─────────────────────────
  document.querySelectorAll('.btn-remove-cart').forEach(btn => {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      const itemId = this.dataset.itemId;
      if (!confirmAction('Remove this item from cart?')) return;

      const fd = new FormData();
      fd.append('item_id', itemId);
      fd.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');

      fetch(appUrl + '/public/cart.php?action=remove', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      })
      .then(parseResponse)
      .then(data => {
        if (data.success) {
          const row = document.querySelector(`tr[data-item-id="${itemId}"]`);
          if (row) row.remove();
          if (data.cart_count !== undefined) {
            const badge = document.querySelector('.cart-badge-count');
            if (badge) badge.textContent = data.cart_count;
          }
          if (data.html) {
            document.querySelector('#cart-summary')?.innerHTML && (document.querySelector('#cart-summary').innerHTML = data.html);
          }
          showToast(data.message || 'Item removed', 'success');
          if (data.redirect) window.location.href = data.redirect;
        } else {
          showToast(data.message || 'Could not remove item', 'danger');
        }
      })
      .catch(error => showToast(error.message || 'Network error.', 'danger'));
    });
  });

  // ── AJAX: Update quantity ──────────────────────────
  document.querySelectorAll('.btn-update-cart').forEach(btn => {
    btn.addEventListener('click', function () {
      const row     = this.closest('tr');
      const itemId  = row?.dataset?.itemId;
      const input   = row?.querySelector('input[name="qty"]');
      const qty     = parseInt(input?.value) || 1;
      const csrf    = document.querySelector('input[name="csrf_token"]')?.value;

      const fd = new FormData();
      fd.append('item_id', itemId);
      fd.append('quantity', qty);
      fd.append('csrf_token', csrf);

      fetch(appUrl + '/public/cart.php?action=update', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      })
      .then(parseResponse)
      .then(data => {
        if (data.success) {
          const qtyCell  = row?.querySelector('.cart-qty');
          const subCell  = row?.querySelector('.cart-subtotal');
          if (qtyCell)  qtyCell.textContent = data.quantity;
          if (subCell)  subCell.textContent = '$' + data.subtotal.toFixed(2);
          if (data.summary) document.querySelector('#cart-summary') && (document.querySelector('#cart-summary').innerHTML = data.summary);
          showToast('Cart updated', 'success');
        } else {
          showToast(data.message || 'Could not update cart', 'danger');
          input.value = data.old_quantity ?? input?.value;
        }
      })
      .catch(error => showToast(error.message || 'Network error.', 'danger'));
    });
  });

  // ── Live quantity change (blur) ────────────────────
  document.querySelectorAll('input[name="qty"]').forEach(input => {
    input.addEventListener('blur', function () {
      const btn = this.closest('tr')?.querySelector('.btn-update-cart');
      if (btn) btn.click();
    });
  });
});
