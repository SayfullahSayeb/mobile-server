<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Server Login</title>
<link rel="stylesheet" href="panel/control.css">
<style>
body{display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px}
.card{background:rgba(30,41,59,.7);backdrop-filter:blur(20px);border:1px solid rgba(148,163,184,.1);border-radius:24px;padding:40px;width:100%;max-width:400px;box-shadow:0 25px 80px rgba(0,0,0,.5)}
h1{text-align:center;font-size:26px;font-weight:700;letter-spacing:-.5px;margin-bottom:4px}
.sub{text-align:center;color:#64748b;font-size:14px;margin-bottom:28px}
label{display:block;font-size:13px;color:#94a3b8;margin-bottom:6px;font-weight:500}
input[type=password]{width:100%;padding:14px 16px;border:1px solid #334155;border-radius:12px;background:#0f172a;color:#fff;font-size:15px;outline:none;transition:border-color .2s}
input[type=password]:focus{border-color:#3b82f6}
button{width:100%;padding:14px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;margin-top:20px;transition:transform .2s,box-shadow .2s;box-shadow:0 4px 20px rgba(59,130,246,.3)}
button:hover{transform:translateY(-1px);box-shadow:0 8px 30px rgba(59,130,246,.4)}
.err{background:rgba(239,68,68,.15);color:#ef4444;padding:12px;border-radius:10px;text-align:center;margin-bottom:16px;font-size:14px;font-weight:500}
</style>
</head>
<body>
<div class="card">
<h1>Server Control</h1>
<div class="sub">Enter password to continue</div>
<?php if (!empty($login_err)): ?>
<div class="err"><?= htmlspecialchars($login_err) ?></div>
<?php endif; ?>
<form method="post">
<input type="hidden" name="login" value="1">
<label for="pw">Password</label>
<input type="password" name="password" id="pw" placeholder="Enter password" required>
<button type="submit">Unlock</button>
</form>
</div>
</body>
</html>
