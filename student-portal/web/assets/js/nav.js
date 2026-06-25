/**
 * Shared top navigation, injected into every authenticated page's
 * `.topbar`. Role-aware: a parent account sees a different link set than
 * a student account, since most of the API (notebook, assignments,
 * placement, gamification) is student-only and parents instead get
 * children/reports/consent. Each page calls `Nav.init('current-page-key')`
 * after confirming auth, passing which nav item to mark active.
 */
const Nav = (() => {
  const STUDENT_ITEMS = [
    { key: 'dashboard', href: 'dashboard.html', label: 'Dashboard' },
    { key: 'schedule', href: 'schedule.html', label: 'Schedule' },
    { key: 'notebook', href: 'notebook.html', label: 'Notebook' },
    { key: 'assignments', href: 'assignments.html', label: 'Assignments' },
    { key: 'progress', href: 'progress.html', label: 'Progress' },
    { key: 'materials', href: 'materials.html', label: 'Library' },
    { key: 'wallet', href: 'wallet.html', label: 'Wallet' },
    { key: 'certificates', href: 'certificates.html', label: 'Certificates' },
    { key: 'support', href: 'support.html', label: 'Support' },
  ];

  const PARENT_ITEMS = [
    { key: 'dashboard', href: 'dashboard.html', label: 'Dashboard' },
    { key: 'parent-children', href: 'parent-dashboard.html', label: 'My Children' },
    { key: 'wallet', href: 'wallet.html', label: 'Wallet' },
    { key: 'support', href: 'support.html', label: 'Support' },
  ];

  async function init(currentKey) {
    const topbar = document.querySelector('.topbar');
    if (!topbar) return;

    let user;
    try {
      user = await Api.get('/auth/me');
    } catch {
      return; // page's own auth guard already handles redirect-to-login.
    }

    const isParent = (user.role_slug || user.role || '').toLowerCase().includes('parent');
    const items = isParent ? PARENT_ITEMS : STUDENT_ITEMS;

    const nav = document.createElement('nav');
    nav.className = 'topnav';
    nav.id = 'topnav';
    nav.innerHTML = items
      .map((item) => `<a href="${item.href}" class="${item.key === currentKey ? 'active' : ''}">${item.label}</a>`)
      .join('');

    const toggle = document.createElement('button');
    toggle.className = 'nav-toggle';
    toggle.setAttribute('aria-label', 'Toggle menu');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.innerHTML = '<span></span><span></span><span></span>';
    toggle.addEventListener('click', () => {
      const isOpen = nav.classList.toggle('open');
      toggle.classList.toggle('open', isOpen);
      toggle.setAttribute('aria-expanded', String(isOpen));
    });

    const brand = topbar.querySelector('.brand');
    if (brand) {
      brand.insertAdjacentElement('afterend', nav);
      nav.insertAdjacentElement('afterend', toggle);
    } else {
      topbar.prepend(toggle);
      topbar.prepend(nav);
    }

    renderNotificationBell(topbar);
    return user;
  }

  async function renderNotificationBell(topbar) {
    const bell = document.createElement('a');
    bell.href = 'notifications.html';
    bell.className = 'nav-bell';
    bell.textContent = '🔔';
    bell.title = 'Notifications';

    const logoutBtn = topbar.querySelector('#logout-btn');
    if (logoutBtn) {
      logoutBtn.insertAdjacentElement('beforebegin', bell);
    } else {
      topbar.appendChild(bell);
    }

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

  return { init };
})();
