<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>My Profile</span>
    </div>
    <h1 class="page-title">My Profile</h1>
    <p class="page-subtitle">Manage your personal information, security, and preferences</p>
  </div>
</div>

<div class="row g-3">
  <!-- Left: Avatar + Quick Info -->
  <div class="col-xl-3">
    <div class="card text-center" style="padding:32px 20px">
      <!-- Avatar -->
      <div style="position:relative;display:inline-block;margin:0 auto 16px">
        <div style="width:100px;height:100px;border-radius:50%;overflow:hidden;border:4px solid #e2e8f0;margin:0 auto">
          <?php if (!empty($user['avatar'])): ?>
          <img src="<?= \App\Core\View::e($user['avatar']) ?>" style="width:100%;height:100%;object-fit:cover" id="avatarImg">
          <?php else: ?>
          <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['first_name'].' '.$user['last_name']) ?>&size=100&background=6366f1&color=fff&bold=true"
               style="width:100%;height:100%;object-fit:cover" id="avatarImg">
          <?php endif; ?>
        </div>
        <label for="avatarUpload" style="position:absolute;bottom:0;right:0;width:28px;height:28px;background:#6366f1;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;font-size:11px;border:2px solid #fff" title="Change avatar">
          <i class="fas fa-camera"></i>
        </label>
        <input type="file" id="avatarUpload" accept="image/*" style="display:none" onchange="uploadAvatar(this)">
      </div>

      <div style="font-size:18px;font-weight:800;color:#0f172a"><?= \App\Core\View::e($user['first_name'] . ' ' . $user['last_name']) ?></div>
      <div style="font-size:13px;color:#94a3b8;margin-bottom:16px"><?= \App\Core\View::e($user['email']) ?></div>

      <?php
      $roleColors = ['super_admin'=>'#6366f1','admin'=>'#3b82f6'];
      $rc = $roleColors[$user['role_slug'] ?? ''] ?? '#64748b';
      ?>
      <span class="badge" style="background:<?= $rc ?>18;color:<?= $rc ?>;font-size:13px;padding:6px 16px">
        <?= ucfirst(str_replace('_',' ',$user['role_slug'] ?? '')) ?>
      </span>

      <div style="margin-top:24px;padding-top:16px;border-top:1px solid #f1f5f9">
        <div class="d-flex justify-content-between mb-2" style="font-size:13px">
          <span style="color:#94a3b8">Member Since</span>
          <span style="font-weight:600;color:#374151"><?= \App\Core\View::formatDate($user['created_at'], 'M Y') ?></span>
        </div>
        <div class="d-flex justify-content-between mb-2" style="font-size:13px">
          <span style="color:#94a3b8">Last Login</span>
          <span style="font-weight:600;color:#374151"><?= $user['last_login_at'] ? \App\Core\View::timeAgo($user['last_login_at']) : '—' ?></span>
        </div>
        <div class="d-flex justify-content-between" style="font-size:13px">
          <span style="color:#94a3b8">Account Status</span>
          <?= \App\Core\View::badge($user['status'] ?? 'active') ?>
        </div>
      </div>
    </div>

    <!-- Active Sessions -->
    <div class="card mt-3">
      <div class="card-header"><h3 class="card-title" style="font-size:13.5px"><i class="fas fa-desktop" style="color:#6366f1"></i> Active Sessions</h3></div>
      <div class="card-body" style="padding:8px 16px">
        <?php foreach ($sessions as $sess): ?>
        <div class="d-flex align-items-center gap-2 py-2" style="border-bottom:1px solid #f1f5f9">
          <div style="width:28px;height:28px;background:#f1f5f9;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#64748b;flex-shrink:0">
            <i class="fas fa-<?= str_contains(strtolower($sess['user_agent']??''),'mobile') ? 'mobile-alt' : 'desktop' ?>"></i>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:12px;font-weight:600;color:#374151;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= \App\Core\View::e(substr($sess['user_agent'] ?? 'Unknown', 0, 40)) ?>
            </div>
            <div style="font-size:11px;color:#94a3b8"><?= \App\Core\View::e($sess['ip_address'] ?? '') ?> · <?= \App\Core\View::timeAgo($sess['last_activity'] ?? $sess['created_at']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($sessions)): ?>
        <p style="font-size:13px;color:#94a3b8;text-align:center;padding:12px 0">No active sessions</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Right: Tabs -->
  <div class="col-xl-9">
    <div class="card">
      <div class="card-header" style="padding-bottom:0">
        <ul class="nav nav-tabs" style="border:none;gap:4px">
          <li class="nav-item">
            <button class="nav-link active" onclick="switchProfileTab('info', this)" style="border-radius:8px 8px 0 0;font-size:13.5px;font-weight:600">
              <i class="fas fa-user"></i> Personal Info
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" onclick="switchProfileTab('security', this)" style="border-radius:8px 8px 0 0;font-size:13.5px;font-weight:600">
              <i class="fas fa-shield-alt"></i> Security
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" onclick="switchProfileTab('activity', this)" style="border-radius:8px 8px 0 0;font-size:13.5px;font-weight:600">
              <i class="fas fa-history"></i> Activity
            </button>
          </li>
        </ul>
      </div>

      <!-- Personal Info Tab -->
      <div class="profile-tab" id="tab-info">
        <form method="POST" action="/super-admin/profile/update" data-loading="Saving…">
          <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="form-label">First Name <span style="color:#ef4444">*</span></label>
                  <input type="text" name="first_name" class="form-control <?= \App\Core\View::hasError('first_name') ? 'is-invalid' : '' ?>"
                         value="<?= \App\Core\View::old('first_name', $user['first_name'] ?? '') ?>">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="form-label">Last Name <span style="color:#ef4444">*</span></label>
                  <input type="text" name="last_name" class="form-control"
                         value="<?= \App\Core\View::old('last_name', $user['last_name'] ?? '') ?>">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" value="<?= \App\Core\View::e($user['email']) ?>" disabled>
                  <div class="form-hint">Email cannot be changed.</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="form-label">Phone</label>
                  <input type="text" name="phone" class="form-control"
                         value="<?= \App\Core\View::old('phone', $user['phone'] ?? '') ?>" placeholder="+91 XXXXX XXXXX">
                </div>
              </div>
              <div class="col-12">
                <div class="form-group">
                  <label class="form-label">Bio</label>
                  <textarea name="bio" class="form-control" rows="3" placeholder="A brief description about yourself…"><?= \App\Core\View::old('bio', $user['bio'] ?? '') ?></textarea>
                </div>
              </div>
            </div>
          </div>
          <div class="card-footer">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
          </div>
        </form>
      </div>

      <!-- Security Tab -->
      <div class="profile-tab d-none" id="tab-security">
        <div class="card-body">
          <h5 style="font-size:15px;font-weight:700;color:#0f172a;margin-bottom:20px">Change Password</h5>
          <form method="POST" action="/super-admin/profile/change-password" data-loading="Updating…">
            <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
            <div class="row g-3">
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label">Current Password</label>
                  <input type="password" name="current_password" class="form-control" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label">New Password</label>
                  <input type="password" name="new_password" class="form-control" id="profileNewPass" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label">Confirm New Password</label>
                  <input type="password" name="new_password_confirmation" class="form-control" required>
                </div>
              </div>
            </div>
            <div class="mt-3">
              <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Change Password</button>
            </div>
          </form>

          <hr style="margin:28px 0;border-color:#f1f5f9">

          <div class="d-flex align-items-center justify-content-between">
            <div>
              <h5 style="font-size:15px;font-weight:700;color:#0f172a;margin:0">Two-Factor Authentication</h5>
              <p style="font-size:13px;color:#64748b;margin:4px 0 0">Add an extra layer of security to your account.</p>
            </div>
            <?php if ($user['two_factor_enabled'] ?? false): ?>
            <button onclick="disable2FA()" class="btn btn-danger btn-sm"><i class="fas fa-times"></i> Disable 2FA</button>
            <?php else: ?>
            <button onclick="enable2FA()" class="btn btn-success btn-sm"><i class="fas fa-shield-alt"></i> Enable 2FA</button>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Activity Tab -->
      <div class="profile-tab d-none" id="tab-activity">
        <div class="table-responsive">
          <table class="data-table">
            <thead><tr><th>Action</th><th>Module</th><th>IP</th><th>When</th></tr></thead>
            <tbody>
              <?php foreach ($activity as $a):
                $actionColors = ['login'=>'#10b981','logout'=>'#64748b','created'=>'#6366f1','updated'=>'#f59e0b','deleted'=>'#ef4444'];
                $ac = $actionColors[$a['action']] ?? '#94a3b8';
              ?>
              <tr>
                <td>
                  <span class="badge" style="background:<?= $ac ?>18;color:<?= $ac ?>">
                    <?= str_replace('_', ' ', ucfirst(\App\Core\View::e($a['action']))) ?>
                  </span>
                </td>
                <td style="font-size:13px;color:#64748b;text-transform:capitalize"><?= \App\Core\View::e($a['module']) ?></td>
                <td style="font-family:monospace;font-size:12px;color:#64748b"><?= \App\Core\View::e($a['ip_address'] ?? '—') ?></td>
                <td style="font-size:12.5px;color:#94a3b8"><?= \App\Core\View::timeAgo($a['created_at']) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($activity)): ?>
              <tr><td colspan="4"><div class="empty-state" style="padding:24px"><p class="empty-state-desc">No activity recorded.</p></div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function switchProfileTab(tab, btn) {
  document.querySelectorAll('.profile-tab').forEach(t => t.classList.add('d-none'));
  document.getElementById('tab-' + tab).classList.remove('d-none');
  document.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}

async function uploadAvatar(input) {
  if (!input.files[0]) return;
  const formData = new FormData();
  formData.append('avatar', input.files[0]);
  formData.append('_csrf_token', CG.csrf);

  const resp = await fetch('/super-admin/profile/avatar', { method: 'POST', body: formData });
  const data = await resp.json().catch(() => ({}));
  if (data.success || resp.redirected) {
    CGToast.success('Avatar updated!');
    setTimeout(() => location.reload(), 800);
  } else CGToast.error('Upload failed');
}

async function enable2FA() {
  const d = await cgFetch('/super-admin/profile/2fa/enable', { method: 'POST' });
  if (d.success) { CGToast.success('2FA enabled!'); setTimeout(() => location.reload(), 800); }
  else CGToast.error(d.message);
}

async function disable2FA() {
  const r = await Swal.fire({ title: 'Disable 2FA?', text: 'This reduces account security.', icon: 'warning',
    showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Disable' });
  if (!r.isConfirmed) return;
  const d = await cgFetch('/super-admin/profile/2fa/disable', { method: 'POST' });
  if (d.success) { CGToast.success('2FA disabled'); setTimeout(() => location.reload(), 800); }
}
</script>
