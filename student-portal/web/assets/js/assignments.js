(() => {
  if (!Api.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const alertBox = document.getElementById('alert-box');
  const successBox = document.getElementById('success-box');
  const listPanel = document.getElementById('list-panel');
  const detailPanel = document.getElementById('detail-panel');
  let currentAssignmentId = null;

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

  async function loadList() {
    try {
      const me = await Nav.init('assignments');
      if (!me.current_enrollment) {
        listPanel.innerHTML = '<div class="empty-state">No active course enrollment found.</div>';
        return;
      }
      const assignments = await Api.get(`/courses/${me.current_enrollment.course_id}/assignments`);
      if (!assignments.length) {
        listPanel.innerHTML = '<div class="empty-state">No assignments published yet for this course.</div>';
        return;
      }
      listPanel.innerHTML = '';
      for (const a of assignments) {
        const card = document.createElement('div');
        card.className = 'list-card';
        card.classList.add('clickable');
        const due = a.due_date ? new Date(a.due_date).toLocaleString() : 'No due date';
        const statusTag = statusBadge(a.own_submission_status, a.is_overdue);
        card.innerHTML = `
          <div class="list-card-row">
            <div>
              <h4>${escapeHtml(a.title)}</h4>
              <p class="subtitle">${a.type} · Due ${due}</p>
            </div>
            ${statusTag}
          </div>
        `;
        card.addEventListener('click', () => openDetail(a.id));
        listPanel.appendChild(card);
      }
    } catch (err) {
      showError(err.message);
    }
  }

  function statusBadge(status, isOverdue) {
    if (status === 'graded') return '<span class="tag tag-success">Graded</span>';
    if (status === 'submitted' || status === 'resubmitted') return '<span class="tag tag-success">Submitted</span>';
    if (status === 'returned') return '<span class="tag tag-warning">Returned — resubmit</span>';
    if (isOverdue) return '<span class="tag tag-danger">Overdue</span>';
    if (status === 'draft') return '<span class="tag tag-muted">Draft saved</span>';
    return '<span class="tag tag-muted">Not started</span>';
  }

  async function openDetail(id) {
    currentAssignmentId = id;
    alertBox.classList.add('hidden');
    successBox.classList.add('hidden');
    try {
      const [assignment, submission] = await Promise.all([
        Api.get(`/assignments/${id}`),
        Api.get(`/assignments/${id}/submission`),
      ]);

      document.getElementById('a-title').textContent = assignment.title;
      document.getElementById('a-meta').textContent =
        `${assignment.type} · ${assignment.total_marks} marks · Due ${assignment.due_date ? new Date(assignment.due_date).toLocaleString() : 'no due date'}`;
      document.getElementById('a-description').textContent = assignment.description || '';

      document.getElementById('f-text').value = submission?.submission_text || '';
      document.getElementById('f-url').value = submission?.url || '';
      document.getElementById('f-github').value = submission?.github_repo_url || '';
      document.getElementById('f-video').value = submission?.demo_video_url || '';

      const finalized = submission && ['submitted', 'graded', 'resubmitted'].includes(submission.status);
      document.getElementById('submit-btn').disabled = !!finalized;
      document.getElementById('submission-status').textContent = submission
        ? `Status: ${submission.status}${submission.is_late ? ' (late)' : ''}`
        : 'No submission saved yet.';

      listPanel.classList.add('hidden');
      detailPanel.classList.remove('hidden');
    } catch (err) {
      showError(err.message);
    }
  }

  function closeDetail() {
    detailPanel.classList.add('hidden');
    document.getElementById('list-panel').classList.remove('hidden');
    loadList();
  }

  async function saveDraft() {
    try {
      await Api.put(`/assignments/${currentAssignmentId}/submission`, {
        submission_text: document.getElementById('f-text').value,
        url: document.getElementById('f-url').value,
        github_repo_url: document.getElementById('f-github').value,
        demo_video_url: document.getElementById('f-video').value,
      });
      showSuccess('Draft saved.');
      document.getElementById('submit-btn').disabled = false;
      document.getElementById('submission-status').textContent = 'Status: draft';
    } catch (err) {
      showError(err.message);
    }
  }

  async function submitAssignment() {
    if (!confirm('Submit this assignment now?')) return;
    try {
      const result = await Api.post(`/assignments/${currentAssignmentId}/submission/submit`);
      showSuccess(`Submitted!${result.is_late ? ' (marked late)' : ''}`);
      document.getElementById('submit-btn').disabled = true;
      document.getElementById('submission-status').textContent = `Status: ${result.status}`;
    } catch (err) {
      showError(err.message);
    }
  }

  document.getElementById('back-btn').addEventListener('click', closeDetail);
  document.getElementById('save-draft-btn').addEventListener('click', saveDraft);
  document.getElementById('submit-btn').addEventListener('click', submitAssignment);

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

  loadList();
})();
