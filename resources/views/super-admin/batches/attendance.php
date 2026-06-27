<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/batches">Batches</a>
      <span class="sep">/</span>
      <a href="/super-admin/batches/<?= $batch['id'] ?>"><?= \App\Core\View::e($batch['name']) ?></a>
      <span class="sep">/</span><span>Attendance</span>
    </div>
    <h1 class="page-title">Attendance</h1>
    <p class="page-subtitle"><?= \App\Core\View::e($batch['name']) ?></p>
  </div>
</div>

<!-- Class selector -->
<div class="card mb-3">
  <div class="card-body py-3">
    <div class="d-flex gap-3 flex-wrap align-items-center">
      <label class="form-label mb-0" style="white-space:nowrap">Session:</label>
      <select id="classSelect" class="form-select" style="width:320px;font-size:13px" onchange="goToClass(this.value)">
        <option value="">Select a live class…</option>
        <?php foreach ($classes as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $selectedClassId === (int)$c['id'] ? 'selected' : '' ?>>
          <?= \App\Core\View::e($c['title']) ?> — <?= \App\Core\View::formatDate($c['start_datetime'], 'd M Y, h:i A') ?> (<?= $c['status'] ?>)
        </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</div>

<?php if (empty($classes)): ?>
<div class="empty-state">
  <i class="fas fa-video empty-state-icon"></i>
  <h4 class="empty-state-title">No Live Classes Scheduled Yet</h4>
  <p class="empty-state-desc">Schedule a live class for this batch first — attendance is tracked per session.</p>
  <a href="/super-admin/live-classes" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Schedule a Class</a>
</div>
<?php elseif (!$selectedClassId): ?>
<div class="empty-state">
  <i class="fas fa-user-check empty-state-icon"></i>
  <h4 class="empty-state-title">Pick a Session</h4>
  <p class="empty-state-desc">Select a live class above to mark or review attendance for it.</p>
</div>
<?php else: ?>
<form method="POST" action="/super-admin/batches/<?= $batch['id'] ?>/attendance/save">
  <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
  <input type="hidden" name="class_id" value="<?= $selectedClassId ?>">

  <div class="table-container">
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>Email</th>
            <th style="width:220px">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($roster)): ?>
          <tr><td colspan="3">
            <div class="empty-state">
              <i class="fas fa-user-graduate empty-state-icon"></i>
              <h4 class="empty-state-title">No Active Students in This Batch</h4>
            </div>
          </td></tr>
          <?php else: ?>
          <?php foreach ($roster as $r): ?>
          <tr>
            <td style="font-weight:600;font-size:13.5px;color:#0f172a"><?= \App\Core\View::e($r['first_name'] . ' ' . $r['last_name']) ?></td>
            <td style="font-size:13px;color:#64748b"><?= \App\Core\View::e($r['email']) ?></td>
            <td>
              <select name="status[<?= $r['student_id'] ?>]" class="form-select" style="font-size:13px">
                <option value="present" <?= $r['attendance_status'] === 'present' ? 'selected' : '' ?>>Present</option>
                <option value="absent" <?= $r['attendance_status'] === 'absent' || !$r['attendance_status'] ? 'selected' : '' ?>>Absent</option>
                <option value="late" <?= $r['attendance_status'] === 'late' ? 'selected' : '' ?>>Late</option>
                <option value="partial" <?= $r['attendance_status'] === 'partial' ? 'selected' : '' ?>>Partial</option>
                <option value="excused" <?= $r['attendance_status'] === 'excused' ? 'selected' : '' ?>>Excused</option>
              </select>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (!empty($roster)): ?>
  <div class="d-flex justify-content-end mt-3">
    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Save Attendance</button>
  </div>
  <?php endif; ?>
</form>
<?php endif; ?>

<script>
function goToClass(classId) {
  if (!classId) return;
  window.location.href = '/super-admin/batches/<?= $batch['id'] ?>/attendance?class_id=' + classId;
}
</script>
