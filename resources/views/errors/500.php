<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>500 — Server Error | CodeGurukul</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Inter',sans-serif;background:#0f172a;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
    .container{text-align:center;max-width:500px}
    .code{font-size:120px;font-weight:900;line-height:1;letter-spacing:-4px;background:linear-gradient(135deg,#f59e0b,#ef4444);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
    .icon{width:80px;height:80px;background:#f59e0b18;border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:36px;color:#f59e0b}
    h1{font-size:28px;font-weight:800;color:#f1f5f9;margin-bottom:12px}
    p{color:#94a3b8;font-size:16px;line-height:1.6;margin-bottom:12px}
    .error-id{font-family:monospace;font-size:12px;color:#475569;margin-bottom:32px;background:#1e293b;padding:8px 16px;border-radius:8px;display:inline-block}
    .btn{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;border-radius:12px;font-weight:600;font-size:15px;text-decoration:none;transition:all .2s}
    .btn-primary{background:#6366f1;color:#fff}
    .btn-primary:hover{background:#4f46e5}
    .btn-secondary{background:#1e293b;color:#94a3b8;margin-left:12px;border:1px solid #334155}
    .btn-secondary:hover{background:#334155;color:#f1f5f9}
  </style>
</head>
<body>
  <div class="container">
    <div class="code">500</div>
    <div class="icon"><i class="fas fa-triangle-exclamation"></i></div>
    <h1>Internal Server Error</h1>
    <p>Something went wrong on our end. Our team has been notified and we're working to fix it.</p>
    <div class="error-id">Error ID: <?= htmlspecialchars($errorId ?? uniqid('ERR-', true)) ?></div>
    <div>
      <button onclick="location.reload()" class="btn btn-secondary"><i class="fas fa-rotate-right"></i> Retry</button>
      <a href="/super-admin/dashboard" class="btn btn-primary"><i class="fas fa-house"></i> Dashboard</a>
    </div>
  </div>
</body>
</html>
