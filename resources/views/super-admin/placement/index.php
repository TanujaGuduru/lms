<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Placement</span>
    </div>
    <h1 class="page-title">Placement Center</h1>
    <p class="page-subtitle">Connect students with companies and track placement outcomes</p>
  </div>
  <div class="d-flex gap-2">
    <a href="/super-admin/placement/companies" class="btn btn-secondary btn-sm"><i class="fas fa-building"></i> Companies</a>
    <a href="/super-admin/placement/jobs" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Post Job</a>
  </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php
  $pCards = [
    ['label'=>'Companies',        'value'=>$stats['companies']??0,  'icon'=>'fas fa-building',    'color'=>'#6366f1'],
    ['label'=>'Open Jobs',        'value'=>$stats['open_jobs']??0,  'icon'=>'fas fa-briefcase',   'color'=>'#10b981'],
    ['label'=>'Applications',     'value'=>$stats['applications']??0,'icon'=>'fas fa-file-alt',   'color'=>'#f59e0b'],
    ['label'=>'Students Placed',  'value'=>$stats['placed']??0,     'icon'=>'fas fa-user-check',  'color'=>'#8b5cf6'],
    ['label'=>'Avg Package',      'value'=>$stats['avg_salary'] ? '₹'.number_format((float)$stats['avg_salary']/100000,1).'L' : '—','icon'=>'fas fa-rupee-sign','color'=>'#06b6d4'],
  ];
  ?>
  <?php foreach ($pCards as $s): ?>
  <div class="col-xl col-md-4 col-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:<?= $s['color'] ?>18;color:<?= $s['color'] ?>"><i class="<?= $s['icon'] ?>"></i></div>
      <div><div class="stat-mini-value"><?= $s['value'] ?></div><div class="stat-mini-label"><?= $s['label'] ?></div></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <!-- Recent Placements -->
  <div class="col-xl-7">
    <div class="card h-100">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-trophy" style="color:#f59e0b"></i> Recent Placements</h3>
        <a href="/super-admin/placement/applications?status=accepted" class="btn btn-ghost btn-sm">View All →</a>
      </div>
      <div class="card-body" style="padding:0">
        <?php if (empty($recentPlacements)): ?>
        <div class="empty-state" style="padding:40px">
          <i class="fas fa-trophy empty-state-icon"></i>
          <p class="empty-state-desc">No placements yet. Add job openings to get started.</p>
        </div>
        <?php else: ?>
        <?php foreach ($recentPlacements as $p): ?>
        <div class="d-flex align-items-center gap-3 px-4 py-3" style="border-bottom:1px solid #f1f5f9">
          <div style="width:36px;height:36px;background:#6366f118;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;color:#6366f1;flex-shrink:0">
            <i class="fas fa-user"></i>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:13.5px;font-weight:700;color:#0f172a"><?= \App\Core\View::e($p['student_name']) ?></div>
            <div style="font-size:12px;color:#64748b">
              <?= \App\Core\View::e($p['job_title']) ?> at <strong><?= \App\Core\View::e($p['company_name']) ?></strong>
            </div>
          </div>
          <?php if ($p['salary_offered']): ?>
          <div style="font-size:13px;font-weight:700;color:#10b981">₹<?= number_format((float)$p['salary_offered']/100000,1) ?>L/yr</div>
          <?php endif; ?>
          <span class="badge badge-soft-success">Placed</span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Top Companies -->
  <div class="col-xl-5">
    <div class="card h-100">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-star" style="color:#6366f1"></i> Top Hiring Companies</h3>
        <a href="/super-admin/placement/companies" class="btn btn-ghost btn-sm">All →</a>
      </div>
      <div class="card-body" style="padding:8px 0">
        <?php if (empty($topCompanies)): ?>
        <div class="empty-state" style="padding:32px">
          <i class="fas fa-building empty-state-icon"></i>
          <p class="empty-state-desc">No companies added yet.</p>
          <a href="/super-admin/placement/companies" class="btn btn-primary btn-sm">Add Company</a>
        </div>
        <?php else: ?>
        <?php foreach ($topCompanies as $co): ?>
        <a href="/super-admin/placement/companies" class="d-flex align-items-center gap-3 px-4 py-2"
           style="text-decoration:none;transition:background .15s" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
          <div style="width:36px;height:36px;background:#f1f5f9;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden">
            <?php if ($co['logo']): ?>
            <img src="<?= \App\Core\View::e($co['logo']) ?>" style="width:100%;height:100%;object-fit:cover">
            <?php else: ?>
            <i class="fas fa-building" style="color:#94a3b8;font-size:14px"></i>
            <?php endif; ?>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:13.5px;font-weight:600;color:#0f172a"><?= \App\Core\View::e($co['name']) ?></div>
            <div style="font-size:11.5px;color:#94a3b8"><?= \App\Core\View::e($co['industry'] ?? '') ?></div>
          </div>
          <div class="d-flex gap-3 text-end">
            <div style="font-size:11.5px;color:#64748b"><span style="font-weight:700;color:#6366f1"><?= $co['jobs'] ?></span><br>Jobs</div>
            <div style="font-size:11.5px;color:#64748b"><span style="font-weight:700;color:#10b981"><?= $co['applications'] ?></span><br>Applied</div>
          </div>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
