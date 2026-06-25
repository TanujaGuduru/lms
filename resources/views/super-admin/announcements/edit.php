<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/announcements">Announcements</a>
      <span class="sep">/</span><span>Edit</span>
    </div>
    <h1 class="page-title">Edit Announcement</h1>
    <p class="page-subtitle">Update the announcement content and settings</p>
  </div>
  <a href="/super-admin/announcements" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<form method="POST" action="/super-admin/announcements/<?= (int)$ann['id'] ?>/update" data-loading="Saving…">
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
                   value="<?= \App\Core\View::e($ann['title']) ?>">
          </div>

          <div class="form-group mb-3">
            <label class="form-label required">Content</label>
            <textarea name="content" class="form-control" rows="6" required minlength="10"><?= \App\Core\View::e($ann['content']) ?></textarea>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label required">Type</label>
                <select name="type" class="form-select" required>
                  <?php foreach (['general'=>'General','urgent'=>'Urgent','event'=>'Event','maintenance'=>'Maintenance','feature'=>'Feature'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= $ann['type'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label required">Priority</label>
                <select name="priority" class="form-select" required>
                  <?php foreach (['low'=>'Low','medium'=>'Medium','high'=>'High','critical'=>'Critical'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= $ann['priority'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right -->
    <div class="col-xl-4">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Status</h3></div>
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span style="font-size:13px;color:#64748b">Current status:</span>
            <?= \App\Core\View::badge($ann['status']) ?>
          </div>
          <hr style="margin:12px 0;border-color:#f1f5f9">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div style="font-weight:600;font-size:13.5px;color:#374151">Pin Announcement</div>
              <div style="font-size:12px;color:#94a3b8">Keep at the top of the list</div>
            </div>
            <label class="form-switch">
              <input type="checkbox" name="is_pinned" value="1" <?= $ann['is_pinned'] ? 'checked' : '' ?>>
              <span class="toggle-track"></span>
            </label>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <button type="submit" class="btn btn-primary w-100 mb-2"><i class="fas fa-save"></i> Save Changes</button>
          <a href="/super-admin/announcements" class="btn btn-secondary w-100">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</form>
