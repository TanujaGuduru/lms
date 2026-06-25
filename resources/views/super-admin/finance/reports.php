<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/finance">Finance</a>
      <span class="sep">/</span><span>Reports</span>
    </div>
    <h1 class="page-title">Finance Reports</h1>
    <p class="page-subtitle">Download payment reports for a given period</p>
  </div>
  <a href="/super-admin/finance" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-file-csv" style="color:#10b981"></i> Export Payment Report (CSV)</h3>
  </div>
  <div class="card-body">
    <p style="font-size:13.5px;color:#64748b;margin-bottom:20px">
      Choose a period below to download a CSV report of all successful payments, including invoice number, student, course, amount, gateway, method, status, and payment date.
    </p>
    <div class="row g-3">
      <div class="col-md-3">
        <a href="/super-admin/finance/reports?format=csv&period=today" class="btn btn-secondary w-100">
          <i class="fas fa-calendar-day"></i> Today
        </a>
      </div>
      <div class="col-md-3">
        <a href="/super-admin/finance/reports?format=csv&period=week" class="btn btn-secondary w-100">
          <i class="fas fa-calendar-week"></i> This Week
        </a>
      </div>
      <div class="col-md-3">
        <a href="/super-admin/finance/reports?format=csv&period=month" class="btn btn-primary w-100">
          <i class="fas fa-calendar-alt"></i> This Month
        </a>
      </div>
      <div class="col-md-3">
        <a href="/super-admin/finance/reports?format=csv&period=year" class="btn btn-secondary w-100">
          <i class="fas fa-calendar"></i> This Year
        </a>
      </div>
    </div>
  </div>
</div>
