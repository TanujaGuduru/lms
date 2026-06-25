<?php
$roleColors = ['super_admin'=>'#dc2626','admin'=>'#7c3aed','teacher'=>'#0891b2','student'=>'#059669','parent'=>'#d97706'];
?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>User Management</span>
    </div>
    <h1 class="page-title">User Management</h1>
    <p class="page-subtitle">Manage all users — admins, teachers, students and parents</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-secondary btn-sm" onclick="document.getElementById('importModal').style.display='flex'">
      <i class="fas fa-file-import"></i> Import
    </button>
    <a href="/super-admin/users/export" class="btn btn-secondary btn-sm">
      <i class="fas fa-file-export"></i> Export
    </a>
    <a href="/super-admin/users/create" class="btn btn-primary btn-sm">
      <i class="fas fa-user-plus"></i> Add User
    </a>
  </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
  <?php
  $statItems = [
    ['label'=>'Total Users',   'value'=>array_sum(array_column([[$stats['total'] ?? 0]], 0)), 'icon'=>'fas fa-users',        'color'=>'#6366f1'],
    ['label'=>'Students',      'value'=>$stats['students'] ?? 0,  'icon'=>'fas fa-user-graduate',   'color'=>'#059669'],
    ['label'=>'Teachers',      'value'=>$stats['teachers'] ?? 0,  'icon'=>'fas fa-chalkboard-teacher','color'=>'#0891b2'],
    ['label'=>'Active Users',  'value'=>$stats['active'] ?? 0,    'icon'=>'fas fa-user-check',       'color'=>'#10b981'],
    ['label'=>'New This Month','value'=>$stats['this_month'] ?? 0,'icon'=>'fas fa-user-clock',       'color'=>'#f59e0b'],
  ];
  ?>
  <?php foreach ($statItems as $s): ?>
  <div class="col-xl col-md-4 col-6">
    <div class="stat-mini">
      <div class="stat-mini-icon" style="background:<?= $s['color'] ?>18;color:<?= $s['color'] ?>">
        <i class="<?= $s['icon'] ?>"></i>
      </div>
      <div>
        <div class="stat-mini-value"><?= number_format((int)$s['value']) ?></div>
        <div class="stat-mini-label"><?= $s['label'] ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters + Table -->
<div class="table-container">

  <!-- Toolbar -->
  <div class="table-toolbar">
    <div class="table-toolbar-left">
      <!-- Search -->
      <div class="table-search">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="userSearch" placeholder="Search name, email, phone…" value="<?= \App\Core\View::e($filters['search'] ?? '') ?>">
      </div>

      <!-- Role Filter -->
      <select id="roleFilter" class="form-select" style="width:140px;font-size:13px">
        <option value="">All Roles</option>
        <?php foreach ($roles as $r): ?>
        <option value="<?= $r['id'] ?>" <?= ($filters['role_id'] ?? '') == $r['id'] ? 'selected' : '' ?>>
          <?= \App\Core\View::e($r['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>

      <!-- Status Filter -->
      <select id="statusFilter" class="form-select" style="width:130px;font-size:13px">
        <option value="">All Status</option>
        <option value="active"    <?= ($filters['status'] ?? '') === 'active'    ? 'selected' : '' ?>>Active</option>
        <option value="inactive"  <?= ($filters['status'] ?? '') === 'inactive'  ? 'selected' : '' ?>>Inactive</option>
        <option value="pending"   <?= ($filters['status'] ?? '') === 'pending'   ? 'selected' : '' ?>>Pending</option>
        <option value="suspended" <?= ($filters['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
      </select>

      <!-- Bulk action -->
      <div id="bulkActionsBar" style="display:none" class="d-flex gap-2 align-items-center">
        <span id="selectedCount" style="font-size:13px;color:#6366f1;font-weight:600">0 selected</span>
        <button class="btn btn-secondary btn-sm" onclick="bulkAction('activate')"><i class="fas fa-check"></i> Activate</button>
        <button class="btn btn-secondary btn-sm" onclick="bulkAction('deactivate')"><i class="fas fa-ban"></i> Deactivate</button>
        <button class="btn btn-danger btn-sm" onclick="bulkAction('delete')"><i class="fas fa-trash"></i> Delete</button>
      </div>
    </div>
    <div class="table-toolbar-right">
      <span style="font-size:12.5px;color:#94a3b8">
        Showing <strong><?= $users['from'] ?? 0 ?>–<?= $users['to'] ?? 0 ?></strong> of <strong><?= number_format($users['total'] ?? 0) ?></strong>
      </span>
    </div>
  </div>

  <!-- Table -->
  <div class="table-responsive">
    <table class="data-table" id="usersTable">
      <thead>
        <tr>
          <th class="col-check">
            <input type="checkbox" id="selectAll" style="cursor:pointer">
          </th>
          <th>User</th>
          <th>Role</th>
          <th>Status</th>
          <th>Joined</th>
          <th>Last Login</th>
          <th style="width:120px">Actions</th>
        </tr>
      </thead>
      <tbody id="usersTableBody">
        <?php if (!empty($users['data'])): ?>
          <?php foreach ($users['data'] as $u): ?>
          <tr data-id="<?= $u['id'] ?>">
            <td class="col-check">
              <input type="checkbox" class="row-check" value="<?= $u['id'] ?>" style="cursor:pointer">
            </td>
            <td>
              <div class="user-cell">
                <img src="<?= \App\Core\View::e((new \App\Models\User())->avatarUrl($u)) ?>" class="avatar" alt="">
                <div class="user-info">
                  <div class="user-name">
                    <a href="/super-admin/users/<?= $u['id'] ?>" style="color:inherit;text-decoration:none">
                      <?= \App\Core\View::e($u['first_name'] . ' ' . $u['last_name']) ?>
                    </a>
                  </div>
                  <div class="user-email"><?= \App\Core\View::e($u['email']) ?></div>
                  <?php if ($u['phone']): ?>
                  <div style="font-size:11px;color:#94a3b8"><?= \App\Core\View::e($u['phone']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td>
              <span class="badge" style="background:<?= \App\Core\View::e($u['role_color'] ?? '#6366f1') ?>18;color:<?= \App\Core\View::e($u['role_color'] ?? '#6366f1') ?>">
                <?= \App\Core\View::e($u['role_name'] ?? '—') ?>
              </span>
            </td>
            <td>
              <label class="form-switch" title="Toggle Status">
                <input type="checkbox" <?= $u['status'] === 'active' ? 'checked' : '' ?>
                       onchange="toggleUserStatus(<?= $u['id'] ?>, this)">
                <span class="toggle-track"></span>
              </label>
            </td>
            <td style="font-size:12.5px;color:#64748b;white-space:nowrap">
              <?= \App\Core\View::formatDate($u['created_at'], 'd M Y') ?>
            </td>
            <td style="font-size:12.5px;color:#94a3b8;white-space:nowrap">
              <?= $u['last_login_at'] ? \App\Core\View::timeAgo($u['last_login_at']) : '<span style="color:#cbd5e1">Never</span>' ?>
            </td>
            <td>
              <div class="d-flex gap-1">
                <a href="/super-admin/users/<?= $u['id'] ?>"
                   class="btn btn-ghost btn-sm btn-icon" title="View Profile">
                  <i class="fas fa-eye"></i>
                </a>
                <a href="/super-admin/users/<?= $u['id'] ?>/edit"
                   class="btn btn-ghost btn-sm btn-icon" title="Edit">
                  <i class="fas fa-edit"></i>
                </a>
                <button onclick="deleteUser(<?= $u['id'] ?>, '<?= \App\Core\View::e(addslashes($u['first_name'] . ' ' . $u['last_name'])) ?>')"
                        class="btn btn-ghost btn-sm btn-icon" title="Delete" style="color:#ef4444">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7">
            <div class="empty-state">
              <i class="fas fa-users empty-state-icon"></i>
              <h4 class="empty-state-title">No users found</h4>
              <p class="empty-state-desc">Try changing your filters or add a new user.</p>
              <a href="/super-admin/users/create" class="btn btn-primary btn-sm"><i class="fas fa-user-plus"></i> Add First User</a>
            </div>
          </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if (($users['last_page'] ?? 1) > 1): ?>
  <div class="table-pagination">
    <span class="pagination-info">
      Showing <?= $users['from'] ?>–<?= $users['to'] ?> of <?= number_format($users['total']) ?> users
    </span>
    <div class="pagination-controls">
      <?php if ($users['current_page'] > 1): ?>
      <a href="?page=<?= $users['current_page'] - 1 ?>&<?= http_build_query(array_filter($filters)) ?>" class="page-btn">
        <i class="fas fa-chevron-left"></i>
      </a>
      <?php endif; ?>

      <?php for ($p = max(1, $users['current_page'] - 2); $p <= min($users['last_page'], $users['current_page'] + 2); $p++): ?>
      <a href="?page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>"
         class="page-btn <?= $p === $users['current_page'] ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>

      <?php if ($users['current_page'] < $users['last_page']): ?>
      <a href="?page=<?= $users['current_page'] + 1 ?>&<?= http_build_query(array_filter($filters)) ?>" class="page-btn">
        <i class="fas fa-chevron-right"></i>
      </a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /table-container -->

<!-- Import Modal -->
<div id="importModal" class="modal-backdrop-custom" style="display:none">
  <div class="modal-custom modal-sm">
    <div class="modal-header-custom">
      <h4 class="modal-title-custom"><i class="fas fa-file-import" style="color:#6366f1"></i> Import Users</h4>
      <button class="modal-close" onclick="document.getElementById('importModal').style.display='none'">✕</button>
    </div>
    <div class="modal-body-custom">
      <div class="alert alert-info">
        <span class="alert-icon"><i class="fas fa-info-circle"></i></span>
        <div class="alert-content">Upload a CSV file with columns: first_name, last_name, email, phone</div>
      </div>
      <form id="importForm" enctype="multipart/form-data">
        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
        <div class="form-group">
          <label class="form-label">CSV File <span class="required">*</span></label>
          <input type="file" name="file" class="form-control" accept=".csv" required>
        </div>
        <a href="#" style="font-size:13px;color:#6366f1" download>Download Sample Template</a>
      </form>
    </div>
    <div class="modal-footer-custom">
      <button class="btn btn-secondary" onclick="document.getElementById('importModal').style.display='none'">Cancel</button>
      <button class="btn btn-primary" onclick="submitImport()"><i class="fas fa-upload"></i> Import</button>
    </div>
  </div>
</div>

<script>
// ── Filter / Search ──────────────────────────────────────────
let searchTimeout;
document.getElementById('userSearch').addEventListener('input', function() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => applyFilters(), 400);
});
document.getElementById('roleFilter').addEventListener('change', applyFilters);
document.getElementById('statusFilter').addEventListener('change', applyFilters);

function applyFilters() {
  const params = new URLSearchParams();
  const s = document.getElementById('userSearch').value;
  const r = document.getElementById('roleFilter').value;
  const st = document.getElementById('statusFilter').value;
  if (s)  params.set('search',  s);
  if (r)  params.set('role_id', r);
  if (st) params.set('status',  st);
  window.location.href = '/super-admin/users?' + params.toString();
}

// ── Select All ───────────────────────────────────────────────
document.getElementById('selectAll').addEventListener('change', function() {
  document.querySelectorAll('.row-check').forEach(c => c.checked = this.checked);
  updateBulkBar();
});
document.querySelectorAll('.row-check').forEach(c => c.addEventListener('change', updateBulkBar));

function updateBulkBar() {
  const checked = document.querySelectorAll('.row-check:checked');
  const bar     = document.getElementById('bulkActionsBar');
  document.getElementById('selectedCount').textContent = checked.length + ' selected';
  bar.style.display = checked.length > 0 ? 'flex' : 'none';
}

// ── Toggle Status ────────────────────────────────────────────
function toggleUserStatus(id, checkbox) {
  fetch(`/super-admin/users/${id}/toggle-status`, {
    method: 'POST',
    headers: { 'X-CSRF-Token': CG.csrf, 'Content-Type': 'application/json' }
  })
  .then(r => r.json())
  .then(d => {
    if (!d.success) { checkbox.checked = !checkbox.checked; CGToast.error(d.message); }
    else CGToast.success('Status updated.');
  })
  .catch(() => { checkbox.checked = !checkbox.checked; CGToast.error('Request failed.'); });
}

// ── Delete User ──────────────────────────────────────────────
function deleteUser(id, name) {
  Swal.fire({
    title: 'Delete User?',
    html: `<p style="color:#64748b">Are you sure you want to delete <strong>${name}</strong>? This action cannot be undone.</p>`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, Delete',
    confirmButtonColor: '#ef4444',
    cancelButtonText: 'Cancel',
    reverseButtons: true
  }).then(r => {
    if (r.isConfirmed) {
      fetch(`/super-admin/users/${id}/delete`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': CG.csrf }
      })
      .then(res => res.json())
      .then(d => {
        if (d.success) {
          document.querySelector(`tr[data-id="${id}"]`)?.remove();
          CGToast.success('User deleted.');
        } else CGToast.error(d.message);
      });
    }
  });
}

// ── Bulk Action ──────────────────────────────────────────────
function bulkAction(action) {
  const ids = Array.from(document.querySelectorAll('.row-check:checked')).map(c => c.value);
  if (!ids.length) return;

  Swal.fire({
    title: `${action.charAt(0).toUpperCase() + action.slice(1)} ${ids.length} users?`,
    icon: action === 'delete' ? 'warning' : 'question',
    showCancelButton: true,
    confirmButtonColor: action === 'delete' ? '#ef4444' : '#6366f1',
    confirmButtonText: 'Confirm'
  }).then(r => {
    if (!r.isConfirmed) return;
    fetch('/super-admin/users/bulk-action', {
      method: 'POST',
      headers: { 'X-CSRF-Token': CG.csrf, 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, user_ids: ids })
    })
    .then(res => res.json())
    .then(d => {
      if (d.success) { CGToast.success(d.message); setTimeout(() => location.reload(), 1000); }
      else CGToast.error(d.message);
    });
  });
}

// ── Import ───────────────────────────────────────────────────
function submitImport() {
  const form = document.getElementById('importForm');
  const data = new FormData(form);
  fetch('/super-admin/users/import', { method: 'POST', body: data })
    .then(r => r.json())
    .then(d => {
      document.getElementById('importModal').style.display = 'none';
      if (d.success) {
        Swal.fire('Import Complete', d.message, 'success').then(() => location.reload());
      } else CGToast.error(d.message);
    });
}
</script>
