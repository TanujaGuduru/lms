<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>419 — Session Expired | CodeGurukul</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Inter',sans-serif;background:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
    .container{text-align:center;max-width:480px}
    .code{font-size:120px;font-weight:900;line-height:1;background:linear-gradient(135deg,#8b5cf6,#6366f1);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
    .icon{width:80px;height:80px;background:#8b5cf618;border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:36px;color:#8b5cf6}
    h1{font-size:28px;font-weight:800;color:#0f172a;margin-bottom:12px}
    p{color:#64748b;font-size:16px;line-height:1.6;margin-bottom:32px}
    .btn{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;border-radius:12px;font-weight:600;font-size:15px;text-decoration:none;transition:all .2s}
    .btn-primary{background:#6366f1;color:#fff}
    .btn-primary:hover{background:#4f46e5;transform:translateY(-1px)}
  </style>
</head>
<body>
  <div class="container">
    <div class="code">419</div>
    <div class="icon"><i class="fas fa-clock-rotate-left"></i></div>
    <h1>Session Expired</h1>
    <p>Your session has expired for security reasons. Please refresh the page or go back and try again.</p>
    <div>
      <button onclick="history.back()" class="btn btn-primary" style="margin-right:12px;background:#f1f5f9;color:#374151">
        <i class="fas fa-arrow-left"></i> Go Back
      </button>
      <a href="/login" class="btn btn-primary"><i class="fas fa-right-to-bracket"></i> Log In Again</a>
    </div>
  </div>
  <script>setTimeout(() => location.href='/login', 8000);</script>
</body>
</html>
