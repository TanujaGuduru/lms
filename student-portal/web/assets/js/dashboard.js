(() => {
  if (!Api.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const alertBox = document.getElementById('alert-box');

  async function loadMe() {
    try {
      const user = await Api.get('/auth/me');
      document.getElementById('welcome-name').textContent = `Hi, ${user.first_name} ${user.last_name}`;
      document.getElementById('welcome-role').textContent = `Role: ${user.role_slug || user.role}`;
    } catch (err) {
      if (err.status === 401) {
        Api.clearToken();
        window.location.href = 'login.html';
        return;
      }
      alertBox.textContent = err.message;
      alertBox.classList.remove('hidden');
    }
  }

  Nav.init('dashboard');

  async function loadUpcomingClasses() {
    const container = document.getElementById('upcoming-classes');
    try {
      const classes = await Api.get('/schedule/upcoming?limit=5');
      if (!classes.length) {
        container.innerHTML = '<p class="subtitle">No upcoming classes scheduled.</p>';
        return;
      }
      container.innerHTML = '';
      for (const c of classes) {
        const row = document.createElement('div');
        row.className = 'card';
        row.style.marginBottom = '0.75rem';
        row.style.maxWidth = 'none';
        const when = new Date(c.start_local).toLocaleString();
        row.innerHTML = `
          <strong>${c.title}</strong>
          <p class="subtitle" style="margin: 0.25rem 0;">${when} · ${c.duration_minutes} min · ${c.teacher_name}</p>
          <a href="classroom.html?class_id=${c.live_class_id}"><button class="btn-primary" style="width:auto;padding:0.5rem 1rem;">Join</button></a>
        `;
        container.appendChild(row);
      }
    } catch (err) {
      container.innerHTML = `<p class="subtitle">Couldn't load classes: ${err.message}</p>`;
    }
  }

  document.getElementById('logout-btn').addEventListener('click', async () => {
    try {
      await Api.post('/auth/logout');
    } catch {
      // already logged out / token expired — fine, proceed to clear locally
    }
    Api.clearToken();
    window.location.href = 'login.html';
  });

  loadMe();
  loadUpcomingClasses();
})();
