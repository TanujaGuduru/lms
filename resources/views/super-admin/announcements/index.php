<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Announcements</span>
    </div>
    <h1 class="page-title">Announcement Center</h1>
    <p class="page-subtitle">Broadcast messages to students, teachers and parents across all channels</p>
  </div>
  <a href="/super-admin/announcements/create" class="btn btn-primary btn-sm">
    <i class="fas fa-plus"></i> New Announcement
  </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php
  $db   = \App\Core\Database::getInstance();
  $aStats = $db->selectOne("SELECT COUNT(*) total, SUM(status='sent') sent, SUM(status='draft') drafts, SUM(status='scheduled') scheduled, SUM(read_count) total_reads FROM announcements") ?: [];
  $statItems = [
    ['label'=>'Total',     'value'=>$aStats['total']??0,       'icon'=>'fas fa-bullhorn',    'color'=>'#6366f1'],
    ['label'=>'Sent',      'value'=>$aStats['sent']??0,        'icon'=>'fas fa-paper-plane',  'color'=>'#10b981'],
    ['label'=>'Drafts',    'value'=>$aStats['drafts']??0,      'icon'=>'fas fa-file-alt',     'color'=>'#f59e0b'],
    ['label'=>'Scheduled', 'value'=>$aStats['scheduled']??0,   'icon'=>'fas fa-clock',        'color'=>'#3b82f6'],
    ['label'=>'Total Reads','value'=>$aStats['total_reads']??0,'icon'=>'fas fa-eye',          'color'=>'#8b5cf6'],
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

<!-- List -->
<div class="table-container">
  <div class="table-toolbar">
    <div class="table-toolbar-left">
      <div class="table-search"><i class="fas fa-search search-icon"></i><input type="text" id="annSearch" placeholder="Search announcements…"></div>
      <select id="typeFilter" class="form-select" style="width:130px;font-size:13px">
        <option value="">All Types</option>
        <option value="general">General</option>
        <option value="urgent">Urgent</option>
        <option value="event">Event</option>
        <option value="maintenance">Maintenance</option>
      </select>
      <select id="statusFilter" class="form-select" style="width:130px;font-size:13px">
        <option value="">All Status</option>
        <option value="draft">Draft</option>
        <option value="scheduled">Scheduled</option>
        <option value="sent">Sent</option>
      </select>
    </div>
  </div>

  <?php
  $announcements = $db->select("SELECT a.*, CONCAT(u.first_name,' ',u.last_name) as author FROM announcements a LEFT JOIN users u ON u.id = a.created_by ORDER BY a.created_at DESC LIMIT 25");
  $typeColors = ['general'=>'#6366f1','urgent'=>'#ef4444','event'=>'#f59e0b','maintenance'=>'#64748b','feature'=>'#10b981'];
  $typIcons   = ['general'=>'fas fa-info-circle','urgent'=>'fas fa-exclamation-triangle','event'=>'fas fa-calendar','maintenance'=>'fas fa-wrench','feature'=>'fas fa-star'];
  $channelIcons = ['email'=>'fas fa-envelope','sms'=>'fas fa-sms','whatsapp'=>'fab fa-whatsapp','push'=>'fas fa-bell','inapp'=>'fas fa-desktop'];
  ?>

  <div class="announcement-list p-3" style="display:flex;flex-direction:column;gap:12px">
    <?php if (empty($announcements)): ?>
    <div class="empty-state">
      <i class="fas fa-bullhorn empty-state-icon"></i>
      <h4 class="empty-state-title">No Announcements Yet</h4>
      <p class="empty-state-desc">Keep your community informed with timely announcements.</p>
      <a href="/super-admin/announcements/create" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Create First</a>
    </div>
    <?php else: ?>
    <?php foreach ($announcements as $ann):
      $tc = $typeColors[$ann['type']] ?? '#6366f1';
      $ti = $typIcons[$ann['type']]   ?? 'fas fa-bullhorn';
      $channels = json_decode($ann['channels'] ?? '[]', true) ?: [];
      $audience = json_decode($ann['audience']  ?? '{}', true) ?: [];
    ?>
    <div class="card" style="border-left:4px solid <?= $tc ?>;transition:all .2s" onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.08)'" onmouseout="this.style.boxShadow=''">
      <div class="card-body py-3">
        <div class="d-flex align-items-start gap-3">

          <!-- Icon -->
          <div style="width:44px;height:44px;background:<?= $tc ?>18;border-radius:12px;display:flex;align-items:center;justify-content:center;color:<?= $tc ?>;font-size:18px;flex-shrink:0">
            <i class="<?= $ti ?>"></i>
          </div>

          <!-- Content -->
          <div style="flex:1;min-width:0">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
              <h4 style="font-size:14px;font-weight:700;color:#0f172a;margin:0"><?= \App\Core\View::e($ann['title']) ?></h4>
              <?= \App\Core\View::badge($ann['status']) ?>
              <?php if ($ann['is_pinned']): ?><span class="badge badge-soft-warning"><i class="fas fa-thumbtack"></i> Pinned</span><?php endif; ?>
              <?php
              $priColors = ['low'=>'badge-soft-secondary','medium'=>'badge-soft-info','high'=>'badge-soft-warning','critical'=>'badge-soft-danger'];
              ?>
              <span class="badge <?= $priColors[$ann['priority']] ?? '' ?>"><?= ucfirst($ann['priority']) ?></span>
            </div>
            <p style="font-size:13px;color:#64748b;margin:0 0 10px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">
              <?= \App\Core\View::e($ann['content']) ?>
            </p>

            <!-- Meta row -->
            <div class="d-flex align-items-center gap-3 flex-wrap">
              <!-- Channels -->
              <div class="d-flex gap-2">
                <?php foreach ($channels as $ch): ?>
                <span title="<?= ucfirst($ch) ?>" style="color:#64748b;font-size:14px"><i class="<?= $channelIcons[$ch] ?? 'fas fa-share' ?>"></i></span>
                <?php endforeach; ?>
              </div>

              <!-- Audience -->
              <?php if (!empty($audience['roles'])): ?>
              <span style="font-size:12px;color:#94a3b8"><i class="fas fa-users"></i> <?= implode(', ', array_map('ucfirst', $audience['roles'])) ?></span>
              <?php endif; ?>

              <!-- Stats -->
              <?php if ($ann['sent_count'] > 0): ?>
              <span style="font-size:12px;color:#94a3b8"><i class="fas fa-paper-plane"></i> <?= number_format($ann['sent_count']) ?> sent</span>
              <span style="font-size:12px;color:#94a3b8"><i class="fas fa-eye"></i> <?= number_format($ann['read_count']) ?> read</span>
              <?php endif; ?>

              <!-- Time -->
              <span style="font-size:12px;color:#94a3b8;margin-left:auto">
                By <strong><?= \App\Core\View::e($ann['author'] ?? 'System') ?></strong> · <?= \App\Core\View::timeAgo($ann['created_at']) ?>
              </span>
            </div>
          </div>

          <!-- Actions -->
          <div class="d-flex gap-1" style="flex-shrink:0">
            <?php if ($ann['status'] === 'draft'): ?>
            <button onclick="sendAnnouncement(<?= $ann['id'] ?>)" class="btn btn-success btn-sm" title="Send Now">
              <i class="fas fa-paper-plane"></i>
            </button>
            <?php endif; ?>
            <a href="/super-admin/announcements/<?= $ann['id'] ?>/edit" class="btn btn-ghost btn-sm btn-icon" title="Edit"><i class="fas fa-edit"></i></a>
            <button onclick="confirmDelete('/super-admin/announcements/<?= $ann['id'] ?>/delete','<?= \App\Core\View::e(addslashes($ann['title'])) ?>',() => this.closest('.card').remove())"
                    class="btn btn-ghost btn-sm btn-icon" title="Delete" style="color:#ef4444"><i class="fas fa-trash"></i></button>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
function sendAnnouncement(id) {
  Swal.fire({
    title: 'Send Announcement?',
    html: `<p style="color:#64748b">This will broadcast the announcement to all selected recipients immediately.</p>`,
    icon: 'question', showCancelButton: true,
    confirmButtonColor: '#10b981', confirmButtonText: '<i class="fas fa-paper-plane"></i> Send Now',
  }).then(async r => {
    if (r.isConfirmed) {
      const data = await cgFetch(`/super-admin/announcements/${id}/send`, { method: 'POST' });
      if (data.success) { CGToast.success('Announcement sent!'); setTimeout(() => location.reload(), 1000); }
    }
  });
}
</script>
