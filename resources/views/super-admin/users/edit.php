<?php $fullName = trim($user['first_name'] . ' ' . $user['last_name']); ?>

<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/users">Users</a>
      <span class="sep">/</span>
      <a href="/super-admin/users/<?= $user['id'] ?>"><?= \App\Core\View::e($fullName) ?></a>
      <span class="sep">/</span><span>Edit</span>
    </div>
    <h1 class="page-title">Edit User</h1>
    <p class="page-subtitle"><?= \App\Core\View::e($fullName) ?></p>
  </div>
  <a href="/super-admin/users/<?= $user['id'] ?>" class="btn btn-secondary btn-sm">
    <i class="fas fa-arrow-left"></i> Back to Profile
  </a>
</div>

<form method="POST" action="/super-admin/users/<?= $user['id'] ?>/update" id="editUserForm" novalidate>
  <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">

  <div class="row g-3">

    <!-- Left Column: Personal Info -->
    <div class="col-xl-8">
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-user" style="color:#6366f1"></i> Personal Information</h3>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">First Name <span class="required">*</span></label>
                <input type="text" name="first_name" class="form-control <?= \App\Core\View::hasError('first_name') ? 'is-invalid' : '' ?>"
                       value="<?= \App\Core\View::old('first_name', $user['first_name'] ?? '') ?>" placeholder="Enter first name" required>
                <div class="invalid-feedback"><?= \App\Core\View::error('first_name') ?></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Last Name <span class="required">*</span></label>
                <input type="text" name="last_name" class="form-control <?= \App\Core\View::hasError('last_name') ? 'is-invalid' : '' ?>"
                       value="<?= \App\Core\View::old('last_name', $user['last_name'] ?? '') ?>" placeholder="Enter last name" required>
                <div class="invalid-feedback"><?= \App\Core\View::error('last_name') ?></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Email Address <span class="required">*</span></label>
                <div class="input-group">
                  <span class="input-prefix"><i class="fas fa-envelope"></i></span>
                  <input type="email" name="email" class="form-control <?= \App\Core\View::hasError('email') ? 'is-invalid' : '' ?>"
                         value="<?= \App\Core\View::old('email', $user['email'] ?? '') ?>" placeholder="user@example.com" required>
                </div>
                <div class="invalid-feedback"><?= \App\Core\View::error('email') ?></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Phone Number</label>
                <div class="input-group">
                  <span class="input-prefix">+91</span>
                  <input type="tel" name="phone" class="form-control" value="<?= \App\Core\View::old('phone', $user['phone'] ?? '') ?>" placeholder="9876543210">
                </div>
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label class="form-label">Bio</label>
                <textarea name="bio" class="form-control" rows="3" placeholder="Short bio or description…" maxlength="1000"><?= \App\Core\View::old('bio', $user['bio'] ?? '') ?></textarea>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Address -->
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-map-marker-alt" style="color:#10b981"></i> Address</h3>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control" value="<?= \App\Core\View::old('city', $user['city'] ?? '') ?>" placeholder="City">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">State</label>
                <input type="text" name="state" class="form-control" value="<?= \App\Core\View::old('state', $user['state'] ?? '') ?>" placeholder="State">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Country</label>
                <input type="text" name="country" class="form-control" value="<?= \App\Core\View::old('country', $user['country'] ?? '') ?>" placeholder="Country">
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /col-8 -->

    <!-- Right Column: Account Settings -->
    <div class="col-xl-4">

      <!-- Role & Status -->
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-shield-alt" style="color:#6366f1"></i> Account Settings</h3>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Role <span class="required">*</span></label>
            <select name="role_id" class="form-select select2 <?= \App\Core\View::hasError('role_id') ? 'is-invalid' : '' ?>" required>
              <option value="">Select a role</option>
              <?php foreach ($roles as $r): ?>
              <option value="<?= $r['id'] ?>" <?= \App\Core\View::old('role_id', (string)($user['role_id'] ?? '')) == $r['id'] ? 'selected' : '' ?>>
                <?= \App\Core\View::e($r['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?= \App\Core\View::error('role_id') ?></div>
          </div>

          <div class="form-group">
            <label class="form-label">Account Status <span class="required">*</span></label>
            <select name="status" class="form-select" required>
              <?php $curStatus = \App\Core\View::old('status', $user['status'] ?? 'active'); ?>
              <option value="active"    <?= $curStatus === 'active'    ? 'selected' : '' ?>>Active</option>
              <option value="inactive"  <?= $curStatus === 'inactive'  ? 'selected' : '' ?>>Inactive</option>
              <option value="pending"   <?= $curStatus === 'pending'   ? 'selected' : '' ?>>Pending</option>
              <option value="suspended" <?= $curStatus === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            </select>
            <div class="invalid-feedback"><?= \App\Core\View::error('status') ?></div>
          </div>
        </div>
      </div>

      <!-- Account Info -->
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-info-circle" style="color:#94a3b8"></i> Account Info</h3>
        </div>
        <div class="card-body" style="padding:12px 16px">
          <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid #f8fafc;font-size:13px">
            <span style="color:#94a3b8">Joined</span>
            <span style="font-weight:600;color:#374151"><?= \App\Core\View::formatDate($user['created_at'] ?? null, 'd M Y') ?></span>
          </div>
          <div class="d-flex justify-content-between py-2" style="font-size:13px">
            <span style="color:#94a3b8">Last Login</span>
            <span style="font-weight:600;color:#374151">
              <?= !empty($user['last_login_at']) ? \App\Core\View::timeAgo($user['last_login_at']) : '—' ?>
            </span>
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div class="d-flex flex-column gap-2">
        <button type="submit" class="btn btn-primary" id="submitBtn">
          <i class="fas fa-save"></i> Save Changes
        </button>
        <a href="/super-admin/users/<?= $user['id'] ?>" class="btn btn-secondary">
          <i class="fas fa-times"></i> Cancel
        </a>
      </div>

    </div><!-- /col-4 -->

  </div>
</form>

<script>
document.getElementById('editUserForm').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.disabled  = true;
  btn.innerHTML = '<span class="loading-spinner"></span> Saving…';
});

$(document).ready(function() {
  $('.select2').select2({ theme: 'default', width: '100%' });
});
</script>
