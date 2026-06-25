<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/reports">Reports</a>
      <span class="sep">/</span><span>Attendance</span>
    </div>
    <h1 class="page-title">Attendance Reports</h1>
    <p class="page-subtitle">Daily attendance records by batch and month</p>
  </div>
</div>

<!-- Filter Form -->
<div class="card mb-4">
  <div class="card-body">
    <form method="GET" action="/super-admin/reports/attendance" class="d-flex gap-3 align-items-end flex-wrap">
      <div class="form-group" style="min-width:220px">
        <label class="form-label">Batch</label>
        <select name="batch_id" class="form-select" required>
          <option value="">Select a batch…</option>
          <?php foreach ($batches as $b): ?>
          <option value="<?= (int)$b['id'] ?>" <?= $batchId === (int)$b['id'] ? 'selected' : '' ?>>
            <?= \App\Core\View::e($b['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="min-width:180px">
        <label class="form-label">Month</label>
        <input type="month" name="month" class="form-control" value="<?= \App\Core\View::e($month) ?>">
      </div>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> View Attendance</button>
    </form>
  </div>
</div>

<!-- Attendance Table -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-user-check" style="color:#f59e0b"></i> Attendance Records</h3>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Date</th><th>Student</th><th>Status</th><th>Check-in Time</th></tr>
      </thead>
      <tbody>
        <?php if (empty($attendance)): ?>
        <tr><td colspan="4">
          <div class="empty-state" style="padding:24px">
            <i class="fas fa-user-check empty-state-icon"></i>
            <p class="empty-state-desc"><?= $batchId ? 'No attendance records for this batch and month.' : 'Select a batch and month to view attendance.' ?></p>
          </div>
        </td></tr>
        <?php else: ?>
        <?php
        $statusColors = [
          'present' => ['bg' => '#10b98118', 'fg' => '#059669'],
          'absent'  => ['bg' => '#ef444418', 'fg' => '#dc2626'],
          'late'    => ['bg' => '#f59e0b18', 'fg' => '#b45309'],
          'excused' => ['bg' => '#94a3b818', 'fg' => '#64748b'],
        ];
        ?>
        <?php foreach ($attendance as $a):
          $sc = $statusColors[$a['status']] ?? ['bg' => '#94a3b818', 'fg' => '#64748b'];
        ?>
        <tr>
          <td style="font-size:13px;color:#64748b"><?= \App\Core\View::formatDate($a['session_date']) ?></td>
          <td style="font-weight:600;color:#0f172a"><?= \App\Core\View::e($a['student_name']) ?></td>
          <td>
            <span class="badge" style="background:<?= $sc['bg'] ?>;color:<?= $sc['fg'] ?>">
              <?= ucfirst($a['status']) ?>
            </span>
          </td>
          <td style="font-size:13px;color:#64748b"><?= $a['check_in_time'] ? \App\Core\View::e($a['check_in_time']) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
