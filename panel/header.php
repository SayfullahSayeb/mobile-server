<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mobile Server</title>
<link rel="stylesheet" href="panel/control.css">
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
      <span class="ni">📊</span><span class="nl">Dashboard</span>
    </a>
    <div class="nav-sec">Web</div>
    <a href="?tab=sites" class="nav-i <?= $tab==='sites'?'act':'' ?>">
      <span class="ni">🌐</span><span class="nl">Sites</span>
    </a>
    <a href="?tab=wordpress" class="nav-i <?= $tab==='wordpress'?'act':'' ?>">
      <span class="ni">🔷</span><span class="nl">WordPress</span>
    </a>
    <div class="nav-sec">Network</div>
    <a href="?tab=cloudflare" class="nav-i <?= $tab==='cloudflare'?'act':'' ?>">
      <span class="ni">☁️</span><span class="nl">Cloudflare</span>
    </a>
    <div class="nav-sec">System</div>
    <a href="?tab=files" class="nav-i <?= $tab==='files'?'act':'' ?>">
      <span class="ni">📁</span><span class="nl">File Manager</span>
    </a>
    <a href="?tab=update" class="nav-i <?= $tab==='update'?'act':'' ?>">
      <span class="ni">🔄</span><span class="nl">Update</span>
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
      <a href="?logout=1" class="tb-btn logout">✕ Logout</a>
    </div>
  </div>
  <div class="page">
<?php if (!empty($flash)): ?>
<div class="flash <?= $flash[0]==='success'?'suc':'err' ?>"><?= $flash[1] ?></div>
<?php endif; ?>
