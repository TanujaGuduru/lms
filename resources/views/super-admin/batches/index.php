<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Batches</span>
    </div>
    <h1 class="page-title">Batch Management</h1>
    <p class="page-subtitle">Organize students and teachers into learning batches</p>
  </div>
  <button onclick="openCreateBatchModal()" class="btn btn-primary btn-sm">
    <i class="fas fa-plus"></i> Create Batch
  </button>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php
  $statItems = [
    ['label'=>'Total Batches',  'value'=>$stats['total']??0,          'icon'=>'fas fa-layer-group',  'color'=>'#6366f1'],
    ['label'=>'Active',         'value'=>$stats['active']??0,         'icon'=>'fas fa-play-circle',  'color'=>'#10b981'],
    ['label'=>'Upcoming',       'value'=>$stats['upcoming']??0,       'icon'=>'fas fa-clock',        'color'=>'#f59e0b'],
    ['label'=>'Completed',      'value'=>$stats['completed']??0,      'icon'=>'fas fa-check-circle', 'color'=>'#3b82f6'],
    ['label'=>'Total Students', 'value'=>$stats['total_students']??0, 'icon'=>'fas fa-users',        'color'=>'#8b5cf6'],
  ];
  ?>
  <?php foreach ($statItems as $s): ?>
  <div class="col-xl col-md-4 col-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:<?= $s['color'] ?>18;color:<?= $s['color'] ?>"><i class="<?= $s['icon'] ?>"></i></div>
      <div><div class="stat-mini-value"><?= number_format((int)$s['value']) ?></div><div class="stat-mini-label"><?= $s['label'] ?></div></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-3">
    <div class="d-flex gap-3 flex-wrap align-items-center">
      <div class="table-search">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="batchSearch" placeholder="Search batch name, code…" value="<?= \App\Core\View::e($filters['search'] ?? '') ?>">
      </div>
      <select id="statusFilter" class="form-select" style="width:130px;font-size:13px">
        <option value="">All Status</option>
        <?php foreach (['active','upcoming','completed','cancelled'] as $st): ?>
        <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="modeFilter" class="form-select" style="width:130px;font-size:13px">
        <option value="">All Modes</option>
        <?php foreach (['online','offline','hybrid'] as $md): ?>
        <option value="<?= $md ?>" <?= ($filters['mode'] ?? '') === $md ? 'selected' : '' ?>><?= ucfirst($md) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</div>

<!-- Batch Table -->
<div class="table-container">
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Batch</th>
          <th>Course</th>
          <th>Mode</th>
          <th>Students</th>
          <th>Schedule</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        foreach ($batches as $i => $b):
          $modeColors = ['online'=>'#3b82f6','offline'=>'#f59e0b','hybrid'=>'#8b5cf6'];
          $mc = $modeColors[$b['mode']] ?? '#64748b';
          $pct = $b['max_students'] > 0 ? min(100, round($b['student_count'] / $b['max_students'] * 100)) : 0;
        ?>
        <tr>
          <td style="color:#94a3b8;font-size:12px"><?= $i+1 ?></td>
          <td>
            <div>
              <div style="font-weight:600;font-size:13.5px;color:#0f172a"><?= \App\Core\View::e($b['name']) ?></div>
              <div style="font-size:11.5px;color:#94a3b8;font-family:monospace"><?= \App\Core\View::e($b['code']) ?></div>
            </div>
          </td>
          <td>
            <div style="font-size:13px;color:#374151;font-weight:500;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= \App\Core\View::e($b['course_title'] ?? '—') ?>
            </div>
          </td>
          <td>
            <span class="badge" style="background:<?= $mc ?>18;color:<?= $mc ?>;text-transform:capitalize"><?= $b['mode'] ?></span>
          </td>
          <td>
            <div style="font-size:13px;color:#374151;margin-bottom:4px"><?= (int)$b['student_count'] ?> / <?= (int)$b['max_students'] ?></div>
            <div class="progress-bar-custom" style="width:80px">
              <div class="bar" style="width:<?= $pct ?>%;background:<?= $pct >= 90 ? '#ef4444' : '#6366f1' ?>"></div>
            </div>
          </td>
          <td>
            <div style="font-size:12.5px;color:#374151"><?= \App\Core\View::formatDate($b['start_date'], 'd M Y') ?></div>
            <?php if ($b['time_slot']): ?>
            <div style="font-size:11.5px;color:#94a3b8"><?= \App\Core\View::e($b['time_slot']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= \App\Core\View::badge($b['status']) ?></td>
          <td>
            <div class="d-flex gap-1">
              <a href="/super-admin/batches/<?= $b['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="View"><i class="fas fa-eye"></i></a>
              <button onclick='openEditBatchModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES) ?>)' class="btn btn-ghost btn-sm btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
              <a href="/super-admin/batches/<?= $b['id'] ?>/attendance" class="btn btn-ghost btn-sm btn-icon" title="Attendance"><i class="fas fa-user-check"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($batches)): ?>
        <tr><td colspan="8">
          <div class="empty-state">
            <i class="fas fa-layer-group empty-state-icon"></i>
            <h4 class="empty-state-title">No Batches Yet</h4>
            <p class="empty-state-desc">Group students and teachers into batches for organized learning.</p>
            <button onclick="openCreateBatchModal()" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Create First Batch</button>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Create Batch Modal -->
<div class="modal fade" id="batchModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="batchModalTitle">Create Batch</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="batchForm" method="POST" action="/super-admin/batches/store">
        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
        <div class="modal-body">
          <div class="form-group mb-3">
            <label class="form-label required">Batch Name</label>
            <input type="text" name="name" id="batchName" class="form-control" required placeholder="e.g. Python Evening Batch 5">
          </div>
          <div class="form-group mb-3">
            <label class="form-label required">Batch Code</label>
            <input type="text" name="code" id="batchCode" class="form-control" required maxlength="20" placeholder="e.g. PY-EVE-05">
          </div>
          <div class="form-group mb-3">
            <label class="form-label required">Course</label>
            <select name="course_id" id="batchCourseId" class="form-select" required>
              <option value="">Select course</option>
              <?php foreach ($courses as $c): ?>
              <option value="<?= $c['id'] ?>"><?= \App\Core\View::e($c['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label required">Mode</label>
              <select name="mode" id="batchMode" class="form-select" required>
                <option value="online">Online</option>
                <option value="offline">Offline</option>
                <option value="hybrid">Hybrid</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label required">Max Students</label>
              <input type="number" name="max_students" id="batchMaxStudents" class="form-control" required min="1" value="30">
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label required">Start Date</label>
              <input type="date" name="start_date" id="batchStartDate" class="form-control" required>
            </div>
            <div class="col-6">
              <label class="form-label required">End Date</label>
              <input type="date" name="end_date" id="batchEndDate" class="form-control" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="batchSubmitBtn">Create Batch</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openCreateBatchModal() {
  document.getElementById('batchModalTitle').textContent = 'Create Batch';
  document.getElementById('batchForm').reset();
  document.getElementById('batchForm').action = '/super-admin/batches/store';
  document.getElementById('batchSubmitBtn').textContent = 'Create Batch';
  new bootstrap.Modal(document.getElementById('batchModal')).show();
}

function openEditBatchModal(batch) {
  document.getElementById('batchModalTitle').textContent = 'Edit Batch';
  document.getElementById('batchName').value = batch.name;
  document.getElementById('batchCode').value = batch.code;
  document.getElementById('batchCourseId').value = batch.course_id;
  document.getElementById('batchMode').value = batch.mode;
  document.getElementById('batchMaxStudents').value = batch.max_students;
  document.getElementById('batchStartDate').value = batch.start_date ? batch.start_date.substring(0, 10) : '';
  document.getElementById('batchEndDate').value = batch.end_date ? batch.end_date.substring(0, 10) : '';
  document.getElementById('batchForm').action = `/super-admin/batches/${batch.id}/update`;
  document.getElementById('batchSubmitBtn').textContent = 'Save Changes';
  new bootstrap.Modal(document.getElementById('batchModal')).show();
}

let st;
['batchSearch','statusFilter','modeFilter'].forEach(id => {
  document.getElementById(id)?.addEventListener('input', function() {
    clearTimeout(st); st = setTimeout(() => applyFilters(), id === 'batchSearch' ? 400 : 0);
  });
  document.getElementById(id)?.addEventListener('change', () => applyFilters());
});
function applyFilters() {
  const p = new URLSearchParams();
  const s  = document.getElementById('batchSearch')?.value;
  const st = document.getElementById('statusFilter')?.value;
  const m  = document.getElementById('modeFilter')?.value;
  if (s)  p.set('search', s);
  if (st) p.set('status', st);
  if (m)  p.set('mode', m);
  window.location.href = '/super-admin/batches?' + p.toString();
}
</script>
