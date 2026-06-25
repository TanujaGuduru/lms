<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/placement">Placement</a>
      <span class="sep">/</span><span>Companies</span>
    </div>
    <h1 class="page-title">Partner Companies</h1>
    <p class="page-subtitle">Manage hiring companies and their profiles</p>
  </div>
  <button onclick="openAddCompany()" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Company</button>
</div>

<div class="row g-3">
  <?php if (empty($companies)): ?>
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <div class="empty-state" style="padding:60px">
          <i class="fas fa-building empty-state-icon"></i>
          <h4 class="empty-state-title">No Companies Yet</h4>
          <p class="empty-state-desc">Add partner companies to enable placement tracking.</p>
          <button onclick="openAddCompany()" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Company</button>
        </div>
      </div>
    </div>
  </div>
  <?php else: ?>
  <?php foreach ($companies as $c): ?>
  <div class="col-xl-3 col-md-6">
    <div class="card" style="transition:all .2s" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,.08)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
      <div class="card-body text-center" style="padding:24px">
        <div style="width:64px;height:64px;border-radius:16px;overflow:hidden;margin:0 auto 12px;border:2px solid #e2e8f0">
          <?php if ($c['logo']): ?>
          <img src="<?= \App\Core\View::e($c['logo']) ?>" style="width:100%;height:100%;object-fit:contain">
          <?php else: ?>
          <div style="width:100%;height:100%;background:#6366f118;display:flex;align-items:center;justify-content:center;font-size:22px;color:#6366f1">
            <i class="fas fa-building"></i>
          </div>
          <?php endif; ?>
        </div>

        <div style="font-size:15px;font-weight:700;color:#0f172a;margin-bottom:4px"><?= \App\Core\View::e($c['name']) ?></div>
        <div style="font-size:12.5px;color:#94a3b8;margin-bottom:12px"><?= \App\Core\View::e($c['industry'] ?? '') ?></div>

        <div class="row g-2 text-center mb-3" style="font-size:13px">
          <div class="col-6" style="padding:8px;background:#f8fafc;border-radius:8px">
            <div style="font-weight:700;color:#374151"><?= (int)($c['open_jobs'] ?? 0) ?></div>
            <div style="font-size:11px;color:#94a3b8">Open Jobs</div>
          </div>
          <div class="col-6" style="padding:8px;background:#f8fafc;border-radius:8px">
            <div style="font-weight:700;color:#374151"><?= (int)($c['placed_count'] ?? 0) ?></div>
            <div style="font-size:11px;color:#94a3b8">Placements</div>
          </div>
        </div>

        <?= \App\Core\View::badge($c['is_active'] ? 'active' : 'inactive') ?>

        <div class="d-flex gap-2 mt-3">
          <a href="/super-admin/placement/jobs?company_id=<?= $c['id'] ?>" class="btn btn-primary btn-sm flex-fill">View Jobs</a>
          <button onclick="openEditCompany(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)" class="btn btn-ghost btn-sm btn-icon"><i class="fas fa-edit"></i></button>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Add/Edit Company Modal -->
<div class="modal fade" id="companyModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="compModalTitle">Add Company</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="companyForm" method="POST" action="/super-admin/placement/companies" enctype="multipart/form-data">
        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="_method" id="compMethod" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label required">Company Name</label>
                <input type="text" name="name" id="compName" class="form-control" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Industry</label>
                <input type="text" name="industry" id="compIndustry" class="form-control" placeholder="e.g. Technology, Finance">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Website</label>
                <input type="url" name="website" id="compWebsite" class="form-control" placeholder="https://…">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Logo</label>
                <input type="file" name="logo" class="form-control" accept="image/*">
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label class="form-label">About</label>
                <textarea name="about" id="compAbout" class="form-control" rows="3"></textarea>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">HR Contact Email</label>
                <input type="email" name="hr_email" id="compHr" class="form-control">
              </div>
            </div>
            <div class="col-md-6">
              <div class="d-flex justify-content-between align-items-center pt-4">
                <span style="font-size:13.5px;color:#374151">Active Partner</span>
                <label class="form-switch">
                  <input type="checkbox" name="is_active" id="compActive" value="1" checked>
                  <span class="toggle-track"></span>
                </label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="compSubmit">Add Company</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openAddCompany() {
  document.getElementById('compModalTitle').textContent = 'Add Company';
  document.getElementById('compMethod').value = '';
  document.getElementById('companyForm').action = '/super-admin/placement/companies';
  document.getElementById('compSubmit').textContent = 'Add Company';
  ['compName','compIndustry','compWebsite','compAbout','compHr'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('compActive').checked = true;
  new bootstrap.Modal(document.getElementById('companyModal')).show();
}

function openEditCompany(c) {
  document.getElementById('compModalTitle').textContent = 'Edit Company';
  document.getElementById('compMethod').value = 'PUT';
  document.getElementById('companyForm').action = `/super-admin/placement/companies/${c.id}`;
  document.getElementById('compName').value     = c.name;
  document.getElementById('compIndustry').value = c.industry || '';
  document.getElementById('compWebsite').value  = c.website || '';
  document.getElementById('compAbout').value    = c.about || '';
  document.getElementById('compHr').value       = c.hr_email || '';
  document.getElementById('compActive').checked = !!c.is_active;
  document.getElementById('compSubmit').textContent = 'Save Changes';
  new bootstrap.Modal(document.getElementById('companyModal')).show();
}
</script>
