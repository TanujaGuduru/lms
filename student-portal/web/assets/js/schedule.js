(() => {
  if (!Api.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const alertBox = document.getElementById('alert-box');
  const successBox = document.getElementById('success-box');
  let viewMonth = new Date();

  function showError(message) {
    alertBox.textContent = message;
    alertBox.classList.remove('hidden');
    successBox.classList.add('hidden');
  }
  function showSuccess(message) {
    successBox.textContent = message;
    successBox.classList.remove('hidden');
    alertBox.classList.add('hidden');
  }
  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
  }

  const EVENT_ICON = { class: '📚', assignment_due: '📝', ptm: '👥' };

  async function loadCalendar() {
    const monthStr = `${viewMonth.getFullYear()}-${String(viewMonth.getMonth() + 1).padStart(2, '0')}`;
    document.getElementById('month-label').textContent = viewMonth.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    const el = document.getElementById('calendar-events');
    el.innerHTML = '<p class="subtitle">Loading…</p>';
    try {
      const events = await Api.get(`/schedule/calendar?month=${monthStr}`);
      if (!events.length) {
        el.innerHTML = '<div class="empty-state">No events this month.</div>';
        return;
      }
      el.innerHTML = events.map((e) => `
        <div class="list-card list-card-row">
          <div>${EVENT_ICON[e.event_type] || '•'} <strong>${escapeHtml(e.title)}</strong></div>
          <span class="subtitle">${new Date(e.datetime).toLocaleString()}</span>
        </div>
      `).join('');
    } catch (err) {
      el.innerHTML = '';
      showError(err.message);
    }
  }

  document.getElementById('prev-month-btn').addEventListener('click', () => {
    viewMonth.setMonth(viewMonth.getMonth() - 1);
    loadCalendar();
  });
  document.getElementById('next-month-btn').addEventListener('click', () => {
    viewMonth.setMonth(viewMonth.getMonth() + 1);
    loadCalendar();
  });

  async function loadRescheduleRequests() {
    const el = document.getElementById('reschedule-list');
    try {
      const rows = await Api.get('/reschedule-requests');
      el.innerHTML = rows.length
        ? rows.map((r) => `
          <div class="list-card list-card-row">
            <div>Class #${r.original_class_id} → ${new Date(r.requested_new_datetime).toLocaleString()}</div>
            <span class="tag ${r.status.includes('approved') ? 'tag-success' : 'tag-warning'}">${r.status}</span>
          </div>
        `).join('')
        : '<div class="empty-state">No reschedule requests yet.</div>';
    } catch (err) {
      showError(err.message);
    }
  }

  document.getElementById('r-submit-btn').addEventListener('click', async () => {
    try {
      const result = await Api.post('/reschedule-requests', {
        original_class_id: document.getElementById('r-class-id').value,
        requested_new_datetime: document.getElementById('r-datetime').value,
        reason: document.getElementById('r-reason').value,
      });
      showSuccess(result.message || 'Request submitted.');
      loadRescheduleRequests();
    } catch (err) {
      showError(err.message);
    }
  });

  async function loadTeacherChangeRequests() {
    const el = document.getElementById('teacher-change-list');
    try {
      const rows = await Api.get('/teacher-change-requests');
      el.innerHTML = rows.length
        ? rows.map((r) => `
          <div class="list-card list-card-row">
            <div>Batch #${r.batch_id} — ${r.reason.replace(/_/g, ' ')}</div>
            <span class="tag tag-warning">${r.status}</span>
          </div>
        `).join('')
        : '<div class="empty-state">No teacher change requests yet.</div>';
    } catch (err) {
      showError(err.message);
    }
  }

  document.getElementById('t-submit-btn').addEventListener('click', async () => {
    try {
      await Api.post('/teacher-change-requests', {
        batch_id: document.getElementById('t-batch-id').value,
        reason: document.getElementById('t-reason').value,
        details: document.getElementById('t-details').value,
      });
      showSuccess('Request submitted.');
      loadTeacherChangeRequests();
    } catch (err) {
      showError(err.message);
    }
  });

  const tabLoaders = { calendar: loadCalendar, reschedule: loadRescheduleRequests, 'teacher-change': loadTeacherChangeRequests };
  document.querySelectorAll('.tabs button').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tabs button').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      document.querySelectorAll('[id^="tab-"]').forEach((p) => p.classList.add('hidden'));
      document.getElementById(`tab-${btn.dataset.tab}`).classList.remove('hidden');
      tabLoaders[btn.dataset.tab]();
    });
  });

  document.getElementById('logout-btn').addEventListener('click', async () => {
    try { await Api.post('/auth/logout'); } catch {}
    Api.clearToken();
    window.location.href = 'login.html';
  });

  Nav.init('schedule');
  loadCalendar();
})();
