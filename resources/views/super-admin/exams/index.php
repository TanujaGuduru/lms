<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Exams</span>
    </div>
    <h1 class="page-title">Exams & Assessments</h1>
    <p class="page-subtitle">Create, manage, and analyze all platform assessments</p>
  </div>
  <a href="/super-admin/exams/create" class="btn btn-primary btn-sm">
    <i class="fas fa-plus"></i> New Exam
  </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php
  $eCards = [
    ['label'=>'Total Exams',  'value'=>$stats['total']??0,     'icon'=>'fas fa-clipboard-list','color'=>'#6366f1'],
    ['label'=>'Published',    'value'=>$stats['published']??0, 'icon'=>'fas fa-globe',         'color'=>'#10b981'],
    ['label'=>'Drafts',       'value'=>$stats['draft']??0,     'icon'=>'fas fa-file-alt',      'color'=>'#f59e0b'],
    ['label'=>'Archived',     'value'=>$stats['archived']??0,  'icon'=>'fas fa-archive',       'color'=>'#94a3b8'],
  ];
  ?>
  <?php foreach ($eCards as $s): ?>
  <div class="col-xl-3 col-md-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:<?= $s['color'] ?>18;color:<?= $s['color'] ?>"><i class="<?= $s['icon'] ?>"></i></div>
      <div><div class="stat-mini-value"><?= number_format((int)$s['value']) ?></div><div class="stat-mini-label"><?= $s['label'] ?></div></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Exam List -->
<div class="card">
  <div class="card-header">
    <form method="GET" action="/super-admin/exams" class="d-flex gap-2 flex-grow-1">
      <div class="table-search" style="margin:0;flex:1"><i class="fas fa-search search-icon"></i>
        <input type="text" name="search" placeholder="Search exams…" value="<?= \App\Core\View::e($filters['search'] ?? '') ?>">
      </div>
      <select name="status" class="form-select" style="width:140px;font-size:13px">
        <option value="">All Status</option>
        <option value="draft" <?= ($filters['status']??'') === 'draft' ? 'selected' : '' ?>>Draft</option>
        <option value="published" <?= ($filters['status']??'') === 'published' ? 'selected' : '' ?>>Published</option>
        <option value="archived" <?= ($filters['status']??'') === 'archived' ? 'selected' : '' ?>>Archived</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
    </form>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Exam</th><th>Duration</th><th>Marks</th><th>Attempts</th><th>Avg Score</th><th>Status</th><th>Created By</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($exams)): ?>
        <tr><td colspan="8">
          <div class="empty-state">
            <i class="fas fa-clipboard-list empty-state-icon"></i>
            <h4 class="empty-state-title">No Exams Yet</h4>
            <p class="empty-state-desc">Create your first exam and start assessing students.</p>
            <a href="/super-admin/exams/create" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Create Exam</a>
          </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($exams as $exam): ?>
        <tr>
          <td>
            <div style="font-size:14px;font-weight:700;color:#0f172a"><?= \App\Core\View::e($exam['title']) ?></div>
            <?php if ($exam['starts_at']): ?>
            <div style="font-size:11.5px;color:#94a3b8">
              <i class="fas fa-calendar" style="font-size:10px"></i>
              <?= \App\Core\View::formatDate($exam['starts_at'], 'd M Y, H:i') ?>
              <?php if ($exam['ends_at']): ?> → <?= \App\Core\View::formatDate($exam['ends_at'], 'd M Y, H:i') ?><?php endif; ?>
            </div>
            <?php endif; ?>
          </td>
          <td style="font-size:13px;color:#64748b">
            <i class="fas fa-clock" style="font-size:11px"></i> <?= $exam['duration'] ?> min
          </td>
          <td>
            <span style="font-weight:700;color:#6366f1"><?= $exam['passing_marks'] ?></span>
            <span style="color:#94a3b8;font-size:12px">/ <?= $exam['total_marks'] ?></span>
          </td>
          <td style="font-weight:600"><?= number_format((int)$exam['attempt_count']) ?></td>
          <td>
            <?php if ($exam['avg_score']): ?>
            <?php $pct = round($exam['avg_score'] / max(1, $exam['total_marks']) * 100); ?>
            <span style="color:<?= $pct >= 60 ? '#10b981' : '#ef4444' ?>;font-weight:700"><?= $pct ?>%</span>
            <?php else: ?><span style="color:#94a3b8">—</span><?php endif; ?>
          </td>
          <td><?= \App\Core\View::badge($exam['status']) ?></td>
          <td style="font-size:12.5px;color:#94a3b8"><?= \App\Core\View::e($exam['creator_name'] ?? '—') ?></td>
          <td>
            <div class="d-flex gap-1">
              <?php if ($exam['status'] === 'draft'): ?>
              <button onclick="publishExam(<?= $exam['id'] ?>)" class="btn btn-success btn-sm" title="Publish">
                <i class="fas fa-globe"></i>
              </button>
              <?php endif; ?>
              <a href="/super-admin/exams/<?= $exam['id'] ?>/edit" class="btn btn-ghost btn-sm btn-icon" title="Edit"><i class="fas fa-edit"></i></a>
              <a href="/super-admin/exams/<?= $exam['id'] ?>/results" class="btn btn-ghost btn-sm btn-icon" title="Results"><i class="fas fa-chart-bar"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function publishExam(id) {
  Swal.fire({ title: 'Publish Exam?', text: 'Students will be able to take this exam.', icon: 'question',
    showCancelButton: true, confirmButtonColor: '#10b981', confirmButtonText: 'Publish' })
  .then(async r => {
    if (!r.isConfirmed) return;
    const d = await cgFetch(`/super-admin/exams/${id}/publish`, { method: 'POST' });
    if (d.success) { CGToast.success('Exam published!'); setTimeout(() => location.reload(), 800); }
    else CGToast.error(d.message);
  });
}
</script>
