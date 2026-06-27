<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Roles & Permissions</span>
    </div>
    <h1 class="page-title">Roles & Permissions</h1>
    <p class="page-subtitle">Define what each role can access and do across the platform</p>
  </div>
  <button class="btn btn-primary btn-sm" onclick="CGModal.open('createRoleModal')">
    <i class="fas fa-plus"></i> New Role
  </button>
</div>

<?php
$db          = \App\Core\Database::getInstance();
$roles       = $db->select("SELECT r.*, COUNT(DISTINCT ur.user_id) user_count FROM roles r LEFT JOIN user_roles ur ON ur.role_id=r.id GROUP BY r.id ORDER BY r.id");
$permissions = $db->select("SELECT * FROM permissions ORDER BY module, action");

// Group permissions by module
$permsByModule = [];
foreach ($permissions as $p) {
    $permsByModule[$p['module']][] = $p;
}

// Load all role_permissions
$rp = $db->select("SELECT * FROM role_permissions");
$rpMap = [];
foreach ($rp as $r) {
    $rpMap[$r['role_id']][$r['permission_id']] = $r['is_allowed'];
}
?>

<!-- Role Cards -->
<div class="row g-3 mb-4">
  <?php foreach ($roles as $role):
    $colors = ['super_admin'=>'#6366f1','admin'=>'#3b82f6','teacher'=>'#10b981','student'=>'#f59e0b','parent'=>'#ec4899'];
    $icons  = ['super_admin'=>'fas fa-crown','admin'=>'fas fa-user-shield','teacher'=>'fas fa-chalkboard-teacher','student'=>'fas fa-user-graduate','parent'=>'fas fa-users'];
    $c  = $colors[$role['slug']] ?? '#64748b';
    $ic = $icons[$role['slug']] ?? 'fas fa-user-tag';
  ?>
  <div class="col-xl col-md-4 col-sm-6">
    <div class="card role-card" data-role-id="<?= $role['id'] ?>" style="cursor:pointer;border-top:3px solid <?= $c ?>;transition:all .2s"
         onclick="showRolePermissions(<?= $role['id'] ?>, '<?= htmlspecialchars(addslashes($role['name'])) ?>')"
         onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,.1)'"
         onmouseout="this.style.transform='';this.style.boxShadow=''">
      <div class="card-body py-4 text-center">
        <div style="width:56px;height:56px;background:<?= $c ?>18;border-radius:16px;display:flex;align-items:center;justify-content:center;color:<?= $c ?>;font-size:22px;margin:0 auto 12px">
          <i class="<?= $ic ?>"></i>
        </div>
        <div style="font-size:15px;font-weight:700;color:#0f172a;margin-bottom:4px"><?= htmlspecialchars($role['name']) ?></div>
        <div style="font-size:12px;color:#94a3b8;margin-bottom:12px"><?= $role['user_count'] ?> users</div>
        <div class="d-flex gap-2 justify-content-center">
          <?php if (!$role['is_system']): ?>
          <button onclick="event.stopPropagation();editRole(<?= $role['id'] ?>)" class="btn btn-ghost btn-sm" title="Edit"><i class="fas fa-edit"></i></button>
          <button onclick="event.stopPropagation();cloneRole(<?= $role['id'] ?>, '<?= htmlspecialchars(addslashes($role['name'])) ?>')" class="btn btn-ghost btn-sm" title="Clone"><i class="fas fa-copy"></i></button>
          <?php else: ?>
          <span class="badge badge-soft-secondary" style="font-size:11px"><i class="fas fa-lock"></i> System Role</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Permission Matrix -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-shield-alt" style="color:#6366f1"></i> Permission Matrix</h3>
    <div class="d-flex gap-2">
      <select id="matrixRoleFilter" class="form-select" style="width:160px;font-size:13px" onchange="filterMatrix(this.value)">
        <option value="">All Roles</option>
        <?php foreach ($roles as $r): ?>
        <option value="role-<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button id="saveMatrix" class="btn btn-primary btn-sm" onclick="savePermissions()"><i class="fas fa-save"></i> Save Changes</button>
    </div>
  </div>
  <div class="table-responsive" style="max-height:600px;overflow:auto">
    <table class="data-table" id="permMatrix" style="min-width:800px">
      <thead style="position:sticky;top:0;z-index:2;background:#fff">
        <tr>
          <th style="min-width:220px">Permission</th>
          <?php foreach ($roles as $r):
            $c = $colors[$r['slug']] ?? '#64748b';
          ?>
          <th class="text-center role-col role-<?= $r['id'] ?>" style="min-width:100px">
            <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
              <div style="width:28px;height:28px;background:<?= $c ?>18;border-radius:8px;display:flex;align-items:center;justify-content:center;color:<?= $c ?>;font-size:12px">
                <i class="<?= $icons[$r['slug']] ?? 'fas fa-user' ?>"></i>
              </div>
              <span style="font-size:11.5px;font-weight:600;color:#374151"><?= htmlspecialchars($r['name']) ?></span>
            </div>
          </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($permsByModule as $module => $perms):
          $moduleColors = ['users'=>'#6366f1','courses'=>'#10b981','batches'=>'#f59e0b','assessments'=>'#ef4444','announcements'=>'#3b82f6','finance'=>'#8b5cf6','reports'=>'#06b6d4','system'=>'#64748b','settings'=>'#ec4899'];
          $mc = $moduleColors[$module] ?? '#64748b';
        ?>
        <tr style="background:#f8fafc">
          <td colspan="<?= count($roles) + 1 ?>" style="padding:8px 16px">
            <div style="display:flex;align-items:center;gap-8px">
              <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= $mc ?>;margin-right:8px"></span>
              <strong style="font-size:12px;text-transform:uppercase;letter-spacing:.6px;color:#64748b"><?= ucfirst($module) ?></strong>
            </div>
          </td>
        </tr>
        <?php foreach ($perms as $perm): ?>
        <tr>
          <td>
            <div style="font-size:13.5px;font-weight:500;color:#374151"><?= htmlspecialchars($perm['name']) ?></div>
            <div style="font-size:11.5px;color:#94a3b8;font-family:monospace"><?= htmlspecialchars($perm['slug']) ?></div>
          </td>
          <?php foreach ($roles as $r): ?>
          <td class="text-center role-col role-<?= $r['id'] ?>">
            <?php
            $isSuperAdmin = $r['slug'] === 'super_admin';
            $hasPermission = $isSuperAdmin || ($rpMap[$r['id']][$perm['id']] ?? false);
            ?>
            <label class="perm-toggle <?= $isSuperAdmin ? 'perm-locked' : '' ?>" title="<?= $isSuperAdmin ? 'Super Admin has all permissions' : '' ?>">
              <input type="checkbox" class="perm-check"
                     data-role-id="<?= $r['id'] ?>"
                     data-perm-id="<?= $perm['id'] ?>"
                     <?= $hasPermission ? 'checked' : '' ?>
                     <?= $isSuperAdmin ? 'disabled' : '' ?>
                     onchange="markDirty()">
              <span class="perm-indicator <?= $isSuperAdmin ? 'locked' : '' ?>"></span>
            </label>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Create Role Modal -->
<div class="modal fade" id="createRoleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-plus" style="color:#6366f1"></i> Create New Role</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="/super-admin/roles/create" data-loading="Creating…">
        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
        <div class="modal-body">
          <div class="form-group mb-3">
            <label class="form-label">Role Name <span style="color:#ef4444">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Moderator" required>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Slug <span style="color:#ef4444">*</span></label>
            <input type="text" name="slug" class="form-control" placeholder="e.g. moderator" pattern="[a-z_]+" required>
            <div class="form-hint">Lowercase letters and underscores only.</div>
          </div>
          <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="2" placeholder="What does this role do?"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Create Role</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
.perm-toggle { display:inline-flex;align-items:center;justify-content:center;cursor:pointer; }
.perm-toggle input { display:none; }
.perm-indicator {
  width:20px;height:20px;border-radius:6px;background:#f1f5f9;
  border:1.5px solid #e2e8f0;transition:all .18s;display:flex;align-items:center;justify-content:center;
}
.perm-toggle input:checked + .perm-indicator {
  background:#6366f1;border-color:#6366f1;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'%3E%3Cpath d='M2 6l3 3 5-5' stroke='%23fff' stroke-width='1.8' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat:no-repeat;background-position:center;background-size:70%;
}
.perm-indicator.locked { background:#10b98118;border-color:#10b98140; }
.perm-toggle.perm-locked { cursor:not-allowed; }
.perm-toggle.perm-locked .perm-indicator { background:#10b98118;border-color:#10b98140;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'%3E%3Cpath d='M2 6l3 3 5-5' stroke='%2310b981' stroke-width='1.8' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat:no-repeat;background-position:center;background-size:70%;
}
</style>

<script>
let isDirty = false;

function markDirty() {
  isDirty = true;
  document.getElementById('saveMatrix').classList.add('btn-warning');
  document.getElementById('saveMatrix').innerHTML = '<i class="fas fa-save"></i> Save Changes ●';
}

function filterMatrix(value) {
  document.querySelectorAll('.role-col').forEach(el => {
    el.style.display = (!value || el.classList.contains(value)) ? '' : 'none';
  });
}

function savePermissions() {
  const perms = [];
  document.querySelectorAll('.perm-check:not([disabled])').forEach(cb => {
    perms.push({ role_id: cb.dataset.roleId, perm_id: cb.dataset.permId, allowed: cb.checked });
  });

  cgFetch('/super-admin/roles/permissions/save', {
    method: 'POST',
    body: JSON.stringify({ permissions: perms })
  }).then(d => {
    if (d.success) {
      CGToast.success('Permissions saved');
      isDirty = false;
      document.getElementById('saveMatrix').classList.remove('btn-warning');
      document.getElementById('saveMatrix').innerHTML = '<i class="fas fa-save"></i> Save Changes';
    } else CGToast.error(d.message || 'Save failed');
  });
}

function cloneRole(id, name) {
  Swal.fire({
    title: `Clone "${name}"?`,
    input: 'text', inputLabel: 'Name for the cloned role', inputPlaceholder: `${name} (Copy)`,
    inputValue: `${name} Copy`,
    showCancelButton: true, confirmButtonColor: '#6366f1',
    confirmButtonText: '<i class="fas fa-copy"></i> Clone'
  }).then(async r => {
    if (r.isConfirmed && r.value) {
      const d = await cgFetch(`/super-admin/roles/${id}/clone`, { method: 'POST', body: JSON.stringify({ name: r.value }) });
      if (d.success) { CGToast.success('Role cloned'); setTimeout(() => location.reload(), 800); }
      else CGToast.error(d.message);
    }
  });
}

function showRolePermissions(id, name) {
  document.getElementById('matrixRoleFilter').value = `role-${id}`;
  filterMatrix(`role-${id}`);
  document.getElementById('permMatrix').scrollIntoView({ behavior: 'smooth', block: 'start' });
  CGToast.info(`Showing permissions for ${name}`);
}

// Warn on unsaved changes
window.addEventListener('beforeunload', e => {
  if (isDirty) { e.preventDefault(); e.returnValue = ''; }
});
</script>
