(() => {
  if (!Api.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const alertBox = document.getElementById('alert-box');
  const content = document.getElementById('content');
  let questions = [];
  let attemptId = null;

  function showError(message) {
    alertBox.textContent = message;
    alertBox.classList.remove('hidden');
  }

  async function init() {
    try {
      const status = await Api.get('/placement/status');
      attemptId = status.attempt_id || null;

      if (status.status === 'required') {
        renderIntro();
      } else if (status.status === 'in_progress') {
        await resumeInProgress();
      } else if (status.status === 'pending_review') {
        await renderPendingReview();
      } else if (status.status === 'done') {
        await renderResult();
      }
    } catch (err) {
      showError(err.message);
    }
  }

  function renderIntro() {
    content.innerHTML = `
      <div class="list-card">
        <p>This placement test has a mix of coding, logical reasoning, and a short written question. It's untimed pressure-wise, but has an overall time limit once started.</p>
        <button id="start-btn" class="btn-primary" style="width:auto; padding:0.6rem 1.5rem;">Start placement test</button>
      </div>
    `;
    document.getElementById('start-btn').addEventListener('click', startTest);
  }

  async function startTest() {
    try {
      const started = await Api.post('/placement/start');
      attemptId = started.attempt_id;
      questions = started.questions;
      renderQuestions();
    } catch (err) {
      showError(err.message);
    }
  }

  async function resumeInProgress() {
    // No GET-attempt-detail route exists for placement specifically;
    // starting again against an already in_progress attempt safely
    // resumes it (PlacementController::start()'s own documented behavior).
    await startTest();
  }

  function renderQuestions() {
    content.innerHTML = '<form id="placement-form"></form><button id="submit-btn" class="btn-primary" style="width:auto; padding:0.6rem 1.5rem; margin-top:1rem;">Submit test</button>';
    const form = document.getElementById('placement-form');

    questions.forEach((q, index) => {
      const block = document.createElement('div');
      block.className = 'list-card';
      let inputHtml;
      if (q.type === 'mcq' || q.type === 'true_false') {
        inputHtml = (q.options || []).map((opt) => `
          <label style="display:block; margin:0.4rem 0;"><input type="radio" name="q${q.id}" value="${escapeAttr(opt)}" /> ${escapeHtml(opt)}</label>
        `).join('');
      } else {
        inputHtml = `<textarea name="q${q.id}" rows="4" style="width:100%; padding:0.5rem; border:1px solid var(--border); border-radius:8px;" placeholder="Your answer…"></textarea>`;
      }
      block.innerHTML = `<p><strong>Q${index + 1}.</strong> ${escapeHtml(q.text)}</p>${inputHtml}`;
      form.appendChild(block);

      form.querySelectorAll(`[name="q${q.id}"]`).forEach((el) => {
        el.addEventListener(el.tagName === 'TEXTAREA' ? 'blur' : 'change', () => saveAnswer(q));
      });
    });

    document.getElementById('submit-btn').addEventListener('click', submitTest);
  }

  async function saveAnswer(question) {
    const form = document.getElementById('placement-form');
    let response;
    if (question.type === 'mcq' || question.type === 'true_false') {
      const checked = form.querySelector(`[name="q${question.id}"]:checked`);
      response = checked ? checked.value : null;
    } else {
      response = form.querySelector(`[name="q${question.id}"]`).value;
    }
    try {
      await Api.post(`/placement/${attemptId}/answer`, { question_id: question.id, response });
    } catch {
      // a single autosave failing isn't fatal — final submit is the real checkpoint.
    }
  }

  async function submitTest() {
    if (!confirm('Submit your placement test now?')) return;
    try {
      await Api.post(`/placement/${attemptId}/submit`);
      await renderPendingReview();
    } catch (err) {
      showError(err.message);
    }
  }

  async function renderPendingReview() {
    try {
      const result = await Api.get(`/placement/${attemptId}/result`);
      content.innerHTML = `
        <div class="list-card">
          <h3>Submitted — pending mentor review</h3>
          <p>Our AI suggests level: <strong>${result.ai_recommended_level}</strong></p>
          ${result.is_borderline ? '<p class="subtitle">This score was close to a level boundary, so a mentor will take an extra look before confirming.</p>' : ''}
          <p class="subtitle">A mentor will confirm your placement level shortly. Check back here once it's confirmed.</p>
        </div>
      `;
    } catch (err) {
      showError(err.message);
    }
  }

  async function renderResult() {
    try {
      const result = await Api.get(`/placement/${attemptId}/result`);
      content.innerHTML = `
        <div class="list-card">
          <h3>Placement confirmed: ${result.recommended_level}</h3>
          <div class="stat-grid">
            <div class="stat-tile"><div class="stat-value">${result.scores.coding ?? '—'}</div><div class="stat-label">Coding</div></div>
            <div class="stat-tile"><div class="stat-value">${result.scores.logical_reasoning ?? '—'}</div><div class="stat-label">Logical reasoning</div></div>
            <div class="stat-tile"><div class="stat-value">${result.scores.communication ?? '—'}</div><div class="stat-label">Communication</div></div>
          </div>
          ${result.communication_rationale ? `<p class="subtitle">${escapeHtml(result.communication_rationale)}</p>` : ''}
          <button id="recheck-btn" class="btn-secondary">Request a recheck</button>
        </div>
      `;
      document.getElementById('recheck-btn').addEventListener('click', requestRecheck);
    } catch (err) {
      showError(err.message);
    }
  }

  async function requestRecheck() {
    if (!confirm('Request a recheck of your placement result? This starts a fresh attempt.')) return;
    try {
      await Api.post(`/placement/${attemptId}/request-recheck`);
      window.location.reload();
    } catch (err) {
      showError(err.message);
    }
  }

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
  function escapeAttr(str) {
    return escapeHtml(str).replace(/"/g, '&quot;');
  }

  init();
})();
