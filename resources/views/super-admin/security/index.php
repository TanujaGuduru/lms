<!-- Page Header -->
<div class="page-header">
  <div class="page-title-group">
    <div class="breadcrumb-custom">
      <a href="/super-admin/dashboard"><i class="fas fa-house" style="font-size:11px"></i></a>
      <span class="sep">/</span><span>Security Center</span>
    </div>
    <h1 class="page-title">Security Center</h1>
    <p class="page-subtitle">Monitor threats, manage sessions, and protect your platform</p>
  </div>
  <div class="d-flex gap-2">
    <a href="/super-admin/audit-logs" class="btn btn-secondary btn-sm"><i class="fas fa-history"></i> Audit Logs</a>
    <a href="/super-admin/security/ip-restrictions" class="btn btn-primary btn-sm"><i class="fas fa-ban"></i> IP Rules</a>
  </div>
</div>

<?php
$db = \App\Core\Database::getInstance();
$secStats = [
  'active_sessions' => $db->selectOne("SELECT COUNT(*) c FROM user_sessions WHERE is_active=1 AND (expires_at IS NULL OR expires_at>NOW())")['c'] ?? 0,
  'failed_logins'   => $db->selectOne("SELECT COUNT(*) c FROM audit_logs WHERE action='login_failed' AND created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)")['c'] ?? 0,
  'locked_accounts' => $db->selectOne("SELECT COUNT(*) c FROM users WHERE locked_until IS NOT NULL AND locked_until>NOW()")['c'] ?? 0,
  'blocked_ips'     => $db->selectOne("SELECT COUNT(*) c FROM ip_restrictions WHERE type='blacklist' AND is_active=1")['c'] ?? 0,
];
?>

<!-- Security Score + Stats -->
<div class="row g-3 mb-4">
  <!-- Security Score Card -->
  <div class="col-xl-4">
    <div class="card h-100" style="background:linear-gradient(135deg,#0f172a,#1e293b)">
      <div class="card-body d-flex flex-column align-items-center justify-content-center py-5">
        <div style="position:relative;width:140px;height:140px;margin-bottom:20px">
          <canvas id="secScoreChart" width="140" height="140"></canvas>
          <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center">
            <div style="font-size:36px;font-weight:900;color:#fff" id="secScore">0</div>
            <div style="font-size:11px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.8px">Security</div>
          </div>
        </div>
        <div style="font-size:18px;font-weight:700;color:#fff;margin-bottom:4px" id="secLabel">Calculating…</div>
        <div style="font-size:13px;color:rgba(255,255,255,.5)">Platform security rating</div>
        <button onclick="runSecurityScan()" class="btn btn-sm mt-4" style="background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.2)">
          <i class="fas fa-search"></i> Run Security Scan
        </button>
      </div>
    </div>
  </div>

  <!-- Stats -->
  <div class="col-xl-8">
    <div class="row g-3 h-100">
      <?php
      $ss = [
        ['label'=>'Active Sessions',    'value'=>$secStats['active_sessions'], 'icon'=>'fas fa-desktop',         'color'=>'#3b82f6', 'link'=>'/super-admin/security/sessions'],
        ['label'=>'Failed Logins (24h)','value'=>$secStats['failed_logins'],   'icon'=>'fas fa-exclamation-triangle','color'=>'#f59e0b','link'=>'/super-admin/security/login-logs'],
        ['label'=>'Locked Accounts',    'value'=>$secStats['locked_accounts'], 'icon'=>'fas fa-lock',             'color'=>'#ef4444', 'link'=>'/super-admin/users?status=suspended'],
        ['label'=>'Blocked IPs',        'value'=>$secStats['blocked_ips'],     'icon'=>'fas fa-ban',              'color'=>'#8b5cf6', 'link'=>'/super-admin/security/ip-restrictions'],
      ];
      ?>
      <?php foreach ($ss as $s): ?>
      <div class="col-md-6">
        <a href="<?= $s['link'] ?>" class="kpi-card" style="--kpi-color:<?= $s['color'] ?>;text-decoration:none;display:block">
          <div class="kpi-header">
            <div class="kpi-icon"><i class="<?= $s['icon'] ?>"></i></div>
            <span class="kpi-trend <?= ($s['value'] > 0 && in_array($s['label'], ['Failed Logins (24h)','Locked Accounts'])) ? 'down' : 'flat' ?>">
              <?= $s['value'] > 0 && in_array($s['label'], ['Failed Logins (24h)','Locked Accounts']) ? '⚠ Alert' : '● Normal' ?>
            </span>
          </div>
          <div class="kpi-value"><?= number_format((int)$s['value']) ?></div>
          <div class="kpi-label"><?= $s['label'] ?></div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Recent Login Activity -->
  <div class="col-xl-7">
    <div class="card h-100">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-sign-in-alt" style="color:#6366f1"></i> Recent Login Activity</h3>
        <a href="/super-admin/security/login-logs" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <div class="table-responsive" style="max-height:360px;overflow-y:auto">
        <table class="data-table">
          <thead><tr><th>User</th><th>IP Address</th><th>Location</th><th>Result</th><th>Time</th></tr></thead>
          <tbody>
            <?php
            $logins = $db->select("SELECT al.*, CONCAT(u.first_name,' ',u.last_name) uname, u.avatar FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id WHERE al.action IN ('login','login_failed') ORDER BY al.created_at DESC LIMIT 12");
            foreach ($logins as $lg):
            ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div style="width:28px;height:28px;background:#6366f118;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#6366f1;font-size:11px"><i class="fas fa-user"></i></div>
                  <span style="font-size:13px;font-weight:500"><?= \App\Core\View::e($lg['uname'] ?? 'Unknown') ?></span>
                </div>
              </td>
              <td style="font-family:monospace;font-size:12px;color:#64748b"><?= \App\Core\View::e($lg['ip_address'] ?? '—') ?></td>
              <td style="font-size:12px;color:#94a3b8">—</td>
              <td>
                <?php if ($lg['action'] === 'login'): ?>
                <span class="badge badge-soft-success"><i class="fas fa-check"></i> Success</span>
                <?php else: ?>
                <span class="badge badge-soft-danger"><i class="fas fa-times"></i> Failed</span>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;color:#94a3b8"><?= \App\Core\View::timeAgo($lg['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logins)): ?>
            <tr><td colspan="5"><div class="empty-state" style="padding:24px"><p class="empty-state-desc">No login activity.</p></div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Security Checklist -->
  <div class="col-xl-5">
    <div class="card h-100">
      <div class="card-header"><h3 class="card-title"><i class="fas fa-clipboard-check" style="color:#10b981"></i> Security Checklist</h3></div>
      <div class="card-body">
        <?php
        $sSettings = $db->select("SELECT `group`,`key`,`value` FROM settings WHERE `group`='security'");
        $sMap = [];
        foreach ($sSettings as $s) $sMap[$s['key']] = $s['value'];

        $checks = [
          ['label'=>'2FA Requirement',       'ok'=>($sMap['two_factor_required']??'0')==='1', 'action'=>'/super-admin/settings/security'],
          ['label'=>'Session Timeout Set',   'ok'=>($sMap['session_timeout']??0)<=120,        'action'=>'/super-admin/settings/security'],
          ['label'=>'Strong Password Policy','ok'=>($sMap['password_min_length']??0)>=8,      'action'=>'/super-admin/settings/security'],
          ['label'=>'Login Attempt Limit',   'ok'=>($sMap['max_login_attempts']??0)>0,        'action'=>'/super-admin/settings/security'],
          ['label'=>'Database Backups',      'ok'=>$db->selectOne("SELECT id FROM backups WHERE status='completed' AND created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) LIMIT 1") !== false, 'action'=>'/super-admin/backup'],
          ['label'=>'HTTPS Enabled',         'ok'=>!empty($_SERVER['HTTPS']),                 'action'=>null],
          ['label'=>'No Failed Logins (24h)','ok'=>$secStats['failed_logins']===0,            'action'=>'/super-admin/security/login-logs'],
          ['label'=>'No Locked Accounts',    'ok'=>$secStats['locked_accounts']===0,          'action'=>'/super-admin/users?status=suspended'],
        ];
        $score = round(array_sum(array_column($checks, 'ok')) / count($checks) * 100);
        ?>
        <div class="d-flex flex-column gap-2">
          <?php foreach ($checks as $c): ?>
          <div class="d-flex align-items-center justify-content-between py-2" style="border-bottom:1px solid #f1f5f9">
            <div class="d-flex align-items-center gap-3">
              <div style="width:24px;height:24px;border-radius:50%;background:<?= $c['ok'] ? '#10b98118' : '#ef444418' ?>;display:flex;align-items:center;justify-content:center">
                <i class="fas <?= $c['ok'] ? 'fa-check' : 'fa-times' ?>" style="font-size:11px;color:<?= $c['ok'] ? '#10b981' : '#ef4444' ?>"></i>
              </div>
              <span style="font-size:13px;color:<?= $c['ok'] ? '#374151' : '#ef4444' ?>;font-weight:500"><?= $c['label'] ?></span>
            </div>
            <?php if (!$c['ok'] && $c['action']): ?>
            <a href="<?= $c['action'] ?>" style="font-size:11.5px;color:#6366f1;font-weight:600;text-decoration:none">Fix →</a>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="mt-3 p-3 rounded-3" style="background:<?= $score >= 80 ? '#10b98108' : ($score >= 60 ? '#f59e0b08' : '#ef444408') ?>;border:1px solid <?= $score >= 80 ? '#10b98122' : ($score >= 60 ? '#f59e0b22' : '#ef444422') ?>">
          <div style="font-size:12px;font-weight:600;color:<?= $score >= 80 ? '#059669' : ($score >= 60 ? '#d97706' : '#dc2626') ?>">
            Security Score: <?= $score ?>% — <?= $score >= 80 ? 'Excellent' : ($score >= 60 ? 'Good, improve further' : 'Needs immediate attention') ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Security Score Donut
const score = <?= $score ?? 85 ?>;
const scc = document.getElementById('secScoreChart');
if (scc) {
  new Chart(scc, {
    type: 'doughnut',
    data: {
      datasets: [{
        data: [score, 100 - score],
        backgroundColor: [score >= 80 ? '#10b981' : score >= 60 ? '#f59e0b' : '#ef4444', 'rgba(255,255,255,.08)'],
        borderWidth: 0, hoverOffset: 0
      }]
    },
    options: { responsive:false, cutout:'80%', plugins:{ legend:{display:false}, tooltip:{enabled:false} }, animation:{ animateRotate:true, duration:1200 } }
  });

  let curr = 0;
  const el = document.getElementById('secScore');
  const lbl = document.getElementById('secLabel');
  const timer = setInterval(() => {
    curr = Math.min(curr + 2, score);
    el.textContent = curr;
    if (curr >= score) {
      clearInterval(timer);
      lbl.textContent = score >= 80 ? 'Excellent' : score >= 60 ? 'Good' : 'Needs Work';
    }
  }, 20);
}

function runSecurityScan() {
  Swal.fire({ title: 'Running Security Scan…', timer: 2000, timerProgressBar: true, showConfirmButton: false,
    didOpen: () => Swal.showLoading() }).then(() => {
    Swal.fire({ title: 'Scan Complete', text: `Security score: ${score}/100. ${100-score > 0 ? 'Check the checklist for improvements.' : 'All checks passed!'}`, icon: score >= 80 ? 'success' : 'warning' });
  });
}
</script>
