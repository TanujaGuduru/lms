/**
 * Tiny fetch wrapper for the Student Portal API.
 * Same domain, different path on GoDaddy (yourdomain.com/api/v1/...) — see
 * ../../../README.md — so this always calls a relative path, no CORS needed
 * in production.
 */
const Api = (() => {
  const BASE = '/api/v1';

  function token() {
    return localStorage.getItem('auth_token');
  }

  async function request(method, path, body) {
    const headers = { 'Content-Type': 'application/json' };
    const t = token();
    if (t) {
      headers.Authorization = `Bearer ${t}`;
    }

    const response = await fetch(BASE + path, {
      method,
      headers,
      body: body ? JSON.stringify(body) : undefined,
    });

    const payload = await response.json().catch(() => ({
      success: false,
      message: 'Unexpected server response.',
    }));

    if (!response.ok || payload.success === false) {
      const error = new Error(payload.message || 'Request failed.');
      error.status = response.status;
      error.errors = payload.errors || {};
      throw error;
    }

    return payload.data;
  }

  return {
    get: (path) => request('GET', path),
    post: (path, body) => request('POST', path, body),
    setToken: (value) => localStorage.setItem('auth_token', value),
    clearToken: () => localStorage.removeItem('auth_token'),
    isAuthenticated: () => !!token(),
  };
})();
