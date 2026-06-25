(() => {
  if (!Api.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const examId = new URLSearchParams(window.location.search).get('id');
  const alertBox = document.getElementById('alert-box');
  const introPanel = document.getElementById('intro-panel');
  const form = document.getElementById('questions-form');
  const submitBtn = document.getElementById('submit-btn');
  const resultPanel = document.getElementById('result-panel');
  const timerEl = document.getElementById('timer');

  let attemptId = null;
  let timerInterval = null;

  function showError(message) {
    alertBox.textContent = message;
    alertBox.classList.remove('hidden');
  }

  async function loadIntro() {
    try {
      const exam = await Api.get(`/exams/${examId}`);
      document.getElementById('exam-title').textContent = exam.title;
      document.getElementById('exam-meta').textContent =
        `${exam.duration_minutes} minutes · ${exam.total_marks} marks · Attempt ${exam.attempts_used + 1} of ${exam.max_attempts}`;
      document.getElementById('exam-instructions').textContent = exam.instructions || '';

      if (exam.attempts_used >= exam.max_attempts) {
        document.getElementById('start-btn').disabled = true;
        document.getElementById('start-btn').textContent = 'No attempts remaining';
      }
    } catch (err) {
      showError(err.message);
    }
  }

  async function startExam() {
    try {
      const started = await Api.post(`/exams/${examId}/attempts`);
      attemptId = started.attempt_id;
      // Re-fetch via attemptDetail so previously-saved answers (if resuming
      // an in-progress attempt) come back prefilled, not blank.
      const detail = await Api.get(`/attempts/${attemptId}`);
      renderQuestions(detail);
      startTimer(detail.ends_at);
      introPanel.classList.add('hidden');
      form.classList.remove('hidden');
      submitBtn.classList.remove('hidden');
    } catch (err) {
      showError(err.message);
    }
  }

  function renderQuestions(detail) {
    form.innerHTML = '';
    detail.questions.forEach((q, index) => {
      const block = document.createElement('div');
      block.className = 'list-card';
      const saved = q.own_response ? q.own_response.response : null;

      let inputHtml = '';
      if (q.type === 'mcq' || q.type === 'true_false') {
        inputHtml = (q.options || []).map((opt) => `
          <label style="display:block; margin: 0.4rem 0;">
            <input type="radio" name="q${q.question_id}" value="${escapeAttr(opt)}" ${saved === opt ? 'checked' : ''} /> ${escapeHtml(opt)}
          </label>
        `).join('');
      } else if (q.type === 'msq') {
        const savedArr = Array.isArray(saved) ? saved : [];
        inputHtml = (q.options || []).map((opt) => `
          <label style="display:block; margin: 0.4rem 0;">
            <input type="checkbox" name="q${q.question_id}" value="${escapeAttr(opt)}" ${savedArr.includes(opt) ? 'checked' : ''} /> ${escapeHtml(opt)}
          </label>
        `).join('');
      } else if (q.type === 'fill_blank') {
        inputHtml = `<input type="text" name="q${q.question_id}" value="${escapeAttr(saved || '')}" style="width:100%; padding:0.5rem; border:1px solid var(--border); border-radius:8px;" />`;
      } else {
        // short_answer / long_answer / coding
        inputHtml = `<textarea name="q${q.question_id}" rows="${q.type === 'coding' ? 8 : 4}" style="width:100%; padding:0.5rem; border:1px solid var(--border); border-radius:8px; font-family: ${q.type === 'coding' ? 'monospace' : 'inherit'};">${escapeHtml(saved || '')}</textarea>`;
      }

      block.innerHTML = `
        <p><strong>Q${index + 1}.</strong> ${escapeHtml(q.question_text)} <span class="tag tag-muted">${q.marks} marks</span></p>
        ${inputHtml}
      `;
      form.appendChild(block);

      // Autosave on change/blur — every field type fires one of these.
      block.querySelectorAll('input, textarea').forEach((el) => {
        el.addEventListener(el.tagName === 'TEXTAREA' || el.type === 'text' ? 'blur' : 'change', () => autosave(q));
      });
    });
  }

  function collectResponse(question) {
    if (question.type === 'msq') {
      return Array.from(form.querySelectorAll(`input[name="q${question.question_id}"]:checked`)).map((el) => el.value);
    }
    if (question.type === 'mcq' || question.type === 'true_false') {
      const checked = form.querySelector(`input[name="q${question.question_id}"]:checked`);
      return checked ? checked.value : null;
    }
    const field = form.querySelector(`[name="q${question.question_id}"]`);
    return field ? field.value : null;
  }

  async function autosave(question) {
    try {
      await Api.put(`/attempts/${attemptId}/responses/${question.question_id}`, {
        response: collectResponse(question),
      });
    } catch {
      // a single autosave failing isn't fatal — the next change retries; final submit is the real checkpoint.
    }
  }

  function startTimer(endsAt) {
    const endTime = new Date(endsAt).getTime();
    timerEl.classList.remove('hidden');
    timerInterval = setInterval(() => {
      const remainingMs = endTime - Date.now();
      if (remainingMs <= 0) {
        clearInterval(timerInterval);
        timerEl.textContent = "Time's up";
        submitExam();
        return;
      }
      const mins = Math.floor(remainingMs / 60000);
      const secs = Math.floor((remainingMs % 60000) / 1000);
      timerEl.textContent = `${mins}:${String(secs).padStart(2, '0')} left`;
    }, 1000);
  }

  async function submitExam() {
    clearInterval(timerInterval);
    submitBtn.disabled = true;
    try {
      const result = await Api.post(`/attempts/${attemptId}/submit`);
      form.classList.add('hidden');
      submitBtn.classList.add('hidden');
      timerEl.classList.add('hidden');
      resultPanel.classList.remove('hidden');

      if (!result.result_visible) {
        resultPanel.innerHTML = '<div class="alert alert-success">Submitted! Your result will be released by your teacher.</div>';
      } else {
        resultPanel.innerHTML = `
          <div class="list-card">
            <h3>${result.is_passed ? '✅ Passed' : '❌ Not passed'}</h3>
            <p>Score: ${result.obtained_marks} (${result.percentage}%)</p>
            ${!result.fully_graded ? '<p class="subtitle">Some answers still need manual grading — this score may update.</p>' : ''}
          </div>
        `;
      }
    } catch (err) {
      submitBtn.disabled = false;
      showError(err.message);
    }
  }

  document.getElementById('start-btn').addEventListener('click', startExam);
  submitBtn.addEventListener('click', () => {
    if (confirm('Submit the exam now? You cannot change answers after this.')) {
      submitExam();
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
  function escapeAttr(str) {
    return escapeHtml(str).replace(/"/g, '&quot;');
  }

  loadIntro();
})();
