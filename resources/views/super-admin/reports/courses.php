<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/reports">Reports</a>
      <span class="sep">/</span><span>Courses</span>
    </div>
    <h1 class="page-title">Course Reports</h1>
    <p class="page-subtitle">Enrollment, completion and revenue per course</p>
  </div>
  <a href="/super-admin/reports/export?type=students" class="btn btn-secondary btn-sm">
    <i class="fas fa-download"></i> Export
  </a>
</div>

<!-- Courses Table -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-book-open" style="color:#10b981"></i> Course Performance</h3>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Course</th><th>Enrollments</th><th>Completions</th><th>Completion Rate</th><th>Rating</th><th>Revenue</th></tr>
      </thead>
      <tbody>
        <?php if (empty($courses)): ?>
        <tr><td colspan="6">
          <div class="empty-state" style="padding:24px">
            <i class="fas fa-book-open empty-state-icon"></i>
            <p class="empty-state-desc">No course data yet.</p>
          </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($courses as $c):
          $enrolled  = (int)$c['enrolled_count'];
          $completed = (int)$c['completions'];
          $pct = $enrolled > 0 ? round(($completed / $enrolled) * 100, 1) : 0;
          $rating = (float)($c['rating_avg'] ?? 0);
        ?>
        <tr>
          <td style="font-weight:600;color:#0f172a"><?= \App\Core\View::e($c['title']) ?></td>
          <td><?= number_format($enrolled) ?></td>
          <td><?= number_format($completed) ?></td>
          <td>
            <span class="badge badge-soft-<?= $pct >= 75 ? 'success' : ($pct >= 40 ? 'warning' : 'danger') ?>"><?= $pct ?>%</span>
          </td>
          <td style="color:#f59e0b;font-weight:600">
            <?php if ($rating > 0): ?>
            <i class="fas fa-star"></i> <?= number_format($rating, 1) ?>
            <?php else: ?>
            <span style="color:#94a3b8">—</span>
            <?php endif; ?>
          </td>
          <td style="font-weight:700;color:#0f172a"><?= \App\Core\View::formatMoney((float)$c['revenue']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
