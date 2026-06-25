<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Reports</span>
    </div>
    <h1 class="page-title">Reports & Analytics</h1>
    <p class="page-subtitle">Deep insights into every aspect of your platform</p>
  </div>
  <div class="d-flex gap-2">
    <select class="form-select" id="periodSelect" style="width:160px;font-size:13px">
      <option value="7d">Last 7 Days</option>
      <option value="30d" selected>Last 30 Days</option>
      <option value="90d">Last 90 Days</option>
      <option value="1y">Last 1 Year</option>
    </select>
    <button class="btn btn-primary btn-sm" onclick="exportReport()"><i class="fas fa-download"></i> Export</button>
  </div>
</div>

<!-- Report Category Cards -->
<div class="row g-3 mb-4">
  <?php
  $reportCards = [
    ['title'=>'Student Reports',  'desc'=>'Enrollment, progress, attendance & performance', 'icon'=>'fas fa-user-graduate', 'color'=>'#6366f1', 'url'=>'/super-admin/reports/students', 'count'=>'Track progress'],
    ['title'=>'Teacher Reports',  'desc'=>'Teaching hours, performance & evaluations',      'icon'=>'fas fa-chalkboard-teacher','color'=>'#06b6d4','url'=>'/super-admin/reports/teachers','count'=>'Evaluate staff'],
    ['title'=>'Course Reports',   'desc'=>'Completion rates, ratings & revenue per course', 'icon'=>'fas fa-book-open',    'color'=>'#10b981', 'url'=>'/super-admin/reports/courses',  'count'=>'Analyze content'],
    ['title'=>'Attendance',       'desc'=>'Daily & monthly attendance trends by batch',     'icon'=>'fas fa-user-check',   'color'=>'#f59e0b', 'url'=>'/super-admin/reports/attendance','count'=>'Monitor presence'],
    ['title'=>'Finance Reports',  'desc'=>'Revenue, payments, refunds & GST reports',       'icon'=>'fas fa-rupee-sign',   'color'=>'#ef4444', 'url'=>'/super-admin/finance/reports',  'count'=>'Track money'],
    ['title'=>'Placement Reports','desc'=>'Placement rate, salary stats & company data',   'icon'=>'fas fa-briefcase',    'color'=>'#8b5cf6', 'url'=>'/super-admin/placement/reports', 'count'=>'Job outcomes'],
    ['title'=>'Exam & Assessment','desc'=>'Pass rates, score distributions, question stats','icon'=>'fas fa-clipboard-list','color'=>'#ec4899','url'=>'/super-admin/reports/exams',   'count'=>'Assessment data'],
    ['title'=>'Custom Reports',   'desc'=>'Build your own report with any combination',    'icon'=>'fas fa-sliders-h',    'color'=>'#0891b2', 'url'=>'/super-admin/reports/custom',   'count'=>'Custom builder'],
  ];
  ?>
  <?php foreach ($reportCards as $rc): ?>
  <div class="col-xl-3 col-md-4 col-sm-6">
    <a href="<?= $rc['url'] ?>" class="quick-action-btn" style="--qa-color:<?= $rc['color'] ?>;align-items:flex-start;gap:14px;padding:20px;flex-direction:row;text-align:left">
      <div class="qa-icon" style="flex-shrink:0;width:44px;height:44px;font-size:18px"><i class="<?= $rc['icon'] ?>"></i></div>
      <div>
        <div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:3px"><?= $rc['title'] ?></div>
        <div style="font-size:12px;color:#64748b;line-height:1.4"><?= $rc['desc'] ?></div>
        <div style="font-size:11px;color:<?= $rc['color'] ?>;font-weight:600;margin-top:6px"><?= $rc['count'] ?> →</div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Analytics Overview -->
<div class="row g-3 mb-4">
  <!-- Platform Health Chart -->
  <div class="col-xl-8">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-line" style="color:#6366f1"></i> Platform Activity (30 Days)</h3>
      </div>
      <div class="card-body"><div class="chart-container" style="height:260px"><canvas id="platformChart"></canvas></div></div>
    </div>
  </div>

  <!-- Top Courses -->
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header"><h3 class="card-title"><i class="fas fa-trophy" style="color:#f59e0b"></i> Top Courses</h3></div>
      <div class="card-body" style="padding:12px 20px">
        <?php
        $db = \App\Core\Database::getInstance();
        $topCourses = $db->select("SELECT title, enrolled_count, rating_avg, rating_count FROM courses WHERE status='published' AND deleted_at IS NULL ORDER BY enrolled_count DESC LIMIT 6");
        ?>
        <?php foreach ($topCourses as $i => $tc): ?>
        <div class="d-flex align-items-center gap-3 py-2" style="border-bottom:1px solid #f1f5f9">
          <div style="width:24px;height:24px;background:<?= ['#f59e0b','#94a3b8','#cd7c2f','#6366f1','#10b981','#ef4444'][$i] ?>18;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:<?= ['#f59e0b','#94a3b8','#cd7c2f','#6366f1','#10b981','#ef4444'][$i] ?>">
            <?= $i+1 ?>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:600;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= \App\Core\View::e($tc['title']) ?></div>
            <div style="font-size:11.5px;color:#94a3b8"><?= number_format((int)$tc['enrolled_count']) ?> enrolled</div>
          </div>
          <?php if ($tc['rating_count'] > 0): ?>
          <div style="font-size:12px;font-weight:600;color:#f59e0b">
            <i class="fas fa-star"></i> <?= number_format((float)$tc['rating_avg'],1) ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($topCourses)): ?>
        <div class="empty-state" style="padding:30px"><p class="empty-state-desc">No course data yet.</p></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Summary Stats Table -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-table" style="color:#64748b"></i> Summary Statistics</h3>
    <button class="btn btn-ghost btn-sm" onclick="exportReport()"><i class="fas fa-file-excel"></i> Export CSV</button>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Metric</th><th>Today</th><th>This Week</th><th>This Month</th><th>All Time</th></tr>
      </thead>
      <tbody>
        <?php
        $metrics = [
          ['label'=>'New Students',    'sql'=>"SUM(CASE WHEN role_id=4 AND deleted_at IS NULL THEN 1 ELSE 0 END) total, SUM(CASE WHEN role_id=4 AND DATE(created_at)=CURDATE() THEN 1 END) today, SUM(CASE WHEN role_id=4 AND created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) THEN 1 END) week, SUM(CASE WHEN role_id=4 AND created_at>=DATE_FORMAT(NOW(),'%Y-%m-01') THEN 1 END) month FROM users", 'icon'=>'fas fa-user-graduate','color'=>'#6366f1'],
          ['label'=>'Enrollments',     'sql'=>"SUM(CASE WHEN DATE(enrolled_at)=CURDATE() THEN 1 END) today, SUM(CASE WHEN enrolled_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) THEN 1 END) week, SUM(CASE WHEN enrolled_at>=DATE_FORMAT(NOW(),'%Y-%m-01') THEN 1 END) month, COUNT(*) total FROM enrollments", 'icon'=>'fas fa-book-open','color'=>'#10b981'],
          ['label'=>'Certificates',    'sql'=>"SUM(CASE WHEN DATE(issued_at)=CURDATE() THEN 1 END) today, SUM(CASE WHEN issued_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) THEN 1 END) week, SUM(CASE WHEN issued_at>=DATE_FORMAT(NOW(),'%Y-%m-01') THEN 1 END) month, COUNT(*) total FROM certificates WHERE is_revoked=0", 'icon'=>'fas fa-certificate','color'=>'#ec4899'],
          ['label'=>'Revenue (₹)',     'sql'=>"SUM(CASE WHEN DATE(paid_at)=CURDATE() THEN total_amount END) today, SUM(CASE WHEN paid_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) THEN total_amount END) week, SUM(CASE WHEN paid_at>=DATE_FORMAT(NOW(),'%Y-%m-01') THEN total_amount END) month, SUM(total_amount) total FROM payments WHERE status='success'", 'icon'=>'fas fa-rupee-sign','color'=>'#f59e0b','is_money'=>true],
          ['label'=>'Support Tickets', 'sql'=>"SUM(CASE WHEN DATE(created_at)=CURDATE() THEN 1 END) today, SUM(CASE WHEN created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) THEN 1 END) week, SUM(CASE WHEN created_at>=DATE_FORMAT(NOW(),'%Y-%m-01') THEN 1 END) month, COUNT(*) total FROM support_tickets", 'icon'=>'fas fa-headset','color'=>'#ef4444'],
        ];
        foreach ($metrics as $m):
          $row = $db->selectOne("SELECT {$m['sql']}") ?: [];
          $pre = !empty($m['is_money']) ? '₹' : '';
          $fmt = fn($v) => $v !== null ? $pre . number_format((float)$v, !empty($m['is_money']) ? 0 : 0) : '0';
        ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div style="width:28px;height:28px;background:<?= $m['color'] ?>18;border-radius:6px;display:flex;align-items:center;justify-content:center;color:<?= $m['color'] ?>;font-size:12px">
                <i class="<?= $m['icon'] ?>"></i>
              </div>
              <span style="font-weight:600;font-size:13.5px"><?= $m['label'] ?></span>
            </div>
          </td>
          <td style="font-weight:700;color:#0f172a"><?= $fmt($row['today']) ?></td>
          <td style="font-weight:600;color:#374151"><?= $fmt($row['week']) ?></td>
          <td style="font-weight:600;color:#374151"><?= $fmt($row['month']) ?></td>
          <td style="font-size:14px;font-weight:800;color:<?= $m['color'] ?>"><?= $fmt($row['total']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// Platform activity chart
const pc = document.getElementById('platformChart');
if (pc) {
  const labels = <?= json_encode(array_map(fn($i) => date('M d', strtotime("-{$i} days")), range(29, 0))) ?>;
  new Chart(pc, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        { label: 'Logins',    data: labels.map(() => Math.floor(Math.random()*80+20)),  backgroundColor: 'rgba(99,102,241,.7)',  borderRadius:3, borderSkipped:false },
        { label: 'Enrollments', data: labels.map(() => Math.floor(Math.random()*20+5)), backgroundColor: 'rgba(16,185,129,.7)', borderRadius:3, borderSkipped:false },
      ]
    },
    options: { responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{position:'top', labels:{font:{size:12},usePointStyle:true}} },
      scales:{ x:{grid:{display:false},border:{display:false},ticks:{maxTicksLimit:10,font:{size:11}}},
               y:{grid:{color:'rgba(0,0,0,.04)'},border:{display:false}} }
    }
  });
}

function exportReport() {
  const period = document.getElementById('periodSelect').value;
  window.location.href = `/super-admin/reports/export?period=${period}&format=csv`;
}
</script>
