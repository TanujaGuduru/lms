<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/events">Events</a>
      <span class="sep">/</span><span><?= \App\Core\View::e($event['title']) ?></span>
    </div>
    <h1 class="page-title"><?= \App\Core\View::e($event['title']) ?></h1>
  </div>
  <div class="d-flex gap-2">
    <a href="/super-admin/events/<?= $event['id'] ?>/edit" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i> Edit</a>
    <a href="/super-admin/events" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>

<?php
$now    = time();
$starts = strtotime($event['start_datetime']);
$ends   = strtotime($event['end_datetime']);
$status = $starts > $now ? 'upcoming' : ($ends >= $now ? 'live' : 'ended');
$typeColors = ['webinar'=>'#6366f1','workshop'=>'#10b981','seminar'=>'#f59e0b','hackathon'=>'#ef4444','other'=>'#64748b'];
$tc = $typeColors[$event['type']] ?? '#64748b';
?>

<div class="row g-3">
  <!-- Left -->
  <div class="col-xl-8">
    <?php if ($event['thumbnail']): ?>
    <div style="border-radius:14px;overflow:hidden;margin-bottom:16px;max-height:320px">
      <img src="<?= \App\Core\View::e($event['thumbnail']) ?>" style="width:100%;object-fit:cover">
    </div>
    <?php endif; ?>

    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex gap-2 mb-3">
          <span class="badge" style="background:<?= $tc ?>18;color:<?= $tc ?>;text-transform:capitalize"><?= $event['type'] ?></span>
          <?php if ($status === 'live'): ?>
          <span class="badge" style="background:#ef444418;color:#ef4444"><span style="display:inline-block;width:6px;height:6px;background:#ef4444;border-radius:50%;margin-right:4px"></span>Live Now</span>
          <?php elseif ($status === 'upcoming'): ?>
          <span class="badge" style="background:#3b82f618;color:#3b82f6">Upcoming</span>
          <?php else: ?>
          <span class="badge" style="background:#94a3b818;color:#94a3b8">Ended</span>
          <?php endif; ?>
          <?php if (!$event['is_paid']): ?>
          <span class="badge" style="background:#10b98118;color:#10b981">Free</span>
          <?php endif; ?>
        </div>

        <div style="font-size:14px;line-height:1.8;color:#374151"><?= nl2br(\App\Core\View::e($event['description'] ?? '')) ?></div>

        <div class="row g-3 mt-3 pt-3" style="border-top:1px solid #f1f5f9">
          <div class="col-md-4">
            <div style="font-size:11.5px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px">Starts</div>
            <div style="font-size:14px;font-weight:600;color:#374151"><?= \App\Core\View::formatDate($event['start_datetime'], 'd M Y') ?></div>
            <div style="font-size:12.5px;color:#64748b"><?= \App\Core\View::formatDate($event['start_datetime'], 'H:i') ?></div>
          </div>
          <div class="col-md-4">
            <div style="font-size:11.5px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px">Ends</div>
            <div style="font-size:14px;font-weight:600;color:#374151"><?= \App\Core\View::formatDate($event['end_datetime'], 'd M Y') ?></div>
            <div style="font-size:12.5px;color:#64748b"><?= \App\Core\View::formatDate($event['end_datetime'], 'H:i') ?></div>
          </div>
          <div class="col-md-4">
            <div style="font-size:11.5px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px">Format</div>
            <div style="font-size:14px;font-weight:600;color:#374151"><?= !empty($event['venue']) ? 'In-Person' : (!empty($event['meeting_link']) ? 'Online' : '—') ?></div>
            <?php if ($event['venue']): ?>
            <div style="font-size:12.5px;color:#64748b"><?= \App\Core\View::e($event['venue']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!empty($event['meeting_link'])): ?>
        <div class="mt-3 p-3" style="background:#f0fdf4;border-radius:10px">
          <div style="font-size:12.5px;font-weight:600;color:#059669;margin-bottom:4px"><i class="fas fa-video"></i> Meeting Link</div>
          <a href="<?= \App\Core\View::e($event['meeting_link']) ?>" target="_blank" style="font-size:13px;color:#6366f1;word-break:break-all"><?= \App\Core\View::e($event['meeting_link']) ?></a>
          <?php if ($event['meeting_password']): ?>
          <div style="font-size:12px;color:#64748b;margin-top:4px">Password: <code><?= \App\Core\View::e($event['meeting_password']) ?></code></div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Registrations -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-users" style="color:#6366f1"></i> Registrations (<?= count($registrations) ?>)</h3>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Student</th><th>Email</th><th>Registered</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (empty($registrations)): ?>
            <tr><td colspan="4"><div class="empty-state" style="padding:24px"><p class="empty-state-desc">No registrations yet.</p></div></td></tr>
            <?php else: ?>
            <?php foreach ($registrations as $r): ?>
            <tr>
              <td style="font-size:13.5px;font-weight:600;color:#374151"><?= \App\Core\View::e($r['student_name']) ?></td>
              <td style="font-size:12.5px;color:#64748b"><?= \App\Core\View::e($r['email']) ?></td>
              <td style="font-size:12.5px;color:#94a3b8"><?= \App\Core\View::timeAgo($r['registered_at']) ?></td>
              <td><?= \App\Core\View::badge(!empty($r['attended']) ? 'Attended' : 'Registered') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Right Sidebar -->
  <div class="col-xl-4">
    <!-- Stats -->
    <div class="card mb-3">
      <div class="card-header"><h3 class="card-title" style="font-size:13.5px">Registration Stats</h3></div>
      <div class="card-body" style="padding:16px">
        <?php $pct = $event['max_participants'] > 0 ? round(count($registrations) / $event['max_participants'] * 100) : 0; ?>
        <div class="d-flex justify-content-between mb-1" style="font-size:13px">
          <span style="color:#64748b">Registrations</span>
          <span style="font-weight:700"><?= count($registrations) ?> / <?= $event['max_participants'] ?: '∞' ?></span>
        </div>
        <div class="progress" style="height:8px;border-radius:100px;background:#f1f5f9;margin-bottom:16px">
          <div class="progress-bar" style="width:<?= min($pct, 100) ?>%;background:<?= $pct >= 90 ? '#ef4444' : $tc ?>;border-radius:100px"></div>
        </div>

        <?php
        $attended = count(array_filter($registrations, fn($r) => !empty($r['attended'])));
        $stats = [
          ['label'=>'Total Registered', 'value'=>count($registrations)],
          ['label'=>'Attended',         'value'=>$attended],
          ['label'=>'Attendance Rate',  'value'=> count($registrations) > 0 ? round($attended / count($registrations) * 100) . '%' : '—'],
          ['label'=>'Remaining Seats',  'value'=> $event['max_participants'] ? max(0, $event['max_participants'] - count($registrations)) : '∞'],
        ];
        ?>
        <?php foreach ($stats as $s): ?>
        <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid #f8fafc;font-size:13px">
          <span style="color:#94a3b8"><?= $s['label'] ?></span>
          <span style="font-weight:600;color:#374151"><?= $s['value'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Speakers -->
    <?php if (!empty($event['speakers'])): ?>
    <div class="card">
      <div class="card-header"><h3 class="card-title" style="font-size:13.5px"><i class="fas fa-microphone-alt" style="color:#6366f1"></i> Speakers</h3></div>
      <div class="card-body" style="padding:12px 16px">
        <?php foreach (json_decode($event['speakers'] ?? '[]', true) ?: [] as $sp): ?>
        <div class="d-flex align-items-center gap-2 py-2">
          <div style="width:36px;height:36px;background:#6366f118;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;color:#6366f1">
            <i class="fas fa-user"></i>
          </div>
          <div>
            <div style="font-size:13.5px;font-weight:600;color:#374151"><?= \App\Core\View::e($sp['name'] ?? '') ?></div>
            <div style="font-size:12px;color:#94a3b8"><?= \App\Core\View::e($sp['title'] ?? '') ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
