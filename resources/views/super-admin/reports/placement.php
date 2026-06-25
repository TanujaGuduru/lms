<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/reports">Reports</a>
      <span class="sep">/</span><span>Placement</span>
    </div>
    <h1 class="page-title">Placement Reports</h1>
    <p class="page-subtitle">Applications, status breakdown and hiring company stats</p>
  </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php
  $statusMap = [];
  foreach ($statusBreakdown as $row) { $statusMap[$row['status']] = (int)$row['cnt']; }
  $accepted = $statusMap['accepted'] ?? 0;
  $sCards = [
    ['label' => 'Total Applications', 'value' => number_format($totalApplications), 'icon' => 'fas fa-file-alt', 'color' => '#6366f1'],
    ['label' => 'Accepted Offers',    'value' => number_format($accepted),          'icon' => 'fas fa-check-circle', 'color' => '#10b981'],
    ['label' => 'Rejected',           'value' => number_format($statusMap['rejected'] ?? 0), 'icon' => 'fas fa-times-circle', 'color' => '#ef4444'],
    ['label' => 'Average CTC',        'value' => $avgCtc > 0 ? \App\Core\View::formatMoney($avgCtc) : '—', 'icon' => 'fas fa-rupee-sign', 'color' => '#f59e0b'],
  ];
  ?>
  <?php foreach ($sCards as $s): ?>
  <div class="col-xl-3 col-md-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:<?= $s['color'] ?>18;color:<?= $s['color'] ?>"><i class="<?= $s['icon'] ?>"></i></div>
      <div><div class="stat-mini-value"><?= $s['value'] ?></div><div class="stat-mini-label"><?= $s['label'] ?></div></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <!-- Status Breakdown Chart -->
  <div class="col-xl-5">
    <div class="card h-100">
      <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-pie" style="color:#8b5cf6"></i> Application Status</h3></div>
      <div class="card-body"><canvas id="statusChart" height="240"></canvas></div>
    </div>
  </div>

  <!-- Top Companies -->
  <div class="col-xl-7">
    <div class="card h-100">
      <div class="card-header"><h3 class="card-title"><i class="fas fa-building" style="color:#06b6d4"></i> Top Companies by Applications</h3></div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Company</th><th>Applications</th></tr></thead>
          <tbody>
            <?php if (empty($topCompanies)): ?>
            <tr><td colspan="2">
              <div class="empty-state" style="padding:24px">
                <i class="fas fa-building empty-state-icon"></i>
                <p class="empty-state-desc">No applications recorded yet.</p>
              </div>
            </td></tr>
            <?php else: ?>
            <?php foreach ($topCompanies as $tc): ?>
            <tr>
              <td style="font-weight:600;color:#0f172a"><?= \App\Core\View::e($tc['name']) ?></td>
              <td><?= number_format((int)$tc['applications']) ?></td>
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
const sc = document.getElementById('statusChart');
if (sc) {
  const data = <?= json_encode($statusBreakdown ?? []) ?>;
  const colors = { applied:'#6366f1', shortlisted:'#06b6d4', interview_scheduled:'#f59e0b', offer_made:'#8b5cf6', accepted:'#10b981', rejected:'#ef4444', withdrawn:'#94a3b8' };
  new Chart(sc, {
    type: 'doughnut',
    data: {
      labels: data.map(r => r.status.replace('_',' ')),
      datasets: [{ data: data.map(r => r.cnt), backgroundColor: data.map(r => colors[r.status] || '#94a3b8') }]
    },
    options: { plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, usePointStyle: true } } } }
  });
}
</script>
