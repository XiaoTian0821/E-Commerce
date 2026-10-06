/* ─── NovaMart — Admin JavaScript ───────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  const appUrl = new URL('../', window.location.href).pathname.replace(/\/$/, '');

  // ── Mobile sidebar toggle ──────────────────────────
  const sidebar   = document.getElementById('adminSidebar');
  const toggleBtn = document.getElementById('sidebarToggle');
  if (sidebar && toggleBtn) {
    toggleBtn.addEventListener('click', () => sidebar.classList.toggle('show'));
    document.addEventListener('click', e => {
      if (window.innerWidth < 993 && sidebar.classList.contains('show')
          && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
        sidebar.classList.remove('show');
      }
    });
  }

  // ── Confirm delete actions ─────────────────────────
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function () {
      if (!confirm(this.dataset.confirm)) return false;
    });
  });

  // ── Live search for tables ─────────────────────────
  document.querySelectorAll('.table-search').forEach(input => {
    const table = input.closest('table');
    if (!table) return;
    input.addEventListener('input', debounce(function () {
      const term = this.value.toLowerCase();
      table.querySelectorAll('tbody tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
      });
    }, 250));
  });

  // ── Status change AJAX ─────────────────────────────
  document.querySelectorAll('.btn-status-toggle').forEach(btn => {
    btn.addEventListener('click', function () {
      const row   = this.closest('tr');
      const id    = this.dataset.id;
      const table = this.dataset.table;
      const field = this.dataset.field || 'status';
      const csrf  = document.querySelector('input[name="csrf_token"]')?.value;
      const current = this.dataset.current;
      const next  = current === 'active' ? 'inactive' : 'active';

      const fd = new FormData();
      fd.append('id', id);
      fd.append(field, next);
      fd.append('csrf_token', csrf);

      fetch(appUrl + '/admin/' + table + '.php?action=toggle_status', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          this.dataset.current = data.new_status;
          this.textContent = data.new_status === 'active' ? 'Activate' : 'Suspend';
          const badge = row?.querySelector('.badge-status');
          if (badge) {
            badge.textContent = data.new_status;
            badge.className = 'badge-status badge-' + data.new_status;
          }
          showToast(data.message || 'Updated', 'success');
        } else {
          showToast(data.message || 'Update failed', 'danger');
        }
      })
      .catch(() => showToast('Network error', 'danger'));
    });
  });
});

function debounce(fn, ms = 250) {
  let t;
  return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
}
