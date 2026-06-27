/**
 * Shared sidebar navigation, built at runtime and injected before every
 * authenticated page's existing `.topbar`/`.container` pair — no page's
 * HTML needs sidebar markup; only this file and style.css define it.
 * Role-aware: a parent account sees a different link set than a student
 * account, since most of the API (notebook, assignments, placement,
 * gamification) is student-only and parents instead get children/reports/
 * consent. Each page calls `Nav.init('current-page-key')` after confirming
 * auth, passing which nav item to mark active.
 */
const Nav = (() => {
  const STUDENT_ITEMS = [
    { key: 'dashboard', href: 'dashboard.html', label: 'Dashboard', icon: '🏠' },
    { key: 'schedule', href: 'schedule.html', label: 'Schedule', icon: '📅' },
    { key: 'notebook', href: 'notebook.html', label: 'Notebook', icon: '📓' },
    { key: 'assignments', href: 'assignments.html', label: 'Assignments', icon: '📝' },
    { key: 'progress', href: 'progress.html', label: 'Progress', icon: '📈' },
    { key: 'materials', href: 'materials.html', label: 'Library', icon: '📚' },
    { key: 'wallet', href: 'wallet.html', label: 'Wallet', icon: '💰' },
    { key: 'certificates', href: 'certificates.html', label: 'Certificates', icon: '🏆' },
    { key: 'support', href: 'support.html', label: 'Support', icon: '🎧' },
  ];

  const PARENT_ITEMS = [
    { key: 'dashboard', href: 'dashboard.html', label: 'Dashboard', icon: '🏠' },
    { key: 'parent-children', href: 'parent-dashboard.html', label: 'My Children', icon: '👨‍👩‍👧' },
    { key: 'wallet', href: 'wallet.html', label: 'Wallet', icon: '💰' },
    { key: 'support', href: 'support.html', label: 'Support', icon: '🎧' },
  ];

  async function init(currentKey) {
    const topbar = document.querySelector('.topbar');
    const container = document.querySelector('.container');
    if (!topbar || !container) return;

    let user;
    try {
      user = await Api.get('/auth/me');
    } catch {
      return; // page's own auth guard already handles redirect-to-login.
    }

    const isParent = (user.role_slug || user.role || '').toLowerCase().includes('parent');
    const items = isParent ? PARENT_ITEMS : STUDENT_ITEMS;

    const sidebar = buildSidebar(items, currentKey, user, topbar);
    topbar.querySelector('.brand')?.remove(); // identity now lives in .sidebar-brand instead

    const backdrop = document.createElement('div');
    backdrop.className = 'sidebar-backdrop';

    const mainContent = document.createElement('div');
    mainContent.className = 'main-content';
    mainContent.appendChild(topbar);
    mainContent.appendChild(container);

    document.body.classList.add('has-sidebar');
    document.body.prepend(mainContent);
    document.body.prepend(backdrop);
    document.body.prepend(sidebar);

    const toggle = buildMobileToggle(sidebar, backdrop);
    topbar.prepend(toggle);

    renderNotificationBell(topbar);
    return user;
  }

  function buildSidebar(items, currentKey, user, topbar) {
    const sidebar = document.createElement('aside');
    sidebar.className = 'sidebar';
    sidebar.id = 'sidebar';

    const brand = document.createElement('div');
    brand.className = 'sidebar-brand';
    brand.innerHTML = '<span>🎓</span><span>CodeGurukul</span>';
    sidebar.appendChild(brand);

    const nav = document.createElement('nav');
    nav.className = 'sidebar-nav';
    nav.innerHTML = items
      .map((item) => `
        <a href="${item.href}" class="${item.key === currentKey ? 'active' : ''}">
          <span class="nav-icon">${item.icon}</span><span>${item.label}</span>
        </a>`)
      .join('');
    sidebar.appendChild(nav);

    const footer = document.createElement('div');
    footer.className = 'sidebar-footer';
    const name = [user.first_name, user.last_name].filter(Boolean).join(' ') || user.email || 'Account';
    const initials = (((user.first_name || '')[0] || '') + ((user.last_name || '')[0] || '')).toUpperCase() || (name[0] || '?').toUpperCase();
    footer.innerHTML = `
      <div class="sidebar-footer-top">
        <div class="avatar-circle">${initials}</div>
        <div class="user-info"><div class="user-name">${escapeHtml(name)}</div></div>
      </div>
    `;

    const logoutBtn = topbar.querySelector('#logout-btn');
    if (logoutBtn) {
      footer.appendChild(logoutBtn); // moves the existing element — preserves its event listener
    }
    sidebar.appendChild(footer);

    return sidebar;
  }

  function buildMobileToggle(sidebar, backdrop) {
    const toggle = document.createElement('button');
    toggle.className = 'sidebar-toggle';
    toggle.setAttribute('aria-label', 'Toggle menu');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.innerHTML = '<span></span><span></span><span></span>';

    const setOpen = (isOpen) => {
      sidebar.classList.toggle('open', isOpen);
      backdrop.classList.toggle('open', isOpen);
      toggle.classList.toggle('open', isOpen);
      toggle.setAttribute('aria-expanded', String(isOpen));
    };

    toggle.addEventListener('click', () => setOpen(!sidebar.classList.contains('open')));
    backdrop.addEventListener('click', () => setOpen(false));
    sidebar.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => setOpen(false)));

    return toggle;
  }

  async function renderNotificationBell(topbar) {
    const bell = document.createElement('a');
    bell.href = 'notifications.html';
    bell.className = 'nav-bell';
    bell.textContent = '🔔';
    bell.title = 'Notifications';
    topbar.appendChild(bell);

    try {
      const notifications = await Api.get('/notifications');
      const unread = notifications.filter((n) => !n.is_read).length;
      if (unread > 0) {
        const badge = document.createElement('span');
        badge.className = 'nav-bell-badge';
        badge.textContent = unread > 9 ? '9+' : String(unread);
        bell.appendChild(badge);
      }
    } catch {
      // notification fetch failing shouldn't block the rest of the page.
    }
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
  }

  return { init };
})();
