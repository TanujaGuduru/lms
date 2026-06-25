<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/exams">Exams</a>
      <span class="sep">/</span><span>Results</span>
    </div>
    <h1 class="page-title"><?= \App\Core\View::e($exam['title']) ?> — Results</h1>
  </div>
  <a href="/super-admin/exams" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back to Exams</a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php
  $pass = array_filter($results ?? [], fn($r) => (float)$r['score'] >= (float)($exam['pass_mark'] ?? 60));
  $avgScore = count($results) > 0 ? round(array_sum(array_column($results, 'score')) / count($results), 1) : 0;
  $rCards = [
    ['label'=>'Total Attempts',  'value'=>count($results??[]),     'icon'=>'fas fa-users',       'color'=>'#6366f1'],
    ['label'=>'Passed',          'value'=>count($pass),            'icon'=>'fas fa-check-circle','color'=>'#10b981'],
    ['label'=>'Failed',          'value'=>count($results??[])-count($pass),'icon'=>'fas fa-times-circle','color'=>'#ef4444'],
    ['label'=>'Average Score',   'value'=>$avgScore . '%',         'icon'=>'fas fa-chart-bar',   'color'=>'#f59e0b'],
  ];
  ?>
  <?php foreach ($rCards as $s): ?>
  <div class="col-xl-3 col-md-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:<?= $s['color'] ?>18;color:<?= $s['color'] ?>"><i class="<?= $s['icon'] ?>"></i></div>
      <div><div class="stat-mini-value"><?= $s['value'] ?></div><div class="stat-mini-label"><?= $s['label'] ?></div></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Score Distribution Chart + Results Table -->
<div class="row g-3">
  <div class="col-xl-4">
    <div class="card">
      <div class="card-header"><h3 class="card-title">Score Distribution</h3></div>
      <div class="card-body"><canvas id="scoreChart" height="200"></canvas></div>
    </div>
  </div>
  <div class="col-xl-8">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Student Results</h3>
        <a href="/super-admin/exams/<?= $exam['id'] ?>/results/export" class="btn btn-secondary btn-sm">
          <i class="fas fa-download"></i> Export CSV
        </a>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Student</th><th>Score</th><th>Marks</th><th>Time Taken</th><th>Attempt</th><th>Status</th><th>Submitted</th></tr></thead>
          <tbody>
            <?php if (empty($results)): ?>
            <tr><td colspan="7">
              <div class="empty-state" style="padding:24px">
                <i class="fas fa-chart-bar empty-state-icon"></i>
                <p class="empty-state-desc">No submissions yet.</p>
              </div>
            </td></tr>
            <?php else: ?>
            <?php foreach ($results as $r):
              $isPassed = (float)$r['score'] >= (float)($exam['pass_mark'] ?? 60);
              $scoreColor = $isPassed ? '#10b981' : '#ef4444';
            ?>
            <tr>
              <td style="font-size:13.5px;font-weight:600;color:#374151"><?= \App\Core\View::e($r['student_name']) ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  <div style="width:40px;text-align:right;font-size:14px;font-weight:700;color:<?= $scoreColor ?>"><?= number_format((float)$r['score'], 1) ?>%</div>
                  <div style="flex:1;height:6px;background:#f1f5f9;border-radius:100px;min-width:60px">
                    <div style="height:6px;width:<?= min(100, (float)$r['score']) ?>%;background:<?= $scoreColor ?>;border-radius:100px"></div>
                  </div>
                </div>
              </td>
              <td style="font-size:13px;color:#64748b"><?= (int)$r['marks_obtained'] ?>/<?= (int)$r['total_marks'] ?></td>
              <td style="font-size:12.5px;color:#64748b">
                <?php
                $mins = $r['time_taken'] ? floor($r['time_taken'] / 60) : null;
                echo $mins !== null ? "{$mins}m " . ($r['time_taken'] % 60) . "s" : '—';
                ?>
              </td>
              <td style="font-size:13px;color:#64748b"><?= (int)$r['attempt_number'] ?></td>
              <td>
                <span class="badge" style="background:<?= $isPassed ? '#10b98118' : '#ef444418' ?>;color:<?= $isPassed ? '#059669' : '#dc2626' ?>">
                  <?= $isPassed ? 'Passed' : 'Failed' ?>
                </span>
              </td>
              <td style="font-size:12px;color:#94a3b8"><?= \App\Core\View::timeAgo($r['submitted_at'] ?? $r['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
// Score distribution: bucket into 10-point ranges
const scores = <?= json_encode(array_column($results ?? [], 'score')) ?>;
const buckets = Array(10).fill(0);
scores.forEach(s => { const b = Math.min(9, Math.floor(s / 10)); buckets[b]++; });

new Chart(document.getElementById('scoreChart'), {
  type: 'bar',
  data: {
    labels: ['0-10','10-20','20-30','30-40','40-50','50-60','60-70','70-80','80-90','90-100'],
    datasets: [{ data: buckets, backgroundColor: '#6366f1cc', borderRadius: 6 }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
  }
});
</script>
