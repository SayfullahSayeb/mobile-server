<?php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://code.jquery.com https://cdnjs.cloudflare.com 'unsafe-inline'; style-src 'self' https://cdnjs.cloudflare.com 'unsafe-inline'; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com; frame-ancestors 'self'; connect-src 'self';");

function csrf(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '') . '">';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-Content-Type-Options" content="nosniff">
<title>Mobile Server</title>
<link rel="stylesheet" href="panel/control.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="sidebar">
  <div class="sb-brand">
    <div class="sb-logo">MS</div>
    <div>
      <div class="sb-text">Mobile Server</div>
      <div class="sb-sub">Control Panel</div>
    </div>
  </div>
  <div class="sb-nav">
    <div class="nav-sec">Overview</div>
    <a href="?tab=dashboard" class="nav-i <?= $tab==='dashboard'?'act':'' ?>">
      <span class="ni"><i class="fas fa-chart-bar"></i></span><span class="nl">Dashboard</span>
    </a>
    <div class="nav-sec">Web</div>
    <a href="?tab=sites" class="nav-i <?= $tab==='sites'?'act':'' ?>">
      <span class="ni"><i class="fas fa-globe"></i></span><span class="nl">Sites</span>
    </a>
    <a href="?tab=wordpress" class="nav-i <?= $tab==='wordpress'?'act':'' ?>">
      <span class="ni"><i class="fab fa-wordpress"></i></span><span class="nl">WordPress</span>
    </a>
    <div class="nav-sec">Network</div>
    <a href="?tab=cloudflare" class="nav-i <?= $tab==='cloudflare'?'act':'' ?>">
      <span class="ni"><i class="fas fa-cloud"></i></span><span class="nl">Cloudflare</span>
    </a>
    <div class="nav-sec">System</div>
    <a href="?tab=files" class="nav-i <?= $tab==='files'?'act':'' ?>">
      <span class="ni"><i class="fas fa-folder"></i></span><span class="nl">File Manager</span>
    </a>
    <a href="?tab=update" class="nav-i <?= $tab==='update'?'act':'' ?>">
      <span class="ni"><i class="fas fa-sync-alt"></i></span><span class="nl">Update</span>
    </a>
  </div>
  <div class="sb-foot">Mobile Server v1.0</div>
</div>
<div class="main">
  <div class="topbar">
    <div class="tbl">
      <div class="tb-title"><?= ucfirst($tab) ?></div>
      <div class="tb-bread">/ <span><?= $tab ?></span></div>
    </div>
    <div class="tbr">
      <div class="tb-info">
        <?php if (isset($hostname)): ?><strong><?= htmlspecialchars($hostname) ?></strong><br><?php endif; ?>
        <?php if (isset($server_time)): ?><?= htmlspecialchars($server_time) ?><?php endif; ?>
      </div>
      <a href="?logout=1" class="tb-btn logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </div>
  <div class="page">
<?php if (!empty($flash)): ?>
<div class="flash <?= $flash[0]==='success'?'suc':'err' ?>"><?= $flash[1] ?></div>
<?php endif; ?>
