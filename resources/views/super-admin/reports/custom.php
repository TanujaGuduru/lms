<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/reports">Reports</a>
      <span class="sep">/</span><span>Custom</span>
    </div>
    <h1 class="page-title">Custom Report Builder</h1>
    <p class="page-subtitle">Pick a report type and time range, then export to CSV</p>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-sliders-h" style="color:#0891b2"></i> Build a Report</h3>
  </div>
  <div class="card-body">
    <form method="GET" action="/super-admin/reports/export" class="row g-3 align-items-end">
      <div class="col-md-4">
        <div class="form-group">
          <label class="form-label">Report Type</label>
          <select name="type" class="form-select">
            <?php foreach ($reportTypes as $val => $label): ?>
            <option value="<?= \App\Core\View::e($val) ?>"><?= \App\Core\View::e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-group">
          <label class="form-label">Time Period</label>
          <select name="period" class="form-select">
            <?php foreach ($periods as $val => $label): ?>
            <option value="<?= \App\Core\View::e($val) ?>" <?= $val === '30d' ? 'selected' : '' ?>><?= \App\Core\View::e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-group">
          <label class="form-label">Format</label>
          <select name="format" class="form-select">
            <option value="csv" selected>CSV</option>
          </select>
        </div>
      </div>
      <div class="col-12">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-download"></i> Generate &amp; Download</button>
      </div>
    </form>
  </div>
</div>
