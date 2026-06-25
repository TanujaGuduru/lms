<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/announcements">Announcements</a>
      <span class="sep">/</span><span>New Announcement</span>
    </div>
    <h1 class="page-title">New Announcement</h1>
    <p class="page-subtitle">Broadcast a message to students, teachers and parents across all channels</p>
  </div>
  <a href="/super-admin/announcements" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<form method="POST" action="/super-admin/announcements/store" data-loading="Saving…">
  <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">

  <div class="row g-3">
    <!-- Left -->
    <div class="col-xl-8">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Announcement Details</h3></div>
        <div class="card-body">
          <div class="form-group mb-3">
            <label class="form-label required">Title</label>
            <input type="text" name="title" class="form-control" required minlength="3" maxlength="200"
                   placeholder="e.g. Mid-term Exam Schedule Released" value="<?= \App\Core\View::old('title') ?>">
          </div>

          <div class="form-group mb-3">
            <label class="form-label required">Content</label>
            <textarea name="content" class="form-control" rows="6" required minlength="10"
                      placeholder="Write the announcement message…"><?= \App\Core\View::old('content') ?></textarea>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label required">Type</label>
                <select name="type" class="form-select" required>
                  <?php foreach (['general'=>'General','urgent'=>'Urgent','event'=>'Event','maintenance'=>'Maintenance','feature'=>'Feature'] as $k=>$v): ?>
                  <option value="<?= $k ?>"><?= $v ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label required">Priority</label>
                <select name="priority" class="form-select" required>
                  <?php foreach (['low'=>'Low','medium'=>'Medium','high'=>'High','critical'=>'Critical'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= $k === 'medium' ? 'selected' : '' ?>><?= $v ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Channels -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Delivery Channels</h3></div>
        <div class="card-body">
          <p style="font-size:12.5px;color:#94a3b8;margin-bottom:14px">Choose how this announcement should be delivered to recipients.</p>
          <div class="row g-2">
            <?php
            $channels = [
              'email'    => ['label'=>'Email',    'icon'=>'fas fa-envelope'],
              'sms'      => ['label'=>'SMS',       'icon'=>'fas fa-sms'],
              'whatsapp' => ['label'=>'WhatsApp',  'icon'=>'fab fa-whatsapp'],
              'push'     => ['label'=>'Push',      'icon'=>'fas fa-bell'],
              'inapp'    => ['label'=>'In-App',    'icon'=>'fas fa-desktop'],
            ];
            ?>
            <?php foreach ($channels as $key => $ch): ?>
            <div class="col-md-4 col-6">
              <label class="d-flex align-items-center gap-2" style="padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;cursor:pointer">
                <input type="checkbox" name="channels[]" value="<?= $key ?>" <?= $key === 'inapp' ? 'checked' : '' ?>>
                <i class="<?= $ch['icon'] ?>" style="color:#6366f1"></i>
                <span style="font-size:13px;color:#374151"><?= $ch['label'] ?></span>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Audience -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Audience</h3></div>
        <div class="card-body">
          <p style="font-size:12.5px;color:#94a3b8;margin-bottom:14px">Select who should receive this announcement.</p>
          <div class="row g-2">
            <?php
            $audiences = [
              'all'     => ['label'=>'Everyone', 'icon'=>'fas fa-globe'],
              'student' => ['label'=>'Students',  'icon'=>'fas fa-user-graduate'],
              'teacher' => ['label'=>'Teachers',  'icon'=>'fas fa-chalkboard-teacher'],
              'admin'   => ['label'=>'Admins',    'icon'=>'fas fa-user-shield'],
              'parent'  => ['label'=>'Parents',   'icon'=>'fas fa-user-friends'],
            ];
            ?>
            <?php foreach ($audiences as $key => $a): ?>
            <div class="col-md-4 col-6">
              <label class="d-flex align-items-center gap-2" style="padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;cursor:pointer">
                <input type="checkbox" name="audience[]" value="<?= $key ?>" <?= $key === 'all' ? 'checked' : '' ?>>
                <i class="<?= $a['icon'] ?>" style="color:#6366f1"></i>
                <span style="font-size:13px;color:#374151"><?= $a['label'] ?></span>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Right -->
    <div class="col-xl-4">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Scheduling</h3></div>
        <div class="card-body">
          <div class="form-group mb-3">
            <label class="form-label">Schedule For (optional)</label>
            <input type="datetime-local" name="scheduled_at" class="form-control" value="<?= \App\Core\View::old('scheduled_at') ?>">
            <small style="font-size:11.5px;color:#94a3b8">Leave blank to save as a draft or send immediately.</small>
          </div>
          <hr style="margin:12px 0;border-color:#f1f5f9">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div style="font-weight:600;font-size:13.5px;color:#374151">Pin Announcement</div>
              <div style="font-size:12px;color:#94a3b8">Keep at the top of the list</div>
            </div>
            <label class="form-switch">
              <input type="checkbox" name="is_pinned" value="1">
              <span class="toggle-track"></span>
            </label>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <button type="submit" name="send_now" value="1" class="btn btn-success w-100 mb-2">
            <i class="fas fa-paper-plane"></i> Send Now
          </button>
          <button type="submit" class="btn btn-primary w-100 mb-2">
            <i class="fas fa-file-alt"></i> Save as Draft
          </button>
          <a href="/super-admin/announcements" class="btn btn-secondary w-100">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</form>
