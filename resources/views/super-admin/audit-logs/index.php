<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Audit Logs</span>
    </div>
    <h1 class="page-title">Audit Logs</h1>
    <p class="page-subtitle">Complete trail of every significant action on the platform</p>
  </div>
  <a href="/super-admin/audit-logs/export?<?= http_build_query($filters ?? []) ?>" class="btn btn-secondary btn-sm">
    <i class="fas fa-download"></i> Export CSV
  </a>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" action="/super-admin/audit-logs" class="row g-2 align-items-end">
      <div class="col-md-3">
        <div class="table-search" style="margin:0"><i class="fas fa-search search-icon"></i>
          <input type="text" name="search" placeholder="Search action, module, user…" value="<?= \App\Core\View::e($filters['search'] ?? '') ?>">
        </div>
      </div>
      <div class="col-md-2">
        <select name="module" class="form-select" style="font-size:13px">
          <option value="">All Modules</option>
          <?php foreach ($modules as $m): ?>
          <option value="<?= \App\Core\View::e($m['module']) ?>" <?= ($filters['module'] ?? '') === $m['module'] ? 'selected' : '' ?>>
            <?= ucfirst(\App\Core\View::e($m['module'])) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="action" class="form-select" style="font-size:13px">
          <option value="">All Actions</option>
          <?php foreach ($actions as $a): ?>
          <option value="<?= \App\Core\View::e($a['action']) ?>" <?= ($filters['action'] ?? '') === $a['action'] ? 'selected' : '' ?>>
            <?= str_replace('_', ' ', ucfirst(\App\Core\View::e($a['action']))) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <input type="date" name="date_from" class="form-control" value="<?= \App\Core\View::e($filters['date_from'] ?? '') ?>" placeholder="From">
      </div>
      <div class="col-md-2">
        <input type="date" name="date_to" class="form-control" value="<?= \App\Core\View::e($filters['date_to'] ?? '') ?>" placeholder="To">
      </div>
      <div class="col-md-1">
        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i></button>
      </div>
    </form>
  </div>
</div>

<!-- Audit Log Table -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-history" style="color:#6366f1"></i> Activity Log</h3>
    <span class="badge badge-soft-secondary"><?= number_format($meta['total'] ?? 0) ?> records</span>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th>User</th>
          <th>Action</th>
          <th>Module</th>
          <th>Record</th>
          <th>IP Address</th>
          <th>When</th>
          <th style="width:40px"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
        <tr>
          <td colspan="8">
            <div class="empty-state">
              <i class="fas fa-history empty-state-icon"></i>
              <h4 class="empty-state-title">No Logs Found</h4>
              <p class="empty-state-desc">No audit log entries match your filters.</p>
            </div>
          </td>
        </tr>
        <?php else: ?>
        <?php
        $actionColors = [
          'login'       => '#10b981', 'login_failed' => '#ef4444',
          'logout'      => '#64748b', 'created'      => '#6366f1',
          'updated'     => '#f59e0b', 'deleted'      => '#ef4444',
          'exported'    => '#06b6d4', 'imported'     => '#8b5cf6',
          'published'   => '#10b981', 'settings_updated' => '#ec4899',
          'role_created'=> '#6366f1', 'permissions_updated' => '#f59e0b',
          'bulk_action' => '#0891b2', 'password_reset' => '#ef4444',
        ];
        $actionIcons = [
          'login'       => 'fas fa-sign-in-alt', 'login_failed' => 'fas fa-ban',
          'logout'      => 'fas fa-sign-out-alt','created'      => 'fas fa-plus',
          'updated'     => 'fas fa-pen',         'deleted'      => 'fas fa-trash',
          'exported'    => 'fas fa-download',    'imported'     => 'fas fa-upload',
          'published'   => 'fas fa-globe',       'settings_updated' => 'fas fa-cog',
          'role_created'=> 'fas fa-user-shield', 'permissions_updated' => 'fas fa-shield-alt',
          'bulk_action' => 'fas fa-tasks',       'password_reset' => 'fas fa-key',
        ];
        foreach ($logs as $i => $log):
          $ac = $actionColors[$log['action']] ?? '#94a3b8';
          $ai = $actionIcons[$log['action']]  ?? 'fas fa-circle';
          $old = $log['old_values'] ? json_decode($log['old_values'], true) : null;
          $new = $log['new_values'] ? json_decode($log['new_values'], true) : null;
        ?>
        <tr>
          <td style="color:#94a3b8;font-size:12px"><?= ($meta['from'] ?? 0) + $i ?></td>

          <!-- User -->
          <td>
            <div class="d-flex align-items-center gap-2">
              <div style="width:30px;height:30px;background:#6366f118;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#6366f1;font-size:12px;flex-shrink:0">
                <i class="fas fa-user"></i>
              </div>
              <div>
                <div style="font-size:13px;font-weight:600;color:#374151"><?= \App\Core\View::e($log['user_name'] ?? 'System') ?></div>
                <div style="font-size:11px;color:#94a3b8"><?= \App\Core\View::e($log['user_email'] ?? '') ?></div>
              </div>
            </div>
          </td>

          <!-- Action -->
          <td>
            <span class="badge" style="background:<?= $ac ?>18;color:<?= $ac ?>;font-size:12px">
              <i class="<?= $ai ?>" style="font-size:10px"></i>
              <?= str_replace('_', ' ', ucfirst(\App\Core\View::e($log['action']))) ?>
            </span>
          </td>

          <!-- Module -->
          <td>
            <span style="font-size:13px;color:#64748b;text-transform:capitalize"><?= \App\Core\View::e($log['module']) ?></span>
          </td>

          <!-- Record -->
          <td>
            <?php if ($log['record_id']): ?>
            <code style="font-size:11.5px;background:#f1f5f9;padding:2px 6px;border-radius:4px;color:#64748b"><?= \App\Core\View::e($log['record_id']) ?></code>
            <?php else: ?>
            <span style="color:#94a3b8">—</span>
            <?php endif; ?>
          </td>

          <!-- IP -->
          <td style="font-family:monospace;font-size:12px;color:#64748b"><?= \App\Core\View::e($log['ip_address'] ?? '—') ?></td>

          <!-- Time -->
          <td>
            <div style="font-size:12.5px;color:#374151"><?= \App\Core\View::formatDate($log['created_at'], 'd M Y') ?></div>
            <div style="font-size:11px;color:#94a3b8"><?= \App\Core\View::formatDate($log['created_at'], 'H:i:s') ?></div>
          </td>

          <!-- Detail -->
          <td>
            <?php if ($old || $new): ?>
            <button type="button" onclick="showLogDetail(<?= $i ?>)"
                    class="btn btn-ghost btn-sm btn-icon" title="View Changes">
              <i class="fas fa-eye"></i>
            </button>
            <script>
            window.__logDetail = window.__logDetail || {};
            window.__logDetail[<?= $i ?>] = {
              action: '<?= \App\Core\View::e($log['action']) ?>',
              old: <?= json_encode($old) ?>,
              new_val: <?= json_encode($new) ?>
            };
            </script>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if (($meta['last_page'] ?? 1) > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <div style="font-size:13px;color:#64748b">
      Showing <?= $meta['from'] ?>–<?= $meta['to'] ?> of <?= number_format($meta['total']) ?> entries
    </div>
    <nav>
      <ul class="pagination" style="margin:0;gap:4px">
        <?php for ($p = max(1, ($meta['current_page']-3)); $p <= min($meta['last_page'], ($meta['current_page']+3)); $p++): ?>
        <li class="page-item <?= $p === $meta['current_page'] ? 'active' : '' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($filters ?? [], ['page' => $p])) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
      </ul>
    </nav>
  </div>
  <?php endif; ?>
</div>

<!-- Change Detail Modal -->
<div class="modal fade" id="logDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-code-branch" style="color:#6366f1"></i> Change Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="logDetailBody">Loading…</div>
    </div>
  </div>
</div>

<script>
function showLogDetail(idx) {
  const d = window.__logDetail?.[idx];
  if (!d) return;

  let html = `<div class="mb-2"><span class="badge badge-soft-primary">${d.action.replace(/_/g,' ')}</span></div>`;

  if (d.old && d.new_val) {
    html += '<div class="row g-3">';
    html += `<div class="col-md-6"><div style="font-size:12px;font-weight:700;color:#ef4444;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px"><i class="fas fa-minus-circle"></i> Before</div><pre style="font-size:12px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px;overflow:auto;max-height:300px">${JSON.stringify(d.old, null, 2)}</pre></div>`;
    html += `<div class="col-md-6"><div style="font-size:12px;font-weight:700;color:#10b981;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px"><i class="fas fa-plus-circle"></i> After</div><pre style="font-size:12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;overflow:auto;max-height:300px">${JSON.stringify(d.new_val, null, 2)}</pre></div>`;
    html += '</div>';
  } else if (d.new_val) {
    html += `<pre style="font-size:12px;background:#f8fafc;border-radius:8px;padding:12px">${JSON.stringify(d.new_val, null, 2)}</pre>`;
  } else if (d.old) {
    html += `<pre style="font-size:12px;background:#fef2f2;border-radius:8px;padding:12px">${JSON.stringify(d.old, null, 2)}</pre>`;
  }

  document.getElementById('logDetailBody').innerHTML = html;
  new bootstrap.Modal(document.getElementById('logDetailModal')).show();
}
</script>
