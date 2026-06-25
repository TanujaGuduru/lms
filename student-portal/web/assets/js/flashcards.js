(() => {
  if (!Api.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const alertBox = document.getElementById('alert-box');
  const cardArea = document.getElementById('card-area');
  const progressLabel = document.getElementById('progress-label');

  let cards = [];
  let index = 0;
  let revealed = false;

  function showError(message) {
    alertBox.textContent = message;
    alertBox.classList.remove('hidden');
  }

  async function load() {
    try {
      cards = await Api.get('/flashcards/due');
      index = 0;
      render();
    } catch (err) {
      showError(err.message);
    }
  }

  function render() {
    if (!cards.length) {
      progressLabel.textContent = '';
      cardArea.innerHTML = '<div class="empty-state">No flashcards due right now. Come back later, or create more from a note.</div>';
      return;
    }
    if (index >= cards.length) {
      progressLabel.textContent = '';
      cardArea.innerHTML = '<div class="empty-state">All caught up! You reviewed every due card.</div>';
      return;
    }

    const card = cards[index];
    revealed = false;
    progressLabel.textContent = `Card ${index + 1} of ${cards.length} · ${card.deck_name}`;
    cardArea.innerHTML = `
      <div class="list-card" style="min-height: 160px; display:flex; align-items:center; justify-content:center; text-align:center; cursor:pointer;" id="flip-card">
        <p style="font-size:1.1rem; margin:0;">${escapeHtml(card.front_text)}</p>
      </div>
      <div id="answer-area" class="hidden">
        <div class="list-card" style="background:#eef2ff;">
          <p style="margin:0;">${escapeHtml(card.back_text)}</p>
        </div>
        <div style="display:flex; gap:0.5rem; margin-top:1rem;">
          <button data-outcome="again" class="btn-danger" style="flex:1;">Again</button>
          <button data-outcome="hard" class="btn-secondary" style="flex:1;">Hard</button>
          <button data-outcome="good" class="btn-secondary" style="flex:1;">Good</button>
          <button data-outcome="easy" class="btn-primary" style="flex:1; width:auto;">Easy</button>
        </div>
      </div>
    `;

    document.getElementById('flip-card').addEventListener('click', () => {
      revealed = true;
      document.getElementById('answer-area').classList.remove('hidden');
    });

    cardArea.querySelectorAll('[data-outcome]').forEach((btn) => {
      btn.addEventListener('click', () => review(card.id, btn.dataset.outcome));
    });
  }

  async function review(cardId, outcome) {
    try {
      await Api.post(`/flashcards/${cardId}/review`, { outcome });
      index++;
      render();
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

  load();
})();
