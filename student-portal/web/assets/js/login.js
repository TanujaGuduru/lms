(() => {
  if (Api.isAuthenticated()) {
    window.location.href = 'dashboard.html';
    return;
  }

  const form = document.getElementById('login-form');
  const alertBox = document.getElementById('alert-box');
  const submitBtn = document.getElementById('submit-btn');

  function showError(message) {
    alertBox.textContent = message;
    alertBox.classList.remove('hidden');
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    alertBox.classList.add('hidden');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Signing in…';

    try {
      const data = await Api.post('/auth/login', {
        email: document.getElementById('email').value.trim(),
        password: document.getElementById('password').value,
      });
      Api.setToken(data.token);
      window.location.href = 'dashboard.html';
    } catch (err) {
      showError(err.message);
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Sign in';
    }
  });
})();
