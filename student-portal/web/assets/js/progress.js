(() => {
  if (!Api.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const alertBox = document.getElementById('alert-box');
  let enrollmentId = null;

  function showError(message) {
    alertBox.textContent = message;
    alertBox.classList.remove('hidden');
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
  }

  async function loadOverview() {
    const el = document.getElementById('tab-overview');
    el.innerHTML = '<div class="skeleton skeleton-block"></div><div class="skeleton skeleton-block"></div>';
    try {
      const [profile, badges] = await Promise.all([
        Api.get('/gamification/profile'),
        Api.get('/gamification/badges'),
      ]);

      el.innerHTML = `
        <div class="stat-grid">
          <div class="stat-tile"><div class="stat-value">${profile.total_xp}</div><div class="stat-label">Total XP</div></div>
          <div class="stat-tile"><div class="stat-value">${profile.current_level}</div><div class="stat-label">Level</div></div>
          <div class="stat-tile"><div class="stat-value">${profile.current_streak_days}🔥</div><div class="stat-label">Current streak</div></div>
          <div class="stat-tile"><div class="stat-value">${profile.longest_streak_days}</div><div class="stat-label">Longest streak</div></div>
        </div>
        <h3>Badges</h3>
        <div class="stat-grid" id="badges-grid"></div>
      `;

      const grid = document.getElementById('badges-grid');
      for (const b of badges) {
        const tile = document.createElement('div');
        tile.className = 'stat-tile';
        tile.style.opacity = b.locked ? '0.4' : '1';
        tile.innerHTML = `<div style="font-size:1.8rem;">${b.locked ? '🔒' : '🏆'}</div><div class="stat-label">${escapeHtml(b.name)}</div>`;
        tile.title = b.description || '';
        grid.appendChild(tile);
      }
    } catch (err) {
      showError(err.message);
    }
  }

  async function loadCourseProgress() {
    const el = document.getElementById('tab-course');
    el.innerHTML = '<div class="skeleton skeleton-block"></div><div class="skeleton skeleton-block"></div>';
    try {
      const me = await Api.get('/auth/me');
      if (!me.current_enrollment) {
        el.innerHTML = '<div class="empty-state">No active enrollment found.</div>';
        return;
      }
      enrollmentId = me.current_enrollment.enrollment_id;
      const snapshot = await Api.get(`/progress/snapshot?enrollment_id=${enrollmentId}`);

      if (!snapshot) {
        el.innerHTML = '<div class="empty-state">No progress snapshot yet — check back after tonight\'s update.</div>';
        return;
      }

      el.innerHTML = `
        <p class="subtitle">As of ${snapshot.snapshot_date}</p>
        <div class="stat-grid">
          <div class="stat-tile"><div class="stat-value">${fmtPct(snapshot.attendance_percent)}</div><div class="stat-label">Attendance</div></div>
          <div class="stat-tile"><div class="stat-value">${fmtPct(snapshot.course_completion_percent)}</div><div class="stat-label">Course completion</div></div>
          <div class="stat-tile"><div class="stat-value">${fmtPct(snapshot.assignment_completion_percent)}</div><div class="stat-label">Assignments done</div></div>
          <div class="stat-tile"><div class="stat-value">${snapshot.avg_project_score ?? '—'}</div><div class="stat-label">Avg project score</div></div>
          <div class="stat-tile"><div class="stat-value">${snapshot.avg_assessment_score ?? '—'}</div><div class="stat-label">Avg assessment score</div></div>
        </div>
      `;
    } catch (err) {
      showError(err.message);
    }
  }

  function fmtPct(v) {
    return v === null ? '—' : `${v}%`;
  }

  async function loadLeaderboard() {
    const el = document.getElementById('tab-leaderboard');
    el.innerHTML = '<div class="skeleton skeleton-block"></div><div class="skeleton skeleton-block"></div>';
    try {
      const rows = await Api.get('/leaderboard?scope=global');
      const me = await Api.get('/auth/me');
      el.innerHTML = rows.map((r) => `
        <div class="list-card" style="${r.student_id === me.id ? 'border-color: var(--primary);' : ''}">
          <div class="list-card-row">
            <div><strong>#${r.rank}</strong> ${escapeHtml(r.first_name)} ${escapeHtml(r.last_name)}${r.student_id === me.id ? ' (you)' : ''}</div>
            <div class="tag">${r.total_xp} XP · Lvl ${r.current_level}</div>
          </div>
        </div>
      `).join('') || '<div class="empty-state">No leaderboard data yet.</div>';
    } catch (err) {
      showError(err.message);
    }
  }

  async function loadInsights() {
    const el = document.getElementById('tab-insights');
    el.innerHTML = '<div class="skeleton skeleton-block"></div><div class="skeleton skeleton-block"></div>';
    try {
      const grouped = await Api.get('/progress/insights');
      const types = Object.keys(grouped);
      if (!types.length) {
        el.innerHTML = '<div class="empty-state">No AI insights yet.</div>';
        return;
      }
      el.innerHTML = types.map((type) => `
        <h3>${escapeHtml(type.replace(/_/g, ' '))}</h3>
        ${grouped[type].map((i) => `
          <div class="list-card">
            <p style="margin:0;">${escapeHtml(i.summary)}</p>
            <p class="subtitle" style="margin:0.3rem 0 0;">${new Date(i.created_at).toLocaleDateString()}</p>
          </div>
        `).join('')}
      `).join('');
    } catch (err) {
      showError(err.message);
    }
  }

  const loaders = { overview: loadOverview, course: loadCourseProgress, leaderboard: loadLeaderboard, insights: loadInsights };
  const loaded = new Set();

  document.querySelectorAll('.tabs button').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tabs button').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      document.querySelectorAll('[id^="tab-"]').forEach((panel) => panel.classList.add('hidden'));
      const tab = btn.dataset.tab;
      document.getElementById(`tab-${tab}`).classList.remove('hidden');
      if (!loaded.has(tab)) {
        loaded.add(tab);
        loaders[tab]();
      }
    });
  });

  document.getElementById('logout-btn').addEventListener('click', async () => {
    try { await Api.post('/auth/logout'); } catch {}
    Api.clearToken();
    window.location.href = 'login.html';
  });

  Nav.init('progress');
  loaded.add('overview');
  loadOverview();
})();
