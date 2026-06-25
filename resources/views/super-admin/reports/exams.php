<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/reports">Reports</a>
      <span class="sep">/</span><span>Exams</span>
    </div>
    <h1 class="page-title">Exam &amp; Assessment Reports</h1>
    <p class="page-subtitle">Attempt counts, average scores and pass rates per exam</p>
  </div>
</div>

<!-- Exam Stats Table -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-clipboard-list" style="color:#ec4899"></i> Exam Performance</h3>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Exam</th><th>Attempts</th><th>Average Score</th><th>Passed</th><th>Failed</th><th>Pass Rate</th></tr>
      </thead>
      <tbody>
        <?php if (empty($examStats)): ?>
        <tr><td colspan="6">
          <div class="empty-state" style="padding:24px">
            <i class="fas fa-clipboard-list empty-state-icon"></i>
            <p class="empty-state-desc">No exam attempts recorded yet.</p>
          </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($examStats as $ex):
          $attempts = (int)$ex['attempts'];
          $passed   = (int)$ex['passed'];
          $failed   = (int)$ex['failed'];
          $passRate = $attempts > 0 ? round(($passed / $attempts) * 100, 1) : 0;
          $barColor = $passRate >= 75 ? '#10b981' : ($passRate >= 40 ? '#f59e0b' : '#ef4444');
        ?>
        <tr>
          <td style="font-weight:600;color:#0f172a"><?= \App\Core\View::e($ex['title']) ?></td>
          <td><?= number_format($attempts) ?></td>
          <td style="font-weight:700;color:#374151"><?= $ex['avg_score'] !== null ? number_format((float)$ex['avg_score'], 1) . '%' : '—' ?></td>
          <td style="color:#059669;font-weight:600"><?= number_format($passed) ?></td>
          <td style="color:#dc2626;font-weight:600"><?= number_format($failed) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:42px;text-align:right;font-size:13px;font-weight:700;color:<?= $barColor ?>"><?= $passRate ?>%</div>
              <div style="flex:1;height:6px;background:#f1f5f9;border-radius:100px;min-width:80px">
                <div style="height:6px;width:<?= min(100, $passRate) ?>%;background:<?= $barColor ?>;border-radius:100px"></div>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
