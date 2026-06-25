(() => {
  if (!Api.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const alertBox = document.getElementById('alert-box');
  const list = document.getElementById('list');

  function showError(message) {
    alertBox.textContent = message;
    alertBox.classList.remove('hidden');
  }

  async function load() {
    try {
      const notifications = await Api.get('/notifications');
      if (!notifications.length) {
        list.innerHTML = '<div class="empty-state">No notifications yet.</div>';
        return;
      }
      list.innerHTML = '';
      for (const n of notifications) {
        const card = document.createElement('div');
        card.className = 'list-card';
        if (!n.is_read) {
          card.style.borderLeft = '3px solid var(--primary)';
        }
        const when = new Date(n.created_at).toLocaleString();
        card.innerHTML = `
          <div class="list-card-row">
            <div>
              <h4>${escapeHtml(n.title)}</h4>
              <p style="margin:0.25rem 0;">${escapeHtml(n.message)}</p>
              <p class="subtitle" style="margin:0;">${when}</p>
            </div>
            <div style="display:flex; flex-direction:column; gap:0.4rem;">
              ${!n.is_read ? `<button class="btn-secondary" data-mark-read="${n.id}">Mark read</button>` : ''}
              <button class="btn-danger" data-delete="${n.id}">Delete</button>
            </div>
          </div>
        `;
        list.appendChild(card);
      }

      list.querySelectorAll('[data-mark-read]').forEach((btn) => {
        btn.addEventListener('click', () => markRead(btn.dataset.markRead));
      });
      list.querySelectorAll('[data-delete]').forEach((btn) => {
        btn.addEventListener('click', () => remove(btn.dataset.delete));
      });
    } catch (err) {
      showError(err.message);
    }
  }

  async function markRead(id) {
    try {
      await Api.post(`/notifications/${id}/mark-read`);
      load();
    } catch (err) {
      showError(err.message);
    }
  }

  async function remove(id) {
    try {
      await Api.delete(`/notifications/${id}`);
      load();
    } catch (err) {
      showError(err.message);
    }
  }

  document.getElementById('mark-all-btn').addEventListener('click', async () => {
    try {
      await Api.post('/notifications/mark-all-read');
      load();
    } catch (err) {
      showError(err.message);
    }
  });

  document.getElementById('logout-btn').addEventListener('click', async () => {
    try { await Api.post('/auth/logout'); } catch {}
    Api.clearToken();
    window.location.href = 'login.html';
  });

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
  }

  load();
})();
