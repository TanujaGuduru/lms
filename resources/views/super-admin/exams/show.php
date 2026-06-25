<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/exams">Exams</a>
      <span class="sep">/</span><span><?= \App\Core\View::e($exam['title']) ?></span>
    </div>
    <h1 class="page-title"><?= \App\Core\View::e($exam['title']) ?></h1>
    <p class="page-subtitle"><?= \App\Core\View::badge($exam['status']) ?></p>
  </div>
  <div class="d-flex gap-2">
    <a href="/super-admin/exams/<?= $exam['id'] ?>/edit" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i> Edit</a>
    <a href="/super-admin/exams/<?= $exam['id'] ?>/results" class="btn btn-primary btn-sm"><i class="fas fa-chart-bar"></i> Results</a>
    <a href="/super-admin/exams" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="card mb-3">
      <div class="card-header"><h3 class="card-title">Details</h3></div>
      <div class="card-body">
        <p style="font-size:13.5px;color:#374151;white-space:pre-line"><?= \App\Core\View::e($exam['instructions'] ?? 'No instructions provided.') ?></p>
        <div class="row g-3 mt-2">
          <div class="col-md-3"><div style="font-size:12px;color:#94a3b8">Duration</div><div style="font-weight:600"><?= (int)$exam['duration_minutes'] ?> min</div></div>
          <div class="col-md-3"><div style="font-size:12px;color:#94a3b8">Total Marks</div><div style="font-weight:600"><?= $exam['total_marks'] ?></div></div>
          <div class="col-md-3"><div style="font-size:12px;color:#94a3b8">Passing Marks</div><div style="font-weight:600"><?= $exam['passing_marks'] ?></div></div>
          <div class="col-md-3"><div style="font-size:12px;color:#94a3b8">Max Attempts</div><div style="font-weight:600"><?= (int)$exam['max_attempts'] ?></div></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="fas fa-question-circle" style="color:#6366f1"></i> Questions (<?= count($questions ?? []) ?>)</h3></div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>#</th><th>Question</th><th>Type</th><th>Difficulty</th><th>Marks</th></tr></thead>
          <tbody>
            <?php if (empty($questions)): ?>
            <tr><td colspan="5"><div class="empty-state" style="padding:24px"><p class="empty-state-desc">No questions added yet.</p></div></td></tr>
            <?php else: ?>
            <?php foreach ($questions as $i => $q): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td style="font-size:13.5px;color:#374151"><?= \App\Core\View::e(substr($q['text'], 0, 100)) ?><?= strlen($q['text']) > 100 ? '…' : '' ?></td>
              <td><span class="badge" style="background:#6366f118;color:#6366f1"><?= $q['type'] ?></span></td>
              <td><span class="badge" style="background:#f59e0b18;color:#f59e0b"><?= $q['difficulty'] ?></span></td>
              <td style="font-weight:600"><?= $q['marks_override'] ?? $q['marks'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="card">
      <div class="card-header"><h3 class="card-title">Recent Attempts</h3></div>
      <div class="card-body" style="max-height:520px;overflow-y:auto">
        <?php if (empty($attempts)): ?>
        <div class="empty-state" style="padding:24px"><p class="empty-state-desc">No attempts yet.</p></div>
        <?php else: ?>
        <?php foreach ($attempts as $a): ?>
        <div class="d-flex justify-content-between align-items-center mb-3 pb-3" style="border-bottom:1px solid #f1f5f9">
          <div>
            <div style="font-size:13.5px;font-weight:600;color:#0f172a"><?= \App\Core\View::e($a['student_name']) ?></div>
            <div style="font-size:12px;color:#94a3b8">Attempt #<?= (int)$a['attempt_number'] ?> · <?= \App\Core\View::timeAgo($a['started_at']) ?></div>
          </div>
          <div style="text-align:right">
            <div style="font-weight:700;color:<?= $a['is_passed'] ? '#10b981' : '#ef4444' ?>"><?= $a['score'] !== null ? number_format((float)$a['score'], 1) . '%' : '—' ?></div>
            <div style="font-size:11px;color:#94a3b8"><?= \App\Core\View::badge($a['status']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
