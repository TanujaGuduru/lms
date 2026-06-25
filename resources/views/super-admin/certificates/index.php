<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Certificates</span>
    </div>
    <h1 class="page-title">Certificate Management</h1>
    <p class="page-subtitle">Issue, verify, and manage student achievement certificates</p>
  </div>
  <a href="/super-admin/certificates/templates" class="btn btn-primary btn-sm">
    <i class="fas fa-paint-brush"></i> Manage Templates
  </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php
  $cCards = [
    ['label'=>'Total Issued', 'value'=>$stats['total']??0,  'icon'=>'fas fa-certificate',   'color'=>'#6366f1'],
    ['label'=>'Active',       'value'=>$stats['active']??0, 'icon'=>'fas fa-check-circle',   'color'=>'#10b981'],
    ['label'=>'Revoked',      'value'=>$stats['revoked']??0,'icon'=>'fas fa-ban',            'color'=>'#ef4444'],
    ['label'=>'Today',        'value'=>$stats['today']??0,  'icon'=>'fas fa-star',           'color'=>'#f59e0b'],
  ];
  ?>
  <?php foreach ($cCards as $s): ?>
  <div class="col-xl-3 col-md-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:<?= $s['color'] ?>18;color:<?= $s['color'] ?>"><i class="<?= $s['icon'] ?>"></i></div>
      <div><div class="stat-mini-value"><?= number_format((int)$s['value']) ?></div><div class="stat-mini-label"><?= $s['label'] ?></div></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Certificates Table -->
<div class="card">
  <div class="card-header">
    <form method="GET" action="/super-admin/certificates" class="d-flex gap-2 flex-grow-1">
      <div class="table-search" style="margin:0;flex:1"><i class="fas fa-search search-icon"></i>
        <input type="text" name="search" placeholder="Search student, certificate number…" value="<?= \App\Core\View::e($filters['search'] ?? '') ?>">
      </div>
      <select name="course_id" class="form-select" style="width:200px;font-size:13px">
        <option value="">All Courses</option>
        <?php foreach ($courses as $c): ?>
        <option value="<?= $c['id'] ?>" <?= ($filters['course_id']??'') == $c['id'] ? 'selected' : '' ?>><?= \App\Core\View::e($c['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
    </form>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Student</th><th>Certificate #</th><th>Course</th><th>Issued At</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($certificates)): ?>
        <tr><td colspan="6">
          <div class="empty-state">
            <i class="fas fa-certificate empty-state-icon"></i>
            <h4 class="empty-state-title">No Certificates Yet</h4>
            <p class="empty-state-desc">Certificates are issued automatically when students complete courses.</p>
          </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($certificates as $cert): ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div style="width:32px;height:32px;background:#6366f118;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#6366f1;font-size:12px">
                <i class="fas fa-user"></i>
              </div>
              <div>
                <div style="font-size:13.5px;font-weight:600"><?= \App\Core\View::e($cert['student_name']) ?></div>
                <div style="font-size:11.5px;color:#94a3b8"><?= \App\Core\View::e($cert['student_email']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <code style="font-size:12px;background:#f1f5f9;padding:3px 8px;border-radius:6px;color:#6366f1">
              <?= \App\Core\View::e($cert['certificate_number']) ?>
            </code>
          </td>
          <td style="font-size:13px;color:#64748b;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <?= \App\Core\View::e($cert['course_title']) ?>
          </td>
          <td style="font-size:12.5px;color:#374151"><?= \App\Core\View::formatDate($cert['issued_at'], 'd M Y') ?></td>
          <td>
            <?php if ($cert['is_revoked']): ?>
            <span class="badge badge-soft-danger"><i class="fas fa-ban"></i> Revoked</span>
            <?php else: ?>
            <span class="badge badge-soft-success"><i class="fas fa-check"></i> Active</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="d-flex gap-1">
              <a href="/certificate/verify/<?= \App\Core\View::e($cert['verify_code']) ?>" target="_blank"
                 class="btn btn-ghost btn-sm btn-icon" title="Verify">
                <i class="fas fa-qrcode"></i>
              </a>
              <?php if (!$cert['is_revoked']): ?>
              <button onclick="revokeCertificate(<?= $cert['id'] ?>)"
                      class="btn btn-ghost btn-sm btn-icon" title="Revoke" style="color:#ef4444">
                <i class="fas fa-ban"></i>
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if (($meta['last_page'] ?? 1) > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <div style="font-size:13px;color:#64748b">Showing <?= $meta['from'] ?>–<?= $meta['to'] ?> of <?= number_format($meta['total']) ?></div>
    <nav><ul class="pagination" style="margin:0;gap:4px">
      <?php for ($p = max(1,$meta['current_page']-3); $p <= min($meta['last_page'],$meta['current_page']+3); $p++): ?>
      <li class="page-item <?= $p === $meta['current_page'] ? 'active' : '' ?>">
        <a class="page-link" href="?<?= http_build_query(array_merge($filters??[],['page'=>$p])) ?>"><?= $p ?></a>
      </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>

<script>
function revokeCertificate(id) {
  Swal.fire({
    title: 'Revoke Certificate?', icon: 'warning',
    input: 'text', inputLabel: 'Reason for revocation', inputPlaceholder: 'e.g. Fraudulent activity',
    showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Revoke'
  }).then(async r => {
    if (r.isConfirmed) {
      const d = await cgFetch(`/super-admin/certificates/${id}/revoke`, {
        method: 'POST',
        body: JSON.stringify({ reason: r.value })
      });
      if (d.success) { CGToast.success('Certificate revoked'); setTimeout(() => location.reload(), 800); }
      else CGToast.error(d.message);
    }
  });
}
</script>
