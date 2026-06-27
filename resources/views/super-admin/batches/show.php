<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/batches">Batches</a>
      <span class="sep">/</span><span><?= \App\Core\View::e($batch['name']) ?></span>
    </div>
    <h1 class="page-title"><?= \App\Core\View::e($batch['name']) ?></h1>
    <p class="page-subtitle"><?= \App\Core\View::e($batch['course_title'] ?? 'No course linked') ?></p>
  </div>
  <div class="d-flex gap-2">
    <a href="/super-admin/batches/<?= $batch['id'] ?>/attendance" class="btn btn-secondary btn-sm"><i class="fas fa-user-check"></i> Attendance</a>
    <button onclick="openAddStudentModal()" class="btn btn-primary btn-sm"><i class="fas fa-user-plus"></i> Add Student</button>
  </div>
</div>

<div class="row g-3 mb-4">
  <?php
  $infoItems = [
    ['label' => 'Code', 'value' => $batch['code']],
    ['label' => 'Mode', 'value' => ucfirst($batch['mode'])],
    ['label' => 'Status', 'value' => null, 'badge' => $batch['status']],
    ['label' => 'Capacity', 'value' => count($roster) . ' / ' . (int)$batch['max_students']],
    ['label' => 'Start Date', 'value' => \App\Core\View::formatDate($batch['start_date'], 'd M Y')],
    ['label' => 'End Date', 'value' => $batch['end_date'] ? \App\Core\View::formatDate($batch['end_date'], 'd M Y') : '—'],
  ];
  ?>
  <?php foreach ($infoItems as $item): ?>
  <div class="col-md-2 col-4">
    <div class="stat-mini" style="display:block;text-align:left">
      <div class="stat-mini-label" style="margin-bottom:4px"><?= $item['label'] ?></div>
      <?php if (isset($item['badge'])): ?>
        <?= \App\Core\View::badge($item['badge']) ?>
      <?php else: ?>
        <div style="font-size:14px;font-weight:700;color:#0f172a"><?= \App\Core\View::e((string)$item['value']) ?></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Roster -->
<div class="table-container">
  <div class="card-header" style="padding:14px 18px;border-bottom:1px solid #f1f5f9">
    <h3 class="card-title" style="margin:0">Roster (<?= count($roster) ?>)</h3>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Student</th>
          <th>Email</th>
          <th>Enrollment Status</th>
          <th>Enrolled On</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($roster)): ?>
        <tr><td colspan="4">
          <div class="empty-state">
            <i class="fas fa-user-graduate empty-state-icon"></i>
            <h4 class="empty-state-title">No Students Yet</h4>
            <p class="empty-state-desc">Add students to this batch to get started.</p>
          </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($roster as $r): ?>
        <tr>
          <td style="font-weight:600;font-size:13.5px;color:#0f172a"><?= \App\Core\View::e($r['first_name'] . ' ' . $r['last_name']) ?></td>
          <td style="font-size:13px;color:#64748b"><?= \App\Core\View::e($r['email']) ?></td>
          <td><?= \App\Core\View::badge($r['enrollment_status']) ?></td>
          <td style="font-size:12.5px;color:#374151"><?= \App\Core\View::formatDate($r['enrolled_at'], 'd M Y') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Student to Batch</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php if (empty($availableStudents)): ?>
        <p style="color:#94a3b8;font-size:13.5px">No active students available to add (all are already in this batch, or none exist yet).</p>
        <?php else: ?>
        <div class="form-group mb-3">
          <label class="form-label required">Student</label>
          <select id="addStudentSelect" class="form-select">
            <?php foreach ($availableStudents as $s): ?>
            <option value="<?= $s['id'] ?>"><?= \App\Core\View::e($s['name']) ?> (<?= \App\Core\View::e($s['email']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <?php if (!empty($availableStudents)): ?>
        <button type="button" class="btn btn-primary" onclick="submitAddStudent()">Add Student</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
function openAddStudentModal() {
  new bootstrap.Modal(document.getElementById('addStudentModal')).show();
}

async function submitAddStudent() {
  const userId = document.getElementById('addStudentSelect')?.value;
  if (!userId) return;
  const d = await cgFetch('/super-admin/batches/<?= $batch['id'] ?>/add-student', {
    method: 'POST',
    body: JSON.stringify({ user_id: userId }),
  });
  if (d.success) { CGToast.success('Student added.'); setTimeout(() => location.reload(), 600); }
  else CGToast.error(d.message);
}
</script>
