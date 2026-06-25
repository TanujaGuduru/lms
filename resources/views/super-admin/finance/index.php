<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Finance</span>
    </div>
    <h1 class="page-title">Finance & Revenue</h1>
    <p class="page-subtitle">Track payments, revenue, fees, and financial health</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-secondary btn-sm" onclick="exportReport()"><i class="fas fa-file-export"></i> Export</button>
    <a href="/super-admin/finance/fee-structures" class="btn btn-primary btn-sm"><i class="fas fa-cog"></i> Fee Structures</a>
  </div>
</div>

<?php
$db = \App\Core\Database::getInstance();
$rev = $db->selectOne("SELECT
    COALESCE(SUM(total_amount),0) total,
    COALESCE(SUM(CASE WHEN DATE(paid_at)=CURDATE() THEN total_amount END),0) today,
    COALESCE(SUM(CASE WHEN paid_at>=DATE_FORMAT(NOW(),'%Y-%m-01') THEN total_amount END),0) this_month,
    COUNT(CASE WHEN status='success' THEN 1 END) success_count,
    COUNT(CASE WHEN status='pending' THEN 1 END) pending_count,
    COUNT(CASE WHEN status='failed'  THEN 1 END) failed_count
    FROM payments WHERE status='success' OR status='pending' OR status='failed'") ?: [];
?>

<!-- Revenue KPI Row -->
<div class="row g-3 mb-4">
  <div class="col-xl-3 col-md-6">
    <div class="kpi-card" style="--kpi-color:#10b981">
      <div class="kpi-header">
        <div class="kpi-icon"><i class="fas fa-rupee-sign"></i></div>
        <span class="kpi-trend up"><i class="fas fa-arrow-up"></i> This Month</span>
      </div>
      <div class="kpi-value">₹<?= number_format((float)($rev['this_month']??0),0) ?></div>
      <div class="kpi-label">Monthly Revenue</div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6">
    <div class="kpi-card" style="--kpi-color:#6366f1">
      <div class="kpi-header">
        <div class="kpi-icon"><i class="fas fa-chart-line"></i></div>
        <span class="kpi-trend up"><i class="fas fa-arrow-up"></i> All Time</span>
      </div>
      <div class="kpi-value">₹<?= number_format((float)($rev['total']??0),0) ?></div>
      <div class="kpi-label">Total Revenue</div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6">
    <div class="kpi-card" style="--kpi-color:#f59e0b">
      <div class="kpi-header">
        <div class="kpi-icon"><i class="fas fa-sun"></i></div>
        <span class="kpi-trend flat">Today</span>
      </div>
      <div class="kpi-value">₹<?= number_format((float)($rev['today']??0),0) ?></div>
      <div class="kpi-label">Revenue Today</div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6">
    <div class="kpi-card" style="--kpi-color:#ef4444">
      <div class="kpi-header">
        <div class="kpi-icon"><i class="fas fa-clock"></i></div>
        <span class="kpi-trend down"><i class="fas fa-exclamation"></i> Pending</span>
      </div>
      <div class="kpi-value"><?= number_format((int)($rev['pending_count']??0)) ?></div>
      <div class="kpi-label">Pending Payments</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Revenue Chart -->
  <div class="col-xl-8">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-area" style="color:#10b981"></i> Revenue Trend (12 Months)</h3>
      </div>
      <div class="card-body"><div class="chart-container" style="height:260px"><canvas id="revTrendChart"></canvas></div></div>
    </div>
  </div>
  <!-- Payment Methods -->
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header"><h3 class="card-title"><i class="fas fa-credit-card" style="color:#6366f1"></i> Payment Methods</h3></div>
      <div class="card-body d-flex flex-column align-items-center justify-content-center">
        <div style="height:180px;width:180px"><canvas id="payMethodChart"></canvas></div>
      </div>
    </div>
  </div>
</div>

<!-- Recent Payments -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-receipt" style="color:#6366f1"></i> Recent Payments</h3>
    <a href="/super-admin/finance/payments" class="btn btn-ghost btn-sm">View All →</a>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Invoice</th><th>Student</th><th>Course/Batch</th>
          <th>Amount</th><th>Gateway</th><th>Status</th><th>Date</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php
        $payments = $db->select("SELECT p.*, CONCAT(u.first_name,' ',u.last_name) student_name, u.email student_email, c.title course_title
            FROM payments p LEFT JOIN users u ON u.id=p.user_id LEFT JOIN courses c ON c.id=p.course_id
            ORDER BY p.created_at DESC LIMIT 15");
        foreach ($payments as $p):
        ?>
        <tr>
          <td style="font-family:monospace;font-size:12px;color:#6366f1"><?= \App\Core\View::e($p['invoice_number']) ?></td>
          <td>
            <div style="font-size:13.5px;font-weight:600"><?= \App\Core\View::e($p['student_name']) ?></div>
            <div style="font-size:11.5px;color:#94a3b8"><?= \App\Core\View::e($p['student_email']) ?></div>
          </td>
          <td style="font-size:13px;color:#64748b;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= \App\Core\View::e($p['course_title'] ?? '—') ?></td>
          <td style="font-weight:700;font-size:14px">₹<?= number_format((float)$p['total_amount'],2) ?></td>
          <td>
            <?php
            $gColors = ['razorpay'=>'#3395FF','stripe'=>'#635BFF','manual'=>'#64748b','scholarship'=>'#10b981'];
            $gc = $gColors[$p['gateway']] ?? '#64748b';
            ?>
            <span class="badge" style="background:<?= $gc ?>18;color:<?= $gc ?>;text-transform:capitalize"><?= $p['gateway'] ?></span>
          </td>
          <td><?= \App\Core\View::badge($p['status']) ?></td>
          <td style="font-size:12.5px;color:#94a3b8"><?= \App\Core\View::formatDate($p['created_at'], 'd M Y') ?></td>
          <td>
            <button onclick="viewPayment(<?= $p['id'] ?>)" class="btn btn-ghost btn-sm btn-icon" title="View Receipt">
              <i class="fas fa-file-invoice"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($payments)): ?>
        <tr><td colspan="8"><div class="empty-state"><i class="fas fa-receipt empty-state-icon"></i><p class="empty-state-desc">No payments recorded yet.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// Revenue Trend
const revData = <?= json_encode($db->select("SELECT DATE_FORMAT(paid_at,'%b %Y') lbl, SUM(total_amount) val FROM payments WHERE status='success' AND paid_at>=DATE_SUB(NOW(),INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(paid_at,'%Y-%m') ORDER BY paid_at")) ?>;
const rc = document.getElementById('revTrendChart');
if (rc) {
  new Chart(rc, {
    type: 'line',
    data: {
      labels: revData.map(r=>r.lbl),
      datasets: [{ label:'Revenue (₹)', data: revData.map(r=>r.val||0),
        borderColor:'#10b981', borderWidth:2.5, fill:true,
        backgroundColor:'rgba(16,185,129,.07)', tension:.4,
        pointBackgroundColor:'#10b981', pointRadius:4, pointHoverRadius:6 }]
    },
    options: { responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{display:false} },
      scales:{ x:{grid:{display:false},border:{display:false}},
               y:{grid:{color:'rgba(0,0,0,.04)'},border:{display:false},
                  ticks:{callback:v=>'₹'+(v/1000).toFixed(0)+'K'}} }
    }
  });
}

// Payment Methods
const pmData = <?= json_encode($db->select("SELECT COALESCE(payment_method,'Unknown') mth, COUNT(*) cnt FROM payments WHERE status='success' GROUP BY payment_method ORDER BY cnt DESC LIMIT 5")) ?>;
const pm = document.getElementById('payMethodChart');
if (pm) {
  new Chart(pm, {
    type: 'doughnut',
    data: { labels: pmData.map(r=>r.mth), datasets:[{ data:pmData.map(r=>r.cnt), backgroundColor:['#6366f1','#10b981','#f59e0b','#3b82f6','#ef4444'], borderWidth:0, hoverOffset:6 }] },
    options: { responsive:true, maintainAspectRatio:false, cutout:'70%',
      plugins:{ legend:{position:'bottom',labels:{font:{size:11},boxWidth:8}} } }
  });
}

function viewPayment(id) {
  window.open(`/super-admin/finance/payments?id=${id}`, '_blank');
}

function exportReport() {
  window.location.href = '/super-admin/finance/reports?format=excel&period=month';
}
</script>
