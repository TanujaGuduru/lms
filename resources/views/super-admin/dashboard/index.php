<?php
$hour     = (int)date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
$u        = $currentUser ?? [];
?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <i class="fas fa-house" style="font-size:11px"></i>
      <span class="sep">/</span>
      <span>Dashboard</span>
    </div>
    <h1 class="page-title"><?= $greeting ?>, <?= \App\Core\View::e($u['first_name'] ?? 'Admin') ?> 👋</h1>
    <p class="page-subtitle">Here's what's happening across your platform today — <?= date('l, d F Y') ?></p>
  </div>
  <div class="d-flex gap-2 align-items-center">
    <button class="btn btn-secondary btn-sm" onclick="refreshDashboard()">
      <i class="fas fa-sync-alt"></i> Refresh
    </button>
    <a href="/super-admin/reports" class="btn btn-primary btn-sm">
      <i class="fas fa-chart-bar"></i> View Reports
    </a>
  </div>
</div>

<!-- ═══ KPI GRID ═══ -->
<div class="row g-3 mb-4" id="kpiGrid">

  <div class="col-xl-3 col-md-4 col-sm-6">
    <div class="kpi-card" style="--kpi-color:#6366f1">
      <div class="kpi-header">
        <div class="kpi-icon"><i class="fas fa-user-graduate"></i></div>
        <span class="kpi-trend up" id="kpi-student-trend"><i class="fas fa-arrow-up"></i> —%</span>
      </div>
      <div class="kpi-value" data-target="<?= (int)($stats['users']['students'] ?? 0) ?>">0</div>
      <div class="kpi-label">Total Students</div>
      <div class="kpi-sparkline"><canvas id="spark-students" height="36"></canvas></div>
      <a href="/super-admin/users?role_id=4" class="kpi-quick-action">
        Manage <i class="fas fa-arrow-right" style="font-size:10px"></i>
      </a>
    </div>
  </div>

  <div class="col-xl-3 col-md-4 col-sm-6">
    <div class="kpi-card" style="--kpi-color:#06b6d4">
      <div class="kpi-header">
        <div class="kpi-icon"><i class="fas fa-chalkboard-teacher"></i></div>
        <span class="kpi-trend flat" id="kpi-teacher-trend"><i class="fas fa-minus"></i> —</span>
      </div>
      <div class="kpi-value" data-target="<?= (int)($stats['users']['teachers'] ?? 0) ?>">0</div>
      <div class="kpi-label">Total Teachers</div>
      <div class="kpi-sparkline"><canvas id="spark-teachers" height="36"></canvas></div>
      <a href="/super-admin/users?role_id=3" class="kpi-quick-action" style="color:#06b6d4">
        Manage <i class="fas fa-arrow-right" style="font-size:10px"></i>
      </a>
    </div>
  </div>

  <div class="col-xl-3 col-md-4 col-sm-6">
    <div class="kpi-card" style="--kpi-color:#10b981">
      <div class="kpi-header">
        <div class="kpi-icon"><i class="fas fa-book-open"></i></div>
        <span class="kpi-trend up" style="color:#10b981;background:rgba(16,185,129,.1)"><i class="fas fa-arrow-up"></i> Active</span>
      </div>
      <div class="kpi-value" data-target="<?= (int)($stats['courses']['published'] ?? 0) ?>">0</div>
      <div class="kpi-label">Live Courses</div>
      <div class="kpi-sparkline"><canvas id="spark-courses" height="36"></canvas></div>
      <a href="/super-admin/courses" class="kpi-quick-action" style="color:#10b981">
        Manage <i class="fas fa-arrow-right" style="font-size:10px"></i>
      </a>
    </div>
  </div>

  <div class="col-xl-3 col-md-4 col-sm-6">
    <div class="kpi-card" style="--kpi-color:#f59e0b">
      <div class="kpi-header">
        <div class="kpi-icon"><i class="fas fa-rupee-sign"></i></div>
        <span class="kpi-trend <?= $stats['revenueGrowth'] >= 0 ? 'up' : 'down' ?>" id="kpi-rev-trend">
          <i class="fas fa-arrow-<?= $stats['revenueGrowth'] >= 0 ? 'up' : 'down' ?>"></i>
          <?= abs($stats['revenueGrowth']) ?>%
        </span>
      </div>
      <div class="kpi-value" data-prefix="₹" data-target="<?= (int)($stats['revenue']['month'] ?? 0) ?>">₹0</div>
      <div class="kpi-label">Revenue This Month</div>
      <div class="kpi-sparkline"><canvas id="spark-revenue" height="36"></canvas></div>
      <a href="/super-admin/finance" class="kpi-quick-action" style="color:#f59e0b">
        View Finance <i class="fas fa-arrow-right" style="font-size:10px"></i>
      </a>
    </div>
  </div>

  <div class="col-xl-3 col-md-4 col-sm-6">
    <div class="kpi-card" style="--kpi-color:#8b5cf6">
      <div class="kpi-header">
        <div class="kpi-icon"><i class="fas fa-users-class"></i></div>
        <span class="kpi-trend flat"><i class="fas fa-minus"></i> Running</span>
      </div>
      <div class="kpi-value" data-target="<?= (int)($stats['batches']['active'] ?? 0) ?>">0</div>
      <div class="kpi-label">Active Batches</div>
      <div class="kpi-sparkline"><canvas id="spark-batches" height="36"></canvas></div>
      <a href="/super-admin/batches" class="kpi-quick-action" style="color:#8b5cf6">
        Manage <i class="fas fa-arrow-right" style="font-size:10px"></i>
      </a>
    </div>
  </div>

  <div class="col-xl-3 col-md-4 col-sm-6">
    <div class="kpi-card" style="--kpi-color:#ec4899">
      <div class="kpi-header">
        <div class="kpi-icon"><i class="fas fa-certificate"></i></div>
        <span class="kpi-trend up" style="color:#ec4899;background:rgba(236,72,153,.1)"><i class="fas fa-arrow-up"></i> Issued</span>
      </div>
      <div class="kpi-value" data-target="<?= (int)($stats['certs']['total'] ?? 0) ?>">0</div>
      <div class="kpi-label">Certificates Issued</div>
      <div class="kpi-sparkline"><canvas id="spark-certs" height="36"></canvas></div>
      <a href="/super-admin/certificates" class="kpi-quick-action" style="color:#ec4899">
        Manage <i class="fas fa-arrow-right" style="font-size:10px"></i>
      </a>
    </div>
  </div>

  <div class="col-xl-3 col-md-4 col-sm-6">
    <div class="kpi-card" style="--kpi-color:#ef4444">
      <div class="kpi-header">
        <div class="kpi-icon"><i class="fas fa-headset"></i></div>
        <?php if (($stats['tickets']['open'] ?? 0) > 0): ?>
        <span class="kpi-trend down"><i class="fas fa-exclamation"></i> Action Needed</span>
        <?php else: ?>
        <span class="kpi-trend up"><i class="fas fa-check"></i> All Clear</span>
        <?php endif; ?>
      </div>
      <div class="kpi-value" data-target="<?= (int)($stats['tickets']['open'] ?? 0) ?>">0</div>
      <div class="kpi-label">Open Support Tickets</div>
      <div class="kpi-sparkline"><canvas id="spark-tickets" height="36"></canvas></div>
      <a href="/super-admin/support" class="kpi-quick-action" style="color:#ef4444">
        Respond <i class="fas fa-arrow-right" style="font-size:10px"></i>
      </a>
    </div>
  </div>

  <div class="col-xl-3 col-md-4 col-sm-6">
    <div class="kpi-card" style="--kpi-color:#0891b2">
      <div class="kpi-header">
        <div class="kpi-icon"><i class="fas fa-user-clock"></i></div>
        <?php if (($stats['pending']['total'] ?? 0) > 0): ?>
        <span class="kpi-trend down"><i class="fas fa-clock"></i> Pending</span>
        <?php else: ?>
        <span class="kpi-trend up"><i class="fas fa-check"></i> Done</span>
        <?php endif; ?>
      </div>
      <div class="kpi-value" data-target="<?= (int)($stats['pending']['total'] ?? 0) ?>">0</div>
      <div class="kpi-label">Pending Approvals</div>
      <div class="kpi-sparkline"><canvas id="spark-pending" height="36"></canvas></div>
      <a href="/super-admin/users?status=pending" class="kpi-quick-action" style="color:#0891b2">
        Review <i class="fas fa-arrow-right" style="font-size:10px"></i>
      </a>
    </div>
  </div>

</div><!-- /kpiGrid -->

<!-- ═══ ROW 2: Quick Actions + Today's Snapshot ═══ -->
<div class="row g-3 mb-4">

  <!-- Quick Actions -->
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-bolt" style="color:#f59e0b"></i> Quick Actions</h3>
      </div>
      <div class="card-body">
        <div class="quick-action-grid">

          <a href="/super-admin/users/create" class="quick-action-btn" style="--qa-color:#6366f1">
            <div class="qa-icon"><i class="fas fa-user-plus"></i></div>
            <span class="qa-label">Add Student</span>
          </a>

          <a href="/super-admin/users/create?role=teacher" class="quick-action-btn" style="--qa-color:#06b6d4">
            <div class="qa-icon"><i class="fas fa-chalkboard-teacher"></i></div>
            <span class="qa-label">Add Teacher</span>
          </a>

          <a href="/super-admin/courses/create" class="quick-action-btn" style="--qa-color:#10b981">
            <div class="qa-icon"><i class="fas fa-book-medical"></i></div>
            <span class="qa-label">New Course</span>
          </a>

          <a href="/super-admin/batches/create" class="quick-action-btn" style="--qa-color:#8b5cf6">
            <div class="qa-icon"><i class="fas fa-layer-group"></i></div>
            <span class="qa-label">New Batch</span>
          </a>

          <a href="/super-admin/announcements/create" class="quick-action-btn" style="--qa-color:#f59e0b">
            <div class="qa-icon"><i class="fas fa-bullhorn"></i></div>
            <span class="qa-label">Announce</span>
          </a>

          <a href="/super-admin/exams/create" class="quick-action-btn" style="--qa-color:#ec4899">
            <div class="qa-icon"><i class="fas fa-file-alt"></i></div>
            <span class="qa-label">Create Exam</span>
          </a>

          <a href="/super-admin/reports" class="quick-action-btn" style="--qa-color:#0891b2">
            <div class="qa-icon"><i class="fas fa-chart-bar"></i></div>
            <span class="qa-label">Reports</span>
          </a>

          <a href="/super-admin/backup/create" class="quick-action-btn" style="--qa-color:#64748b" onclick="event.preventDefault();triggerBackup()">
            <div class="qa-icon"><i class="fas fa-database"></i></div>
            <span class="qa-label">Backup Now</span>
          </a>

        </div>
      </div>
    </div>
  </div>

  <!-- Today's Snapshot -->
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-sun" style="color:#f59e0b"></i> Today's Snapshot</h3>
        <span style="font-size:12px;color:#94a3b8"><?= date('d M Y') ?></span>
      </div>
      <div class="card-body">
        <div class="d-flex flex-column gap-3">

          <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background:#f8fafc">
            <div class="d-flex align-items-center gap-10">
              <div style="width:36px;height:36px;background:rgba(99,102,241,.12);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#6366f1;font-size:15px"><i class="fas fa-user-plus"></i></div>
              <div>
                <div style="font-size:12px;color:#64748b;font-weight:500">New Registrations</div>
                <div style="font-size:18px;font-weight:800;color:#0f172a"><?= (int)($stats['users']['today'] ?? 0) ?></div>
              </div>
            </div>
            <a href="/super-admin/users?date_from=<?= date('Y-m-d') ?>" style="font-size:11px;color:#6366f1"><i class="fas fa-external-link-alt"></i></a>
          </div>

          <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background:#f8fafc">
            <div class="d-flex align-items-center gap-10">
              <div style="width:36px;height:36px;background:rgba(245,158,11,.12);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#f59e0b;font-size:15px"><i class="fas fa-rupee-sign"></i></div>
              <div>
                <div style="font-size:12px;color:#64748b;font-weight:500">Revenue Today</div>
                <div style="font-size:18px;font-weight:800;color:#0f172a">₹<?= number_format((float)($stats['revenue']['today'] ?? 0), 0) ?></div>
              </div>
            </div>
            <a href="/super-admin/finance/payments" style="font-size:11px;color:#f59e0b"><i class="fas fa-external-link-alt"></i></a>
          </div>

          <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background:#f8fafc">
            <div class="d-flex align-items-center gap-10">
              <div style="width:36px;height:36px;background:rgba(16,185,129,.12);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#10b981;font-size:15px"><i class="fas fa-video"></i></div>
              <div>
                <div style="font-size:12px;color:#64748b;font-weight:500">Live Classes Today</div>
                <div style="font-size:18px;font-weight:800;color:#0f172a"><?= (int)($stats['exams']['live'] ?? 0) ?></div>
              </div>
            </div>
            <a href="/super-admin/batches" style="font-size:11px;color:#10b981"><i class="fas fa-external-link-alt"></i></a>
          </div>

          <?php if (!empty($upcoming)): ?>
          <div style="border-top:1px solid #f1f5f9;padding-top:12px">
            <div style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">Upcoming Events</div>
            <?php foreach(array_slice($upcoming, 0, 2) as $ev): ?>
            <div class="d-flex align-items-center gap-2 mb-2">
              <div style="width:6px;height:6px;background:#6366f1;border-radius:50%;flex-shrink:0"></div>
              <div style="font-size:12.5px;color:#374151;font-weight:500;flex:1"><?= \App\Core\View::e($ev['title']) ?></div>
              <div style="font-size:11px;color:#94a3b8"><?= date('d M', strtotime($ev['start_datetime'])) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>

  <!-- System Health -->
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-server" style="color:#10b981"></i> System Health</h3>
        <span class="badge badge-soft-success"><i class="fas fa-circle" style="font-size:8px"></i> All Systems</span>
      </div>
      <div class="card-body">

        <div class="health-item">
          <span class="health-item-label">
            <i class="fas fa-database" style="color:#6366f1;width:16px"></i> Database
          </span>
          <span class="health-item-status <?= $health['db_status'] === 'operational' ? 'status-dot online' : 'status-dot danger' ?>">
            <?= ucfirst($health['db_status']) ?>
          </span>
        </div>

        <div class="health-item">
          <span class="health-item-label">
            <i class="fas fa-hdd" style="color:#f59e0b;width:16px"></i> Storage
          </span>
          <div class="d-flex align-items-center gap-2">
            <div class="progress-bar-custom" style="width:80px">
              <div class="bar" style="width:<?= min(100, $health['storage_pct']) ?>%;background:<?= $health['storage_pct'] > 80 ? '#ef4444' : '#10b981' ?>"></div>
            </div>
            <span class="health-item-status" style="color:<?= $health['storage_pct'] > 80 ? '#ef4444' : '#10b981' ?>"><?= $health['storage_pct'] ?>%</span>
          </div>
        </div>

        <div class="health-item">
          <span class="health-item-label">
            <i class="fas fa-memory" style="color:#8b5cf6;width:16px"></i> Memory
          </span>
          <span class="health-item-status status-dot online"><?= $health['memory_usage_mb'] ?> MB</span>
        </div>

        <div class="health-item">
          <span class="health-item-label">
            <i class="fas fa-shield-alt" style="color:#10b981;width:16px"></i> Security
          </span>
          <span class="health-item-status status-dot online">Protected</span>
        </div>

        <div class="health-item">
          <span class="health-item-label">
            <i class="fas fa-database" style="color:#06b6d4;width:16px"></i> Last Backup
          </span>
          <span class="health-item-status" style="font-size:12px;color:#64748b">
            <?php if ($health['last_backup']): ?>
              <?= \App\Core\View::timeAgo($health['last_backup']['created_at']) ?>
            <?php else: ?>
              <span style="color:#ef4444">Never</span>
            <?php endif; ?>
          </span>
        </div>

        <div class="health-item">
          <span class="health-item-label">
            <i class="fab fa-php" style="color:#6366f1;width:16px"></i> PHP Version
          </span>
          <span class="health-item-status" style="font-size:12px;color:#64748b"><?= $health['php_version'] ?></span>
        </div>

        <div class="mt-3">
          <a href="/super-admin/security" class="btn btn-outline-primary btn-sm w-100">
            <i class="fas fa-shield-alt"></i> View Security Center
          </a>
        </div>
      </div>
    </div>
  </div>

</div><!-- /row 2 -->

<!-- ═══ ROW 3: Charts ═══ -->
<div class="row g-3 mb-4">

  <!-- Student Growth Chart -->
  <div class="col-xl-8">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-line" style="color:#6366f1"></i> Growth Analytics</h3>
        <div class="d-flex gap-2">
          <button class="btn btn-ghost btn-sm chart-period active" data-period="6m">6M</button>
          <button class="btn btn-ghost btn-sm chart-period" data-period="3m">3M</button>
          <button class="btn btn-ghost btn-sm chart-period" data-period="1m">1M</button>
        </div>
      </div>
      <div class="card-body">
        <div class="chart-container" style="height:260px">
          <canvas id="growthChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Course Distribution -->
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-donut" style="color:#06b6d4"></i> Courses by Level</h3>
      </div>
      <div class="card-body d-flex flex-column align-items-center justify-content-center">
        <div style="height:200px;width:200px">
          <canvas id="courseDistChart"></canvas>
        </div>
        <div class="chart-legend mt-3 justify-content-center">
          <div class="chart-legend-item"><span class="chart-legend-dot" style="background:#6366f1"></span> Beginner</div>
          <div class="chart-legend-item"><span class="chart-legend-dot" style="background:#06b6d4"></span> Intermediate</div>
          <div class="chart-legend-item"><span class="chart-legend-dot" style="background:#10b981"></span> Advanced</div>
          <div class="chart-legend-item"><span class="chart-legend-dot" style="background:#f59e0b"></span> Expert</div>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- ═══ ROW 4: Activity + Revenue ═══ -->
<div class="row g-3 mb-4">

  <!-- Revenue Chart -->
  <div class="col-xl-5">
    <div class="card h-100">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-rupee-sign" style="color:#f59e0b"></i> Revenue Trend</h3>
      </div>
      <div class="card-body">
        <div class="chart-container" style="height:220px">
          <canvas id="revenueChart"></canvas>
        </div>
      </div>
      <div class="card-footer">
        <div class="d-flex justify-content-between">
          <div>
            <div style="font-size:11px;color:#94a3b8;font-weight:500">This Month</div>
            <div style="font-size:18px;font-weight:800;color:#0f172a">₹<?= number_format((float)($stats['revenue']['month'] ?? 0)) ?></div>
          </div>
          <div>
            <div style="font-size:11px;color:#94a3b8;font-weight:500">Total Revenue</div>
            <div style="font-size:18px;font-weight:800;color:#0f172a">₹<?= number_format((float)($stats['revenue']['total'] ?? 0)) ?></div>
          </div>
          <div>
            <div style="font-size:11px;color:#94a3b8;font-weight:500">Growth</div>
            <div style="font-size:18px;font-weight:800;color:<?= $stats['revenueGrowth'] >= 0 ? '#10b981' : '#ef4444' ?>">
              <?= $stats['revenueGrowth'] >= 0 ? '+' : '' ?><?= $stats['revenueGrowth'] ?>%
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Activity Feed -->
  <div class="col-xl-7">
    <div class="card h-100">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-stream" style="color:#8b5cf6"></i> Recent Activity</h3>
        <a href="/super-admin/audit-logs" class="btn btn-ghost btn-sm" style="font-size:12px">View All →</a>
      </div>
      <div class="card-body" style="padding:16px 22px;max-height:340px;overflow-y:auto" id="activityFeed">
        <?php if (!empty($recent)): ?>
        <ul class="timeline">
          <?php foreach ($recent as $log):
            $iconMap = [
              'login'          => ['fas fa-sign-in-alt',  'primary'],
              'user_created'   => ['fas fa-user-plus',    'success'],
              'user_updated'   => ['fas fa-user-edit',    'primary'],
              'user_deleted'   => ['fas fa-user-minus',   'danger'],
              'course_created' => ['fas fa-book-medical', 'success'],
              'payment'        => ['fas fa-rupee-sign',   'warning'],
              'login_failed'   => ['fas fa-exclamation',  'danger'],
            ];
            $icon  = $iconMap[$log['action']] ?? ['fas fa-circle', 'primary'];
          ?>
          <li class="timeline-item <?= $icon[1] ?>">
            <div class="timeline-icon"><i class="<?= $icon[0] ?>"></i></div>
            <div class="timeline-body">
              <div class="d-flex align-items-center gap-2">
                <?php if (!empty($log['avatar'])): ?>
                  <img src="<?= \App\Core\View::e($log['avatar']) ?>" style="width:20px;height:20px;border-radius:5px;object-fit:cover">
                <?php endif; ?>
                <span class="timeline-text"><?= \App\Core\View::e($log['description'] ?? ucwords(str_replace('_', ' ', $log['action']))) ?></span>
              </div>
              <div class="timeline-meta">
                <?= \App\Core\View::e($log['user_name'] ?? 'System') ?> •
                <?= \App\Core\View::timeAgo($log['created_at']) ?> •
                <span style="text-transform:capitalize"><?= \App\Core\View::e($log['module']) ?></span>
              </div>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-stream empty-state-icon"></i>
          <p class="empty-state-desc">No recent activity to display.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<!-- ═══ ROW 5: Recent Users ═══ -->
<div class="row g-3">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-users" style="color:#6366f1"></i> Recent Registrations</h3>
        <a href="/super-admin/users" class="btn btn-primary btn-sm">View All Users</a>
      </div>
      <div class="card-body p-0">
        <?php
          $recentUsers = (new \App\Models\User())->getRecentUsers(8);
        ?>
        <?php if (!empty($recentUsers)): ?>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>#</th>
                <th>User</th>
                <th>Role</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Last Login</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentUsers as $i => $u): ?>
              <tr>
                <td style="color:#94a3b8;font-size:12px"><?= $i + 1 ?></td>
                <td>
                  <div class="user-cell">
                    <img src="<?= \App\Core\View::e((new \App\Models\User())->avatarUrl($u)) ?>" class="avatar" alt="">
                    <div class="user-info">
                      <div class="user-name"><?= \App\Core\View::e($u['first_name'] . ' ' . $u['last_name']) ?></div>
                      <div class="user-email"><?= \App\Core\View::e($u['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="badge" style="background:<?= \App\Core\View::e($u['role_color'] ?? '#6366f1') ?>22;color:<?= \App\Core\View::e($u['role_color'] ?? '#6366f1') ?>;font-size:11px">
                    <?= \App\Core\View::e($u['role_name'] ?? '—') ?>
                  </span>
                </td>
                <td><?= \App\Core\View::badge($u['status']) ?></td>
                <td style="font-size:12.5px;color:#64748b"><?= \App\Core\View::formatDate($u['created_at'], 'd M Y') ?></td>
                <td style="font-size:12.5px;color:#94a3b8"><?= $u['last_login_at'] ? \App\Core\View::timeAgo($u['last_login_at']) : '—' ?></td>
                <td>
                  <div class="d-flex gap-1">
                    <a href="/super-admin/users/<?= $u['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="View">
                      <i class="fas fa-eye"></i>
                    </a>
                    <a href="/super-admin/users/<?= $u['id'] ?>/edit" class="btn btn-ghost btn-sm btn-icon" title="Edit">
                      <i class="fas fa-edit"></i>
                    </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="empty-state"><p class="empty-state-desc">No users registered yet.</p></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
// Dashboard charts & counters
document.addEventListener('DOMContentLoaded', function() {

  // ── Animated counter ────────────────────────────────────────
  document.querySelectorAll('.kpi-value[data-target]').forEach(el => {
    const target = parseInt(el.dataset.target) || 0;
    const prefix = el.dataset.prefix || '';
    let current  = 0;
    const step   = Math.max(1, Math.ceil(target / 60));
    const timer  = setInterval(() => {
      current = Math.min(current + step, target);
      el.textContent = prefix + (prefix === '₹' ? '₹' + current.toLocaleString('en-IN') : current.toLocaleString());
      if (current >= target) clearInterval(timer);
    }, 16);
  });

  // ── Sparkline helper ─────────────────────────────────────────
  function sparkline(id, data, color) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
    new Chart(ctx, {
      type: 'line',
      data: { labels: data.map((_, i) => i), datasets: [{ data, borderColor: color, borderWidth: 2, fill: true,
        backgroundColor: color + '18', tension: .4, pointRadius: 0 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: false } },
        scales: { x: { display: false }, y: { display: false } }, animation: { duration: 1000 } }
    });
  }

  // Random sparkline data for demo
  const rand = (n, base) => Array.from({ length: n }, (_, i) => base + Math.floor(Math.random() * base * .3) + i);
  sparkline('spark-students', rand(10, 120), '#6366f1');
  sparkline('spark-teachers', rand(10, 20),  '#06b6d4');
  sparkline('spark-courses',  rand(10, 15),  '#10b981');
  sparkline('spark-revenue',  rand(10, 5000),'#f59e0b');
  sparkline('spark-batches',  rand(10, 8),   '#8b5cf6');
  sparkline('spark-certs',    rand(10, 30),  '#ec4899');
  sparkline('spark-tickets',  rand(10, 3),   '#ef4444');
  sparkline('spark-pending',  rand(10, 4),   '#0891b2');

  // ── Growth Chart (Student + Revenue) ─────────────────────────
  const chartData = <?= json_encode($charts) ?>;
  const growthLabels   = chartData.studentGrowth.map(r => r.label);
  const studentValues  = chartData.studentGrowth.map(r => r.value);
  const revenueLabels  = chartData.revenueByMonth.map(r => r.label);
  const revenueValues  = chartData.revenueByMonth.map(r => r.value);

  const gc = document.getElementById('growthChart');
  if (gc) {
    new Chart(gc, {
      type: 'bar',
      data: {
        labels: growthLabels.length ? growthLabels : ['Jan','Feb','Mar','Apr','May','Jun'],
        datasets: [
          {
            label: 'Students',
            data: studentValues.length ? studentValues : [120,145,180,160,200,240],
            backgroundColor: 'rgba(99,102,241,.8)',
            borderRadius: 6, borderSkipped: false
          },
          {
            label: 'Revenue (₹00)',
            data: revenueValues.length ? revenueValues.map(v => v/100) : [45,62,78,55,90,110],
            backgroundColor: 'rgba(16,185,129,.8)',
            borderRadius: 6, borderSkipped: false
          }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'top', labels: { usePointStyle: true, font: { size: 12 } } } },
        scales: {
          x: { grid: { display: false }, border: { display: false } },
          y: { grid: { color: 'rgba(0,0,0,.04)' }, border: { display: false }, ticks: { font: { size: 11 } } }
        }
      }
    });
  }

  // ── Course Distribution Donut ─────────────────────────────────
  const cdc = document.getElementById('courseDistChart');
  if (cdc) {
    const distData  = chartData.coursesByLevel || [];
    const labels    = distData.map(r => r.label);
    const values    = distData.map(r => r.value);
    const colors    = ['#6366f1','#06b6d4','#10b981','#f59e0b'];
    new Chart(cdc, {
      type: 'doughnut',
      data: {
        labels: labels.length ? labels : ['Beginner','Intermediate','Advanced','Expert'],
        datasets: [{ data: values.length ? values : [40,30,20,10], backgroundColor: colors, borderWidth: 0, hoverOffset: 8 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '72%',
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw}` } } }
      }
    });
  }

  // ── Revenue Chart ─────────────────────────────────────────────
  const rc = document.getElementById('revenueChart');
  if (rc) {
    new Chart(rc, {
      type: 'line',
      data: {
        labels: revenueLabels.length ? revenueLabels : ['Jan','Feb','Mar','Apr','May','Jun'],
        datasets: [{
          label: 'Revenue (₹)',
          data: revenueValues.length ? revenueValues : [45000,62000,78000,55000,90000,110000],
          borderColor: '#f59e0b', borderWidth: 2,
          backgroundColor: 'rgba(245,158,11,.08)', fill: true, tension: .4,
          pointBackgroundColor: '#f59e0b', pointRadius: 4
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false }, border: { display: false } },
          y: { grid: { color: 'rgba(0,0,0,.04)' }, border: { display: false },
            ticks: { callback: v => '₹' + (v/1000).toFixed(0) + 'K', font: { size: 11 } } }
        }
      }
    });
  }

});

function refreshDashboard() {
  const btn = event.currentTarget;
  btn.innerHTML = '<span class="loading-spinner"></span> Refreshing…';
  btn.disabled  = true;
  setTimeout(() => location.reload(), 800);
}

function triggerBackup() {
  Swal.fire({
    title: 'Create Backup?',
    text: 'This will backup your entire database. Continue?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, Backup Now',
    confirmButtonColor: '#6366f1',
    cancelButtonText: 'Cancel'
  }).then(r => {
    if (r.isConfirmed) {
      fetch('/super-admin/backup/create', {
        method: 'POST',
        headers: { 'X-CSRF-Token': CG.csrf, 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'database' })
      })
      .then(r => r.json())
      .then(d => {
        if (d.success) Swal.fire('Backup Started!', 'Your backup is being created.', 'success');
        else Swal.fire('Error', d.message, 'error');
      });
    }
  });
}
</script>
