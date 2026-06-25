<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 — Page Not Found | CodeGurukul</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Inter',sans-serif;background:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
    .container{text-align:center;max-width:480px}
    .code{font-size:120px;font-weight:900;color:#e2e8f0;line-height:1;letter-spacing:-4px;background:linear-gradient(135deg,#6366f1,#8b5cf6);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
    .icon{width:80px;height:80px;background:#6366f118;border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:36px;color:#6366f1}
    h1{font-size:28px;font-weight:800;color:#0f172a;margin-bottom:12px}
    p{color:#64748b;font-size:16px;line-height:1.6;margin-bottom:32px}
    .btn{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;border-radius:12px;font-weight:600;font-size:15px;text-decoration:none;transition:all .2s}
    .btn-primary{background:#6366f1;color:#fff}
    .btn-primary:hover{background:#4f46e5;transform:translateY(-1px);box-shadow:0 8px 24px rgba(99,102,241,.3)}
    .btn-secondary{background:#f1f5f9;color:#374151;margin-left:12px}
    .btn-secondary:hover{background:#e2e8f0}
  </style>
</head>
<body>
  <div class="container">
    <div class="code">404</div>
    <div class="icon"><i class="fas fa-search"></i></div>
    <h1>Page Not Found</h1>
    <p>The page you're looking for doesn't exist or has been moved. Double-check the URL or head back home.</p>
    <div>
      <a href="javascript:history.back()" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Go Back</a>
      <a href="/super-admin/dashboard" class="btn btn-primary"><i class="fas fa-house"></i> Dashboard</a>
    </div>
  </div>
</body>
</html>
