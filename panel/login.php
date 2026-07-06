<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mobile Server - Login</title>
<link rel="icon" type="image/x-icon" href="panel/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="panel/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="panel/favicon-16x16.png">
<link rel="stylesheet" href="panel/control.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="lp">
<div class="lc">
<div class="ll"><img src="panel/favicon-32x32.png" alt="logo" style="width:28px;height:28px"></div>
<div class="lt">Mobile Server</div>
<div class="lsu">Enter password to access the control panel</div>
<?php if (!empty($login_err)): ?>
<div class="flash err"><?= htmlspecialchars($login_err) ?></div>
<?php endif; ?>
<form method="post">
<input type="hidden" name="login" value="1">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
<div style="position:relative;margin-bottom:16px">
<input type="password" name="password" id="login-pass" class="inp" placeholder="Password" autofocus style="padding:13px 40px 13px 16px;width:100%;box-sizing:border-box">
<i id="login-pass-toggle" class="fas fa-eye" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text3);font-size:16px" onclick="toggleLoginPass()"></i>
</div>
<button type="submit" class="btn btn-p btn-l btn-wd" style="padding:13px">Unlock Panel</button>
</form>
</div>
</div>
<script>
function toggleLoginPass() {
  var inp = document.getElementById('login-pass');
  var icon = document.getElementById('login-pass-toggle');
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.className = 'fas fa-eye-slash';
  } else {
    inp.type = 'password';
    icon.className = 'fas fa-eye';
  }
}
</script>
</body>
</html>
