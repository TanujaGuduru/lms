<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span>
      <a href="/super-admin/finance">Finance</a>
      <span class="sep">/</span><span>Fee Structures</span>
    </div>
    <h1 class="page-title">Fee Structures</h1>
    <p class="page-subtitle">Define course pricing, billing cycles, and GST rates</p>
  </div>
  <button onclick="openAddFeeStructure()" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Fee Structure</button>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr><th>Name</th><th>Course</th><th>Amount</th><th>Billing Cycle</th><th>GST %</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php if (empty($structures)): ?>
        <tr><td colspan="6">
          <div class="empty-state">
            <i class="fas fa-file-invoice-dollar empty-state-icon"></i>
            <h4 class="empty-state-title">No Fee Structures</h4>
            <p class="empty-state-desc">Add a fee structure to define pricing for a course.</p>
          </div>
        </td></tr>
        <?php else: ?>
        <?php
        $cycleLabels = [
          'one_time'    => 'One Time',
          'monthly'     => 'Monthly',
          'quarterly'   => 'Quarterly',
          'semi_annual' => 'Semi-Annual',
          'annual'      => 'Annual',
        ];
        foreach ($structures as $fs):
        ?>
        <tr>
          <td style="font-size:13.5px;font-weight:600;color:#0f172a"><?= \App\Core\View::e($fs['name']) ?></td>
          <td style="font-size:13px;color:#64748b"><?= \App\Core\View::e($fs['course_title'] ?? 'All Courses') ?></td>
          <td style="font-weight:700;font-size:14px"><?= \App\Core\View::formatMoney((float)$fs['amount'], $fs['currency'] ?? 'INR') ?></td>
          <td>
            <span class="badge" style="background:#6366f118;color:#6366f1"><?= $cycleLabels[$fs['billing_cycle']] ?? ucfirst($fs['billing_cycle']) ?></span>
          </td>
          <td style="font-size:13px;color:#64748b"><?= number_format((float)($fs['gst_percentage'] ?? 0), 2) ?>%</td>
          <td>
            <?php $active = (bool)($fs['is_active'] ?? false); ?>
            <span class="badge" style="background:<?= $active ? '#10b98118' : '#94a3b818' ?>;color:<?= $active ? '#059669' : '#64748b' ?>">
              <?= $active ? 'Active' : 'Inactive' ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Fee Structure Modal -->
<div class="modal fade" id="feeStructureModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Fee Structure</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="feeStructureForm" method="POST" action="/super-admin/finance/fee-structures/store">
        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label required">Name</label>
              <input type="text" name="name" class="form-control" placeholder="e.g. Full Stack Bootcamp Fee" required minlength="3" maxlength="150">
            </div>
            <div class="col-md-4">
              <label class="form-label">Course</label>
              <select name="course_id" class="form-select">
                <option value="">All Courses</option>
                <?php foreach ($courses as $c): ?>
                <option value="<?= $c['id'] ?>"><?= \App\Core\View::e($c['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label required">Amount (₹)</label>
              <input type="number" name="amount" class="form-control" min="0" step="0.01" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">GST (%)</label>
              <input type="number" name="gst_percentage" class="form-control" min="0" max="100" step="0.01" value="0">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Fee Structure</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openAddFeeStructure() {
  document.getElementById('feeStructureForm').reset();
  new bootstrap.Modal(document.getElementById('feeStructureModal')).show();
}
</script>
