(() => {
  if (!Api.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const alertBox = document.getElementById('alert-box');
  const successBox = document.getElementById('success-box');
  const listPanel = document.getElementById('list-panel');
  const detailPanel = document.getElementById('detail-panel');
  const newTicketModal = document.getElementById('new-ticket-modal');
  let currentTicketId = null;

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

  async function loadList() {
    try {
      const tickets = await Api.get('/support/tickets');
      if (!tickets.length) {
        listPanel.innerHTML = '<div class="empty-state">No support tickets yet.</div>';
        return;
      }
      listPanel.innerHTML = tickets.map((t) => `
        <div class="list-card list-card-row clickable" data-open="${t.id}">
          <div>
            <h4>${escapeHtml(t.subject)}</h4>
            <p class="subtitle">${t.ticket_number} · ${new Date(t.created_at).toLocaleDateString()}</p>
          </div>
          <span class="tag ${t.status === 'resolved' || t.status === 'closed' ? 'tag-success' : 'tag-warning'}">${t.status}</span>
        </div>
      `).join('');
      listPanel.querySelectorAll('[data-open]').forEach((card) => {
        card.addEventListener('click', () => openDetail(card.dataset.open));
      });
    } catch (err) {
      showError(err.message);
    }
  }

  async function openNewTicketModal() {
    try {
      const categories = await Api.get('/support/categories');
      const select = document.getElementById('category-select');
      select.innerHTML = categories.map((c) => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
      newTicketModal.classList.remove('hidden');
    } catch (err) {
      showError(err.message);
    }
  }

  async function createTicket() {
    try {
      await Api.post('/support/tickets', {
        category_id: document.getElementById('category-select').value,
        subject: document.getElementById('ticket-subject').value,
        description: document.getElementById('ticket-description').value,
      });
      newTicketModal.classList.add('hidden');
      document.getElementById('ticket-subject').value = '';
      document.getElementById('ticket-description').value = '';
      showSuccess('Ticket created.');
      loadList();
    } catch (err) {
      showError(err.message);
    }
  }

  async function openDetail(id) {
    currentTicketId = id;
    try {
      const ticket = await Api.get(`/support/tickets/${id}`);
      document.getElementById('t-subject').textContent = ticket.subject;
      document.getElementById('t-meta').textContent = `${ticket.ticket_number} · ${ticket.priority} priority · ${ticket.status}`;
      document.getElementById('t-description').textContent = ticket.description;

      document.getElementById('replies-list').innerHTML = ticket.replies.map((r) => `
        <div class="list-card">
          <p style="margin:0;">${escapeHtml(r.message)}</p>
          <p class="subtitle" style="margin:0.25rem 0 0;">${new Date(r.created_at).toLocaleString()}</p>
        </div>
      `).join('') || '<p class="subtitle">No replies yet.</p>';

      const canRate = ['resolved', 'closed'].includes(ticket.status) && !ticket.satisfaction_rating;
      const satPanel = document.getElementById('satisfaction-panel');
      satPanel.classList.toggle('hidden', !canRate);
      if (canRate) {
        document.getElementById('rating-stars').innerHTML = [1, 2, 3, 4, 5]
          .map((n) => `<button class="btn-secondary" data-rate="${n}" style="margin-right:0.3rem;">${'★'.repeat(n)}</button>`)
          .join('');
        document.querySelectorAll('[data-rate]').forEach((btn) => {
          btn.addEventListener('click', () => rate(btn.dataset.rate));
        });
      }

      listPanel.classList.add('hidden');
      detailPanel.classList.remove('hidden');
    } catch (err) {
      showError(err.message);
    }
  }

  async function rate(value) {
    try {
      await Api.post(`/support/tickets/${currentTicketId}/satisfaction`, { satisfaction_rating: value });
      showSuccess('Thanks for the feedback!');
      openDetail(currentTicketId);
    } catch (err) {
      showError(err.message);
    }
  }

  document.getElementById('send-reply-btn').addEventListener('click', async () => {
    const message = document.getElementById('reply-message').value.trim();
    if (!message) return;
    try {
      await Api.post(`/support/tickets/${currentTicketId}/replies`, { message });
      document.getElementById('reply-message').value = '';
      openDetail(currentTicketId);
    } catch (err) {
      showError(err.message);
    }
  });

  document.getElementById('new-ticket-btn').addEventListener('click', openNewTicketModal);
  document.getElementById('close-new-ticket-btn').addEventListener('click', () => newTicketModal.classList.add('hidden'));
  document.getElementById('create-ticket-btn').addEventListener('click', createTicket);
  document.getElementById('back-btn').addEventListener('click', () => {
    detailPanel.classList.add('hidden');
    listPanel.classList.remove('hidden');
    loadList();
  });

  document.getElementById('logout-btn').addEventListener('click', async () => {
    try { await Api.post('/auth/logout'); } catch {}
    Api.clearToken();
    window.location.href = 'login.html';
  });

  Nav.init('support');
  loadList();
})();
