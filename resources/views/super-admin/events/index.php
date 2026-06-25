<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Events</span>
    </div>
    <h1 class="page-title">Events & Webinars</h1>
    <p class="page-subtitle">Create and manage platform events, workshops, and live sessions</p>
  </div>
  <a href="/super-admin/events/create" class="btn btn-primary btn-sm">
    <i class="fas fa-plus"></i> New Event
  </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php
  $evCards = [
    ['label'=>'Total Events', 'value'=>$stats['total']??0,    'icon'=>'fas fa-calendar',      'color'=>'#6366f1'],
    ['label'=>'Upcoming',     'value'=>$stats['upcoming']??0, 'icon'=>'fas fa-calendar-plus', 'color'=>'#3b82f6'],
    ['label'=>'Live Now',     'value'=>$stats['live']??0,     'icon'=>'fas fa-circle',        'color'=>'#ef4444'],
    ['label'=>'Past',         'value'=>$stats['past']??0,     'icon'=>'fas fa-history',       'color'=>'#94a3b8'],
  ];
  ?>
  <?php foreach ($evCards as $s): ?>
  <div class="col-xl-3 col-md-6">
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
    <form method="GET" action="/super-admin/events" class="d-flex gap-2">
      <div class="table-search" style="margin:0;flex:1"><i class="fas fa-search search-icon"></i>
        <input type="text" name="search" placeholder="Search events…" value="<?= \App\Core\View::e($filters['search'] ?? '') ?>">
      </div>
      <select name="type" class="form-select" style="width:160px;font-size:13px">
        <option value="">All Types</option>
        <?php foreach (['webinar'=>'Webinar','workshop'=>'Workshop','seminar'=>'Seminar','hackathon'=>'Hackathon','other'=>'Other'] as $k=>$v): ?>
        <option value="<?= $k ?>" <?= ($filters['type']??'') === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
    </form>
  </div>
</div>

<!-- Event Cards -->
<?php if (empty($events['data'] ?? $events)): ?>
<div class="card">
  <div class="card-body">
    <div class="empty-state" style="padding:60px">
      <i class="fas fa-calendar empty-state-icon"></i>
      <h4 class="empty-state-title">No Events Yet</h4>
      <p class="empty-state-desc">Create your first event to engage your community.</p>
      <a href="/super-admin/events/create" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Create Event</a>
    </div>
  </div>
</div>
<?php else: ?>
<div class="row g-3">
  <?php
  $typeColors = ['webinar'=>'#6366f1','workshop'=>'#10b981','seminar'=>'#f59e0b','hackathon'=>'#ef4444','other'=>'#64748b'];
  $events = $events['data'] ?? $events;
  foreach ($events as $ev):
    $tc = $typeColors[$ev['type']] ?? '#64748b';
    $now = time();
    $starts = strtotime($ev['starts_at']);
    $ends   = strtotime($ev['ends_at']);
    $status = $starts > $now ? 'upcoming' : ($ends >= $now ? 'live' : 'ended');
    $stColors = ['upcoming'=>'#3b82f6','live'=>'#ef4444','ended'=>'#94a3b8'];
  ?>
  <div class="col-xl-4 col-md-6">
    <div class="card" style="border-top:3px solid <?= $tc ?>;transition:all .2s" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,.1)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
      <?php if ($ev['banner']): ?>
      <div style="height:160px;overflow:hidden;border-radius:0">
        <img src="<?= \App\Core\View::e($ev['banner']) ?>" style="width:100%;height:100%;object-fit:cover">
      </div>
      <?php endif; ?>
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between mb-2">
          <span class="badge" style="background:<?= $tc ?>18;color:<?= $tc ?>;text-transform:capitalize"><?= $ev['type'] ?></span>
          <span class="badge" style="background:<?= $stColors[$status] ?>18;color:<?= $stColors[$status] ?>">
            <?php if ($status === 'live'): ?><span style="display:inline-block;width:6px;height:6px;background:<?= $stColors[$status] ?>;border-radius:50%;margin-right:4px"></span><?php endif; ?>
            <?= ucfirst($status) ?>
          </span>
        </div>

        <h4 style="font-size:15px;font-weight:700;color:#0f172a;margin-bottom:8px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">
          <?= \App\Core\View::e($ev['title']) ?>
        </h4>

        <div class="d-flex flex-column gap-1 mb-3" style="font-size:12.5px;color:#64748b">
          <div><i class="fas fa-calendar" style="width:14px;color:#94a3b8"></i> <?= \App\Core\View::formatDate($ev['starts_at'], 'd M Y, H:i') ?></div>
          <div><i class="fas fa-clock" style="width:14px;color:#94a3b8"></i> <?= $ev['is_online'] ? 'Online' : 'In-Person' ?> · <?= $ev['is_free'] ? 'Free' : 'Paid' ?></div>
          <div><i class="fas fa-users" style="width:14px;color:#94a3b8"></i> <?= number_format((int)$ev['registrations']) ?> / <?= number_format((int)$ev['max_seats']) ?> registered</div>
        </div>

        <!-- Registration progress -->
        <div class="progress" style="height:4px;border-radius:100px;margin-bottom:12px;background:#f1f5f9">
          <?php $pct = $ev['max_seats'] > 0 ? round($ev['registrations'] / $ev['max_seats'] * 100) : 0; ?>
          <div class="progress-bar" style="width:<?= min($pct, 100) ?>%;background:<?= $pct >= 90 ? '#ef4444' : $tc ?>;border-radius:100px"></div>
        </div>

        <div class="d-flex gap-2">
          <a href="/super-admin/events/<?= $ev['id'] ?>" class="btn btn-primary btn-sm flex-fill">View Details</a>
          <a href="/super-admin/events/<?= $ev['id'] ?>/edit" class="btn btn-ghost btn-sm btn-icon" title="Edit"><i class="fas fa-edit"></i></a>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
