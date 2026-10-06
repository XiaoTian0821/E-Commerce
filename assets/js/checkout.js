/* ─── NovaMart — Checkout JavaScript ────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  const appUrl = new URL('../', window.location.href).pathname.replace(/\/$/, '');

  // ── Coupon validation via AJAX ─────────────────────
  const couponBtn = document.getElementById('btn-apply-coupon');
  if (couponBtn) {
    couponBtn.addEventListener('click', function (e) {
      e.preventDefault();
      const codeInput  = document.getElementById('coupon_code');
      const code       = codeInput?.value.trim();
      const csrf       = document.querySelector('input[name="csrf_token"]')?.value;
      const resultDiv  = document.getElementById('coupon-result');

      if (!code) {
        if (resultDiv) resultDiv.innerHTML = '<div class="alert alert-warning">Please enter a coupon code.</div>';
        return;
      }

      const fd = new FormData();
      fd.append('code', code);
      fd.append('csrf_token', csrf);

      couponBtn.disabled = true;
      couponBtn.textContent = 'Checking…';

      fetch(appUrl + '/public/checkout.php?action=validate_coupon', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      })
      .then(r => r.json())
      .then(data => {
        couponBtn.disabled = false;
        couponBtn.textContent = 'Apply';
        if (resultDiv) {
          if (data.success) {
            resultDiv.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
            if (data.html) document.getElementById('order-summary') && (document.getElementById('order-summary').innerHTML = data.html);
            if (data.coupon_id) {
              const hidden = document.createElement('input');
              hidden.type = 'hidden';
              hidden.name = 'coupon_id';
              hidden.value = data.coupon_id;
              document.getElementById('checkout-form')?.appendChild(hidden);
            }
          } else {
            resultDiv.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
          }
        }
      })
      .catch(() => {
        couponBtn.disabled = false;
        couponBtn.textContent = 'Apply';
        if (resultDiv) resultDiv.innerHTML = '<div class="alert alert-danger">Could not validate coupon. Please try again.</div>';
      });
    });
  }

  // ── Remove applied coupon ──────────────────────────
  document.querySelectorAll('.btn-remove-coupon').forEach(btn => {
    btn.addEventListener('click', function () {
      const fd = new FormData();
      fd.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
      fetch(appUrl + '/public/checkout.php?action=remove_coupon', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          const resultDiv = document.getElementById('coupon-result');
          if (resultDiv) resultDiv.innerHTML = '';
          if (data.html) document.getElementById('order-summary') && (document.getElementById('order-summary').innerHTML = data.html);
          const couponInput = document.querySelector('input[name="coupon_id"]');
          if (couponInput) couponInput.remove();
          showToast('Coupon removed', 'info');
        }
      });
    });
  });
});
