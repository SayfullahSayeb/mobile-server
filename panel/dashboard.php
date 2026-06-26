<div class="df jb ac fw g2 mb4">
  <div class="st" style="margin-bottom:0">Service Status</div>
  <form method="post">
    <?= csrf() ?>
    <input type="hidden" name="action" value="restart_all">
    <button type="submit" class="btn btn-w btn-s" onclick="return confirm('Restart all services?')">↻ Restart All</button>
  </form>
</div>
<div class="sr">
<?php $icons = ['Nginx'=>'nx','PHP-FPM'=>'pp','MariaDB'=>'my']; ?>
<?php foreach ($services as $name => $s): $is_running = $status[$name]; ?>
<div class="sc">
  <div class="sc-h">
    <div class="sc-ic <?= $icons[$name]??'nx' ?>"><?= $name==='Nginx'?'⚡':($name==='PHP-FPM'?'🐘':'🗄️') ?></div>
    <span class="bdg <?= $is_running ? 'on' : 'off' ?>"><span class="dt"></span><?= $is_running ? 'Running' : 'Stopped' ?></span>
  </div>
  <div class="sc-n"><?= htmlspecialchars($name) ?></div>
  <div class="sc-ac">
    <?php if (!$is_running): ?>
    <form method="post"><?= csrf() ?><input type="hidden" name="action" value="start"><input type="hidden" name="service" value="<?= $name ?>"><button type="submit" class="btn btn-st btn-s">▶ Start</button></form>
    <?php else: ?>
    <form method="post"><?= csrf() ?><input type="hidden" name="action" value="stop"><input type="hidden" name="service" value="<?= $name ?>"><button type="submit" class="btn btn-sp btn-s" onclick="return confirm('Stop <?= $name ?>?')">■ Stop</button></form>
    <?php endif; ?>
    <form method="post"><?= csrf() ?><input type="hidden" name="action" value="restart"><input type="hidden" name="service" value="<?= $name ?>"><button type="submit" class="btn btn-rs btn-s" onclick="return confirm('Restart <?= $name ?>?')">↻ Restart</button></form>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php if ($tunnelInstalled && $tunnelAuthenticated && $tunnelManager->hasActiveTunnel()): $ts = $tunnelStatus; ?>
<div class="sec">
  <div class="df jb ac fw g3">
    <div class="df ac g3">
      <div class="sc-ic tn">☁️</div>
      <div class="st" style="margin:0">Cloudflare Tunnel</div>
      <span class="bdg <?= $ts['running'] ? 'on' : 'off' ?>"><span class="dt"></span><?= $ts['running'] ? 'Running' : 'Stopped' ?></span>
    </div>
    <div class="df ac g2 fw">
      <form method="post" style="display:inline"><?= csrf() ?><input type="hidden" name="action" value="tunnel_start"><button type="submit" class="btn btn-st btn-s" <?= $ts['running'] ? 'disabled' : '' ?>>▶ Start</button></form>
      <form method="post" style="display:inline"><?= csrf() ?><input type="hidden" name="action" value="tunnel_stop"><button type="submit" class="btn btn-sp btn-s" onclick="return confirm('Stop tunnel?')" <?= !$ts['running'] ? 'disabled' : '' ?>>■ Stop</button></form>
      <form method="post" style="display:inline"><?= csrf() ?><input type="hidden" name="action" value="tunnel_restart"><button type="submit" class="btn btn-rs btn-s" onclick="return confirm('Restart tunnel?')">↻ Restart</button></form>
      <a href="?tab=cloudflare" class="btn btn-p btn-s">📋 Logs</a>
    </div>
  </div>
  <?php if (!empty($ts['urls'])): ?>
  <div class="turl">
    <?php foreach ($ts['urls'] as $url): ?>
    <div class="tunnel-url-card" style="background:rgba(15,23,42,.5);border:1px solid rgba(148,163,184,.06);padding:12px 14px;border-radius:var(--rs)">
      <div style="font-size:9px;color:var(--text3);text-transform:uppercase;letter-spacing:.8px;font-weight:600;margin-bottom:3px">Public URL</div>
      <div class="df ac g2">
        <a href="https://<?= htmlspecialchars($url) ?>" target="_blank" style="color:var(--blue);font-size:12px;font-weight:600;text-decoration:none;flex:1;word-break:break-all"><?= htmlspecialchars($url) ?></a>
        <button onclick="copyUrl('<?= htmlspecialchars($url) ?>')" style="background:rgba(59,130,246,.12);color:var(--blue);border:1px solid rgba(59,130,246,.2);border-radius:5px;padding:2px 7px;cursor:pointer;font-size:10px;font-weight:600;font-family:inherit;flex-shrink:0">Copy</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="ig" style="margin-top:12px;margin-bottom:0">
    <div class="ii"><div class="l">Tunnel ID</div><div class="v" style="font-size:11px;color:var(--text2)"><?= htmlspecialchars($ts['tunnel_id'] ?? 'N/A') ?></div></div>
    <div class="ii"><div class="l">Last Connected</div><div class="v" style="font-size:11px;color:var(--text2)"><?= htmlspecialchars($ts['last_connected'] ?: 'Never') ?></div></div>
  </div>
  <?php if (!empty($tunnelHealth['issues'])): ?>
  <div class="mt2">
    <?php foreach ($tunnelHealth['issues'] as $issue): ?>
    <div style="background:rgba(239,68,68,.1);color:var(--red);padding:7px 10px;border-radius:var(--rs);font-size:11px;margin-bottom:3px">⚠ <?= htmlspecialchars($issue) ?></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="ig">
  <div class="ii"><div class="l">Hostname</div><div class="v"><?= htmlspecialchars($hostname) ?></div></div>
  <div class="ii"><div class="l">IP Address</div><div class="v"><?= htmlspecialchars($ip_addr) ?></div></div>
  <div class="ii"><div class="l">Server Time</div><div class="v"><?= htmlspecialchars($server_time) ?></div></div>
  <div class="ii"><div class="l">Uptime</div><div class="v"><?= htmlspecialchars($uptime) ?></div></div>
  <div class="ii"><div class="l">PHP Version</div><div class="v"><?= htmlspecialchars($php_ver) ?></div></div>
  <div class="ii"><div class="l">Web Server</div><div class="v">Nginx :8080</div></div>
</div>

<div class="sec">
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0">Log Viewer</div>
    <form method="get">
      <select name="log" class="sel" style="width:auto;margin-bottom:0;padding:7px 10px;font-size:12px" onchange="this.form.submit()">
        <option value="">Select a log...</option>
        <?php foreach ($log_files as $name => $path): ?>
        <option value="<?= $name ?>" <?= ($_GET['log'] ?? '') === $name ? 'selected' : '' ?>><?= ucfirst($name) ?>.log<?= $path ? '' : ' (empty)' ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <?php
  if (isset($_GET['log']) && isset($log_files[$_GET['log']]) && $log_files[$_GET['log']]) {
      $lines = file($log_files[$_GET['log']]);
      $lines = $lines ? array_slice($lines, -LOG_MAX_LINES) : [];
      echo '<pre class="lv">' . ($lines ? htmlspecialchars(implode('', $lines)) : 'Log is empty') . '</pre>';
  } elseif (isset($_GET['log'])) {
      echo '<pre class="lv">Log file not found</pre>';
  } else {
      echo '<pre class="lv" style="color:var(--text3)">Select a log file above to view</pre>';
  }
  ?>
</div>
