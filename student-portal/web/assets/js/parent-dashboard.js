(() => {
  if (!Api.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const alertBox = document.getElementById('alert-box');
  const successBox = document.getElementById('success-box');
  let currentStudentId = null;
  let otpStudentId = null;

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

  async function loadConsentRequests() {
    const el = document.getElementById('consent-section');
    try {
      const pending = await Api.get('/parent/consent-requests');
      if (!pending.length) {
        el.innerHTML = '';
        return;
      }
      el.innerHTML = `
        <h3>Pending consent requests</h3>
        ${pending.map((p) => `
          <div class="list-card list-card-row">
            <div>${escapeHtml(p.first_name)} ${escapeHtml(p.last_name)} <span class="tag tag-warning">${p.consent_status}</span></div>
            <button class="btn-secondary" data-initiate="${p.student_id}">Start verification</button>
          </div>
        `).join('')}
      `;
      el.querySelectorAll('[data-initiate]').forEach((btn) => {
        btn.addEventListener('click', () => initiateConsent(btn.dataset.initiate));
      });
    } catch (err) {
      showError(err.message);
    }
  }

  async function initiateConsent(studentId) {
    try {
      await Api.post(`/parent/consent/${studentId}/initiate`);
      otpStudentId = studentId;
      document.getElementById('otp-modal').classList.remove('hidden');
    } catch (err) {
      showError(err.message);
    }
  }

  document.getElementById('verify-otp-btn').addEventListener('click', async () => {
    try {
      await Api.post(`/parent/consent/${otpStudentId}/grant`, {
        method: 'otp_verified',
        otp_code: document.getElementById('otp-input').value,
      });
      document.getElementById('otp-modal').classList.add('hidden');
      showSuccess('Consent granted.');
      loadConsentRequests();
      loadChildren();
    } catch (err) {
      showError(err.message);
    }
  });
  document.getElementById('close-otp-btn').addEventListener('click', () => {
    document.getElementById('otp-modal').classList.add('hidden');
  });

  async function loadChildren() {
    const el = document.getElementById('children-list');
    try {
      const children = await Api.get('/parent/children');
      if (!children.length) {
        el.innerHTML = '<div class="empty-state">No linked children with granted consent yet.</div>';
        return;
      }
      el.innerHTML = children.map((c) => `
        <div class="list-card list-card-row clickable" data-open="${c.student_id}">
          <div><h4>${escapeHtml(c.first_name)} ${escapeHtml(c.last_name)}</h4><p class="subtitle">${c.relationship}${c.is_primary_guardian ? ' · primary guardian' : ''}</p></div>
        </div>
      `).join('');
      el.querySelectorAll('[data-open]').forEach((card) => {
        card.addEventListener('click', () => openChild(card.dataset.open));
      });
    } catch (err) {
      showError(err.message);
    }
  }

  async function openChild(studentId) {
    currentStudentId = studentId;
    const children = await Api.get('/parent/children');
    const child = children.find((c) => String(c.student_id) === String(studentId));
    document.getElementById('child-name').textContent = child ? `${child.first_name} ${child.last_name}` : '';

    document.querySelector('.page-header').classList.add('hidden');
    document.getElementById('children-list').classList.add('hidden');
    document.getElementById('child-detail').classList.remove('hidden');
    document.querySelectorAll('.tabs button').forEach((b) => b.classList.remove('active'));
    document.querySelector('[data-tab="overview"]').classList.add('active');
    document.querySelectorAll('[id^="tab-"]').forEach((p) => p.classList.add('hidden'));
    document.getElementById('tab-overview').classList.remove('hidden');
    loadOverview();
  }

  async function loadOverview() {
    const el = document.getElementById('tab-overview');
    el.innerHTML = '<div class="skeleton skeleton-block"></div><div class="skeleton skeleton-block"></div>';
    try {
      const [dashboard, risk] = await Promise.all([
        Api.get(`/parent/students/${currentStudentId}/dashboard`),
        Api.get(`/parent/students/${currentStudentId}/risk-summary`),
      ]);

      let html = '';
      if (risk.has_active_flag) {
        html += `<div class="alert alert-error">${escapeHtml(risk.message)}${risk.ptm_booking_available ? ' <a href="ptm-room.html">Book a check-in call →</a>' : ''}</div>`;
      }
      if (!dashboard) {
        html += '<div class="empty-state">No progress data yet.</div>';
      } else {
        html += `
          <div class="stat-grid">
            <div class="stat-tile"><div class="stat-value">${fmtPct(dashboard.attendance_percent)}</div><div class="stat-label">Attendance</div></div>
            <div class="stat-tile"><div class="stat-value">${fmtPct(dashboard.course_completion_percent)}</div><div class="stat-label">Course completion</div></div>
            <div class="stat-tile"><div class="stat-value">${fmtPct(dashboard.assignment_completion_percent)}</div><div class="stat-label">Assignments done</div></div>
            <div class="stat-tile"><div class="stat-value">${dashboard.avg_project_score ?? '—'}</div><div class="stat-label">Avg project score</div></div>
          </div>
        `;
      }
      el.innerHTML = html;
    } catch (err) {
      el.innerHTML = '';
      showError(err.message);
    }
  }

  function fmtPct(v) {
    return v === null || v === undefined ? '—' : `${v}%`;
  }

  async function loadAttendance() {
    const el = document.getElementById('tab-attendance');
    el.innerHTML = '<div class="field" style="max-width:300px;"><label>Course ID</label><input id="attendance-course-id" type="number" /></div><button id="load-attendance-btn" class="btn-secondary">Load</button><div id="attendance-rows"></div>';
    document.getElementById('load-attendance-btn').addEventListener('click', async () => {
      const courseId = document.getElementById('attendance-course-id').value;
      try {
        const rows = await Api.get(`/parent/students/${currentStudentId}/attendance?course_id=${courseId}`);
        document.getElementById('attendance-rows').innerHTML = rows.length
          ? rows.map((r) => `<div class="list-card list-card-row"><div>${escapeHtml(r.title)}</div><span class="tag ${r.status === 'present' ? 'tag-success' : 'tag-danger'}">${r.status}</span></div>`).join('')
          : '<div class="empty-state">No attendance records.</div>';
      } catch (err) {
        showError(err.message);
      }
    });
  }

  async function loadRecordings() {
    const el = document.getElementById('tab-recordings');
    el.innerHTML = '<div class="skeleton skeleton-block"></div><div class="skeleton skeleton-block"></div>';
    try {
      const rows = await Api.get(`/parent/students/${currentStudentId}/recordings`);
      el.innerHTML = rows.length
        ? rows.map((r) => `<div class="list-card">${escapeHtml(r.title)} <span class="tag">${r.processing_status}</span></div>`).join('')
        : '<div class="empty-state">No recordings visible (or not enabled for this guardian).</div>';
    } catch (err) {
      el.innerHTML = '';
      showError(err.message);
    }
  }

  async function loadWallet() {
    const el = document.getElementById('tab-wallet');
    el.innerHTML = '<div class="skeleton skeleton-block"></div><div class="skeleton skeleton-block"></div>';
    try {
      const wallet = await Api.get(`/parent/students/${currentStudentId}/wallet`);
      el.innerHTML = `
        <div class="stat-grid">
          <div class="stat-tile"><div class="stat-value">${wallet.credits_balance}</div><div class="stat-label">Balance</div></div>
          <div class="stat-tile"><div class="stat-value">${wallet.credits_purchased}</div><div class="stat-label">Purchased</div></div>
          <div class="stat-tile"><div class="stat-value" style="font-size:1rem;">${wallet.status}</div><div class="stat-label">Status</div></div>
        </div>
      `;
    } catch (err) {
      el.innerHTML = '';
      showError(err.message);
    }
  }

  async function loadReports() {
    const el = document.getElementById('tab-reports');
    el.innerHTML = '<div class="skeleton skeleton-block"></div><div class="skeleton skeleton-block"></div>';
    try {
      const reports = await Api.get(`/parent/students/${currentStudentId}/reports`);
      if (!reports.length) {
        el.innerHTML = '<div class="empty-state">No monthly reports yet.</div>';
        return;
      }
      el.innerHTML = reports.map((r) => `
        <div class="list-card list-card-row">
          <div>${r.period_month}${r.is_partial_period ? ' <span class="tag tag-muted">partial</span>' : ''}${!r.viewed_by_parent_at ? ' <span class="tag tag-warning">new</span>' : ''}</div>
          <button class="btn-secondary" data-view-report="${r.id}">View</button>
        </div>
      `).join('');
      el.querySelectorAll('[data-view-report]').forEach((btn) => {
        btn.addEventListener('click', () => viewReport(btn.dataset.viewReport));
      });
    } catch (err) {
      el.innerHTML = '';
      showError(err.message);
    }
  }

  async function viewReport(id) {
    try {
      const report = await Api.get(`/reports/${id}`);
      await Api.post(`/reports/${id}/viewed`).catch(() => {});
      if (report.pdf_url) {
        window.open(report.pdf_url, '_blank');
      } else {
        alert(report.summary_text || 'No summary available.');
      }
      loadReports();
    } catch (err) {
      showError(err.message);
    }
  }

  const tabLoaders = { overview: loadOverview, attendance: loadAttendance, recordings: loadRecordings, wallet: loadWallet, reports: loadReports };

  document.querySelectorAll('.tabs button').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tabs button').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      document.querySelectorAll('[id^="tab-"]').forEach((p) => p.classList.add('hidden'));
      document.getElementById(`tab-${btn.dataset.tab}`).classList.remove('hidden');
      tabLoaders[btn.dataset.tab]();
    });
  });

  document.getElementById('back-btn').addEventListener('click', () => {
    document.getElementById('child-detail').classList.add('hidden');
    document.getElementById('children-list').classList.remove('hidden');
    document.querySelector('.page-header').classList.remove('hidden');
  });

  document.getElementById('logout-btn').addEventListener('click', async () => {
    try { await Api.post('/auth/logout'); } catch {}
    Api.clearToken();
    window.location.href = 'login.html';
  });

  Nav.init('parent-children');
  loadConsentRequests();
  loadChildren();
})();
