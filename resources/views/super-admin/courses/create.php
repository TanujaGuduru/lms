<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/courses">Courses</a>
      <span class="sep">/</span><span>Create</span>
    </div>
    <h1 class="page-title">Create Course</h1>
    <p class="page-subtitle">Build a new course with modules and lessons</p>
  </div>
  <a href="/super-admin/courses" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<form method="POST" action="/super-admin/courses" enctype="multipart/form-data" data-loading="Creating…">
  <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">

  <div class="row g-3">
    <!-- Main -->
    <div class="col-xl-8">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Basic Info</h3></div>
        <div class="card-body">
          <div class="form-group mb-3">
            <label class="form-label required">Course Title</label>
            <input type="text" name="title" class="form-control <?= \App\Core\View::hasError('title') ? 'is-invalid' : '' ?>"
                   required placeholder="e.g. Complete Python Bootcamp 2025" value="<?= \App\Core\View::old('title') ?>">
            <?= \App\Core\View::error('title') ?>
          </div>

          <div class="form-group mb-3">
            <label class="form-label">Slug</label>
            <div class="input-group">
              <span class="input-group-text" style="font-size:13px;background:#f8fafc">/courses/</span>
              <input type="text" name="slug" id="courseSlug" class="form-control" placeholder="auto-generated" value="<?= \App\Core\View::old('slug') ?>">
            </div>
            <div class="form-hint">Leave blank to auto-generate from title</div>
          </div>

          <div class="form-group mb-3">
            <label class="form-label">Short Description</label>
            <textarea name="short_description" class="form-control" rows="2"
                      placeholder="One-line description shown in listings…"><?= \App\Core\View::old('short_description') ?></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">Full Description</label>
            <textarea name="description" class="form-control" rows="6"
                      placeholder="Full course overview, what students will learn…"><?= \App\Core\View::old('description') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Details -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Course Details</h3></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Department</label>
                <select name="department_id" class="form-select">
                  <option value="">Select department…</option>
                  <?php foreach ($depts as $dept): ?>
                  <option value="<?= $dept['id'] ?>"><?= \App\Core\View::e($dept['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Instructor</label>
                <select name="instructor_id" class="form-select">
                  <option value="">Select instructor…</option>
                  <?php foreach ($teachers as $ins): ?>
                  <option value="<?= $ins['id'] ?>"><?= \App\Core\View::e($ins['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Level</label>
                <select name="level" class="form-select">
                  <option value="beginner">Beginner</option>
                  <option value="intermediate">Intermediate</option>
                  <option value="advanced">Advanced</option>
                  <option value="all_levels">All Levels</option>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Language</label>
                <input type="text" name="language" class="form-control" placeholder="English" value="<?= \App\Core\View::old('language', 'English') ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Duration (hours)</label>
                <input type="number" name="duration_hours" class="form-control" min="0" step="0.5" placeholder="e.g. 20" value="<?= \App\Core\View::old('duration_hours') ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Max Students</label>
                <input type="number" name="max_students" class="form-control" min="1" placeholder="0 = unlimited" value="<?= \App\Core\View::old('max_students') ?>">
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pricing -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Pricing</h3></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Type</label>
                <select name="pricing_type" id="pricingType" class="form-select" onchange="togglePricing(this.value)">
                  <option value="free">Free</option>
                  <option value="paid">Paid</option>
                </select>
                <input type="hidden" name="is_free" id="isFreeField" value="1">
              </div>
            </div>
            <div class="col-md-4" id="priceField" style="display:none">
              <div class="form-group">
                <label class="form-label">Price (₹)</label>
                <input type="number" name="price" class="form-control" min="0" step="1" value="<?= \App\Core\View::old('price', '0') ?>">
              </div>
            </div>
            <div class="col-md-4" id="discountField" style="display:none">
              <div class="form-group">
                <label class="form-label">Discounted Price (₹)</label>
                <input type="number" name="discount_price" class="form-control" min="0" step="1" value="<?= \App\Core\View::old('discount_price') ?>">
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right -->
    <div class="col-xl-4">
      <!-- Thumbnail -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Thumbnail</h3></div>
        <div class="card-body">
          <div class="file-upload-area" onclick="document.getElementById('thumbInput').click()">
            <div id="thumbPreview">
              <i class="fas fa-image" style="font-size:40px;color:#cbd5e1;margin-bottom:12px"></i>
              <p style="font-size:13px;color:#94a3b8;margin:0">Click to upload thumbnail</p>
              <p style="font-size:12px;color:#cbd5e1;margin:4px 0 0">Recommended: 800×450px (16:9)</p>
            </div>
          </div>
          <input type="file" id="thumbInput" name="thumbnail" accept="image/*" style="display:none"
                 onchange="previewThumb(this)">
        </div>
      </div>

      <!-- Options -->
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Options</h3></div>
        <div class="card-body">
          <?php
          $opts = [
            ['name'=>'certificate_enabled', 'label'=>'Certificate on Completion', 'checked'=>true],
            ['name'=>'discussion_enabled',  'label'=>'Enable Discussion Forum',   'checked'=>true],
            ['name'=>'is_featured',         'label'=>'Feature on Homepage',       'checked'=>false],
          ];
          ?>
          <?php foreach ($opts as $opt): ?>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <span style="font-size:13.5px;color:#374151"><?= $opt['label'] ?></span>
            <label class="form-switch">
              <input type="checkbox" name="<?= $opt['name'] ?>" value="1" <?= $opt['checked'] ? 'checked' : '' ?>>
              <span class="toggle-track"></span>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Actions -->
      <div class="card">
        <div class="card-body">
          <button type="submit" name="status" value="draft" class="btn btn-secondary w-100 mb-2">
            <i class="fas fa-save"></i> Save as Draft
          </button>
          <button type="submit" name="status" value="published" class="btn btn-primary w-100">
            <i class="fas fa-globe"></i> Publish Course
          </button>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
// Auto-slug from title
document.querySelector('[name="title"]').addEventListener('input', function () {
  const slug = document.getElementById('courseSlug');
  if (!slug.dataset.manual) {
    slug.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  }
});
document.getElementById('courseSlug').addEventListener('input', function() {
  this.dataset.manual = '1';
});

function togglePricing(val) {
  document.getElementById('priceField').style.display    = val !== 'free' ? 'block' : 'none';
  document.getElementById('discountField').style.display = val === 'paid'  ? 'block' : 'none';
  document.getElementById('isFreeField').value = val === 'free' ? '1' : '0';
}

function previewThumb(input) {
  if (!input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('thumbPreview').innerHTML = `<img src="${e.target.result}" style="width:100%;border-radius:8px">`;
  };
  reader.readAsDataURL(input.files[0]);
}
</script>
