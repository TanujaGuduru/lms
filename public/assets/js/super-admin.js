/**
 * CodeGurukul LMS — Super Admin JavaScript Engine
 * v1.0.0
 */

'use strict';

// ══════════════════════════════════════════════════════════════
// 1.  GLOBAL APP OBJECT
// ══════════════════════════════════════════════════════════════
window.App = {
  sidebar:  { collapsed: localStorage.getItem('cg_sidebar_collapsed') === '1' },
  theme:    localStorage.getItem('cg_theme') || 'light',
  toasts:   [],
};

// ══════════════════════════════════════════════════════════════
// 2.  TOAST NOTIFICATION SYSTEM
// ══════════════════════════════════════════════════════════════
window.CGToast = {
  _container: null,

  _ensure() {
    if (!this._container) {
      this._container = document.getElementById('flashContainer');
      if (!this._container) {
        this._container = document.createElement('div');
        this._container.id = 'flashContainer';
        Object.assign(this._container.style, {
          position: 'fixed', top: '74px', right: '20px',
          zIndex: '1500', width: '360px', pointerEvents: 'none',
        });
        document.body.appendChild(this._container);
      }
    }
    return this._container;
  },

  _show(type, message, duration = 4000) {
    const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
    const c     = this._ensure();
    const el    = document.createElement('div');
    el.className = `alert alert-${type === 'error' ? 'danger' : type} animate-slide-up`;
    el.style.pointerEvents = 'all';
    el.style.marginBottom  = '8px';
    el.innerHTML = `
      <span class="alert-icon"><i class="fas ${icons[type] || icons.info}"></i></span>
      <div class="alert-content">${message}</div>
      <button class="alert-close" onclick="this.closest('.alert').remove()">✕</button>`;
    c.appendChild(el);

    setTimeout(() => {
      el.style.transition = 'opacity .4s, transform .4s';
      el.style.opacity    = '0';
      el.style.transform  = 'translateX(20px)';
      setTimeout(() => el.remove(), 400);
    }, duration);
  },

  success(msg, d)  { this._show('success', msg, d); },
  error(msg, d)    { this._show('error',   msg, d); },
  warning(msg, d)  { this._show('warning', msg, d); },
  info(msg, d)     { this._show('info',    msg, d); },
};

// ══════════════════════════════════════════════════════════════
// 3.  AJAX HELPER
// ══════════════════════════════════════════════════════════════
window.cgFetch = async function(url, options = {}) {
  const defaults = {
    headers: {
      'X-CSRF-Token':   (window.CG && CG.csrf) || '',
      'X-Requested-With': 'XMLHttpRequest',
      'Content-Type':   'application/json',
    },
  };
  const merged = { ...defaults, ...options };
  if (options.headers) merged.headers = { ...defaults.headers, ...options.headers };

  try {
    const res  = await fetch(url, merged);
    const data = await res.json();
    if (!res.ok && !data.success) {
      CGToast.error(data.message || `Request failed (${res.status})`);
    }
    return data;
  } catch (err) {
    CGToast.error('Network error. Please check your connection.');
    throw err;
  }
};

// ══════════════════════════════════════════════════════════════
// 4.  SIDEBAR
// ══════════════════════════════════════════════════════════════
function initSidebar() {
  const sidebar    = document.getElementById('appSidebar');
  const mainContent = document.getElementById('mainContent');
  const topbar     = document.getElementById('appTopbar');
  const toggle     = document.getElementById('sidebarToggle');

  if (!sidebar || !toggle) return;

  function applyCollapsed(collapsed) {
    sidebar.classList.toggle('collapsed', collapsed);
    mainContent?.classList.toggle('sidebar-collapsed', collapsed);
    topbar?.classList.toggle('sidebar-collapsed', collapsed);
    localStorage.setItem('cg_sidebar_collapsed', collapsed ? '1' : '0');
    App.sidebar.collapsed = collapsed;
  }

  // Restore state on load
  if (App.sidebar.collapsed) applyCollapsed(true);

  toggle.addEventListener('click', () => {
    // Mobile: show/hide
    if (window.innerWidth <= 992) {
      sidebar.classList.toggle('mobile-open');
      const overlay = document.getElementById('sidebarOverlay');
      if (overlay) overlay.classList.toggle('d-none');
    } else {
      applyCollapsed(!App.sidebar.collapsed);
    }
  });
}

window.closeMobileSidebar = function() {
  document.getElementById('appSidebar')?.classList.remove('mobile-open');
  const overlay = document.getElementById('sidebarOverlay');
  if (overlay) overlay.classList.add('d-none');
};

// ══════════════════════════════════════════════════════════════
// 5.  DROPDOWNS (topbar)
// ══════════════════════════════════════════════════════════════
function initDropdowns() {
  const pairs = [
    ['notifToggle',    'notifDropdown'],
    ['quickAddBtn',    'quickAddDropdown'],
    ['userMenuToggle', 'userMenuDropdown'],
  ];

  pairs.forEach(([btnId, menuId]) => {
    const btn  = document.getElementById(btnId);
    const menu = document.getElementById(menuId);
    if (!btn || !menu) return;

    btn.addEventListener('click', e => {
      e.stopPropagation();
      const isOpen = menu.style.display === 'block';
      // Close all others
      pairs.forEach(([, mid]) => {
        const m = document.getElementById(mid);
        if (m) m.style.display = 'none';
      });
      menu.style.display = isOpen ? 'none' : 'block';
      if (!isOpen) menu.classList.add('animate-fade-in');
    });
  });

  document.addEventListener('click', () => {
    pairs.forEach(([, mid]) => {
      const m = document.getElementById(mid);
      if (m) m.style.display = 'none';
    });
  });
}

// ══════════════════════════════════════════════════════════════
// 6.  NOTIFICATIONS
// ══════════════════════════════════════════════════════════════
function initNotifications() {
  loadNotifications();

  document.getElementById('markAllReadBtn')?.addEventListener('click', async () => {
    const data = await cgFetch('/api/v1/notifications/read-all', { method: 'POST' });
    if (data.success) {
      document.querySelectorAll('.notification-item.unread').forEach(el => el.classList.remove('unread'));
      document.getElementById('notifDot').style.display = 'none';
      CGToast.success('All notifications marked as read.');
    }
  });
}

async function loadNotifications() {
  try {
    const data = await cgFetch('/api/v1/notifications?limit=10');
    if (!data.success || !data.data?.length) return;

    const list = document.getElementById('notifList');
    const dot  = document.getElementById('notifDot');
    if (!list) return;

    const unread = data.data.filter(n => !n.is_read).length;
    if (unread > 0) dot.style.display = 'block';

    list.innerHTML = data.data.map(n => `
      <div class="notification-item ${!n.is_read ? 'unread' : ''}" onclick="markNotifRead(${n.id}, this)">
        <div class="notification-icon" style="background:${n.color || '#6366f1'}18;color:${n.color || '#6366f1'}">
          <i class="${n.icon || 'fas fa-bell'}"></i>
        </div>
        <div>
          <div class="notification-text">${escHtml(n.title)}</div>
          <div class="notification-time">${timeAgo(n.created_at)}</div>
        </div>
      </div>`).join('');
  } catch {}
}

window.markNotifRead = async function(id, el) {
  el.classList.remove('unread');
  await cgFetch(`/api/v1/notifications/${id}/read`, { method: 'POST' });
};

// ══════════════════════════════════════════════════════════════
// 7.  GLOBAL SEARCH
// ══════════════════════════════════════════════════════════════
function initGlobalSearch() {
  const input   = document.getElementById('globalSearch');
  const results = document.getElementById('searchResults');
  if (!input || !results) return;

  let searchTimer;

  input.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 2) { results.style.display = 'none'; return; }

    searchTimer = setTimeout(async () => {
      results.style.display = 'block';
      results.innerHTML = `<div style="padding:12px 16px;font-size:13px;color:#94a3b8"><span class="loading-spinner"></span> Searching…</div>`;

      try {
        const data = await cgFetch(`/api/v1/search?q=${encodeURIComponent(q)}`);
        if (data.success && data.data?.length) {
          results.innerHTML = data.data.map(item => `
            <a href="${item.url}" style="display:flex;align-items:center;gap:10px;padding:10px 16px;text-decoration:none;color:#0f172a;border-bottom:1px solid #f1f5f9;transition:background .15s"
               onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
              <div style="width:32px;height:32px;background:${item.color||'#6366f1'}18;border-radius:8px;display:flex;align-items:center;justify-content:center;color:${item.color||'#6366f1'};font-size:13px;flex-shrink:0">
                <i class="${item.icon || 'fas fa-search'}"></i>
              </div>
              <div>
                <div style="font-size:13.5px;font-weight:600">${escHtml(item.title)}</div>
                <div style="font-size:11.5px;color:#94a3b8">${escHtml(item.type)} · ${escHtml(item.subtitle || '')}</div>
              </div>
            </a>`).join('');
        } else {
          results.innerHTML = `<div style="padding:16px;text-align:center;font-size:13px;color:#94a3b8"><i class="fas fa-search" style="opacity:.4;margin-right:6px"></i>No results for "${escHtml(q)}"</div>`;
        }
      } catch {
        results.style.display = 'none';
      }
    }, 300);
  });

  document.addEventListener('click', e => {
    if (!input.contains(e.target) && !results.contains(e.target)) {
      results.style.display = 'none';
    }
  });

  input.addEventListener('keydown', e => {
    if (e.key === 'Escape') { results.style.display = 'none'; input.blur(); }
    if (e.key === 'Enter') {
      e.preventDefault();
      const q = input.value.trim();
      if (q) window.location.href = `/super-admin/users?search=${encodeURIComponent(q)}`;
    }
  });
}

// ══════════════════════════════════════════════════════════════
// 8.  CONFIRM DELETE HELPER
// ══════════════════════════════════════════════════════════════
window.confirmDelete = function(url, itemName, onSuccess) {
  Swal.fire({
    title: 'Delete ' + (itemName ? `"${itemName}"` : 'this item') + '?',
    html: `<p style="color:#64748b;font-size:14px">This action cannot be undone.</p>`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, Delete',
    confirmButtonColor: '#ef4444',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    focusCancel: true,
  }).then(async r => {
    if (!r.isConfirmed) return;
    const data = await cgFetch(url, { method: 'POST' });
    if (data.success) {
      CGToast.success(data.message || 'Deleted successfully.');
      if (typeof onSuccess === 'function') onSuccess(data);
    }
  });
};

// ══════════════════════════════════════════════════════════════
// 9.  FORM SUBMIT LOADER
// ══════════════════════════════════════════════════════════════
function initFormLoaders() {
  document.querySelectorAll('form[data-loading]').forEach(form => {
    const label = form.dataset.loading || 'Saving…';
    form.addEventListener('submit', function() {
      const btn = this.querySelector('[type="submit"]');
      if (btn) {
        btn.disabled  = true;
        btn._origHtml = btn.innerHTML;
        btn.innerHTML = `<span class="loading-spinner"></span> ${label}`;
      }
    });
  });
}

// ══════════════════════════════════════════════════════════════
// 10. CHART DEFAULTS
// ══════════════════════════════════════════════════════════════
function setChartDefaults() {
  if (typeof Chart === 'undefined') return;

  Chart.defaults.font.family = "'Inter', sans-serif";
  Chart.defaults.font.size   = 12;
  Chart.defaults.color       = '#64748b';
  Chart.defaults.plugins.legend.labels.boxWidth      = 10;
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.tooltip.backgroundColor     = '#0f172a';
  Chart.defaults.plugins.tooltip.titleColor          = '#f8fafc';
  Chart.defaults.plugins.tooltip.bodyColor           = '#94a3b8';
  Chart.defaults.plugins.tooltip.padding             = 10;
  Chart.defaults.plugins.tooltip.cornerRadius        = 8;
  Chart.defaults.plugins.tooltip.boxPadding          = 4;
}

// ══════════════════════════════════════════════════════════════
// 11. SELECT2 & FLATPICKR INIT
// ══════════════════════════════════════════════════════════════
function initFormControls() {
  if (typeof $ !== 'undefined' && $.fn.select2) {
    $('.select2').select2({ theme: 'default', width: '100%' });
  }
  if (typeof flatpickr !== 'undefined') {
    flatpickr('.flatpickr-date', { dateFormat: 'Y-m-d' });
    flatpickr('.flatpickr-datetime', { dateFormat: 'Y-m-d H:i', enableTime: true, time_24hr: true });
    flatpickr('.flatpickr-time', { noCalendar: true, enableTime: true, dateFormat: 'H:i', time_24hr: true });
  }
}

// ══════════════════════════════════════════════════════════════
// 12. AUTO-DISMISS FLASH MESSAGES
// ══════════════════════════════════════════════════════════════
function initFlashDismiss() {
  setTimeout(() => {
    document.querySelectorAll('#flashContainer .alert').forEach(el => {
      el.style.transition = 'opacity .4s, transform .4s';
      el.style.opacity    = '0';
      el.style.transform  = 'translateX(20px)';
      setTimeout(() => el.remove(), 400);
    });
  }, 5000);
}

// ══════════════════════════════════════════════════════════════
// 13. DATA TABLE SORT
// ══════════════════════════════════════════════════════════════
function initTableSort() {
  document.querySelectorAll('.data-table th[data-sort]').forEach(th => {
    th.addEventListener('click', function() {
      const table = this.closest('table');
      const col   = Array.from(this.parentElement.children).indexOf(this);
      const rows  = Array.from(table.querySelectorAll('tbody tr'));
      const asc   = this.dataset.direction !== 'asc';
      this.dataset.direction = asc ? 'asc' : 'desc';

      table.querySelectorAll('th').forEach(h => h.classList.remove('sorted-asc', 'sorted-desc'));
      this.classList.add(asc ? 'sorted-asc' : 'sorted-desc');

      rows.sort((a, b) => {
        const av = (a.cells[col]?.textContent || '').trim();
        const bv = (b.cells[col]?.textContent || '').trim();
        return asc ? av.localeCompare(bv, undefined, { numeric: true }) : bv.localeCompare(av, undefined, { numeric: true });
      });
      rows.forEach(r => table.querySelector('tbody').appendChild(r));
    });
  });
}

// ══════════════════════════════════════════════════════════════
// 14. KEYBOARD SHORTCUTS
// ══════════════════════════════════════════════════════════════
function initKeyboardShortcuts() {
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === '/') {
      e.preventDefault();
      document.getElementById('globalSearch')?.focus();
    }
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'N') {
      e.preventDefault();
      window.location.href = '/super-admin/users/create';
    }
    if (e.key === 'Escape') {
      document.getElementById('globalSearch')?.blur();
      closeMobileSidebar();
      document.querySelectorAll('.modal-backdrop-custom').forEach(m => m.style.display = 'none');
    }
  });
}

// ══════════════════════════════════════════════════════════════
// 15. COPY TO CLIPBOARD
// ══════════════════════════════════════════════════════════════
window.copyToClipboard = function(text, label) {
  navigator.clipboard?.writeText(text)
    .then(() => CGToast.success(`${label || 'Text'} copied!`))
    .catch(() => CGToast.error('Copy failed.'));
};

// ══════════════════════════════════════════════════════════════
// 16. INLINE EDIT (generic)
// ══════════════════════════════════════════════════════════════
window.inlineEdit = async function(url, field, value, onSuccess) {
  const data = await cgFetch(url, {
    method: 'POST',
    body: JSON.stringify({ [field]: value }),
  });
  if (data.success) {
    CGToast.success(data.message || 'Updated.');
    if (typeof onSuccess === 'function') onSuccess(data);
  }
};

// ══════════════════════════════════════════════════════════════
// 17. UTILITY FUNCTIONS
// ══════════════════════════════════════════════════════════════
function escHtml(str) {
  return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function timeAgo(dateStr) {
  if (!dateStr) return '';
  const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
  if (diff < 60)    return 'just now';
  if (diff < 3600)  return Math.floor(diff / 60) + 'm ago';
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
  return Math.floor(diff / 86400) + 'd ago';
}

function formatMoney(amount, currency = '₹') {
  return currency + new Intl.NumberFormat('en-IN').format(Number(amount) || 0);
}

function formatNumber(n) {
  const num = Number(n) || 0;
  if (num >= 1_000_000) return (num / 1_000_000).toFixed(1) + 'M';
  if (num >= 1_000)     return (num / 1_000).toFixed(1) + 'K';
  return num.toString();
}

window.escHtml     = escHtml;
window.timeAgo     = timeAgo;
window.formatMoney = formatMoney;
window.formatNumber = formatNumber;

// ══════════════════════════════════════════════════════════════
// 18. REAL-TIME NOTIFICATION POLLING
// ══════════════════════════════════════════════════════════════
function startNotifPolling() {
  // Poll every 60 seconds for new notifications
  setInterval(async () => {
    try {
      const data = await cgFetch('/api/v1/notifications?unread_only=1&limit=1');
      if (data.success && data.data?.length) {
        const dot = document.getElementById('notifDot');
        if (dot) dot.style.display = 'block';
      }
    } catch {}
  }, 60_000);
}

// ══════════════════════════════════════════════════════════════
// 19. MODAL HELPER
// ══════════════════════════════════════════════════════════════
window.CGModal = {
  open(id)  { const m = document.getElementById(id); if (m) { m.style.display = 'flex'; document.body.style.overflow = 'hidden'; } },
  close(id) { const m = document.getElementById(id); if (m) { m.style.display = 'none'; document.body.style.overflow = ''; } },
};

// Close modal on backdrop click
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-backdrop-custom')) {
    e.target.style.display = 'none';
    document.body.style.overflow = '';
  }
});

// ══════════════════════════════════════════════════════════════
// 20. PROGRESS BAR ANIMATE
// ══════════════════════════════════════════════════════════════
function animateProgressBars() {
  document.querySelectorAll('.progress-bar-custom .bar[data-pct]').forEach(bar => {
    const pct = parseFloat(bar.dataset.pct) || 0;
    setTimeout(() => { bar.style.width = Math.min(100, pct) + '%'; }, 200);
  });
}

// ══════════════════════════════════════════════════════════════
// 21. ACTIVE NAV HIGHLIGHT
// ══════════════════════════════════════════════════════════════
function highlightActiveNav() {
  const path = window.location.pathname;
  document.querySelectorAll('.nav-link').forEach(link => {
    const href = link.getAttribute('href');
    if (!href || href === '#' || href.startsWith('#')) return;
    if (path === href || (href.length > 1 && path.startsWith(href))) {
      link.classList.add('active');
      // Open parent collapse
      const submenu = link.closest('.nav-submenu');
      if (submenu) {
        submenu.classList.add('show');
        const trigger = document.querySelector(`[data-bs-target="#${submenu.id}"]`);
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
      }
    }
  });
}

// ══════════════════════════════════════════════════════════════
// 22. DATATABLES AJAX WRAPPER
// ══════════════════════════════════════════════════════════════
window.cgDataTable = function(tableId, options = {}) {
  if (typeof $ === 'undefined' || !$.fn.DataTable) return null;
  const defaults = {
    responsive: true,
    pageLength: 20,
    language: {
      search:       '',
      searchPlaceholder: 'Search…',
      lengthMenu:   '_MENU_ per page',
      info:         'Showing _START_–_END_ of _TOTAL_',
      paginate:     { previous: '‹', next: '›' },
      zeroRecords:  'No results found.',
      emptyTable:   'No data available.',
    },
    dom: 'rt<"table-pagination d-flex justify-content-between align-items-center px-3 py-3"ip>',
  };
  return $(`#${tableId}`).DataTable({ ...defaults, ...options });
};

// ══════════════════════════════════════════════════════════════
// 23. FILE UPLOAD PREVIEW
// ══════════════════════════════════════════════════════════════
window.previewImage = function(inputEl, previewEl, size = 80) {
  const file = inputEl.files[0];
  if (!file) return;
  if (file.size > 2 * 1024 * 1024) { CGToast.error('File must be under 2 MB.'); return; }

  const reader = new FileReader();
  reader.onload = e => {
    if (typeof previewEl === 'string') previewEl = document.getElementById(previewEl);
    if (previewEl) {
      previewEl.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover">`;
    }
  };
  reader.readAsDataURL(file);
};

// ══════════════════════════════════════════════════════════════
// 24. COUNTDOWN TIMER
// ══════════════════════════════════════════════════════════════
window.startCountdown = function(el, endDateTime, onExpire) {
  function update() {
    const diff = new Date(endDateTime) - Date.now();
    if (diff <= 0) {
      el.textContent = 'Expired';
      if (typeof onExpire === 'function') onExpire();
      return;
    }
    const h = String(Math.floor(diff / 3_600_000)).padStart(2, '0');
    const m = String(Math.floor((diff % 3_600_000) / 60_000)).padStart(2, '0');
    const s = String(Math.floor((diff % 60_000) / 1_000)).padStart(2, '0');
    el.textContent = `${h}:${m}:${s}`;
  }
  update();
  return setInterval(update, 1_000);
};

// ══════════════════════════════════════════════════════════════
// 25. INIT ON DOM READY
// ══════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
  initSidebar();
  initDropdowns();
  initNotifications();
  initGlobalSearch();
  initFormControls();
  initFormLoaders();
  initFlashDismiss();
  initTableSort();
  initKeyboardShortcuts();
  animateProgressBars();
  highlightActiveNav();
  setChartDefaults();
  startNotifPolling();

  // Tooltip init (Bootstrap)
  document.querySelectorAll('[title]').forEach(el => {
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
      new bootstrap.Tooltip(el, { trigger: 'hover', placement: 'top' });
    }
  });
});
