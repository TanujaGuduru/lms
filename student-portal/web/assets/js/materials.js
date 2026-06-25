(() => {
  if (!Api.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const alertBox = document.getElementById('alert-box');
  let courseId = null;
  let batchId = null;
  let currentRecordingId = null;

  function showError(message) {
    alertBox.textContent = message;
    alertBox.classList.remove('hidden');
  }
  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
  }

  async function loadContext() {
    const me = await Api.get('/auth/me');
    courseId = me.current_enrollment ? me.current_enrollment.course_id : null;
    try {
      const batch = await Api.get('/batch/current');
      batchId = batch.batch_id;
    } catch {
      batchId = null; // no active batch — recordings tab will show empty state.
    }
  }

  async function loadMaterials() {
    const el = document.getElementById('tab-materials');
    if (!courseId) {
      el.innerHTML = '<div class="empty-state">No active course enrollment found.</div>';
      return;
    }
    try {
      const materials = await Api.get(`/courses/${courseId}/materials`);
      if (!materials.length) {
        el.innerHTML = '<div class="empty-state">No materials uploaded yet for this course.</div>';
        return;
      }
      el.innerHTML = materials.map((m) => `
        <div class="list-card list-card-row">
          <div><h4>${escapeHtml(m.title)}</h4><p class="subtitle">${m.file_type} · v${m.current_version}</p></div>
          ${m.is_downloadable ? `<button class="btn-secondary" data-download="${m.id}">Download</button>` : '<span class="tag tag-muted">View only</span>'}
        </div>
      `).join('');
      el.querySelectorAll('[data-download]').forEach((btn) => {
        btn.addEventListener('click', () => downloadMaterial(btn.dataset.download));
      });
    } catch (err) {
      showError(err.message);
    }
  }

  async function downloadMaterial(id) {
    try {
      const result = await Api.get(`/materials/${id}/download`);
      window.open(result.url, '_blank');
    } catch (err) {
      showError(err.message);
    }
  }

  async function loadRecordings() {
    const el = document.getElementById('recordings-list');
    if (!batchId) {
      el.innerHTML = '<div class="empty-state">No active batch found.</div>';
      return;
    }
    try {
      const recordings = await Api.get(`/recordings?batch_id=${batchId}`);
      renderRecordingList(recordings);
    } catch (err) {
      showError(err.message);
    }
  }

  function renderRecordingList(recordings) {
    const el = document.getElementById('recordings-list');
    if (!recordings.length) {
      el.innerHTML = '<div class="empty-state">No recordings available yet.</div>';
      return;
    }
    el.innerHTML = recordings.map((r) => `
      <div class="list-card list-card-row clickable" data-open="${r.id}">
        <div><h4>${escapeHtml(r.title)}</h4><p class="subtitle">${r.processing_status}${r.relevance ? ' · match: ' + Number(r.relevance).toFixed(2) : ''}</p></div>
      </div>
    `).join('');
    el.querySelectorAll('[data-open]').forEach((card) => {
      card.addEventListener('click', () => openRecording(card.dataset.open));
    });
  }

  document.getElementById('search-btn').addEventListener('click', async () => {
    const q = document.getElementById('search-input').value.trim();
    if (q.length < 2) {
      showError('Type at least 2 characters to search.');
      return;
    }
    try {
      const results = await Api.get(`/recordings/search?q=${encodeURIComponent(q)}`);
      renderRecordingList(results);
    } catch (err) {
      showError(err.message);
    }
  });

  async function openRecording(id) {
    currentRecordingId = id;
    try {
      const recording = await Api.get(`/recordings/${id}`);
      if (recording.processing_status !== 'completed') {
        showError(`This recording is still processing (${recording.processing_status}).`);
        return;
      }
      document.getElementById('recording-title').textContent = '';
      document.getElementById('generate-notes-result').innerHTML = '';
      const video = document.getElementById('video-player');
      video.src = recording.processed_video_url;
      video.currentTime = recording.last_position_seconds || 0;

      video.onpause = () => Api.post(`/recordings/${id}/progress`, { position_seconds: Math.floor(video.currentTime) }).catch(() => {});
      video.onended = () => Api.post(`/recordings/${id}/progress`, { position_seconds: Math.floor(video.duration) }).catch(() => {});

      document.getElementById('tab-recordings').classList.add('hidden');
      document.querySelectorAll('.tabs').forEach((t) => t.classList.add('hidden'));
      document.getElementById('player-panel').classList.remove('hidden');
      loadBookmarks(id);
    } catch (err) {
      showError(err.message);
    }
  }

  async function loadBookmarks(id) {
    const el = document.getElementById('bookmarks-list');
    try {
      const bookmarks = await Api.get(`/recordings/${id}/bookmarks`);
      el.innerHTML = bookmarks.length
        ? bookmarks.map((b) => `<div class="list-card">${formatTime(b.timestamp_seconds)} ${b.label ? '— ' + escapeHtml(b.label) : ''}</div>`).join('')
        : '<p class="subtitle">No bookmarks yet.</p>';
    } catch (err) {
      showError(err.message);
    }
  }

  function formatTime(seconds) {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
  }

  document.getElementById('add-bookmark-btn').addEventListener('click', async () => {
    const video = document.getElementById('video-player');
    const label = prompt('Bookmark label (optional):') || '';
    try {
      await Api.post(`/recordings/${currentRecordingId}/bookmarks`, {
        timestamp_seconds: Math.floor(video.currentTime),
        label,
      });
      loadBookmarks(currentRecordingId);
    } catch (err) {
      showError(err.message);
    }
  });

  document.getElementById('generate-notes-btn').addEventListener('click', async (e) => {
    const btn = e.target;
    btn.disabled = true;
    btn.textContent = 'Generating…';
    try {
      const note = await Api.post(`/recordings/${currentRecordingId}/generate-notes`);
      document.getElementById('generate-notes-result').innerHTML =
        `<div class="alert alert-success">Notes created: "${escapeHtml(note.title)}". <a href="notebook.html">Open Notebook →</a></div>`;
    } catch (err) {
      document.getElementById('generate-notes-result').innerHTML = `<div class="alert alert-error">${escapeHtml(err.message)}</div>`;
    } finally {
      btn.disabled = false;
      btn.textContent = 'AI: Generate notes from this class';
    }
  });

  document.getElementById('back-to-recordings').addEventListener('click', () => {
    document.getElementById('player-panel').classList.add('hidden');
    document.querySelectorAll('.tabs').forEach((t) => t.classList.remove('hidden'));
    document.getElementById('tab-recordings').classList.remove('hidden');
    document.getElementById('video-player').pause();
  });

  document.querySelectorAll('.tabs button').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tabs button').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById('tab-materials').classList.toggle('hidden', btn.dataset.tab !== 'materials');
      document.getElementById('tab-recordings').classList.toggle('hidden', btn.dataset.tab !== 'recordings');
      if (btn.dataset.tab === 'recordings') {
        loadRecordings();
      }
    });
  });

  document.getElementById('logout-btn').addEventListener('click', async () => {
    try { await Api.post('/auth/logout'); } catch {}
    Api.clearToken();
    window.location.href = 'login.html';
  });

  Nav.init('materials');
  loadContext().then(loadMaterials);
})();
