<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/security">Security Center</a>
      <span class="sep">/</span><span>Active Sessions</span>
    </div>
    <h1 class="page-title">Active Sessions</h1>
    <p class="page-subtitle">Monitor and manage all currently signed-in user sessions</p>
  </div>
  <div class="d-flex gap-2">
    <button onclick="revokeAllSessions()" class="btn btn-danger btn-sm"><i class="fas fa-power-off"></i> Revoke All</button>
  </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-xl col-md-4 col-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:#3b82f618;color:#3b82f6"><i class="fas fa-desktop"></i></div>
      <div><div class="stat-mini-value"><?= number_format((int)($meta['total'] ?? 0)) ?></div><div class="stat-mini-label">Active Sessions</div></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-desktop" style="color:#3b82f6"></i> Sessions</h3>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>User</th><th>Device</th><th>IP Address</th><th>Location</th><th>Last Activity</th><th>Started</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (empty($sessions)): ?>
        <tr><td colspan="7">
          <div class="empty-state">
            <i class="fas fa-desktop empty-state-icon"></i>
            <h4 class="empty-state-title">No Active Sessions</h4>
            <p class="empty-state-desc">There are currently no active user sessions.</p>
          </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($sessions as $s): ?>
        <tr data-token="<?= \App\Core\View::e($s['session_token']) ?>">
          <td>
            <div class="d-flex align-items-center gap-2">
              <?php if (!empty($s['avatar'])): ?>
              <img src="<?= \App\Core\View::e($s['avatar']) ?>" style="width:28px;height:28px;border-radius:6px;object-fit:cover">
              <?php else: ?>
              <div style="width:28px;height:28px;background:#6366f118;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#6366f1;font-size:11px"><i class="fas fa-user"></i></div>
              <?php endif; ?>
              <div>
                <div style="font-size:13px;font-weight:600;color:#0f172a"><?= \App\Core\View::e($s['user_name'] ?? 'Unknown') ?></div>
                <div style="font-size:11.5px;color:#94a3b8"><?= \App\Core\View::e($s['email'] ?? '') ?></div>
              </div>
            </div>
          </td>
          <td>
            <div style="font-size:13px;color:#374151">
              <i class="fas <?= ($s['device_type'] ?? '') === 'mobile' ? 'fa-mobile-alt' : (($s['device_type'] ?? '') === 'tablet' ? 'fa-tablet-alt' : 'fa-desktop') ?>" style="color:#94a3b8;margin-right:4px"></i>
              <?= \App\Core\View::e($s['browser'] ?? '—') ?>
            </div>
            <div style="font-size:11.5px;color:#94a3b8"><?= \App\Core\View::e($s['os'] ?? '—') ?></div>
          </td>
          <td style="font-family:monospace;font-size:12px;color:#64748b"><?= \App\Core\View::e($s['ip_address'] ?? '—') ?></td>
          <td style="font-size:12.5px;color:#64748b"><?= \App\Core\View::e($s['location'] ?? '—') ?></td>
          <td style="font-size:12px;color:#94a3b8"><?= \App\Core\View::timeAgo($s['last_activity']) ?></td>
          <td style="font-size:12px;color:#94a3b8"><?= \App\Core\View::timeAgo($s['created_at']) ?></td>
          <td>
            <button onclick="revokeSession('<?= \App\Core\View::e($s['session_token']) ?>')" class="btn btn-ghost btn-sm btn-icon" title="Revoke Session">
              <i class="fas fa-times-circle" style="color:#ef4444"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if (($meta['last_page'] ?? 1) > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <div style="font-size:13px;color:#64748b">Showing <?= $meta['from'] ?? 0 ?>–<?= $meta['to'] ?? 0 ?> of <?= number_format($meta['total'] ?? 0) ?></div>
    <nav><ul class="pagination" style="margin:0;gap:4px">
      <?php for ($p = max(1, ($meta['current_page'] ?? 1) - 3); $p <= min($meta['last_page'] ?? 1, ($meta['current_page'] ?? 1) + 3); $p++): ?>
      <li class="page-item <?= $p === ($meta['current_page'] ?? 1) ? 'active' : '' ?>">
        <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
      </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>

<script>
function revokeSession(token) {
  Swal.fire({
    title: 'Revoke Session?',
    html: '<p style="color:#64748b">This will immediately sign the user out of this device.</p>',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, Revoke',
    confirmButtonColor: '#ef4444',
    cancelButtonText: 'Cancel',
    reverseButtons: true
  }).then(async (r) => {
    if (!r.isConfirmed) return;
    const d = await cgFetch(`/super-admin/security/sessions/${token}/revoke`, { method: 'POST' });
    if (d.success) {
      document.querySelector(`tr[data-token="${token}"]`)?.remove();
      CGToast.success(d.message || 'Session revoked.');
    } else {
      CGToast.error(d.message || 'Failed to revoke session.');
    }
  });
}

function revokeAllSessions() {
  Swal.fire({
    title: 'Revoke All Sessions?',
    html: '<p style="color:#64748b">This will sign out every user (except you) from all devices. This cannot be undone.</p>',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, Revoke All',
    confirmButtonColor: '#ef4444',
    cancelButtonText: 'Cancel',
    reverseButtons: true
  }).then(async (r) => {
    if (!r.isConfirmed) return;
    const d = await cgFetch('/super-admin/security/sessions/revoke-all', { method: 'POST' });
    if (d.success) {
      CGToast.success(d.message || 'Sessions revoked.');
      setTimeout(() => location.reload(), 800);
    } else {
      CGToast.error(d.message || 'Failed to revoke sessions.');
    }
  });
}
</script>
