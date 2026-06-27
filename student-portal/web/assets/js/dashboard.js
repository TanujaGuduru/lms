(() => {
  if (!Api.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const alertBox = document.getElementById('alert-box');

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
  }

  function buildProgressRing(percent) {
    const r = 41;
    const c = 2 * Math.PI * r;
    const pct = percent ?? 0;
    const offset = c * (1 - pct / 100);
    return `
      <div class="progress-ring">
        <svg width="90" height="90" viewBox="0 0 90 90">
          <circle class="ring-bg" cx="45" cy="45" r="${r}"></circle>
          <circle class="ring-value" cx="45" cy="45" r="${r}" stroke-dasharray="${c}" stroke-dashoffset="${offset}"></circle>
        </svg>
        <div class="ring-label">${percent === null || percent === undefined ? '—' : pct + '%'}</div>
      </div>
    `;
  }

  /** Real trend from /progress/history (earliest vs latest in the window) — never fabricated. */
  function buildTrendPill(history, key) {
    const vals = (history || []).map((h) => h[key]).filter((v) => v !== null && v !== undefined);
    if (vals.length < 2) return '';
    const delta = vals[vals.length - 1] - vals[0];
    if (delta > 0) return `<span class="trend-pill trend-up">▲ +${delta}%</span>`;
    if (delta < 0) return `<span class="trend-pill trend-down">▼ ${delta}%</span>`;
    return '<span class="trend-pill trend-flat">No change</span>';
  }

  async function loadDashboard() {
    let user;
    try {
      user = await Api.get('/auth/me');
    } catch (err) {
      if (err.status === 401) {
        Api.clearToken();
        window.location.href = 'login.html';
        return;
      }
      alertBox.textContent = err.message;
      alertBox.classList.remove('hidden');
      return;
    }

    const hero = document.getElementById('hero-section');
    const statsSection = document.getElementById('stats-section');

    if (!user.current_enrollment) {
      // Parents, and students with no active enrollment yet — the
      // progress/gamification endpoints below are enrollment-scoped and
      // have nothing real to show, so this stays a plain greeting rather
      // than rendering empty/fake widgets.
      hero.innerHTML = `
        <div class="hero-banner">
          <div>
            <h2>Welcome back, ${escapeHtml(user.first_name)}</h2>
            <p>${escapeHtml(user.role_slug || user.role || '')}</p>
          </div>
        </div>
      `;
      statsSection.classList.add('hidden');
      return;
    }

    const enrollmentId = user.current_enrollment.enrollment_id;
    const [profileResult, snapshotResult, historyResult] = await Promise.allSettled([
      Api.get('/gamification/profile'),
      Api.get(`/progress/snapshot?enrollment_id=${enrollmentId}`),
      Api.get(`/progress/history?enrollment_id=${enrollmentId}&days=14`),
    ]);

    const profile = profileResult.status === 'fulfilled' ? profileResult.value : null;
    const snapshot = snapshotResult.status === 'fulfilled' ? snapshotResult.value : null;
    const history = historyResult.status === 'fulfilled' ? historyResult.value : [];

    const completionPct = snapshot ? snapshot.course_completion_percent : null;
    const trendPill = buildTrendPill(history, 'course_completion_percent');

    hero.innerHTML = `
      <div class="hero-banner">
        <div>
          <h2>Welcome back, ${escapeHtml(user.first_name)}</h2>
          <p>${escapeHtml(user.current_enrollment.course_title || '')}${trendPill}</p>
        </div>
        ${buildProgressRing(completionPct)}
      </div>
    `;

    const tiles = [];
    if (profile) {
      tiles.push(`<div class="stat-tile"><div class="stat-value">${profile.total_xp}</div><div class="stat-label">Total XP</div></div>`);
      tiles.push(`<div class="stat-tile"><div class="stat-value">${profile.current_level}</div><div class="stat-label">Level</div></div>`);
      tiles.push(`<div class="stat-tile"><div class="stat-value">${profile.current_streak_days}🔥</div><div class="stat-label">Day streak</div></div>`);
    }
    if (snapshot && snapshot.attendance_percent !== null) {
      tiles.push(`<div class="stat-tile"><div class="stat-value">${snapshot.attendance_percent}%</div><div class="stat-label">Attendance</div></div>`);
    }

    if (tiles.length) {
      statsSection.innerHTML = tiles.join('');
    } else {
      statsSection.classList.add('hidden');
    }
  }

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
        row.className = 'list-card';
        const when = new Date(c.start_local).toLocaleString();
        row.innerHTML = `
          <div class="list-card-row">
            <div>
              <h4>${escapeHtml(c.title)}</h4>
              <span class="subtitle">${when} · ${c.duration_minutes} min · ${escapeHtml(c.teacher_name)}</span>
            </div>
            <a href="classroom.html?class_id=${c.live_class_id}"><button class="btn-primary" style="width:auto;padding:0.5rem 1rem;">Join</button></a>
          </div>
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

  Nav.init('dashboard');
  loadDashboard();
  loadUpcomingClasses();
})();
