<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title ?? 'Forgot Password') ?></title>
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
      overflow: hidden;
      position: relative;
    }
    .bg-grid {
      position: fixed; inset: 0;
      background-image:
        linear-gradient(rgba(99,102,241,.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(99,102,241,.06) 1px, transparent 1px);
      background-size: 44px 44px;
      z-index: 0;
    }
    .bg-glow { position: fixed; border-radius: 50%; filter: blur(80px); z-index: 0; animation: float 8s ease-in-out infinite; }
    .bg-glow-1 { width: 500px; height: 500px; background: rgba(99,102,241,.15); top: -200px; left: -150px; }
    .bg-glow-2 { width: 400px; height: 400px; background: rgba(6,182,212,.1);  bottom: -150px; right: -100px; animation-delay: -4s; }
    @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-20px)} }
    .login-container { position: relative; z-index: 10; width: 100%; max-width: 440px; padding: 20px; }
    .login-card {
      background: rgba(30, 41, 59, 0.8);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 24px;
      padding: 40px;
      box-shadow: 0 25px 60px rgba(0,0,0,.5), 0 0 0 1px rgba(255,255,255,.04);
      animation: slideUp .5s cubic-bezier(.4,0,.2,1);
    }
    @keyframes slideUp { from { opacity:0; transform:translateY(30px); } to { opacity:1; transform:translateY(0); } }
    .login-logo { display: flex; align-items: center; gap: 12px; margin-bottom: 32px; justify-content: center; }
    .logo-icon-wrap {
      width: 48px; height: 48px;
      background: linear-gradient(135deg, #6366f1, #4f46e5);
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px; color: #fff;
      box-shadow: 0 8px 20px rgba(99,102,241,.5);
    }
    .logo-text-group .logo-title { font-size: 20px; font-weight: 800; color: #fff; letter-spacing: -.4px; line-height: 1.2; }
    .logo-text-group .logo-sub { font-size: 11px; color: rgba(255,255,255,.4); font-weight: 500; text-transform: uppercase; letter-spacing: 1px; }
    .login-heading { margin-bottom: 28px; }
    .login-heading h2 { font-size: 24px; font-weight: 800; color: #fff; letter-spacing: -.5px; margin-bottom: 6px; }
    .login-heading p { font-size: 14px; color: rgba(255,255,255,.5); }
    .form-group { margin-bottom: 18px; }
    .form-label { display: block; font-size: 13px; font-weight: 600; color: rgba(255,255,255,.7); margin-bottom: 7px; }
    .input-wrap { position: relative; }
    .input-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,.3); font-size: 14px; pointer-events: none; }
    .form-control {
      width: 100%; padding: 11px 14px 11px 40px;
      background: rgba(255,255,255,.06);
      border: 1.5px solid rgba(255,255,255,.1);
      border-radius: 12px; color: #fff; font-size: 14px; font-family: inherit; outline: none; transition: all .2s;
    }
    .form-control::placeholder { color: rgba(255,255,255,.25); }
    .form-control:focus { border-color: #6366f1; background: rgba(99,102,241,.1); box-shadow: 0 0 0 3px rgba(99,102,241,.2); }
    .btn-login {
      width: 100%; padding: 13px;
      background: linear-gradient(135deg, #6366f1, #4f46e5);
      border: none; border-radius: 12px; color: #fff; font-size: 15px; font-weight: 700; font-family: inherit;
      cursor: pointer; transition: all .2s;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      box-shadow: 0 4px 16px rgba(99,102,241,.4); letter-spacing: .2px;
    }
    .btn-login:hover { background: linear-gradient(135deg,#4f46e5,#4338ca); transform: translateY(-1px); box-shadow: 0 8px 24px rgba(99,102,241,.5); }
    .alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; border-radius: 12px; font-size: 13.5px; margin-bottom: 20px; animation: slideDown .3s ease; }
    @keyframes slideDown { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
    .alert-danger  { background: rgba(239,68,68,.15);  border: 1px solid rgba(239,68,68,.25);  color: #fca5a5; }
    .alert-success { background: rgba(16,185,129,.15); border: 1px solid rgba(16,185,129,.25); color: #6ee7b7; }
    .login-footer { text-align: center; margin-top: 28px; font-size: 12px; color: rgba(255,255,255,.2); }
    .back-link { display: block; text-align: center; margin-top: 20px; font-size: 13px; color: #818cf8; text-decoration: none; font-weight: 500; }
    .back-link:hover { color: #a5b4fc; text-decoration: underline; }
  </style>
</head>
<body>

<div class="bg-grid"></div>
<div class="bg-glow bg-glow-1"></div>
<div class="bg-glow bg-glow-2"></div>

<div class="login-container">
  <div class="login-card">

    <div class="login-logo">
      <div class="logo-icon-wrap"><i class="fas fa-graduation-cap"></i></div>
      <div class="logo-text-group">
        <div class="logo-title">CodeGurukul</div>
        <div class="logo-sub">Super Admin</div>
      </div>
    </div>

    <div class="login-heading">
      <h2>Forgot password?</h2>
      <p>Enter your email and we'll send you a reset link</p>
    </div>

    <?php if ($flashError): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><span><?= htmlspecialchars($flashError) ?></span></div>
    <?php endif; ?>

    <?php if ($flashSuccess): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i><span><?= htmlspecialchars($flashSuccess) ?></span></div>
    <?php endif; ?>

    <form method="POST" action="/forgot-password" novalidate>
      <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">

      <div class="form-group">
        <label class="form-label">Email Address</label>
        <div class="input-wrap">
          <i class="fas fa-envelope input-icon"></i>
          <input type="email" name="email" class="form-control"
                 placeholder="admin@codegurukul.com"
                 value="<?= htmlspecialchars($oldInput['email'] ?? '') ?>"
                 autocomplete="email" required autofocus>
        </div>
        <?php if (!empty($errors['email'])): ?>
        <div style="font-size:12px;color:#f87171;margin-top:5px"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($errors['email']) ?></div>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn-login"><i class="fas fa-paper-plane"></i> Send Reset Link</button>
    </form>

    <a href="/login" class="back-link"><i class="fas fa-arrow-left"></i> Back to sign in</a>

  </div>

  <div class="login-footer">© <?= date('Y') ?> CodeGurukul LMS. All rights reserved.</div>
</div>

<script>
  setTimeout(() => {
    document.querySelectorAll('.alert').forEach(a => {
      a.style.transition = 'opacity .4s';
      a.style.opacity = '0';
      setTimeout(() => a.remove(), 400);
    });
  }, 6000);
</script>

</body>
</html>
