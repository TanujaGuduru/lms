<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Support</span>
    </div>
    <h1 class="page-title">Support Center</h1>
    <p class="page-subtitle">Manage student support tickets and track resolution SLAs</p>
  </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php
  $sCards = [
    ['label'=>'Total Tickets',   'value'=>$stats['total']??0,       'icon'=>'fas fa-ticket-alt',   'color'=>'#6366f1'],
    ['label'=>'Open',            'value'=>$stats['open_tickets']??0,'icon'=>'fas fa-circle-dot',   'color'=>'#ef4444'],
    ['label'=>'In Progress',     'value'=>$stats['in_progress']??0, 'icon'=>'fas fa-spinner',      'color'=>'#f59e0b'],
    ['label'=>'Resolved',        'value'=>$stats['resolved']??0,    'icon'=>'fas fa-check-circle', 'color'=>'#10b981'],
    ['label'=>'Urgent',          'value'=>$stats['urgent']??0,      'icon'=>'fas fa-fire',         'color'=>'#dc2626'],
  ];
  ?>
  <?php foreach ($sCards as $s): ?>
  <div class="col-xl col-md-4 col-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:<?= $s['color'] ?>18;color:<?= $s['color'] ?>"><i class="<?= $s['icon'] ?>"></i></div>
      <div><div class="stat-mini-value"><?= number_format((int)$s['value']) ?></div><div class="stat-mini-label"><?= $s['label'] ?></div></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters + Ticket List -->
<div class="card">
  <div class="card-body py-3">
    <form method="GET" action="/super-admin/support" class="row g-2">
      <div class="col-md-4">
        <div class="table-search" style="margin:0"><i class="fas fa-search search-icon"></i>
          <input type="text" name="search" placeholder="Search ticket, student…" value="<?= \App\Core\View::e($filters['search'] ?? '') ?>">
        </div>
      </div>
      <div class="col-md-3">
        <select name="status" class="form-select" style="font-size:13px">
          <option value="">All Status</option>
          <option value="open" <?= ($filters['status']??'')==='open' ? 'selected' : '' ?>>Open</option>
          <option value="in_progress" <?= ($filters['status']??'')==='in_progress' ? 'selected' : '' ?>>In Progress</option>
          <option value="resolved" <?= ($filters['status']??'')==='resolved' ? 'selected' : '' ?>>Resolved</option>
          <option value="closed" <?= ($filters['status']??'')==='closed' ? 'selected' : '' ?>>Closed</option>
        </select>
      </div>
      <div class="col-md-3">
        <select name="priority" class="form-select" style="font-size:13px">
          <option value="">All Priority</option>
          <option value="low">Low</option>
          <option value="medium">Medium</option>
          <option value="high">High</option>
          <option value="urgent">Urgent</option>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>#</th><th>Subject</th><th>Student</th><th>Assigned To</th><th>Priority</th><th>Status</th><th>Replies</th><th>Created</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (empty($tickets)): ?>
        <tr><td colspan="9">
          <div class="empty-state">
            <i class="fas fa-headset empty-state-icon"></i>
            <h4 class="empty-state-title">No Tickets</h4>
            <p class="empty-state-desc">No support tickets match your filters.</p>
          </div>
        </td></tr>
        <?php else: ?>
        <?php
        $prioColors = ['low'=>'#94a3b8','medium'=>'#f59e0b','high'=>'#ef4444','urgent'=>'#dc2626'];
        foreach ($tickets as $t):
          $pc = $prioColors[$t['priority']] ?? '#94a3b8';
          $isOld = strtotime($t['created_at']) < strtotime('-2 days') && $t['status'] !== 'resolved';
        ?>
        <tr <?= $isOld ? 'style="background:#fef2f2"' : '' ?>>
          <td>
            <code style="font-size:11.5px;color:#6366f1;background:#f1f5f9;padding:2px 6px;border-radius:4px">
              <?= \App\Core\View::e($t['ticket_number'] ?? '#' . $t['id']) ?>
            </code>
          </td>
          <td>
            <a href="/super-admin/support/<?= $t['id'] ?>" style="font-size:13.5px;font-weight:600;color:#0f172a;text-decoration:none">
              <?= \App\Core\View::e($t['subject']) ?>
            </a>
          </td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div style="width:26px;height:26px;background:#6366f118;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:10px;color:#6366f1">
                <i class="fas fa-user"></i>
              </div>
              <span style="font-size:13px;color:#374151"><?= \App\Core\View::e($t['student_name']) ?></span>
            </div>
          </td>
          <td style="font-size:12.5px;color:#64748b">
            <?= $t['assignee_name'] ? \App\Core\View::e($t['assignee_name']) : '<span style="color:#94a3b8">Unassigned</span>' ?>
          </td>
          <td>
            <span class="badge" style="background:<?= $pc ?>18;color:<?= $pc ?>">
              <?= ucfirst($t['priority']) ?>
            </span>
          </td>
          <td><?= \App\Core\View::badge($t['status']) ?></td>
          <td style="font-size:13px;font-weight:600;color:#374151"><?= (int)$t['reply_count'] ?></td>
          <td style="font-size:12px;color:#94a3b8"><?= \App\Core\View::timeAgo($t['created_at']) ?></td>
          <td>
            <a href="/super-admin/support/<?= $t['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Open Ticket">
              <i class="fas fa-arrow-right"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if (($meta['last_page'] ?? 1) > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <div style="font-size:13px;color:#64748b">Showing <?= $meta['from'] ?>–<?= $meta['to'] ?> of <?= number_format($meta['total']) ?></div>
    <nav><ul class="pagination" style="margin:0;gap:4px">
      <?php for ($p = max(1,$meta['current_page']-3); $p <= min($meta['last_page'],$meta['current_page']+3); $p++): ?>
      <li class="page-item <?= $p === $meta['current_page'] ? 'active' : '' ?>">
        <a class="page-link" href="?<?= http_build_query(array_merge($filters??[],['page'=>$p])) ?>"><?= $p ?></a>
      </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
