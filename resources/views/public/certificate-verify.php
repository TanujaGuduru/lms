<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title ?? 'Certificate Verification') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background: #0f172a;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .bg-grid {
      position: fixed; inset: 0;
      background-image:
        linear-gradient(rgba(99,102,241,.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(99,102,241,.06) 1px, transparent 1px);
      background-size: 44px 44px;
      z-index: 0;
    }
    .wrap { position: relative; z-index: 10; width: 100%; max-width: 480px; }
    .card {
      background: rgba(30, 41, 59, 0.8);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 24px;
      padding: 40px;
      box-shadow: 0 25px 60px rgba(0,0,0,.5), 0 0 0 1px rgba(255,255,255,.04);
      text-align: center;
    }
    .logo { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; justify-content: center; }
    .logo-icon-wrap {
      width: 44px; height: 44px;
      background: linear-gradient(135deg, #6366f1, #4f46e5);
      border-radius: 13px;
      display: flex; align-items: center; justify-content: center;
      font-size: 20px; color: #fff;
      box-shadow: 0 8px 20px rgba(99,102,241,.5);
    }
    .logo-text { font-size: 18px; font-weight: 800; color: #fff; letter-spacing: -.4px; }
    .status-icon { width: 72px; height: 72px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 32px; }
    .status-valid   { background: rgba(16,185,129,.15); color: #34d399; }
    .status-revoked  { background: rgba(239,68,68,.15); color: #f87171; }
    .status-invalid  { background: rgba(148,163,184,.15); color: #94a3b8; }
    .status-title { font-size: 20px; font-weight: 800; color: #fff; margin-bottom: 6px; }
    .status-sub { font-size: 13.5px; color: rgba(255,255,255,.5); margin-bottom: 28px; }
    .detail-list { text-align: left; background: rgba(255,255,255,.04); border-radius: 14px; padding: 18px 20px; margin-bottom: 8px; }
    .detail-row { display: flex; justify-content: space-between; gap: 12px; padding: 9px 0; border-bottom: 1px solid rgba(255,255,255,.06); }
    .detail-row:last-child { border-bottom: none; }
    .detail-label { font-size: 12.5px; color: rgba(255,255,255,.4); }
    .detail-value { font-size: 13.5px; color: #fff; font-weight: 600; text-align: right; }
    .footer { text-align: center; margin-top: 24px; font-size: 12px; color: rgba(255,255,255,.25); }
  </style>
</head>
<body>

<div class="bg-grid"></div>

<div class="wrap">
  <div class="card">
    <div class="logo">
      <div class="logo-icon-wrap"><i class="fas fa-graduation-cap"></i></div>
      <div class="logo-text">CodeGurukul</div>
    </div>

    <?php if (!$certificate): ?>
      <div class="status-icon status-invalid"><i class="fas fa-circle-question"></i></div>
      <div class="status-title">Certificate Not Found</div>
      <div class="status-sub">We couldn't find a certificate matching this verification code.</div>

    <?php elseif ($certificate['is_revoked']): ?>
      <div class="status-icon status-revoked"><i class="fas fa-ban"></i></div>
      <div class="status-title">Certificate Revoked</div>
      <div class="status-sub">This certificate has been revoked and is no longer valid.</div>
      <div class="detail-list">
        <div class="detail-row"><span class="detail-label">Certificate #</span><span class="detail-value"><?= htmlspecialchars($certificate['certificate_number']) ?></span></div>
        <div class="detail-row"><span class="detail-label">Revoked On</span><span class="detail-value"><?= $certificate['revoked_at'] ? date('d M Y', strtotime($certificate['revoked_at'])) : '—' ?></span></div>
      </div>

    <?php else: ?>
      <div class="status-icon status-valid"><i class="fas fa-circle-check"></i></div>
      <div class="status-title">Valid Certificate</div>
      <div class="status-sub">This certificate was issued by CodeGurukul LMS.</div>
      <div class="detail-list">
        <div class="detail-row"><span class="detail-label">Issued To</span><span class="detail-value"><?= htmlspecialchars($certificate['student_name']) ?></span></div>
        <div class="detail-row"><span class="detail-label">Course</span><span class="detail-value"><?= htmlspecialchars($certificate['course_title'] ?? '—') ?></span></div>
        <div class="detail-row"><span class="detail-label">Certificate #</span><span class="detail-value"><?= htmlspecialchars($certificate['certificate_number']) ?></span></div>
        <div class="detail-row"><span class="detail-label">Issued On</span><span class="detail-value"><?= date('d M Y', strtotime($certificate['issued_at'])) ?></span></div>
      </div>
    <?php endif; ?>
  </div>

  <div class="footer">© <?= date('Y') ?> CodeGurukul LMS. All rights reserved.</div>
</div>

</body>
</html>
