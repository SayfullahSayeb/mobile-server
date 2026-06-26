<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mobile Server - Login</title>
<link rel="stylesheet" href="panel/control.css">
</head>
<body>
<div class="lp">
<div class="lc">
<div class="ll">MS</div>
<div class="lt">Mobile Server</div>
<div class="lsu">Enter password to access the control panel</div>
<?php if (!empty($login_err)): ?>
<div class="flash err"><?= htmlspecialchars($login_err) ?></div>
<?php endif; ?>
<form method="post">
<input type="hidden" name="login" value="1">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
<input type="password" name="password" class="inp" placeholder="Password" required autofocus style="padding:13px 16px;margin-bottom:16px">
<button type="submit" class="btn btn-p btn-l btn-wd" style="padding:13px">Unlock Panel</button>
</form>
</div>
</div>
</body>
</html>
