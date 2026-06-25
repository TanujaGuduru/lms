<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Departments</span>
    </div>
    <h1 class="page-title">Departments</h1>
    <p class="page-subtitle">Organize courses and users into departments</p>
  </div>
  <button onclick="openAddDept()" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Department</button>
</div>

<div class="row g-3">
  <!-- Department List -->
  <div class="col-xl-8">
    <div class="card">
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr><th>Department</th><th>Head</th><th>Courses</th><th>Students</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            <?php if (empty($departments)): ?>
            <tr><td colspan="6">
              <div class="empty-state">
                <i class="fas fa-building empty-state-icon"></i>
                <h4 class="empty-state-title">No Departments</h4>
                <p class="empty-state-desc">Add your first department to organize courses.</p>
              </div>
            </td></tr>
            <?php else: ?>
            <?php foreach ($departments as $dept): ?>
            <tr>
              <td>
                <div style="width:36px;height:36px;background:#6366f118;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;color:#6366f1;float:left;margin-right:10px">
                  <i class="fas fa-building"></i>
                </div>
                <div>
                  <div style="font-size:13.5px;font-weight:700;color:#0f172a"><?= \App\Core\View::e($dept['name']) ?></div>
                  <div style="font-size:12px;color:#94a3b8"><?= \App\Core\View::e($dept['slug']) ?></div>
                </div>
              </td>
              <td style="font-size:13px;color:#64748b"><?= $dept['head_name'] ? \App\Core\View::e($dept['head_name']) : '—' ?></td>
              <td style="font-size:13.5px;font-weight:600;color:#374151"><?= (int)$dept['courses_count'] ?></td>
              <td style="font-size:13.5px;font-weight:600;color:#374151"><?= (int)$dept['students_count'] ?></td>
              <td><?= \App\Core\View::badge($dept['is_active'] ? 'active' : 'inactive') ?></td>
              <td>
                <div class="d-flex gap-1">
                  <button onclick="openEditDept(<?= htmlspecialchars(json_encode($dept), ENT_QUOTES) ?>)" class="btn btn-ghost btn-sm btn-icon" title="Edit">
                    <i class="fas fa-edit"></i>
                  </button>
                  <?php if ((int)$dept['courses_count'] === 0): ?>
                  <button onclick="deleteDept(<?= $dept['id'] ?>, '<?= \App\Core\View::e($dept['name']) ?>')" class="btn btn-danger btn-sm btn-icon" title="Delete">
                    <i class="fas fa-trash"></i>
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
    </div>
  </div>

  <!-- Stats -->
  <div class="col-xl-4">
    <div class="card mb-3">
      <div class="card-header"><h3 class="card-title">Overview</h3></div>
      <div class="card-body">
        <?php
        $overview = [
          ['label'=>'Total Departments', 'value'=>count($departments)],
          ['label'=>'Active',            'value'=>count(array_filter($departments, fn($d) => $d['is_active']))],
          ['label'=>'Total Courses',     'value'=>array_sum(array_column($departments, 'courses_count'))],
        ];
        ?>
        <?php foreach ($overview as $ov): ?>
        <div class="d-flex justify-content-between py-3" style="border-bottom:1px solid #f1f5f9;font-size:13.5px">
          <span style="color:#94a3b8"><?= $ov['label'] ?></span>
          <span style="font-weight:700;color:#0f172a"><?= $ov['value'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="deptModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deptModalTitle">Add Department</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="deptForm" method="POST" action="/super-admin/departments">
        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="_method" id="deptMethod" value="">
        <input type="hidden" name="dept_id" id="deptId" value="">
        <div class="modal-body">
          <div class="form-group mb-3">
            <label class="form-label required">Name</label>
            <input type="text" name="name" id="deptName" class="form-control" required placeholder="e.g. Computer Science">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" id="deptDesc" class="form-control" rows="3" placeholder="Brief description…"></textarea>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <span style="font-size:13.5px;font-weight:600;color:#374151">Active</span>
            <label class="form-switch">
              <input type="checkbox" name="is_active" id="deptActive" value="1" checked>
              <span class="toggle-track"></span>
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="deptSubmitBtn">Add Department</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openAddDept() {
  document.getElementById('deptModalTitle').textContent = 'Add Department';
  document.getElementById('deptMethod').value  = '';
  document.getElementById('deptId').value      = '';
  document.getElementById('deptName').value    = '';
  document.getElementById('deptDesc').value    = '';
  document.getElementById('deptActive').checked = true;
  document.getElementById('deptForm').action   = '/super-admin/departments';
  document.getElementById('deptSubmitBtn').textContent = 'Add Department';
  new bootstrap.Modal(document.getElementById('deptModal')).show();
}

function openEditDept(dept) {
  document.getElementById('deptModalTitle').textContent = 'Edit Department';
  document.getElementById('deptMethod').value  = 'PUT';
  document.getElementById('deptId').value      = dept.id;
  document.getElementById('deptName').value    = dept.name;
  document.getElementById('deptDesc').value    = dept.description || '';
  document.getElementById('deptActive').checked = !!dept.is_active;
  document.getElementById('deptForm').action   = `/super-admin/departments/${dept.id}`;
  document.getElementById('deptSubmitBtn').textContent = 'Save Changes';
  new bootstrap.Modal(document.getElementById('deptModal')).show();
}

async function deleteDept(id, name) {
  const r = await Swal.fire({
    title: `Delete "${name}"?`, text: 'This cannot be undone.', icon: 'warning',
    showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Delete'
  });
  if (!r.isConfirmed) return;
  const d = await cgFetch(`/super-admin/departments/${id}`, { method: 'DELETE' });
  if (d.success) { CGToast.success('Department deleted'); setTimeout(() => location.reload(), 600); }
  else CGToast.error(d.message);
}
</script>
