<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/events">Events</a>
      <span class="sep">/</span><span>Create Event</span>
    </div>
    <h1 class="page-title">Create Event</h1>
    <p class="page-subtitle">Schedule a new event, webinar, or workshop</p>
  </div>
  <a href="/super-admin/events" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<form method="POST" action="/super-admin/events" enctype="multipart/form-data" data-loading="Creating…">
  <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">

  <div class="row g-3">
    <!-- Left -->
    <div class="col-xl-8">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Event Details</h3></div>
        <div class="card-body">
          <div class="form-group mb-3">
            <label class="form-label required">Event Title</label>
            <input type="text" name="title" class="form-control" required placeholder="e.g. Full Stack Web Dev Workshop 2025" value="<?= \App\Core\View::old('title') ?>">
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label required">Event Type</label>
                <select name="type" class="form-select" required>
                  <?php foreach (['webinar'=>'Webinar','workshop'=>'Workshop','seminar'=>'Seminar','hackathon'=>'Hackathon','other'=>'Other'] as $k=>$v): ?>
                  <option value="<?= $k ?>"><?= $v ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Max Seats</label>
                <input type="number" name="max_participants" class="form-control" min="1" placeholder="0 = unlimited" value="<?= \App\Core\View::old('max_participants') ?>">
              </div>
            </div>
          </div>

          <div class="form-group mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="5" placeholder="Describe this event…"><?= \App\Core\View::old('description') ?></textarea>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label required">Start Date & Time</label>
                <input type="datetime-local" name="start_datetime" class="form-control" required value="<?= \App\Core\View::old('start_datetime') ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label required">End Date & Time</label>
                <input type="datetime-local" name="end_datetime" class="form-control" required value="<?= \App\Core\View::old('end_datetime') ?>">
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Location -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Location</h3></div>
        <div class="card-body">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Format</label>
                <select id="formatSelect" class="form-select" onchange="toggleLocation(this.value)">
                  <option value="1">Online</option>
                  <option value="0">In-Person</option>
                </select>
              </div>
            </div>
            <div class="col-md-6" id="locationField">
              <div class="form-group">
                <label class="form-label">Venue / Address</label>
                <input type="text" name="venue" class="form-control" placeholder="Conference Room A, Building B…" value="<?= \App\Core\View::old('venue') ?>">
              </div>
            </div>
          </div>

          <div id="onlineFields">
            <div class="form-group mb-3">
              <label class="form-label">Meeting Link</label>
              <input type="url" name="meeting_link" class="form-control" placeholder="https://meet.google.com/…" value="<?= \App\Core\View::old('meeting_link') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Meeting Password</label>
              <input type="text" name="meeting_password" class="form-control" placeholder="Optional password" value="<?= \App\Core\View::old('meeting_password') ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right -->
    <div class="col-xl-4">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Banner Image</h3></div>
        <div class="card-body">
          <div class="file-upload-area" id="bannerDropArea" onclick="document.getElementById('bannerInput').click()">
            <div id="bannerPreview">
              <i class="fas fa-image" style="font-size:40px;color:#cbd5e1;margin-bottom:12px"></i>
              <p style="font-size:13px;color:#94a3b8;margin:0">Click or drag banner image</p>
              <p style="font-size:12px;color:#cbd5e1;margin:4px 0 0">Recommended: 1200×630px</p>
            </div>
          </div>
          <input type="file" id="bannerInput" name="banner" accept="image/*" style="display:none" onchange="previewBanner(this)">
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Registration</h3></div>
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <div style="font-weight:600;font-size:13.5px;color:#374151">Free Event</div>
              <div style="font-size:12px;color:#94a3b8">No registration fee</div>
            </div>
            <label class="form-switch">
              <input type="checkbox" name="is_free" id="isFree" value="1" checked onchange="togglePrice(this)">
              <span class="toggle-track"></span>
            </label>
          </div>
          <div id="priceField" style="display:none">
            <div class="form-group">
              <label class="form-label">Ticket Price (₹)</label>
              <input type="number" name="price" class="form-control" min="0" step="1" value="<?= \App\Core\View::old('price', '0') ?>">
            </div>
          </div>
          <hr style="margin:12px 0;border-color:#f1f5f9">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div style="font-weight:600;font-size:13.5px;color:#374151">Registration Required</div>
              <div style="font-size:12px;color:#94a3b8">Students must register to attend</div>
            </div>
            <label class="form-switch">
              <input type="checkbox" name="registration_required" value="1" checked>
              <span class="toggle-track"></span>
            </label>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <button type="submit" class="btn btn-primary w-100 mb-2"><i class="fas fa-calendar-plus"></i> Create Event</button>
          <a href="/super-admin/events" class="btn btn-secondary w-100">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
function toggleLocation(val) {
  document.getElementById('onlineFields').style.display = val === '1' ? 'block' : 'none';
  document.getElementById('locationField').style.display = val === '0' ? 'block' : 'none';
}

function togglePrice(cb) {
  document.getElementById('priceField').style.display = cb.checked ? 'none' : 'block';
}

function previewBanner(input) {
  if (!input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('bannerPreview').innerHTML = `<img src="${e.target.result}" style="width:100%;border-radius:8px">`;
  };
  reader.readAsDataURL(input.files[0]);
}

toggleLocation('1');
</script>
