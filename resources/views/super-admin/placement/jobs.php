<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/placement">Placement</a>
      <span class="sep">/</span><span>Job Openings</span>
    </div>
    <h1 class="page-title">Job Openings</h1>
    <p class="page-subtitle">Manage all available job positions from partner companies</p>
  </div>
  <button onclick="openAddJob()" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Post Job</button>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="d-flex gap-2">
      <div class="table-search" style="margin:0;flex:1"><i class="fas fa-search search-icon"></i>
        <input type="text" name="search" placeholder="Search jobs…" value="<?= \App\Core\View::e($filters['search'] ?? '') ?>">
      </div>
      <select name="company_id" class="form-select" style="width:200px;font-size:13px">
        <option value="">All Companies</option>
        <?php foreach ($companies as $c): ?>
        <option value="<?= $c['id'] ?>" <?= ($filters['company_id']??'') == $c['id'] ? 'selected' : '' ?>><?= \App\Core\View::e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="form-select" style="width:140px;font-size:13px">
        <option value="">All Status</option>
        <option value="open">Open</option>
        <option value="closed">Closed</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Job Title</th><th>Company</th><th>Location</th><th>Type</th><th>Salary</th><th>Applications</th><th>Deadline</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (empty($jobs)): ?>
        <tr><td colspan="9">
          <div class="empty-state">
            <i class="fas fa-briefcase empty-state-icon"></i>
            <h4 class="empty-state-title">No Jobs Posted</h4>
            <p class="empty-state-desc">Post the first job opening to start accepting applications.</p>
          </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($jobs as $job):
          $isExpired = $job['deadline'] && strtotime($job['deadline']) < time();
        ?>
        <tr>
          <td>
            <div style="font-size:13.5px;font-weight:700;color:#0f172a"><?= \App\Core\View::e($job['title']) ?></div>
            <div style="font-size:12px;color:#94a3b8"><?= \App\Core\View::e($job['department'] ?? '') ?></div>
          </td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div style="width:28px;height:28px;background:#f1f5f9;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#6366f1">
                <i class="fas fa-building"></i>
              </div>
              <span style="font-size:13px"><?= \App\Core\View::e($job['company_name']) ?></span>
            </div>
          </td>
          <td style="font-size:13px;color:#64748b"><?= \App\Core\View::e($job['location'] ?? 'Remote') ?></td>
          <td>
            <span class="badge" style="background:#6366f118;color:#6366f1;text-transform:capitalize"><?= $job['job_type'] ?? 'Full-time' ?></span>
          </td>
          <td style="font-size:13px;color:#374151">
            <?php if ($job['salary_min'] || $job['salary_max']): ?>
            ₹<?= number_format((int)$job['salary_min'] / 100000, 1) ?>L – ₹<?= number_format((int)$job['salary_max'] / 100000, 1) ?>L
            <?php else: ?>
            <span style="color:#94a3b8">—</span>
            <?php endif; ?>
          </td>
          <td>
            <a href="/super-admin/placement/applications?job_id=<?= $job['id'] ?>" style="font-size:14px;font-weight:700;color:#6366f1">
              <?= (int)$job['application_count'] ?>
            </a>
          </td>
          <td style="font-size:12px;color:<?= $isExpired ? '#ef4444' : '#94a3b8' ?>">
            <?= $job['deadline'] ? \App\Core\View::formatDate($job['deadline'], 'd M Y') : '—' ?>
          </td>
          <td>
            <?php $statusColor = $job['status'] === 'open' ? '#10b981' : '#94a3b8'; ?>
            <span class="badge" style="background:<?= $statusColor ?>18;color:<?= $statusColor ?>"><?= ucfirst($job['status'] ?? 'open') ?></span>
          </td>
          <td>
            <button onclick="openEditJob(<?= htmlspecialchars(json_encode($job), ENT_QUOTES) ?>)" class="btn btn-ghost btn-sm btn-icon">
              <i class="fas fa-edit"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Job Modal -->
<div class="modal fade" id="jobModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="jobModalTitle">Post Job</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="jobForm" method="POST" action="/super-admin/placement/jobs">
        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="_method" id="jobMethod" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label required">Job Title</label>
              <input type="text" name="title" id="jobTitle" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label required">Company</label>
              <select name="company_id" id="jobCompany" class="form-select" required>
                <option value="">Select…</option>
                <?php foreach ($companies as $c): ?>
                <option value="<?= $c['id'] ?>"><?= \App\Core\View::e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Location</label>
              <input type="text" name="location" id="jobLoc" class="form-control" placeholder="City or Remote">
            </div>
            <div class="col-md-6">
              <label class="form-label">Job Type</label>
              <select name="job_type" id="jobType" class="form-select">
                <option value="full_time">Full-time</option>
                <option value="part_time">Part-time</option>
                <option value="internship">Internship</option>
                <option value="contract">Contract</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Min Salary (₹/year)</label>
              <input type="number" name="salary_min" id="jobSalMin" class="form-control" min="0" step="10000">
            </div>
            <div class="col-md-4">
              <label class="form-label">Max Salary (₹/year)</label>
              <input type="number" name="salary_max" id="jobSalMax" class="form-control" min="0" step="10000">
            </div>
            <div class="col-md-4">
              <label class="form-label">Application Deadline</label>
              <input type="date" name="deadline" id="jobDeadline" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea name="description" id="jobDesc" class="form-control" rows="5"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="jobSubmit">Post Job</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openAddJob() {
  document.getElementById('jobModalTitle').textContent = 'Post Job';
  document.getElementById('jobMethod').value = '';
  document.getElementById('jobForm').action  = '/super-admin/placement/jobs';
  document.getElementById('jobSubmit').textContent = 'Post Job';
  ['jobTitle','jobLoc','jobDesc','jobSalMin','jobSalMax','jobDeadline'].forEach(id => document.getElementById(id).value = '');
  new bootstrap.Modal(document.getElementById('jobModal')).show();
}

function openEditJob(job) {
  document.getElementById('jobModalTitle').textContent = 'Edit Job';
  document.getElementById('jobMethod').value = 'PUT';
  document.getElementById('jobForm').action  = `/super-admin/placement/jobs/${job.id}`;
  document.getElementById('jobTitle').value    = job.title;
  document.getElementById('jobCompany').value  = job.company_id;
  document.getElementById('jobLoc').value      = job.location || '';
  document.getElementById('jobType').value     = job.job_type || 'full_time';
  document.getElementById('jobSalMin').value   = job.salary_min || '';
  document.getElementById('jobSalMax').value   = job.salary_max || '';
  document.getElementById('jobDeadline').value = job.deadline || '';
  document.getElementById('jobDesc').value     = job.description || '';
  document.getElementById('jobSubmit').textContent = 'Save Changes';
  new bootstrap.Modal(document.getElementById('jobModal')).show();
}
</script>
