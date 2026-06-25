<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/certificates">Certificates</a>
      <span class="sep">/</span>
      <a href="/super-admin/certificates/templates">Templates</a>
      <span class="sep">/</span><span>New Template</span>
    </div>
    <h1 class="page-title">New Certificate Template</h1>
    <p class="page-subtitle">Design a reusable HTML template for issuing certificates</p>
  </div>
  <a href="/super-admin/certificates/templates" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<form method="POST" action="/super-admin/certificates/templates/store" data-loading="Saving…">
  <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">

  <div class="row g-3">
    <!-- Left -->
    <div class="col-xl-8">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Template Details</h3></div>
        <div class="card-body">
          <div class="form-group mb-3">
            <label class="form-label required">Template Name</label>
            <input type="text" name="name" class="form-control" required minlength="3" maxlength="150"
                   placeholder="e.g. Standard Gold Certificate" value="<?= \App\Core\View::old('name') ?>">
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label required">Orientation</label>
                <select name="orientation" class="form-select" required>
                  <option value="landscape">Landscape</option>
                  <option value="portrait">Portrait</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label required">Paper Size</label>
                <select name="paper_size" class="form-select" required>
                  <option value="A4">A4</option>
                  <option value="A3">A3</option>
                  <option value="Letter">Letter</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Template HTML</h3></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label required">HTML Markup</label>
            <textarea name="template_html" class="form-control" rows="14" required style="font-family:'Courier New',monospace;font-size:12.5px"
                      placeholder="<div style='text-align:center'>&#10;  <h1>Certificate of Completion</h1>&#10;  <p>This certifies that {{student_name}} has completed {{course_name}}</p>&#10;</div>"><?= \App\Core\View::old('template_html') ?></textarea>
            <small style="font-size:11.5px;color:#94a3b8">
              Available placeholders: <code>{{student_name}}</code>, <code>{{course_name}}</code>,
              <code>{{certificate_number}}</code>, <code>{{date}}</code>. They will be substituted when a certificate is issued.
            </small>
          </div>
        </div>
      </div>
    </div>

    <!-- Right -->
    <div class="col-xl-4">
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Available Variables</h3></div>
        <div class="card-body">
          <p style="font-size:12.5px;color:#94a3b8;margin-bottom:14px">Mark which placeholder variables this template uses.</p>
          <div class="row g-2">
            <?php
            $variables = [
              'student_name'        => 'Student Name',
              'course_name'         => 'Course Name',
              'certificate_number'  => 'Certificate Number',
              'date'                => 'Issue Date',
              'instructor_name'     => 'Instructor Name',
              'duration'            => 'Course Duration',
            ];
            ?>
            <?php foreach ($variables as $key => $label): ?>
            <div class="col-12">
              <label class="d-flex align-items-center gap-2" style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:10px;cursor:pointer">
                <input type="checkbox" name="variables[]" value="<?= $key ?>" <?= in_array($key, ['student_name','course_name','certificate_number','date'], true) ? 'checked' : '' ?>>
                <span style="font-size:13px;color:#374151"><?= $label ?></span>
                <code style="font-size:11px;color:#94a3b8;margin-left:auto">{{<?= $key ?>}}</code>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div style="font-weight:600;font-size:13.5px;color:#374151">Set as Default</div>
              <div style="font-size:12px;color:#94a3b8">Used when issuing certificates</div>
            </div>
            <label class="form-switch">
              <input type="checkbox" name="is_default" value="1">
              <span class="toggle-track"></span>
            </label>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <button type="submit" class="btn btn-primary w-100 mb-2"><i class="fas fa-save"></i> Create Template</button>
          <a href="/super-admin/certificates/templates" class="btn btn-secondary w-100">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</form>
