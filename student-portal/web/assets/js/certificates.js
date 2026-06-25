(() => {
  if (!Api.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const alertBox = document.getElementById('alert-box');

  function showError(message) {
    alertBox.textContent = message;
    alertBox.classList.remove('hidden');
  }
  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
  }

  async function loadCertificates() {
    const el = document.getElementById('certificates-list');
    try {
      const certs = await Api.get('/certificates');
      if (!certs.length) {
        el.innerHTML = '<div class="empty-state">No certificates issued yet — complete a course to earn one.</div>';
        return;
      }
      el.innerHTML = certs.map((c) => `
        <div class="list-card list-card-row">
          <div>
            <h4>${escapeHtml(c.course_title)}</h4>
            <p class="subtitle">${c.certificate_number} · issued ${new Date(c.issued_at).toLocaleDateString()}</p>
            ${c.is_revoked ? '<span class="tag tag-danger">Revoked</span>' : ''}
          </div>
          ${!c.is_revoked ? `<button class="btn-secondary" data-download="${c.id}">Download PDF</button>` : ''}
        </div>
      `).join('');
      el.querySelectorAll('[data-download]').forEach((btn) => {
        btn.addEventListener('click', () => downloadCertificate(btn.dataset.download));
      });
    } catch (err) {
      showError(err.message);
    }
  }

  async function downloadCertificate(id) {
    try {
      const result = await Api.get(`/certificates/${id}/download`);
      window.open(result.url, '_blank');
    } catch (err) {
      showError(err.message);
    }
  }

  async function loadRecommendations() {
    const el = document.getElementById('recommendations-list');
    try {
      const recs = await Api.get('/recommendations');
      if (!recs.length) {
        el.innerHTML = '<div class="empty-state">No recommendations yet — these appear after finishing a course.</div>';
        return;
      }
      el.innerHTML = recs.map((r) => `
        <div class="list-card">
          <div class="list-card-row">
            <h4>${escapeHtml(r.title)}</h4>
            <span class="tag tag-success">${r.confidence_score}% match</span>
          </div>
          ${r.reason_summary ? `<p class="subtitle">${escapeHtml(r.reason_summary)}</p>` : ''}
        </div>
      `).join('');
    } catch (err) {
      showError(err.message);
    }
  }

  document.getElementById('logout-btn').addEventListener('click', async () => {
    try { await Api.post('/auth/logout'); } catch {}
    Api.clearToken();
    window.location.href = 'login.html';
  });

  Nav.init('certificates');
  loadCertificates();
  loadRecommendations();
})();
