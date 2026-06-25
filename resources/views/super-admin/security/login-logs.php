<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/security">Security Center</a>
      <span class="sep">/</span><span>Login Logs</span>
    </div>
    <h1 class="page-title">Login Logs</h1>
    <p class="page-subtitle">Track successful and failed sign-in attempts across the platform</p>
  </div>
</div>

<!-- Filter Tabs -->
<div class="card">
  <div class="card-body py-3">
    <div class="d-flex gap-2">
      <a href="/super-admin/security/login-logs" class="btn btn-sm <?= $filter === '' ? 'btn-primary' : 'btn-secondary' ?>">All</a>
      <a href="/super-admin/security/login-logs?filter=login" class="btn btn-sm <?= $filter === 'login' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-check"></i> Successful</a>
      <a href="/super-admin/security/login-logs?filter=login_failed" class="btn btn-sm <?= $filter === 'login_failed' ? 'btn-primary' : 'btn-secondary' ?>"><i class="fas fa-times"></i> Failed</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>User</th><th>Result</th><th>IP Address</th><th>Time</th></tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
        <tr><td colspan="4">
          <div class="empty-state">
            <i class="fas fa-sign-in-alt empty-state-icon"></i>
            <h4 class="empty-state-title">No Login Activity</h4>
            <p class="empty-state-desc">No login records match this filter.</p>
          </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($logs as $lg): ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <?php if (!empty($lg['avatar'])): ?>
              <img src="<?= \App\Core\View::e($lg['avatar']) ?>" style="width:28px;height:28px;border-radius:6px;object-fit:cover">
              <?php else: ?>
              <div style="width:28px;height:28px;background:#6366f118;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#6366f1;font-size:11px"><i class="fas fa-user"></i></div>
              <?php endif; ?>
              <span style="font-size:13px;font-weight:500;color:#0f172a"><?= \App\Core\View::e($lg['user_name'] ?? 'Unknown') ?></span>
            </div>
          </td>
          <td>
            <?php if ($lg['action'] === 'login'): ?>
            <span class="badge badge-soft-success"><i class="fas fa-check"></i> Success</span>
            <?php else: ?>
            <span class="badge badge-soft-danger"><i class="fas fa-times"></i> Failed</span>
            <?php endif; ?>
          </td>
          <td style="font-family:monospace;font-size:12px;color:#64748b"><?= \App\Core\View::e($lg['ip_address'] ?? '—') ?></td>
          <td style="font-size:12px;color:#94a3b8"><?= \App\Core\View::timeAgo($lg['created_at']) ?></td>
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
        <a class="page-link" href="?<?= http_build_query(array_filter(['filter' => $filter, 'page' => $p])) ?>"><?= $p ?></a>
      </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
