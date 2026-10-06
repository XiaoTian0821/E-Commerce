/* ─── NovaMart — Main JavaScript ────────────────────── */

/**
 * Show a toast notification
 */
function showToast(message, type = 'success') {
  const bg = { success: '#10B981', danger: '#EF4444', warning: '#F59E0B', info: '#3B82F6' }[type] || '#10B981';
  const toast = document.createElement('div');
  toast.style.cssText = `
    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
    background: ${bg}; color: #fff; padding: 12px 20px;
    border-radius: 8px; font-size: .9rem; font-weight: 500;
    box-shadow: 0 4px 12px rgba(0,0,0,.2);
    animation: slideIn .3s ease;
    max-width: 320px;
  `;
  toast.textContent = message;
  document.body.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity .3s';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

/**
 * Confirm dialog wrapper
 */
function confirmAction(message) {
  return confirm(message || 'Are you sure?');
}

/**
 * Debounce utility
 */
function debounce(fn, ms = 300) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), ms);
  };
}

// Inject slide-in animation
const style = document.createElement('style');
style.textContent = `
  @keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to   { transform: translateX(0);    opacity: 1; }
  }
`;
document.head.appendChild(style);

/**
 * Rating star display (clickable or read-only)
 */
function renderStars(rating, max = 5, interactive = false) {
  let html = '';
  for (let i = 1; i <= max; i++) {
    html += `<span class="${i <= rating ? '' : 'empty'}">${interactive ? '<i class="fas fa-star star-btn" data-val="' + i + '"></i>' : '<i class="fas fa-star"></i>'}</span>`;
  }
  return html;
}

document.addEventListener('DOMContentLoaded', () => {
  // Auto-hide alerts after 5 seconds
  document.querySelectorAll('.alert:not(.alert-permanent)').forEach(alert => {
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
      bsAlert.close();
    }, 5000);
  });

  // Rating star click handlers
  document.querySelectorAll('.star-btn').forEach(star => {
    star.addEventListener('click', function () {
      const val   = parseInt(this.dataset.val);
      const group = this.closest('.rating-group') || this.closest('.product-rating-editor');
      if (group) {
        group.querySelectorAll('.star-btn').forEach(s => {
          s.classList.toggle('text-warning', parseInt(s.dataset.val) <= val);
          s.classList.toggle('text-muted', parseInt(s.dataset.val) > val);
        });
        const hidden = group.querySelector('input[name*="rating"]');
        if (hidden) hidden.value = val;
      }
    });
  });
});
