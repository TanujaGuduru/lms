<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Live Classes</span>
    </div>
    <h1 class="page-title">Live Classes</h1>
    <p class="page-subtitle">Schedule live classes for a batch — students and parents are notified automatically</p>
  </div>
  <button onclick="openScheduleModal()" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Schedule Class</button>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php
  $statItems = [
    ['label' => 'Upcoming',  'value' => $stats['upcoming']   ?? 0, 'icon' => 'fas fa-clock',        'color' => '#f59e0b'],
    ['label' => 'Completed', 'value' => $stats['completed']  ?? 0, 'icon' => 'fas fa-check-circle', 'color' => '#10b981'],
    ['label' => 'Cancelled', 'value' => $stats['cancelled']  ?? 0, 'icon' => 'fas fa-ban',           'color' => '#ef4444'],
    ['label' => 'Total',     'value' => $stats['total']      ?? 0, 'icon' => 'fas fa-video',         'color' => '#6366f1'],
  ];
  ?>
  <?php foreach ($statItems as $s): ?>
  <div class="col-xl col-md-3 col-6">
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
      <select id="batchFilter" class="form-select" style="width:200px;font-size:13px">
        <option value="">All Batches</option>
        <?php foreach ($batches as $b): ?>
        <option value="<?= $b['id'] ?>" <?= ($filters['batch_id'] ?? '') == $b['id'] ? 'selected' : '' ?>><?= \App\Core\View::e($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="statusFilter" class="form-select" style="width:150px;font-size:13px">
        <option value="">All Status</option>
        <?php foreach (['scheduled', 'live', 'completed', 'cancelled'] as $st): ?>
        <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</div>

<!-- Classes Table -->
<div class="table-container">
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Class</th>
          <th>Batch</th>
          <th>Teacher</th>
          <th>Date &amp; Time</th>
          <th>Duration</th>
          <th>Recurrence</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($classes)): ?>
        <tr><td colspan="8">
          <div class="empty-state">
            <i class="fas fa-video empty-state-icon"></i>
            <h4 class="empty-state-title">No Live Classes Scheduled</h4>
            <p class="empty-state-desc">Schedule your first live class for a batch.</p>
            <button onclick="openScheduleModal()" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Schedule Class</button>
          </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($classes as $c): ?>
        <tr>
          <td style="font-weight:600;font-size:13.5px;color:#0f172a"><?= \App\Core\View::e($c['title']) ?></td>
          <td style="font-size:13px;color:#374151"><?= \App\Core\View::e($c['batch_name']) ?></td>
          <td style="font-size:13px;color:#374151"><?= \App\Core\View::e($c['teacher_name']) ?></td>
          <td style="font-size:12.5px;color:#374151"><?= \App\Core\View::formatDate($c['start_datetime'], 'd M Y, h:i A') ?></td>
          <td style="font-size:13px;color:#374151"><?= (int)$c['duration_minutes'] ?> min</td>
          <td>
            <?php if (($c['recurrence_rule'] ?? 'none') !== 'none'): ?>
            <span class="badge" style="background:#8b5cf618;color:#8b5cf6;text-transform:capitalize"><?= $c['recurrence_rule'] ?></span>
            <?php else: ?>
            <span style="font-size:12px;color:#94a3b8">One-time</span>
            <?php endif; ?>
          </td>
          <td><?= \App\Core\View::badge($c['status']) ?></td>
          <td>
            <?php if ($c['status'] === 'scheduled'): ?>
            <button onclick="cancelClass(<?= $c['id'] ?>, '<?= \App\Core\View::e(addslashes($c['title'])) ?>')" class="btn btn-danger btn-sm btn-icon" title="Cancel">
              <i class="fas fa-times"></i>
            </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Schedule Class Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Schedule Live Class</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="scheduleForm" method="POST" action="/super-admin/live-classes/store">
        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
        <div class="modal-body">
          <div class="form-group mb-3">
            <label class="form-label required">Title</label>
            <input type="text" name="title" class="form-control" required placeholder="e.g. Arrays &amp; Loops — Live Session">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Optional notes for students…"></textarea>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label required">Batch</label>
              <select name="batch_id" class="form-select" required>
                <option value="">Select batch</option>
                <?php foreach ($batches as $b): ?>
                <option value="<?= $b['id'] ?>"><?= \App\Core\View::e($b['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label required">Teacher</label>
              <select name="teacher_id" class="form-select" required>
                <option value="">Select teacher</option>
                <?php foreach ($teachers as $t): ?>
                <option value="<?= $t['id'] ?>"><?= \App\Core\View::e($t['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label required">Start Date</label>
              <input type="date" name="start_date" class="form-control" required>
            </div>
            <div class="col-6">
              <label class="form-label required">Start Time</label>
              <input type="time" name="start_time" class="form-control" required>
            </div>
          </div>
          <div class="form-group mb-3">
            <label class="form-label required">Duration (minutes)</label>
            <input type="number" name="duration_minutes" class="form-control" value="60" min="10" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Repeat</label>
            <select name="recurrence_rule" id="recurrenceRule" class="form-select" onchange="toggleRecurrenceEnd()">
              <option value="none">Does not repeat</option>
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
            </select>
          </div>
          <div class="form-group mb-3 hidden" id="recurrenceEndGroup">
            <label class="form-label required">Repeat Until</label>
            <input type="date" name="recurrence_end_date" id="recurrenceEndDate" class="form-control">
            <div style="font-size:12px;color:#94a3b8;margin-top:4px">Generates one class per occurrence, up to 52 classes.</div>
          </div>
          <div style="font-size:12.5px;color:#64748b;background:#f8fafc;border-radius:8px;padding:10px 12px">
            <i class="fas fa-circle-info"></i> Students' parents will automatically get an email + in-app notification when this is scheduled, and another reminder 15 minutes before it starts.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Schedule</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openScheduleModal() {
  document.getElementById('scheduleForm').reset();
  document.getElementById('recurrenceEndGroup').classList.add('hidden');
  new bootstrap.Modal(document.getElementById('scheduleModal')).show();
}

function toggleRecurrenceEnd() {
  const isRecurring = document.getElementById('recurrenceRule').value !== 'none';
  const group = document.getElementById('recurrenceEndGroup');
  group.classList.toggle('hidden', !isRecurring);
  document.getElementById('recurrenceEndDate').required = isRecurring;
}

['batchFilter', 'statusFilter'].forEach(id => {
  document.getElementById(id)?.addEventListener('change', () => {
    const p = new URLSearchParams();
    const batchId = document.getElementById('batchFilter').value;
    const status = document.getElementById('statusFilter').value;
    if (batchId) p.set('batch_id', batchId);
    if (status) p.set('status', status);
    window.location.href = '/super-admin/live-classes?' + p.toString();
  });
});

async function cancelClass(id, title) {
  const r = await Swal.fire({
    title: `Cancel "${title}"?`, text: 'Students and parents will see this class as cancelled.', icon: 'warning',
    showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Cancel Class'
  });
  if (!r.isConfirmed) return;
  const d = await cgFetch(`/super-admin/live-classes/${id}/cancel`, { method: 'POST' });
  if (d.success) { CGToast.success('Class cancelled'); setTimeout(() => location.reload(), 600); }
  else CGToast.error(d.message);
}
</script>
