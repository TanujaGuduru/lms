<?php
$userModel = new \App\Models\User();
$fullName  = trim($user['first_name'] . ' ' . $user['last_name']);
?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/users">Users</a>
      <span class="sep">/</span><span><?= \App\Core\View::e($fullName) ?></span>
    </div>
    <h1 class="page-title"><?= \App\Core\View::e($fullName) ?></h1>
    <p class="page-subtitle">User Profile &amp; Activity</p>
  </div>
  <div class="d-flex gap-2">
    <a href="/super-admin/users/<?= $user['id'] ?>/edit" class="btn btn-secondary btn-sm">
      <i class="fas fa-edit"></i> Edit
    </a>
    <button onclick="toggleStatus()" class="btn btn-secondary btn-sm">
      <i class="fas fa-toggle-on"></i> Toggle Status
    </button>
    <button onclick="deleteUser()" class="btn btn-danger btn-sm">
      <i class="fas fa-trash"></i> Delete
    </button>
    <a href="/super-admin/users" class="btn btn-ghost btn-sm">
      <i class="fas fa-arrow-left"></i> Back
    </a>
  </div>
</div>

<!-- Profile Summary -->
<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <img src="<?= \App\Core\View::e($userModel->avatarUrl($user)) ?>" class="avatar" alt=""
           style="width:64px;height:64px;border-radius:14px;object-fit:cover">
      <div style="flex:1;min-width:200px">
        <div style="font-size:18px;font-weight:700;color:#0f172a"><?= \App\Core\View::e($fullName) ?></div>
        <div style="font-size:13px;color:#64748b"><?= \App\Core\View::e($user['email']) ?></div>
        <div class="d-flex gap-2 mt-2">
          <span class="badge" style="background:<?= \App\Core\View::e($user['role_color'] ?? '#6366f1') ?>18;color:<?= \App\Core\View::e($user['role_color'] ?? '#6366f1') ?>">
            <?= \App\Core\View::e($user['role_name'] ?? '—') ?>
          </span>
          <?= \App\Core\View::badge($user['status'] ?? 'inactive') ?>
          <?php if (!empty($user['email_verified_at'])): ?>
          <span class="badge" style="background:#10b98118;color:#10b981"><i class="fas fa-check-circle"></i> Verified</span>
          <?php else: ?>
          <span class="badge" style="background:#f59e0b18;color:#f59e0b">Unverified</span>
          <?php endif; ?>
          <?php if (!empty($user['two_factor_enabled'])): ?>
          <span class="badge" style="background:#6366f118;color:#6366f1"><i class="fas fa-shield-alt"></i> 2FA On</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="row g-3 text-center" style="min-width:280px">
        <div class="col-4">
          <div style="font-size:11.5px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px">Joined</div>
          <div style="font-size:13px;font-weight:600;color:#374151"><?= \App\Core\View::formatDate($user['created_at'], 'd M Y') ?></div>
        </div>
        <div class="col-4">
          <div style="font-size:11.5px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px">Last Login</div>
          <div style="font-size:13px;font-weight:600;color:#374151">
            <?= !empty($user['last_login_at']) ? \App\Core\View::timeAgo($user['last_login_at']) : '<span style="color:#cbd5e1">Never</span>' ?>
          </div>
        </div>
        <div class="col-4">
          <div style="font-size:11.5px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px">Logins</div>
          <div style="font-size:13px;font-weight:600;color:#374151"><?= (int)($user['login_count'] ?? 0) ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Left Column -->
  <div class="col-xl-8">

    <!-- Profile Info -->
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-id-card" style="color:#6366f1"></i> Profile Information</h3>
      </div>
      <div class="card-body">
        <?php
        $infoItems = [
          ['label' => 'Email',        'value' => $user['email'] ?? '—', 'icon' => 'fas fa-envelope'],
          ['label' => 'Phone',        'value' => $user['phone'] ?: '—', 'icon' => 'fas fa-phone'],
          ['label' => 'Gender',       'value' => $user['gender'] ? ucfirst(str_replace('_', ' ', $user['gender'])) : '—', 'icon' => 'fas fa-venus-mars'],
          ['label' => 'Date of Birth','value' => !empty($user['date_of_birth']) ? \App\Core\View::formatDate($user['date_of_birth'], 'd M Y') : '—', 'icon' => 'fas fa-birthday-cake'],
          ['label' => 'Location',    'value' => trim(implode(', ', array_filter([$user['city'] ?? '', $user['state'] ?? '', $user['country'] ?? '']))) ?: '—', 'icon' => 'fas fa-map-marker-alt'],
          ['label' => 'Address',     'value' => $user['address'] ?: '—', 'icon' => 'fas fa-home'],
        ];
        ?>
        <div class="row g-3">
          <?php foreach ($infoItems as $item): ?>
          <div class="col-md-6">
            <div style="font-size:11.5px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px">
              <i class="<?= $item['icon'] ?>" style="width:14px"></i> <?= $item['label'] ?>
            </div>
            <div style="font-size:13.5px;font-weight:600;color:#374151;margin-top:2px"><?= \App\Core\View::e($item['value']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if (!empty($user['bio'])): ?>
        <div class="mt-3 pt-3" style="border-top:1px solid #f1f5f9">
          <div style="font-size:11.5px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Bio</div>
          <div style="font-size:13.5px;line-height:1.7;color:#374151"><?= nl2br(\App\Core\View::e($user['bio'])) ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history" style="color:#f59e0b"></i> Recent Activity</h3>
      </div>
      <div class="card-body" style="padding:12px 16px">
        <?php if (!empty($recentActivity)): ?>
          <?php foreach ($recentActivity as $log): ?>
          <div class="d-flex gap-2 mb-3">
            <div style="width:24px;height:24px;background:#f1f5f9;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:10px;color:#94a3b8;flex-shrink:0">
              <i class="fas fa-circle"></i>
            </div>
            <div>
              <div style="font-size:13px;color:#374151">
                <strong><?= \App\Core\View::e(ucwords(str_replace('_', ' ', $log['action'] ?? ''))) ?></strong>
                <?php if (!empty($log['module'])): ?>
                <span style="color:#94a3b8"> &middot; <?= \App\Core\View::e($log['module']) ?></span>
                <?php endif; ?>
              </div>
              <?php if (!empty($log['description'])): ?>
              <div style="font-size:12.5px;color:#64748b"><?= \App\Core\View::e($log['description']) ?></div>
              <?php endif; ?>
              <div style="font-size:11px;color:#94a3b8"><?= \App\Core\View::timeAgo($log['created_at']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
        <div class="empty-state" style="padding:24px">
          <i class="fas fa-history empty-state-icon"></i>
          <p class="empty-state-desc">No recent activity recorded.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Active Sessions -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-desktop" style="color:#0891b2"></i> Active Sessions</h3>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Device</th>
              <th>Browser / OS</th>
              <th>IP Address</th>
              <th>Location</th>
              <th>Last Activity</th>
              <th>Status</th>
              <th style="width:90px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($sessions)): ?>
              <?php foreach ($sessions as $s): ?>
              <tr>
                <td style="font-size:13px;color:#374151">
                  <i class="fas fa-<?= ($s['device_type'] ?? '') === 'mobile' ? 'mobile-alt' : (($s['device_type'] ?? '') === 'tablet' ? 'tablet-alt' : 'desktop') ?>" style="color:#94a3b8"></i>
                  <?= \App\Core\View::e($s['device_type'] ?? '—') ?>
                </td>
                <td style="font-size:12.5px;color:#64748b"><?= \App\Core\View::e(trim(trim(($s['browser'] ?? '') . ' / ' . ($s['os'] ?? '')), '/')) ?></td>
                <td style="font-size:12.5px;color:#64748b"><?= \App\Core\View::e($s['ip_address'] ?? '—') ?></td>
                <td style="font-size:12.5px;color:#64748b"><?= \App\Core\View::e($s['location'] ?? '—') ?></td>
                <td style="font-size:12.5px;color:#94a3b8;white-space:nowrap">
                  <?= !empty($s['last_activity']) ? \App\Core\View::timeAgo($s['last_activity']) : '—' ?>
                </td>
                <td>
                  <?php if (!empty($s['is_active'])): ?>
                  <span class="badge" style="background:#10b98118;color:#10b981">Active</span>
                  <?php else: ?>
                  <span class="badge" style="background:#94a3b818;color:#94a3b8">Ended</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($s['is_active'])): ?>
                  <button onclick="revokeSession('<?= \App\Core\View::e($s['session_token'] ?? '') ?>', this)" class="btn btn-ghost btn-sm btn-icon" title="Revoke" style="color:#ef4444">
                    <i class="fas fa-ban"></i>
                  </button>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
            <tr><td colspan="7">
              <div class="empty-state" style="padding:24px">
                <i class="fas fa-desktop empty-state-icon"></i>
                <p class="empty-state-desc">No sessions recorded.</p>
              </div>
            </td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /col-8 -->

  <!-- Right Column -->
  <div class="col-xl-4">

    <!-- Enrollments -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-graduation-cap" style="color:#059669"></i> Enrollments (<?= count($enrollments) ?>)</h3>
      </div>
      <div class="card-body" style="padding:12px 16px">
        <?php if (!empty($enrollments)): ?>
          <?php foreach ($enrollments as $en): ?>
          <div class="d-flex gap-2 mb-3 pb-3" style="border-bottom:1px solid #f8fafc">
            <?php if (!empty($en['thumbnail'])): ?>
            <img src="<?= \App\Core\View::e($en['thumbnail']) ?>" style="width:52px;height:40px;border-radius:8px;object-fit:cover;flex-shrink:0">
            <?php else: ?>
            <div style="width:52px;height:40px;border-radius:8px;background:#6366f118;display:flex;align-items:center;justify-content:center;color:#6366f1;flex-shrink:0">
              <i class="fas fa-book"></i>
            </div>
            <?php endif; ?>
            <div style="flex:1;min-width:0">
              <div style="font-size:13px;font-weight:600;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                <?= \App\Core\View::e($en['course_title'] ?? '—') ?>
              </div>
              <div class="d-flex align-items-center gap-1 mt-1">
                <div class="progress" style="height:6px;border-radius:100px;background:#f1f5f9;flex:1">
                  <div class="progress-bar" style="width:<?= (int)($en['progress_percentage'] ?? 0) ?>%;background:#6366f1;border-radius:100px"></div>
                </div>
                <span style="font-size:11px;color:#94a3b8"><?= (int)($en['progress_percentage'] ?? 0) ?>%</span>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-1">
                <?= \App\Core\View::badge($en['status'] ?? 'active') ?>
                <span style="font-size:11px;color:#94a3b8">
                  <?= !empty($en['completed_at']) ? 'Completed ' . \App\Core\View::timeAgo($en['completed_at']) : (!empty($en['enrolled_at']) ? 'Enrolled ' . \App\Core\View::timeAgo($en['enrolled_at']) : '') ?>
                </span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
        <div class="empty-state" style="padding:24px">
          <i class="fas fa-graduation-cap empty-state-icon"></i>
          <p class="empty-state-desc">No course enrollments yet.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /col-4 -->
</div>

<script>
const __userId = <?= (int)$user['id'] ?>;

function toggleStatus() {
  cgFetch(`/super-admin/users/${__userId}/toggle-status`, { method: 'POST' })
    .then(d => {
      if (d.success) { CGToast.success('Status updated.'); setTimeout(() => location.reload(), 600); }
    });
}

function deleteUser() {
  Swal.fire({
    title: 'Delete User?',
    html: '<p style="color:#64748b">Are you sure you want to delete this user? This action cannot be undone.</p>',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, Delete',
    confirmButtonColor: '#ef4444',
    cancelButtonText: 'Cancel',
    reverseButtons: true
  }).then(r => {
    if (r.isConfirmed) {
      cgFetch(`/super-admin/users/${__userId}/delete`, { method: 'POST' })
        .then(d => {
          if (d.success) { CGToast.success('User deleted.'); setTimeout(() => location.href = '/super-admin/users', 600); }
        });
    }
  });
}

function revokeSession(sessionToken, btn) {
  Swal.fire({
    title: 'Revoke Session?',
    text: 'This will sign the user out on that device.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Revoke',
    confirmButtonColor: '#ef4444'
  }).then(r => {
    if (r.isConfirmed) {
      cgFetch(`/super-admin/security/sessions/${sessionToken}/revoke`, { method: 'POST' })
        .then(d => {
          if (d.success) {
            CGToast.success('Session revoked.');
            const row = btn.closest('tr');
            if (row) row.remove();
          }
        });
    }
  });
}
</script>
