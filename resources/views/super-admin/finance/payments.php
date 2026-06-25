<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/finance">Finance</a>
      <span class="sep">/</span><span>Payments</span>
    </div>
    <h1 class="page-title">All Payments</h1>
    <p class="page-subtitle">Browse and filter every payment transaction</p>
  </div>
  <div class="d-flex gap-2">
    <a href="/super-admin/finance/reports" class="btn btn-secondary btn-sm"><i class="fas fa-file-export"></i> Reports</a>
    <a href="/super-admin/finance" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-center">
      <div class="col-md-3">
        <div class="table-search" style="margin:0"><i class="fas fa-search search-icon"></i>
          <input type="text" name="search" placeholder="Invoice, student, email…" value="<?= \App\Core\View::e($filters['search'] ?? '') ?>">
        </div>
      </div>
      <div class="col-md-2">
        <select name="status" class="form-select" style="font-size:13px">
          <option value="">All Status</option>
          <?php foreach (['pending','processing','success','failed','refunded','partially_refunded'] as $s): ?>
          <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="gateway" class="form-select" style="font-size:13px">
          <option value="">All Gateways</option>
          <?php foreach (['razorpay','stripe','manual','scholarship'] as $g): ?>
          <option value="<?= $g ?>" <?= ($filters['gateway'] ?? '') === $g ? 'selected' : '' ?>><?= ucfirst($g) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <input type="date" name="date_from" class="form-control" style="font-size:13px" value="<?= \App\Core\View::e($filters['date_from'] ?? '') ?>" title="From date">
      </div>
      <div class="col-md-2">
        <input type="date" name="date_to" class="form-control" style="font-size:13px" value="<?= \App\Core\View::e($filters['date_to'] ?? '') ?>" title="To date">
      </div>
      <div class="col-md-1">
        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i></button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Invoice</th><th>Student</th><th>Course</th><th>Amount</th><th>Gateway</th><th>Status</th><th>Paid On</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($payments)): ?>
        <tr><td colspan="7">
          <div class="empty-state">
            <i class="fas fa-receipt empty-state-icon"></i>
            <h4 class="empty-state-title">No Payments Found</h4>
            <p class="empty-state-desc">No payments match your current filters.</p>
          </div>
        </td></tr>
        <?php else: ?>
        <?php
        $gColors = ['razorpay'=>'#3395FF','stripe'=>'#635BFF','manual'=>'#64748b','scholarship'=>'#10b981'];
        $statusColors = [
          'success'             => '#10b981',
          'pending'             => '#f59e0b',
          'processing'          => '#f59e0b',
          'failed'              => '#ef4444',
          'refunded'            => '#ef4444',
          'partially_refunded'  => '#ef4444',
        ];
        foreach ($payments as $p):
          $gc = $gColors[$p['gateway']] ?? '#64748b';
          $sc = $statusColors[$p['status']] ?? '#94a3b8';
        ?>
        <tr>
          <td style="font-family:monospace;font-size:12px;color:#6366f1"><?= \App\Core\View::e($p['invoice_number']) ?></td>
          <td>
            <div style="font-size:13.5px;font-weight:600"><?= \App\Core\View::e($p['student_name'] ?? '—') ?></div>
            <div style="font-size:11.5px;color:#94a3b8"><?= \App\Core\View::e($p['student_email'] ?? '') ?></div>
          </td>
          <td style="font-size:13px;color:#64748b;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= \App\Core\View::e($p['course_title'] ?? '—') ?></td>
          <td style="font-weight:700;font-size:14px"><?= \App\Core\View::formatMoney((float)($p['total_amount'] ?? $p['amount'] ?? 0), $p['currency'] ?? 'INR') ?></td>
          <td>
            <span class="badge" style="background:<?= $gc ?>18;color:<?= $gc ?>;text-transform:capitalize"><?= \App\Core\View::e($p['gateway'] ?? '—') ?></span>
          </td>
          <td>
            <span class="badge" style="background:<?= $sc ?>18;color:<?= $sc ?>;text-transform:capitalize"><?= str_replace('_',' ',$p['status']) ?></span>
          </td>
          <td style="font-size:12.5px;color:#94a3b8"><?= \App\Core\View::formatDate($p['paid_at'] ?? $p['created_at'] ?? null, 'd M Y') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if (($meta['last_page'] ?? 1) > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <div style="font-size:13px;color:#64748b">Showing <?= $meta['from'] ?>–<?= $meta['to'] ?> of <?= number_format($meta['total']) ?></div>
    <nav><ul class="pagination" style="margin:0;gap:4px">
      <?php for ($p = max(1, $meta['current_page'] - 3); $p <= min($meta['last_page'], $meta['current_page'] + 3); $p++): ?>
      <li class="page-item <?= $p === $meta['current_page'] ? 'active' : '' ?>">
        <a class="page-link" href="?<?= http_build_query(array_merge($filters ?? [], ['page' => $p])) ?>"><?= $p ?></a>
      </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
