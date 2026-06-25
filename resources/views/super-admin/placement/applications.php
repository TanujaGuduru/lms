<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/placement">Placement</a>
      <span class="sep">/</span><span>Applications</span>
    </div>
    <h1 class="page-title">Job Applications</h1>
    <p class="page-subtitle">Track and manage all student job applications</p>
  </div>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2">
      <div class="col-md-4">
        <div class="table-search" style="margin:0"><i class="fas fa-search search-icon"></i>
          <input type="text" name="search" placeholder="Search applicant, job…" value="<?= \App\Core\View::e($filters['search'] ?? '') ?>">
        </div>
      </div>
      <div class="col-md-3">
        <select name="status" class="form-select" style="font-size:13px">
          <option value="">All Status</option>
          <?php foreach (['applied','shortlisted','interview','selected','rejected','offered'] as $s): ?>
          <option value="<?= $s ?>" <?= ($filters['status']??'') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <select name="company_id" class="form-select" style="font-size:13px">
          <option value="">All Companies</option>
          <?php foreach ($companies ?? [] as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($filters['company_id']??'') == $c['id'] ? 'selected' : '' ?>><?= \App\Core\View::e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Student</th><th>Job Title</th><th>Company</th><th>Applied</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($applications)): ?>
        <tr><td colspan="6">
          <div class="empty-state">
            <i class="fas fa-file-alt empty-state-icon"></i>
            <h4 class="empty-state-title">No Applications</h4>
            <p class="empty-state-desc">No job applications match your filters.</p>
          </div>
        </td></tr>
        <?php else: ?>
        <?php
        $statusColors = ['applied'=>'#6366f1','shortlisted'=>'#3b82f6','interview'=>'#f59e0b','selected'=>'#10b981','rejected'=>'#ef4444','offered'=>'#8b5cf6'];
        foreach ($applications as $app):
          $sc = $statusColors[$app['status']] ?? '#94a3b8';
        ?>
        <tr>
          <td>
            <div style="font-size:13.5px;font-weight:600;color:#0f172a"><?= \App\Core\View::e($app['student_name']) ?></div>
            <div style="font-size:12px;color:#94a3b8"><?= \App\Core\View::e($app['student_email']) ?></div>
          </td>
          <td style="font-size:13.5px;font-weight:600;color:#374151"><?= \App\Core\View::e($app['job_title']) ?></td>
          <td style="font-size:13px;color:#64748b"><?= \App\Core\View::e($app['company_name']) ?></td>
          <td style="font-size:12.5px;color:#94a3b8"><?= \App\Core\View::timeAgo($app['applied_at'] ?? $app['created_at']) ?></td>
          <td>
            <span class="badge" style="background:<?= $sc ?>18;color:<?= $sc ?>;text-transform:capitalize">
              <?= $app['status'] ?>
            </span>
          </td>
          <td>
            <select onchange="updateStatus(<?= $app['id'] ?>, this.value)" class="form-select" style="width:140px;font-size:12px;padding:4px 8px">
              <?php foreach (array_keys($statusColors) as $s): ?>
              <option value="<?= $s ?>" <?= $app['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
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
async function updateStatus(id, status) {
  const d = await cgFetch(`/super-admin/placement/applications/${id}/status`, {
    method: 'POST',
    body: JSON.stringify({ status })
  });
  if (d.success) CGToast.success('Status updated');
  else CGToast.error(d.message || 'Failed to update');
}
</script>
