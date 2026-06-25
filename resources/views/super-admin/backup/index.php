<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Backup & Recovery</span>
    </div>
    <h1 class="page-title">Backup & Recovery</h1>
    <p class="page-subtitle">Create, schedule, and restore platform backups</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-secondary btn-sm" onclick="createBackup('database')">
      <i class="fas fa-database"></i> Backup DB
    </button>
    <button class="btn btn-primary btn-sm" onclick="createBackup('full')">
      <i class="fas fa-hdd"></i> Full Backup
    </button>
  </div>
</div>

<?php
$diskUsed    = $diskTotal - $diskFree;
$diskPercent = $diskTotal > 0 ? round($diskUsed / $diskTotal * 100) : 0;
?>

<!-- Disk Usage + Stats -->
<div class="row g-3 mb-4">
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-4">
          <div style="width:48px;height:48px;background:#6366f118;border-radius:14px;display:flex;align-items:center;justify-content:center;color:#6366f1;font-size:22px">
            <i class="fas fa-server"></i>
          </div>
          <div>
            <div style="font-size:14px;font-weight:700;color:#0f172a">Storage Status</div>
            <div style="font-size:12px;color:#94a3b8">Server disk usage</div>
          </div>
        </div>
        <div style="font-size:28px;font-weight:800;color:#0f172a;margin-bottom:4px">
          <?= round($diskUsed / 1073741824, 1) ?> GB
          <span style="font-size:14px;font-weight:400;color:#94a3b8">/ <?= round($diskTotal / 1073741824, 1) ?> GB</span>
        </div>
        <div class="progress" style="height:8px;border-radius:100px;margin:12px 0;background:#f1f5f9">
          <div class="progress-bar" role="progressbar"
               style="width:<?= $diskPercent ?>%;background:<?= $diskPercent > 85 ? '#ef4444' : ($diskPercent > 65 ? '#f59e0b' : '#10b981') ?>;border-radius:100px">
          </div>
        </div>
        <div style="font-size:13px;color:#64748b">
          <?= $diskPercent ?>% used &middot; <?= round($diskFree / 1073741824, 1) ?> GB free
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-8">
    <div class="row g-3 h-100">
      <?php
      $bStats = [
        'total'     => count($backups),
        'completed' => count(array_filter($backups, fn($b) => $b['status'] === 'completed')),
        'latest'    => $backups[0]['created_at'] ?? null,
        'total_size'=> array_sum(array_column($backups, 'file_size')),
      ];
      $bCards = [
        ['label'=>'Total Backups', 'value'=>$bStats['total'],     'icon'=>'fas fa-archive',   'color'=>'#6366f1'],
        ['label'=>'Completed',     'value'=>$bStats['completed'], 'icon'=>'fas fa-check-circle','color'=>'#10b981'],
        ['label'=>'Storage Used',  'value'=>round($bStats['total_size']/1048576,1).' MB', 'icon'=>'fas fa-cloud','color'=>'#06b6d4'],
        ['label'=>'Last Backup',   'value'=>$bStats['latest'] ? \App\Core\View::timeAgo($bStats['latest']) : 'Never', 'icon'=>'fas fa-clock','color'=>$bStats['latest'] && strtotime($bStats['latest']) > strtotime('-2 days') ? '#10b981' : '#ef4444'],
      ];
      ?>
      <?php foreach ($bCards as $bc): ?>
      <div class="col-md-6">
        <div class="stat-mini">
          <div class="stat-mini-icon" style="background:<?= $bc['color'] ?>18;color:<?= $bc['color'] ?>"><i class="<?= $bc['icon'] ?>"></i></div>
          <div><div class="stat-mini-value"><?= $bc['value'] ?></div><div class="stat-mini-label"><?= $bc['label'] ?></div></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Backup History -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-history" style="color:#6366f1"></i> Backup History</h3>
    <div style="font-size:13px;color:#94a3b8">Last 30 backups</div>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Filename</th><th>Type</th><th>Size</th>
          <th>Duration</th><th>Status</th><th>Created By</th><th>When</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($backups)): ?>
        <tr>
          <td colspan="8">
            <div class="empty-state">
              <i class="fas fa-archive empty-state-icon"></i>
              <h4 class="empty-state-title">No Backups Yet</h4>
              <p class="empty-state-desc">Create your first backup to protect your data.</p>
              <button class="btn btn-primary btn-sm" onclick="createBackup('full')"><i class="fas fa-plus"></i> Create Backup</button>
            </div>
          </td>
        </tr>
        <?php else: ?>
        <?php foreach ($backups as $backup):
          $typeColors = ['full'=>'#6366f1','database'=>'#10b981','files'=>'#f59e0b'];
          $tc = $typeColors[$backup['type']] ?? '#64748b';

          $duration = '';
          if ($backup['started_at'] && $backup['completed_at']) {
            $secs = strtotime($backup['completed_at']) - strtotime($backup['started_at']);
            $duration = $secs < 60 ? "{$secs}s" : floor($secs/60) . 'm ' . ($secs%60) . 's';
          }
        ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div style="width:32px;height:32px;background:<?= $tc ?>18;border-radius:8px;display:flex;align-items:center;justify-content:center;color:<?= $tc ?>;font-size:13px">
                <i class="fas fa-file-zipper"></i>
              </div>
              <code style="font-size:12px;color:#374151"><?= \App\Core\View::e($backup['filename']) ?></code>
            </div>
          </td>
          <td>
            <span class="badge" style="background:<?= $tc ?>18;color:<?= $tc ?>;text-transform:capitalize">
              <?= $backup['type'] ?>
            </span>
          </td>
          <td style="font-size:13px;color:#64748b">
            <?= $backup['file_size'] ? round($backup['file_size']/1048576, 1) . ' MB' : '—' ?>
          </td>
          <td style="font-size:13px;color:#94a3b8"><?= $duration ?: '—' ?></td>
          <td><?= \App\Core\View::badge($backup['status']) ?></td>
          <td style="font-size:13px;color:#64748b"><?= \App\Core\View::e($backup['created_by'] ?? 'System') ?></td>
          <td>
            <div style="font-size:12.5px;color:#374151"><?= \App\Core\View::formatDate($backup['created_at'], 'd M Y') ?></div>
            <div style="font-size:11px;color:#94a3b8"><?= \App\Core\View::formatDate($backup['created_at'], 'H:i') ?></div>
          </td>
          <td>
            <div class="d-flex gap-1">
              <?php if ($backup['status'] === 'completed'): ?>
              <a href="/super-admin/backup/<?= $backup['id'] ?>/download"
                 class="btn btn-ghost btn-sm btn-icon" title="Download">
                <i class="fas fa-download"></i>
              </a>
              <?php endif; ?>
              <button onclick="confirmDelete('/super-admin/backup/<?= $backup['id'] ?>/delete', '<?= \App\Core\View::e($backup['filename']) ?>', () => this.closest('tr').remove())"
                      class="btn btn-ghost btn-sm btn-icon" title="Delete" style="color:#ef4444">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Create Backup Loading State -->
<div id="backupProgress" style="display:none;position:fixed;bottom:24px;right:24px;background:#fff;border-radius:16px;padding:20px 24px;box-shadow:0 8px 32px rgba(0,0,0,.15);border:1px solid #e2e8f0;z-index:9999;min-width:280px">
  <div class="d-flex align-items-center gap-3">
    <div class="loading-spinner" style="width:24px;height:24px;border-width:3px"></div>
    <div>
      <div style="font-size:14px;font-weight:600;color:#0f172a">Creating backup…</div>
      <div style="font-size:12px;color:#64748b" id="backupProgressMsg">Initializing…</div>
    </div>
  </div>
</div>

<script>
async function createBackup(type) {
  const msgs = {
    'full': 'Full backup (DB + files)',
    'database': 'Database-only backup',
    'files': 'Files-only backup',
  };

  const result = await Swal.fire({
    title: `Create ${msgs[type]}?`,
    html: '<p style="color:#64748b">This may take a few minutes. You can continue working while it runs.</p>',
    icon: 'question', showCancelButton: true,
    confirmButtonColor: '#6366f1', confirmButtonText: '<i class="fas fa-play"></i> Start Backup'
  });

  if (!result.isConfirmed) return;

  const prog = document.getElementById('backupProgress');
  const msg  = document.getElementById('backupProgressMsg');
  prog.style.display = 'block';
  msg.textContent = 'Running backup…';

  const data = await cgFetch('/super-admin/backup/create', {
    method: 'POST',
    body: JSON.stringify({ type })
  });

  prog.style.display = 'none';

  if (data.success) {
    CGToast.success('Backup created: ' + (data.data?.filename || ''));
    setTimeout(() => location.reload(), 1200);
  } else {
    CGToast.error(data.message || 'Backup failed');
  }
}
</script>
