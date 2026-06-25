<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/security">Security Center</a>
      <span class="sep">/</span><span>IP Restrictions</span>
    </div>
    <h1 class="page-title">IP Restrictions</h1>
    <p class="page-subtitle">Whitelist or blacklist IP addresses to control platform access</p>
  </div>
  <button onclick="openAddIpRule()" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add IP Rule</button>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php
  $wlCount = count(array_filter($ips, fn($i) => $i['type'] === 'whitelist'));
  $blCount = count(array_filter($ips, fn($i) => $i['type'] === 'blacklist'));
  $activeCount = count(array_filter($ips, fn($i) => (int)$i['is_active'] === 1));
  ?>
  <div class="col-xl col-md-4 col-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:#6366f118;color:#6366f1"><i class="fas fa-list"></i></div>
      <div><div class="stat-mini-value"><?= number_format(count($ips)) ?></div><div class="stat-mini-label">Total Rules</div></div>
    </div>
  </div>
  <div class="col-xl col-md-4 col-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:#10b98118;color:#10b981"><i class="fas fa-check-circle"></i></div>
      <div><div class="stat-mini-value"><?= number_format($wlCount) ?></div><div class="stat-mini-label">Whitelisted</div></div>
    </div>
  </div>
  <div class="col-xl col-md-4 col-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:#ef444418;color:#ef4444"><i class="fas fa-ban"></i></div>
      <div><div class="stat-mini-value"><?= number_format($blCount) ?></div><div class="stat-mini-label">Blacklisted</div></div>
    </div>
  </div>
  <div class="col-xl col-md-4 col-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:#f59e0b18;color:#f59e0b"><i class="fas fa-bolt"></i></div>
      <div><div class="stat-mini-value"><?= number_format($activeCount) ?></div><div class="stat-mini-label">Active Rules</div></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-shield-alt" style="color:#6366f1"></i> IP Rules</h3>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>IP Address</th><th>Type</th><th>Reason</th><th>Status</th><th>Added</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (empty($ips)): ?>
        <tr><td colspan="6">
          <div class="empty-state">
            <i class="fas fa-shield-alt empty-state-icon"></i>
            <h4 class="empty-state-title">No IP Rules</h4>
            <p class="empty-state-desc">Add a whitelist or blacklist rule to control IP access.</p>
            <button onclick="openAddIpRule()" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add IP Rule</button>
          </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($ips as $ip): ?>
        <tr data-id="<?= (int)$ip['id'] ?>">
          <td style="font-family:monospace;font-size:13px;color:#374151;font-weight:600"><?= \App\Core\View::e($ip['ip_address']) ?></td>
          <td>
            <?php if ($ip['type'] === 'whitelist'): ?>
            <span class="badge badge-soft-success"><i class="fas fa-check"></i> Whitelist</span>
            <?php else: ?>
            <span class="badge badge-soft-danger"><i class="fas fa-ban"></i> Blacklist</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12.5px;color:#64748b"><?= \App\Core\View::e($ip['reason'] ?: '—') ?></td>
          <td><?= \App\Core\View::badge($ip['is_active'] ? 'active' : 'inactive') ?></td>
          <td style="font-size:12px;color:#94a3b8"><?= \App\Core\View::timeAgo($ip['created_at']) ?></td>
          <td>
            <button onclick="removeIpRule(<?= (int)$ip['id'] ?>)" class="btn btn-ghost btn-sm btn-icon" title="Remove Rule">
              <i class="fas fa-trash" style="color:#ef4444"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add IP Rule Modal -->
<div class="modal fade" id="ipRuleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add IP Rule</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="ipRuleForm" method="POST" action="/super-admin/security/ip-restrictions/store">
        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <div class="form-group">
                <label class="form-label required">IP Address</label>
                <input type="text" name="ip_address" class="form-control" placeholder="e.g. 192.168.1.1 or 10.0.0.0/24" required>
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label class="form-label required">Type</label>
                <select name="type" class="form-select" required>
                  <option value="whitelist">Whitelist (always allow)</option>
                  <option value="blacklist">Blacklist (always block)</option>
                </select>
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label class="form-label">Reason</label>
                <textarea name="reason" class="form-control" rows="2" placeholder="Optional note about this rule"></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Rule</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openAddIpRule() {
  document.getElementById('ipRuleForm').reset();
  new bootstrap.Modal(document.getElementById('ipRuleModal')).show();
}

function removeIpRule(id) {
  Swal.fire({
    title: 'Remove IP Rule?',
    html: '<p style="color:#64748b">This rule will no longer apply to incoming requests.</p>',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, Remove',
    confirmButtonColor: '#ef4444',
    cancelButtonText: 'Cancel',
    reverseButtons: true
  }).then(async (r) => {
    if (!r.isConfirmed) return;
    const d = await cgFetch(`/super-admin/security/ip-restrictions/${id}/delete`, { method: 'POST' });
    if (d.success) {
      document.querySelector(`tr[data-id="${id}"]`)?.remove();
      CGToast.success(d.message || 'IP rule removed.');
    } else {
      CGToast.error(d.message || 'Failed to remove rule.');
    }
  });
}
</script>
