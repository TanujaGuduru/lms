(() => {
  if (!Api.isAuthenticated()) {
    window.location.href = 'login.html';
    return;
  }

  const alertBox = document.getElementById('alert-box');
  const successBox = document.getElementById('success-box');

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

  async function loadSummary() {
    const el = document.getElementById('wallet-summary');
    try {
      const wallet = await Api.get('/wallet');
      el.innerHTML = `
        <div class="stat-grid">
          <div class="stat-tile"><div class="stat-value">${wallet.credits_balance}</div><div class="stat-label">Balance</div></div>
          <div class="stat-tile"><div class="stat-value">${wallet.credits_purchased}</div><div class="stat-label">Purchased</div></div>
          <div class="stat-tile"><div class="stat-value">${wallet.credits_consumed}</div><div class="stat-label">Consumed</div></div>
          <div class="stat-tile"><div class="stat-value" style="font-size:1rem;">${wallet.status}</div><div class="stat-label">Status</div></div>
        </div>
        ${wallet.expiry_date ? `<p class="subtitle">Expires ${wallet.expiry_date}</p>` : ''}
        ${wallet.credits_balance <= wallet.low_balance_threshold ? '<div class="alert alert-error">Low balance — consider topping up.</div>' : ''}
      `;
    } catch (err) {
      el.innerHTML = '';
      showError(err.message);
    }
  }

  async function loadTransactions() {
    const el = document.getElementById('transactions');
    try {
      const rows = await Api.get('/wallet/transactions');
      if (!rows.length) {
        el.innerHTML = '<div class="empty-state">No transactions yet.</div>';
        return;
      }
      el.innerHTML = rows.map((t) => `
        <div class="list-card">
          <div class="list-card-row">
            <div>
              <strong>${t.type}</strong>
              <p class="subtitle" style="margin:0.25rem 0;">${new Date(t.created_at).toLocaleString()}${t.reason ? ' · ' + escapeHtml(t.reason) : ''}</p>
            </div>
            <div class="tag ${t.amount >= 0 ? 'tag-success' : 'tag-danger'}">${t.amount >= 0 ? '+' : ''}${t.amount}</div>
          </div>
        </div>
      `).join('');
    } catch (err) {
      el.innerHTML = '';
      showError(err.message);
    }
  }

  document.getElementById('purchase-btn').addEventListener('click', async () => {
    const credits = parseInt(document.getElementById('credits-input').value, 10);
    try {
      const result = await Api.post('/wallet/purchase', { credits });
      showSuccess(`Purchase initiated — invoice ${result.invoice_number}. Payment gateway integration is not yet configured, so this records the intent without charging a card.`);
    } catch (err) {
      showError(err.message);
    }
  });

  document.getElementById('freeze-btn').addEventListener('click', async () => {
    const effectiveDate = document.getElementById('freeze-date').value;
    if (!effectiveDate) {
      showError('Pick an effective date first.');
      return;
    }
    try {
      const result = await Api.post('/wallet/freeze', {
        effective_date: effectiveDate,
        reason: document.getElementById('freeze-reason').value,
      });
      showSuccess(`Freeze scheduled for ${result.pending_freeze_effective}.`);
    } catch (err) {
      showError(err.message);
    }
  });

  document.getElementById('resume-btn').addEventListener('click', async () => {
    try {
      await Api.post('/wallet/resume');
      showSuccess('Wallet resumed / pending freeze cancelled.');
      loadSummary();
    } catch (err) {
      showError(err.message);
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

  Nav.init('wallet');
  loadSummary();
  loadTransactions();
})();
