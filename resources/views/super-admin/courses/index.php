<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Courses</span>
    </div>
    <h1 class="page-title">Course Management</h1>
    <p class="page-subtitle">Build and manage your learning content library</p>
  </div>
  <a href="/super-admin/courses/create" class="btn btn-primary btn-sm">
    <i class="fas fa-plus"></i> Create Course
  </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php $cs = $stats ?? []; ?>
  <div class="col-xl col-md-3 col-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:rgba(99,102,241,.12);color:#6366f1"><i class="fas fa-book-open"></i></div>
      <div><div class="stat-mini-value"><?= number_format((int)($cs['total'] ?? 0)) ?></div><div class="stat-mini-label">Total Courses</div></div>
    </div>
  </div>
  <div class="col-xl col-md-3 col-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:rgba(16,185,129,.12);color:#10b981"><i class="fas fa-globe"></i></div>
      <div><div class="stat-mini-value"><?= number_format((int)($cs['published'] ?? 0)) ?></div><div class="stat-mini-label">Published</div></div>
    </div>
  </div>
  <div class="col-xl col-md-3 col-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:rgba(245,158,11,.12);color:#f59e0b"><i class="fas fa-edit"></i></div>
      <div><div class="stat-mini-value"><?= number_format((int)($cs['drafts'] ?? 0)) ?></div><div class="stat-mini-label">Drafts</div></div>
    </div>
  </div>
  <div class="col-xl col-md-3 col-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:rgba(6,182,212,.12);color:#06b6d4"><i class="fas fa-users"></i></div>
      <div><div class="stat-mini-value"><?= number_format((int)($cs['total_enrollments'] ?? 0)) ?></div><div class="stat-mini-label">Total Enrollments</div></div>
    </div>
  </div>
</div>

<!-- Filters + Grid/List Toggle -->
<div class="card mb-3">
  <div class="card-body py-3">
    <div class="d-flex gap-3 flex-wrap align-items-center">
      <div class="table-search">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="courseSearch" placeholder="Search courses…" value="<?= \App\Core\View::e($filters['search'] ?? '') ?>">
      </div>
      <select id="statusFilter" class="form-select" style="width:130px;font-size:13px">
        <option value="">All Status</option>
        <option value="draft"     <?= ($filters['status'] ?? '') === 'draft'     ? 'selected' : '' ?>>Draft</option>
        <option value="published" <?= ($filters['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
        <option value="archived"  <?= ($filters['status'] ?? '') === 'archived'  ? 'selected' : '' ?>>Archived</option>
      </select>
      <select id="levelFilter" class="form-select" style="width:130px;font-size:13px">
        <option value="">All Levels</option>
        <option value="beginner"     <?= ($filters['level'] ?? '') === 'beginner'     ? 'selected' : '' ?>>Beginner</option>
        <option value="intermediate" <?= ($filters['level'] ?? '') === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
        <option value="advanced"     <?= ($filters['level'] ?? '') === 'advanced'     ? 'selected' : '' ?>>Advanced</option>
        <option value="expert"       <?= ($filters['level'] ?? '') === 'expert'       ? 'selected' : '' ?>>Expert</option>
      </select>
      <div class="ms-auto d-flex gap-2">
        <button class="btn btn-ghost btn-sm btn-icon view-toggle active" data-view="grid" title="Grid View"><i class="fas fa-th"></i></button>
        <button class="btn btn-ghost btn-sm btn-icon view-toggle" data-view="list" title="List View"><i class="fas fa-list"></i></button>
      </div>
    </div>
  </div>
</div>

<!-- Course Grid -->
<div id="courseGrid" class="row g-3 mb-4">
  <?php if (!empty($courses['data'])): ?>
    <?php foreach ($courses['data'] as $c): ?>
    <div class="col-xl-3 col-md-4 col-sm-6">
      <div class="card h-100" style="transition:all .2s" onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 10px 30px rgba(0,0,0,.12)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
        <!-- Thumbnail -->
        <div style="position:relative;aspect-ratio:16/9;overflow:hidden;background:#f1f5f9">
          <?php if ($c['thumbnail']): ?>
            <img src="<?= \App\Core\View::e($c['thumbnail']) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
          <?php else: ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#6366f133,#06b6d433)">
              <i class="fas fa-book-open" style="font-size:36px;color:#6366f1;opacity:.5"></i>
            </div>
          <?php endif; ?>
          <!-- Status badge -->
          <div style="position:absolute;top:8px;left:8px">
            <?= \App\Core\View::badge($c['status']) ?>
          </div>
          <?php if ($c['is_featured']): ?>
          <div style="position:absolute;top:8px;right:8px">
            <span class="badge badge-soft-warning"><i class="fas fa-star"></i> Featured</span>
          </div>
          <?php endif; ?>
        </div>

        <div class="card-body">
          <!-- Level -->
          <div class="d-flex align-items-center gap-2 mb-2">
            <?php
            $levelColors = ['beginner'=>'#10b981','intermediate'=>'#3b82f6','advanced'=>'#f59e0b','expert'=>'#ef4444'];
            $lc = $levelColors[$c['level']] ?? '#6366f1';
            ?>
            <span style="font-size:11px;font-weight:600;color:<?= $lc ?>;background:<?= $lc ?>18;padding:2px 8px;border-radius:20px">
              <?= ucfirst($c['level']) ?>
            </span>
            <?php if ($c['department_name']): ?>
            <span style="font-size:11px;color:#94a3b8"><?= \App\Core\View::e($c['department_name']) ?></span>
            <?php endif; ?>
          </div>

          <!-- Title -->
          <h5 style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:6px;line-height:1.4;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">
            <?= \App\Core\View::e($c['title']) ?>
          </h5>

          <!-- Meta -->
          <div class="d-flex align-items-center gap-3 mt-2" style="font-size:12px;color:#64748b">
            <span><i class="fas fa-users" style="color:#6366f1"></i> <?= number_format((int)$c['enrolled_count']) ?></span>
            <?php if ($c['rating_count'] > 0): ?>
            <span><i class="fas fa-star" style="color:#f59e0b"></i> <?= number_format((float)$c['rating_avg'], 1) ?></span>
            <?php endif; ?>
            <?php if ($c['is_free']): ?>
            <span class="badge badge-soft-success">Free</span>
            <?php else: ?>
            <span style="font-weight:700;color:#0f172a">₹<?= number_format((float)$c['price'], 0) ?></span>
            <?php endif; ?>
          </div>

          <!-- Author -->
          <div style="font-size:12px;color:#94a3b8;margin-top:8px">
            By <strong style="color:#64748b"><?= \App\Core\View::e($c['first_name'] . ' ' . $c['last_name']) ?></strong>
            · <?= \App\Core\View::formatDate($c['created_at'], 'd M Y') ?>
          </div>
        </div>

        <div class="card-footer d-flex gap-2">
          <a href="/super-admin/courses/<?= $c['id'] ?>" class="btn btn-ghost btn-sm flex-1" style="font-size:12px">
            <i class="fas fa-eye"></i> View
          </a>
          <a href="/super-admin/courses/<?= $c['id'] ?>/edit" class="btn btn-ghost btn-sm flex-1" style="font-size:12px">
            <i class="fas fa-edit"></i> Edit
          </a>
          <?php if ($c['status'] === 'draft'): ?>
          <button onclick="publishCourse(<?= $c['id'] ?>)" class="btn btn-success btn-sm" style="font-size:12px" title="Publish">
            <i class="fas fa-globe"></i>
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="col-12">
      <div class="empty-state" style="background:#fff;border-radius:16px;border:1px solid #e2e8f0">
        <i class="fas fa-book empty-state-icon"></i>
        <h4 class="empty-state-title">No Courses Found</h4>
        <p class="empty-state-desc">Start building your curriculum by creating your first course.</p>
        <a href="/super-admin/courses/create" class="btn btn-primary"><i class="fas fa-plus"></i> Create First Course</a>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Pagination -->
<?php if (($courses['last_page'] ?? 1) > 1): ?>
<div class="table-pagination" style="background:#fff;border-radius:12px;border:1px solid #e2e8f0">
  <span class="pagination-info">Showing <?= $courses['from'] ?>–<?= $courses['to'] ?> of <?= number_format($courses['total']) ?></span>
  <div class="pagination-controls">
    <?php for ($p = 1; $p <= $courses['last_page']; $p++): ?>
    <a href="?page=<?= $p ?>" class="page-btn <?= $p === $courses['current_page'] ? 'active' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<script>
// Filters
let st;
document.getElementById('courseSearch').addEventListener('input', function() {
  clearTimeout(st);
  st = setTimeout(applyFilters, 400);
});
document.getElementById('statusFilter').addEventListener('change', applyFilters);
document.getElementById('levelFilter').addEventListener('change', applyFilters);

function applyFilters() {
  const p = new URLSearchParams();
  const s  = document.getElementById('courseSearch').value;
  const st = document.getElementById('statusFilter').value;
  const lv = document.getElementById('levelFilter').value;
  if (s)  p.set('search', s);
  if (st) p.set('status', st);
  if (lv) p.set('level',  lv);
  window.location.href = '/super-admin/courses?' + p.toString();
}

// View toggle
document.querySelectorAll('.view-toggle').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.view-toggle').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    // grid vs list switching would go here
  });
});

// Publish
function publishCourse(id) {
  Swal.fire({
    title: 'Publish Course?',
    text: 'This will make the course visible to students.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#10b981',
    confirmButtonText: 'Yes, Publish'
  }).then(r => {
    if (r.isConfirmed) {
      fetch(`/super-admin/courses/${id}/publish`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': CG.csrf }
      })
      .then(res => res.json())
      .then(d => {
        if (d.success) { CGToast.success('Course published!'); setTimeout(() => location.reload(), 800); }
        else CGToast.error(d.message);
      });
    }
  });
}
</script>
