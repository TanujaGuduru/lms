<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/users">Users</a>
      <span class="sep">/</span><span>Add New User</span>
    </div>
    <h1 class="page-title">Add New User</h1>
    <p class="page-subtitle">Create a new user account with role and permissions</p>
  </div>
  <a href="/super-admin/users" class="btn btn-secondary btn-sm">
    <i class="fas fa-arrow-left"></i> Back to Users
  </a>
</div>

<form method="POST" action="/super-admin/users/store" id="createUserForm" novalidate>
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
                       value="<?= \App\Core\View::old('first_name') ?>" placeholder="Enter first name" required>
                <div class="invalid-feedback"><?= \App\Core\View::error('first_name') ?></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Last Name <span class="required">*</span></label>
                <input type="text" name="last_name" class="form-control <?= \App\Core\View::hasError('last_name') ? 'is-invalid' : '' ?>"
                       value="<?= \App\Core\View::old('last_name') ?>" placeholder="Enter last name" required>
                <div class="invalid-feedback"><?= \App\Core\View::error('last_name') ?></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Email Address <span class="required">*</span></label>
                <div class="input-group">
                  <span class="input-prefix"><i class="fas fa-envelope"></i></span>
                  <input type="email" name="email" class="form-control <?= \App\Core\View::hasError('email') ? 'is-invalid' : '' ?>"
                         value="<?= \App\Core\View::old('email') ?>" placeholder="user@example.com" required>
                </div>
                <div class="invalid-feedback"><?= \App\Core\View::error('email') ?></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Phone Number</label>
                <div class="input-group">
                  <span class="input-prefix">+91</span>
                  <input type="tel" name="phone" class="form-control" value="<?= \App\Core\View::old('phone') ?>" placeholder="9876543210">
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Date of Birth</label>
                <input type="text" name="date_of_birth" class="form-control flatpickr-input"
                       value="<?= \App\Core\View::old('date_of_birth') ?>" placeholder="Select date">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Gender</label>
                <select name="gender" class="form-select">
                  <option value="">Select gender</option>
                  <option value="male"   <?= \App\Core\View::old('gender') === 'male'   ? 'selected' : '' ?>>Male</option>
                  <option value="female" <?= \App\Core\View::old('gender') === 'female' ? 'selected' : '' ?>>Female</option>
                  <option value="other"  <?= \App\Core\View::old('gender') === 'other'  ? 'selected' : '' ?>>Other</option>
                  <option value="prefer_not_to_say">Prefer not to say</option>
                </select>
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label class="form-label">Bio</label>
                <textarea name="bio" class="form-control" rows="3" placeholder="Short bio or description…" maxlength="1000"><?= \App\Core\View::old('bio') ?></textarea>
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
            <div class="col-12">
              <div class="form-group">
                <label class="form-label">Street Address</label>
                <input type="text" name="address" class="form-control" value="<?= \App\Core\View::old('address') ?>" placeholder="Street address">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control" value="<?= \App\Core\View::old('city') ?>" placeholder="City">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">State</label>
                <input type="text" name="state" class="form-control" value="<?= \App\Core\View::old('state') ?>" placeholder="State">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">PIN Code</label>
                <input type="text" name="pincode" class="form-control" value="<?= \App\Core\View::old('pincode') ?>" placeholder="6-digit PIN" maxlength="6">
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
              <option value="<?= $r['id'] ?>" <?= \App\Core\View::old('role_id') == $r['id'] ? 'selected' : '' ?>>
                <?= \App\Core\View::e($r['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?= \App\Core\View::error('role_id') ?></div>
          </div>

          <div class="form-group">
            <label class="form-label">Account Status <span class="required">*</span></label>
            <select name="status" class="form-select" required>
              <option value="active"   <?= (\App\Core\View::old('status','active') === 'active')   ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= (\App\Core\View::old('status') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
              <option value="pending"  <?= (\App\Core\View::old('status') === 'pending')  ? 'selected' : '' ?>>Pending</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Password -->
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-key" style="color:#f59e0b"></i> Password</h3>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Password <span class="required">*</span></label>
            <div class="input-wrap position-relative">
              <input type="password" name="password" id="passwordField"
                     class="form-control <?= \App\Core\View::hasError('password') ? 'is-invalid' : '' ?>"
                     placeholder="Min 8 characters" required>
              <button type="button" class="btn btn-ghost btn-sm" style="position:absolute;right:4px;top:50%;transform:translateY(-50%);padding:4px 8px"
                      onclick="togglePw('passwordField','togglePwIcon')">
                <i class="fas fa-eye" id="togglePwIcon"></i>
              </button>
            </div>
            <div class="invalid-feedback"><?= \App\Core\View::error('password') ?></div>
            <!-- Password strength -->
            <div class="mt-2">
              <div class="progress-bar-custom"><div class="bar" id="pwStrengthBar" style="width:0%;background:#ef4444;transition:width .3s,background .3s"></div></div>
              <div style="font-size:11px;color:#94a3b8;margin-top:4px" id="pwStrengthLabel"></div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Confirm Password <span class="required">*</span></label>
            <div class="input-wrap position-relative">
              <input type="password" name="password_confirmation" id="confirmField"
                     class="form-control" placeholder="Repeat password" required>
            </div>
            <div id="pwMatchMsg" style="font-size:12px;margin-top:4px"></div>
          </div>

          <div class="alert alert-info" style="font-size:12px;padding:10px 14px">
            <i class="fas fa-info-circle"></i>
            Password must be 8+ chars with uppercase, lowercase, number and special character.
          </div>
        </div>
      </div>

      <!-- Avatar -->
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-image" style="color:#8b5cf6"></i> Profile Photo</h3>
        </div>
        <div class="card-body text-center">
          <div id="avatarPreview" style="width:80px;height:80px;background:linear-gradient(135deg,#6366f1,#4f46e5);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:32px;color:#fff;margin:0 auto 12px;overflow:hidden">
            <i class="fas fa-user"></i>
          </div>
          <input type="file" name="avatar" id="avatarInput" accept="image/*" style="display:none">
          <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('avatarInput').click()">
            <i class="fas fa-upload"></i> Upload Photo
          </button>
          <div class="form-hint">JPG, PNG or WEBP. Max 2MB.</div>
        </div>
      </div>

      <!-- Submit -->
      <div class="d-flex flex-column gap-2">
        <button type="submit" class="btn btn-primary" id="submitBtn">
          <i class="fas fa-user-plus"></i> Create User
        </button>
        <a href="/super-admin/users" class="btn btn-secondary">
          <i class="fas fa-times"></i> Cancel
        </a>
      </div>

    </div><!-- /col-4 -->

  </div>
</form>

<script>
// Password strength meter
document.getElementById('passwordField').addEventListener('input', function() {
  const val   = this.value;
  const bar   = document.getElementById('pwStrengthBar');
  const label = document.getElementById('pwStrengthLabel');
  let score   = 0;
  if (val.length >= 8)     score++;
  if (/[A-Z]/.test(val))  score++;
  if (/[a-z]/.test(val))  score++;
  if (/[0-9]/.test(val))  score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  const configs = ['','Weak','Fair','Good','Strong','Very Strong'];
  const colors  = ['','#ef4444','#f59e0b','#3b82f6','#10b981','#059669'];
  bar.style.width     = (score * 20) + '%';
  bar.style.background = colors[score] || '#ef4444';
  label.textContent   = score > 0 ? configs[score] : '';
  label.style.color   = colors[score] || '#ef4444';
});

document.getElementById('confirmField').addEventListener('input', function() {
  const pw    = document.getElementById('passwordField').value;
  const msg   = document.getElementById('pwMatchMsg');
  if (this.value && this.value !== pw) {
    msg.textContent = '✕ Passwords do not match';
    msg.style.color = '#ef4444';
  } else if (this.value) {
    msg.textContent = '✓ Passwords match';
    msg.style.color = '#10b981';
  } else msg.textContent = '';
});

function togglePw(fieldId, iconId) {
  const f = document.getElementById(fieldId);
  const i = document.getElementById(iconId);
  f.type     = f.type === 'password' ? 'text' : 'password';
  i.className = f.type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
}

// Avatar preview
document.getElementById('avatarInput').addEventListener('change', function() {
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    const prev = document.getElementById('avatarPreview');
    prev.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover">`;
  };
  reader.readAsDataURL(file);
});

// Submit loading state
document.getElementById('createUserForm').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.disabled  = true;
  btn.innerHTML = '<span class="loading-spinner"></span> Creating…';
});

// Init select2 & flatpickr
$(document).ready(function() {
  $('.select2').select2({ theme: 'default', width: '100%' });
  flatpickr('.flatpickr-input', { dateFormat: 'Y-m-d', maxDate: 'today' });
});
</script>
