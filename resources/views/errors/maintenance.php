<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Maintenance | CodeGurukul LMS</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#0f172a,#1e293b);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
    .container{text-align:center;max-width:520px}
    .logo{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:48px}
    .logo-icon{width:52px;height:52px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff;box-shadow:0 8px 24px rgba(99,102,241,.4)}
    .logo-text{font-size:22px;font-weight:800;color:#f1f5f9}
    .icon-wrap{width:100px;height:100px;background:rgba(99,102,241,.15);border-radius:24px;display:flex;align-items:center;justify-content:center;margin:0 auto 32px;font-size:44px;color:#6366f1;border:1px solid rgba(99,102,241,.2)}
    h1{font-size:32px;font-weight:900;color:#f1f5f9;margin-bottom:16px}
    p{color:#94a3b8;font-size:17px;line-height:1.7;margin-bottom:32px}
    .progress-bar{background:rgba(255,255,255,.08);border-radius:100px;height:6px;margin-bottom:32px;overflow:hidden}
    .progress-fill{height:100%;background:linear-gradient(90deg,#6366f1,#8b5cf6);border-radius:100px;animation:progress 3s ease-in-out infinite alternate;width:60%}
    @keyframes progress{from{width:45%}to{width:85%}}
    .countdown{font-size:14px;color:#64748b;margin-bottom:20px}
    .contact{font-size:14px;color:#64748b}
    .contact a{color:#818cf8;text-decoration:none}
    .contact a:hover{color:#a5b4fc}
    .gear-spin{animation:spin 4s linear infinite}
    @keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
  </style>
</head>
<body>
  <div class="container">
    <div class="logo">
      <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
      <div class="logo-text">CodeGurukul LMS</div>
    </div>

    <div class="icon-wrap">
      <i class="fas fa-gear gear-spin"></i>
    </div>

    <h1>We'll Be Back Soon</h1>
    <p>
      CodeGurukul is undergoing scheduled maintenance to bring you a better experience.
      We're working hard and will be back shortly.
    </p>

    <div class="progress-bar"><div class="progress-fill"></div></div>

    <?php if (!empty($message)): ?>
    <p style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:16px 24px;font-size:14px;color:#94a3b8;margin-bottom:32px">
      <?= htmlspecialchars($message) ?>
    </p>
    <?php endif; ?>

    <div class="contact">
      For urgent matters, contact us at
      <a href="mailto:support@codegurukul.in">support@codegurukul.in</a>
    </div>
  </div>
</body>
</html>
