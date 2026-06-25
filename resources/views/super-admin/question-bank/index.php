<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Question Bank</span>
    </div>
    <h1 class="page-title">Question Bank</h1>
    <p class="page-subtitle">Manage your library of exam questions across all subjects</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-secondary btn-sm" onclick="CGModal.open('importModal')">
      <i class="fas fa-upload"></i> Import CSV
    </button>
    <a href="/super-admin/question-bank/export" class="btn btn-secondary btn-sm">
      <i class="fas fa-download"></i> Export
    </a>
    <a href="/super-admin/question-bank/create" class="btn btn-primary btn-sm">
      <i class="fas fa-plus"></i> Add Question
    </a>
  </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php
  $qStats = [
    ['label'=>'Total Questions', 'value'=>$stats['total']??0,    'icon'=>'fas fa-question-circle', 'color'=>'#6366f1'],
    ['label'=>'MCQ',             'value'=>$stats['mcq']??0,      'icon'=>'fas fa-list',            'color'=>'#10b981'],
    ['label'=>'True/False',      'value'=>$stats['tf']??0,       'icon'=>'fas fa-toggle-on',       'color'=>'#f59e0b'],
    ['label'=>'Coding',          'value'=>$stats['coding']??0,   'icon'=>'fas fa-code',            'color'=>'#8b5cf6'],
    ['label'=>'Approved',        'value'=>$stats['approved']??0, 'icon'=>'fas fa-check-circle',    'color'=>'#10b981'],
    ['label'=>'Pending Review',  'value'=>$stats['pending']??0,  'icon'=>'fas fa-clock',           'color'=>'#f59e0b'],
  ];
  ?>
  <?php foreach ($qStats as $s): ?>
  <div class="col-xl-2 col-md-4 col-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:<?= $s['color'] ?>18;color:<?= $s['color'] ?>"><i class="<?= $s['icon'] ?>"></i></div>
      <div><div class="stat-mini-value"><?= number_format((int)$s['value']) ?></div><div class="stat-mini-label"><?= $s['label'] ?></div></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters + Table -->
<div class="card">
  <div class="card-body" style="padding-bottom:0">
    <form method="GET" action="/super-admin/question-bank" class="row g-2">
      <div class="col-md-4">
        <div class="table-search" style="margin:0"><i class="fas fa-search search-icon"></i>
          <input type="text" name="search" placeholder="Search questions…" value="<?= \App\Core\View::e($filters['search'] ?? '') ?>">
        </div>
      </div>
      <div class="col-md-2">
        <select name="type" class="form-select" style="font-size:13px">
          <option value="">All Types</option>
          <?php foreach (['mcq'=>'MCQ','msq'=>'MSQ','true_false'=>'True/False','short_answer'=>'Short Answer','coding'=>'Coding'] as $k=>$v): ?>
          <option value="<?= $k ?>" <?= ($filters['type']??'') === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="difficulty" class="form-select" style="font-size:13px">
          <option value="">All Levels</option>
          <option value="easy" <?= ($filters['difficulty']??'') === 'easy' ? 'selected' : '' ?>>Easy</option>
          <option value="medium" <?= ($filters['difficulty']??'') === 'medium' ? 'selected' : '' ?>>Medium</option>
          <option value="hard" <?= ($filters['difficulty']??'') === 'hard' ? 'selected' : '' ?>>Hard</option>
        </select>
      </div>
      <div class="col-md-2">
        <select name="category_id" class="form-select" style="font-size:13px">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= ($filters['category_id']??'') == $cat['id'] ? 'selected' : '' ?>><?= \App\Core\View::e($cat['name']) ?></option>
          <?php endforeach; ?>
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
        <tr>
          <th style="width:40%">Question</th>
          <th>Type</th><th>Difficulty</th><th>Category</th><th>Marks</th><th>Status</th><th>Added By</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($questions)): ?>
        <tr><td colspan="8">
          <div class="empty-state">
            <i class="fas fa-question-circle empty-state-icon"></i>
            <h4 class="empty-state-title">No Questions Yet</h4>
            <p class="empty-state-desc">Start building your question bank.</p>
            <a href="/super-admin/question-bank/create" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add First Question</a>
          </div>
        </td></tr>
        <?php else: ?>
        <?php
        $typeColors = ['mcq'=>'#6366f1','msq'=>'#8b5cf6','true_false'=>'#10b981','short_answer'=>'#f59e0b','coding'=>'#06b6d4'];
        $diffColors = ['easy'=>'#10b981','medium'=>'#f59e0b','hard'=>'#ef4444'];
        foreach ($questions as $q):
          $tc = $typeColors[$q['type']] ?? '#64748b';
          $dc = $diffColors[$q['difficulty']] ?? '#64748b';
        ?>
        <tr>
          <td>
            <div style="font-size:13.5px;color:#374151;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">
              <?= \App\Core\View::e($q['text']) ?>
            </div>
          </td>
          <td>
            <span class="badge" style="background:<?= $tc ?>18;color:<?= $tc ?>">
              <?= strtoupper(str_replace('_',' ',$q['type'])) ?>
            </span>
          </td>
          <td>
            <span class="badge" style="background:<?= $dc ?>18;color:<?= $dc ?>">
              <?= ucfirst($q['difficulty']) ?>
            </span>
          </td>
          <td style="font-size:13px;color:#64748b"><?= \App\Core\View::e($q['category_name'] ?? '—') ?></td>
          <td style="font-weight:700;color:#6366f1"><?= $q['marks'] ?></td>
          <td>
            <?php if ($q['status'] === 'approved'): ?>
              <span class="badge badge-soft-success"><i class="fas fa-check"></i> Approved</span>
            <?php else: ?>
              <span class="badge badge-soft-warning"><i class="fas fa-clock"></i> Pending</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12.5px;color:#94a3b8"><?= \App\Core\View::e($q['author_name'] ?? '—') ?></td>
          <td>
            <div class="d-flex gap-1">
              <?php if ($q['status'] !== 'approved'): ?>
              <button onclick="approveQuestion(<?= $q['id'] ?>)" class="btn btn-success btn-sm btn-icon" title="Approve">
                <i class="fas fa-check"></i>
              </button>
              <?php endif; ?>
              <a href="/super-admin/question-bank/<?= $q['id'] ?>/edit" class="btn btn-ghost btn-sm btn-icon" title="Edit">
                <i class="fas fa-edit"></i>
              </a>
              <button onclick="confirmDelete('/super-admin/question-bank/<?= $q['id'] ?>/delete', 'this question', () => this.closest('tr').remove())"
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

  <?php if (($meta['last_page'] ?? 1) > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <div style="font-size:13px;color:#64748b">
      Showing <?= $meta['from'] ?>–<?= $meta['to'] ?> of <?= number_format($meta['total']) ?> questions
    </div>
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

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-upload" style="color:#6366f1"></i> Import Questions (CSV)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="/super-admin/question-bank/import" enctype="multipart/form-data">
        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
        <div class="modal-body">
          <div class="alert alert-info" style="font-size:13px">
            <strong>CSV format:</strong> text, type (mcq/true_false), difficulty, marks, category_name
          </div>
          <div class="form-group">
            <label class="form-label">CSV File <span style="color:#ef4444">*</span></label>
            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Import</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function approveQuestion(id) {
  cgFetch(`/super-admin/question-bank/${id}/approve`, { method: 'POST' }).then(d => {
    if (d.success) {
      CGToast.success('Question approved');
      setTimeout(() => location.reload(), 800);
    }
  });
}
</script>
