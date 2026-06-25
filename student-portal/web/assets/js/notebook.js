(() => {
  if (!Api.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const alertBox = document.getElementById('alert-box');
  const successBox = document.getElementById('success-box');
  const listEl = document.getElementById('notes-list');
  const modal = document.getElementById('editor-modal');
  const titleInput = document.getElementById('note-title');
  const contentInput = document.getElementById('note-content');
  const aiResult = document.getElementById('ai-result');

  let currentNoteId = null;

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

  function clearAlerts() {
    alertBox.classList.add('hidden');
    successBox.classList.add('hidden');
  }

  async function loadNotes() {
    try {
      const notes = await Api.get('/notes');
      if (!notes.length) {
        listEl.innerHTML = '<div class="empty-state">No notes yet. Click "New note" to write your first one.</div>';
        return;
      }
      listEl.innerHTML = '';
      for (const note of notes) {
        const card = document.createElement('div');
        card.className = 'list-card';
        card.classList.add('clickable');
        const updated = new Date(note.updated_at).toLocaleDateString();
        card.innerHTML = `
          <div class="list-card-row">
            <div>
              <h4>${escapeHtml(note.title || 'Untitled')}</h4>
              <p class="subtitle">Updated ${updated}${note.is_ai_generated ? ' · <span class="tag">AI-assisted</span>' : ''}</p>
            </div>
            ${note.is_favorite ? '<span class="tag tag-warning">★ Favorite</span>' : ''}
          </div>
        `;
        card.addEventListener('click', () => openEditor(note.id));
        listEl.appendChild(card);
      }
    } catch (err) {
      listEl.innerHTML = '';
      showError(err.message);
    }
  }

  async function openEditor(id) {
    clearAlerts();
    aiResult.classList.add('hidden');
    aiResult.innerHTML = '';
    currentNoteId = id;

    if (id) {
      try {
        const note = await Api.get(`/notes/${id}`);
        titleInput.value = note.title || '';
        contentInput.value = note.content || '';
      } catch (err) {
        showError(err.message);
        return;
      }
    } else {
      titleInput.value = '';
      contentInput.value = '';
    }

    document.getElementById('delete-note-btn').classList.toggle('hidden', !id);
    document.getElementById('summarize-btn').classList.toggle('hidden', !id);
    document.getElementById('flashcards-btn').classList.toggle('hidden', !id);
    document.getElementById('quiz-btn').classList.toggle('hidden', !id);
    modal.classList.remove('hidden');
  }

  function closeEditor() {
    modal.classList.add('hidden');
    currentNoteId = null;
  }

  async function saveNote() {
    const payload = { title: titleInput.value, content: contentInput.value };
    try {
      if (currentNoteId) {
        await Api.patch(`/notes/${currentNoteId}`, payload);
      } else {
        const created = await Api.post('/notes', payload);
        currentNoteId = created.id;
      }
      closeEditor();
      showSuccess('Note saved.');
      loadNotes();
    } catch (err) {
      showError(err.message);
    }
  }

  async function deleteNote() {
    if (!currentNoteId || !confirm('Delete this note? This cannot be undone.')) {
      return;
    }
    try {
      await Api.delete(`/notes/${currentNoteId}`);
      closeEditor();
      showSuccess('Note deleted.');
      loadNotes();
    } catch (err) {
      showError(err.message);
    }
  }

  async function runAiAction(button, action) {
    const original = button.textContent;
    button.disabled = true;
    button.textContent = 'Thinking…';
    aiResult.classList.add('hidden');
    try {
      await action();
    } catch (err) {
      aiResult.classList.remove('hidden');
      aiResult.innerHTML = `<div class="alert alert-error">${escapeHtml(err.message)}</div>`;
    } finally {
      button.disabled = false;
      button.textContent = original;
    }
  }

  document.getElementById('summarize-btn').addEventListener('click', (e) => runAiAction(e.target, async () => {
    const result = await Api.post(`/notes/${currentNoteId}/summarize`);
    aiResult.classList.remove('hidden');
    aiResult.innerHTML = `
      <div class="list-card">
        <strong>Suggested summary</strong>
        <p style="white-space:pre-wrap;">${escapeHtml(result.suggested_summary)}</p>
        <button id="apply-summary-btn" class="btn-secondary">Replace content with this</button>
      </div>
    `;
    document.getElementById('apply-summary-btn').addEventListener('click', () => {
      contentInput.value = result.suggested_summary;
    });
  }));

  document.getElementById('flashcards-btn').addEventListener('click', (e) => runAiAction(e.target, async () => {
    const result = await Api.post(`/notes/${currentNoteId}/flashcards`);
    aiResult.classList.remove('hidden');
    aiResult.innerHTML = `<div class="alert alert-success">${result.flashcard_ids.length} flashcard(s) added to deck "${escapeHtml(result.deck_name)}". <a href="flashcards.html">Review flashcards →</a></div>`;
  }));

  document.getElementById('quiz-btn').addEventListener('click', (e) => runAiAction(e.target, async () => {
    const result = await Api.post(`/notes/${currentNoteId}/generate-quiz`);
    aiResult.classList.remove('hidden');
    aiResult.innerHTML = `<div class="alert alert-success">Practice quiz generated. <a href="exam.html?id=${result.exam_id}">Start quiz →</a></div>`;
  }));

  document.getElementById('new-note-btn').addEventListener('click', () => openEditor(null));
  document.getElementById('save-note-btn').addEventListener('click', saveNote);
  document.getElementById('delete-note-btn').addEventListener('click', deleteNote);
  document.getElementById('close-editor-btn').addEventListener('click', closeEditor);

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

  Nav.init('notebook');
  loadNotes();
})();
