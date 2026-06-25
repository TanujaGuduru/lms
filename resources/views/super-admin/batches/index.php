<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Batches</span>
    </div>
    <h1 class="page-title">Batch Management</h1>
    <p class="page-subtitle">Organize students and teachers into learning batches</p>
  </div>
  <a href="/super-admin/batches/create" class="btn btn-primary btn-sm">
    <i class="fas fa-plus"></i> Create Batch
  </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php
  $db     = \App\Core\Database::getInstance();
  $bStats = $db->selectOne("SELECT COUNT(*) total, SUM(status='active') active, SUM(status='upcoming') upcoming, SUM(status='completed') completed, SUM(enrolled_count) total_students FROM batches") ?: [];
  $statItems = [
    ['label'=>'Total Batches',  'value'=>$bStats['total']??0,          'icon'=>'fas fa-layer-group',  'color'=>'#6366f1'],
    ['label'=>'Active',         'value'=>$bStats['active']??0,         'icon'=>'fas fa-play-circle',  'color'=>'#10b981'],
    ['label'=>'Upcoming',       'value'=>$bStats['upcoming']??0,       'icon'=>'fas fa-clock',        'color'=>'#f59e0b'],
    ['label'=>'Completed',      'value'=>$bStats['completed']??0,      'icon'=>'fas fa-check-circle', 'color'=>'#3b82f6'],
    ['label'=>'Total Students', 'value'=>$bStats['total_students']??0, 'icon'=>'fas fa-users',        'color'=>'#8b5cf6'],
  ];
  ?>
  <?php foreach ($statItems as $s): ?>
  <div class="col-xl col-md-4 col-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:<?= $s['color'] ?>18;color:<?= $s['color'] ?>"><i class="<?= $s['icon'] ?>"></i></div>
      <div><div class="stat-mini-value"><?= number_format((int)$s['value']) ?></div><div class="stat-mini-label"><?= $s['label'] ?></div></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-3">
    <div class="d-flex gap-3 flex-wrap align-items-center">
      <div class="table-search">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="batchSearch" placeholder="Search batch name, code…">
      </div>
      <select id="statusFilter" class="form-select" style="width:130px;font-size:13px">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="upcoming">Upcoming</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <select id="modeFilter" class="form-select" style="width:130px;font-size:13px">
        <option value="">All Modes</option>
        <option value="online">Online</option>
        <option value="offline">Offline</option>
        <option value="hybrid">Hybrid</option>
      </select>
    </div>
  </div>
</div>

<!-- Batch Table -->
<div class="table-container">
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Batch</th>
          <th>Course</th>
          <th>Mode</th>
          <th>Students</th>
          <th>Schedule</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $batches = $db->select("SELECT b.*, c.title as course_title, c.thumbnail as course_thumb FROM batches b LEFT JOIN courses c ON c.id = b.course_id ORDER BY b.created_at DESC LIMIT 30");
        foreach ($batches as $i => $b):
          $modeColors = ['online'=>'#3b82f6','offline'=>'#f59e0b','hybrid'=>'#8b5cf6'];
          $mc = $modeColors[$b['mode']] ?? '#64748b';
          $pct = $b['max_students'] > 0 ? min(100, round($b['enrolled_count'] / $b['max_students'] * 100)) : 0;
        ?>
        <tr>
          <td style="color:#94a3b8;font-size:12px"><?= $i+1 ?></td>
          <td>
            <div>
              <div style="font-weight:600;font-size:13.5px;color:#0f172a"><?= \App\Core\View::e($b['name']) ?></div>
              <div style="font-size:11.5px;color:#94a3b8;font-family:monospace"><?= \App\Core\View::e($b['code']) ?></div>
            </div>
          </td>
          <td>
            <div style="font-size:13px;color:#374151;font-weight:500;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= \App\Core\View::e($b['course_title'] ?? '—') ?>
            </div>
          </td>
          <td>
            <span class="badge" style="background:<?= $mc ?>18;color:<?= $mc ?>;text-transform:capitalize"><?= $b['mode'] ?></span>
          </td>
          <td>
            <div style="font-size:13px;color:#374151;margin-bottom:4px"><?= (int)$b['enrolled_count'] ?> / <?= (int)$b['max_students'] ?></div>
            <div class="progress-bar-custom" style="width:80px">
              <div class="bar" style="width:<?= $pct ?>%;background:<?= $pct >= 90 ? '#ef4444' : '#6366f1' ?>"></div>
            </div>
          </td>
          <td>
            <div style="font-size:12.5px;color:#374151"><?= \App\Core\View::formatDate($b['start_date'], 'd M Y') ?></div>
            <?php if ($b['time_slot']): ?>
            <div style="font-size:11.5px;color:#94a3b8"><?= \App\Core\View::e($b['time_slot']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= \App\Core\View::badge($b['status']) ?></td>
          <td>
            <div class="d-flex gap-1">
              <a href="/super-admin/batches/<?= $b['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="View"><i class="fas fa-eye"></i></a>
              <a href="/super-admin/batches/<?= $b['id'] ?>/edit" class="btn btn-ghost btn-sm btn-icon" title="Edit"><i class="fas fa-edit"></i></a>
              <a href="/super-admin/batches/<?= $b['id'] ?>/attendance" class="btn btn-ghost btn-sm btn-icon" title="Attendance"><i class="fas fa-user-check"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($batches)): ?>
        <tr><td colspan="8">
          <div class="empty-state">
            <i class="fas fa-layer-group empty-state-icon"></i>
            <h4 class="empty-state-title">No Batches Yet</h4>
            <p class="empty-state-desc">Group students and teachers into batches for organized learning.</p>
            <a href="/super-admin/batches/create" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Create First Batch</a>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
let st;
['batchSearch','statusFilter','modeFilter'].forEach(id => {
  document.getElementById(id)?.addEventListener('input', function() {
    clearTimeout(st); st = setTimeout(() => applyFilters(), id === 'batchSearch' ? 400 : 0);
  });
  document.getElementById(id)?.addEventListener('change', () => applyFilters());
});
function applyFilters() {
  const p = new URLSearchParams();
  const s  = document.getElementById('batchSearch')?.value;
  const st = document.getElementById('statusFilter')?.value;
  const m  = document.getElementById('modeFilter')?.value;
  if (s)  p.set('search', s);
  if (st) p.set('status', st);
  if (m)  p.set('mode', m);
  window.location.href = '/super-admin/batches?' + p.toString();
}
</script>
