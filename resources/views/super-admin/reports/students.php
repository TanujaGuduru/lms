<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/reports">Reports</a>
      <span class="sep">/</span><span>Students</span>
    </div>
    <h1 class="page-title">Student Reports</h1>
    <p class="page-subtitle">Enrollment trends and course completion rates</p>
  </div>
  <div class="d-flex gap-2">
    <?php
    $periodLabels = ['7d' => 'Last 7 Days', '30d' => 'Last 30 Days', '90d' => 'Last 90 Days', '1y' => 'Last 1 Year'];
    ?>
    <div class="btn-group" role="group">
      <?php foreach ($periodLabels as $pVal => $pLabel): ?>
      <a href="/super-admin/reports/students?period=<?= $pVal ?>"
         class="btn btn-sm <?= $period === $pVal ? 'btn-primary' : 'btn-ghost' ?>"><?= $pLabel ?></a>
      <?php endforeach; ?>
    </div>
    <a href="/super-admin/reports/export?type=students&period=<?= \App\Core\View::e($period) ?>" class="btn btn-secondary btn-sm">
      <i class="fas fa-download"></i> Export
    </a>
  </div>
</div>

<!-- Enrollment Trend Chart -->
<div class="card mb-4">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-chart-line" style="color:#6366f1"></i> Enrollment Trend</h3>
  </div>
  <div class="card-body"><div class="chart-container" style="height:280px"><canvas id="enrollmentChart"></canvas></div></div>
</div>

<!-- Completion Rates Table -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-table" style="color:#64748b"></i> Course Completion Rates</h3>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Course</th><th>Enrolled</th><th>Completed</th><th>Completion Rate</th></tr>
      </thead>
      <tbody>
        <?php if (empty($completionRates)): ?>
        <tr><td colspan="4">
          <div class="empty-state" style="padding:24px">
            <i class="fas fa-user-graduate empty-state-icon"></i>
            <p class="empty-state-desc">No enrollment data yet.</p>
          </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($completionRates as $cr):
          $enrolled  = (int)$cr['enrolled'];
          $completed = (int)$cr['completed'];
          $pct = $enrolled > 0 ? round(($completed / $enrolled) * 100, 1) : 0;
          $barColor = $pct >= 75 ? '#10b981' : ($pct >= 40 ? '#f59e0b' : '#ef4444');
        ?>
        <tr>
          <td style="font-weight:600;color:#0f172a"><?= \App\Core\View::e($cr['title']) ?></td>
          <td><?= number_format($enrolled) ?></td>
          <td><?= number_format($completed) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:42px;text-align:right;font-size:13px;font-weight:700;color:<?= $barColor ?>"><?= $pct ?>%</div>
              <div style="flex:1;height:6px;background:#f1f5f9;border-radius:100px;min-width:80px">
                <div style="height:6px;width:<?= min(100, $pct) ?>%;background:<?= $barColor ?>;border-radius:100px"></div>
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

<script>
const ec = document.getElementById('enrollmentChart');
if (ec) {
  const trend = <?= json_encode($enrollmentTrend ?? []) ?>;
  new Chart(ec, {
    type: 'line',
    data: {
      labels: trend.map(r => r.day),
      datasets: [{
        label: 'Enrollments',
        data: trend.map(r => r.cnt),
        borderColor: '#6366f1',
        backgroundColor: 'rgba(99,102,241,.1)',
        fill: true,
        tension: 0.35,
        pointRadius: 2,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, border: { display: false }, ticks: { maxTicksLimit: 12, font: { size: 11 } } },
        y: { grid: { color: 'rgba(0,0,0,.04)' }, border: { display: false }, beginAtZero: true, ticks: { precision: 0 } }
      }
    }
  });
}
</script>
