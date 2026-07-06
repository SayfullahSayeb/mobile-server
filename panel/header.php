<?php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://code.jquery.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://unpkg.com 'unsafe-inline'; style-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://unpkg.com 'unsafe-inline'; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com; frame-ancestors 'self'; frame-src *; connect-src 'self' https://cdn.jsdelivr.net;");

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
<script>
(function(){var t=localStorage.getItem('theme');if(t==='dark'){document.documentElement.setAttribute('data-theme','dark')};var l=document.getElementById('themeLabel');if(l){l.textContent=t==='dark'?'Light Mode':'Dark Mode'}})()
function toggleTheme(){var h=document.documentElement;var n=h.getAttribute('data-theme')==='dark'?'light':'dark';h.setAttribute('data-theme',n);localStorage.setItem('theme',n);document.getElementById('themeToggle').innerHTML=(n==='dark'?'<i class="fas fa-sun"></i>':'<i class="fas fa-moon"></i>')+' <span id="themeLabel">'+(n==='dark'?'Light Mode':'Dark Mode')+'</span>'}
</script>
</head>
<body>
<div class="sb-overlay" onclick="toggleSidebar()"></div>
<div class="sidebar" id="sidebar">
  <div class="sb-brand">
    <div class="sb-logo">MS</div>
    <div>
      <div class="sb-text">Mobile Server</div>
      <div class="sb-sub">Control Panel</div>
    </div>
  </div>
  <div class="sb-nav">
    <a href="/dashboard" class="nav-i <?= $tab==='dashboard'?'act':'' ?>">
      <span class="ni"><i class="fas fa-chart-bar"></i></span><span class="nl">Dashboard</span>
    </a>
    <a href="/sites" class="nav-i <?= $tab==='sites'?'act':'' ?>">
      <span class="ni"><i class="fas fa-globe"></i></span><span class="nl">Sites</span>
    </a>
    <a href="/terminal" class="nav-i <?= $tab==='terminal'?'act':'' ?>">
      <span class="ni"><i class="fas fa-terminal"></i></span><span class="nl">Terminal</span>
    </a>
    <a href="/logs" class="nav-i <?= $tab==='logs'?'act':'' ?>">
      <span class="ni"><i class="fas fa-list-alt"></i></span><span class="nl">Logs</span>
    </a>
    <a href="/files" class="nav-i <?= $tab==='files'?'act':'' ?>">
      <span class="ni"><i class="fas fa-folder"></i></span><span class="nl">File Manager</span>
    </a>
    <a href="/phpmyadmin" target="_blank" rel="noopener" class="nav-i">
      <span class="ni"><i class="fas fa-database"></i></span><span class="nl">phpMyAdmin</span>
    </a>
    <a href="/system" class="nav-i <?= $tab==='system'?'act':'' ?>">
      <span class="ni"><i class="fas fa-microchip"></i></span><span class="nl">System</span>
    </a>
  </div>
  <div class="sb-foot">
    <a href="#" class="sb-logout" id="themeToggle" onclick="toggleTheme();return false"><i class="fas fa-moon"></i> <span id="themeLabel">Dark Mode</span></a>
    <a href="?logout=1" class="sb-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    <div class="sb-ver">Mobile Server v1.0.89</div>
  </div>
</div>
<div class="main">
  <div class="topbar" style="flex-shrink:0">
    <button class="hamburger" onclick="toggleSidebar()" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
    <div class="tbl">
      <div class="tb-title"><?= ucfirst($tab) ?></div>
      <div class="tb-bread">/ <span><?= $tab ?></span></div>
    </div>
    <div class="tbr">
      <div class="tb-info">
        <?php if (isset($hostname)): ?><strong><?= htmlspecialchars($hostname) ?></strong><br><?php endif; ?>
        <?php if (isset($server_time)): ?><?= htmlspecialchars($server_time) ?><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="page" style="flex:1">
<?php if (!empty($flash)): ?>
<div class="flash <?= $flash[0]==='success'?'suc':'err' ?>"><?= $flash[1] ?></div>
<?php endif; ?>
