<div class="df jb ac fw g2 mb4">
  <div class="st" style="margin-bottom:0">Service Status</div>
  <div class="df ac g2 fw">
    <form method="post" style="display:inline">
      <?= csrf() ?>
      <input type="hidden" name="action" value="restart_all">
      <button type="submit" class="btn btn-w btn-s" onclick="return confirm('Restart all services?')">↻ Restart All</button>
    </form>
    <button onclick="startUpdate()" class="btn btn-p btn-s" id="updateBtn"><i class="fas fa-sync-alt"></i> Update</button>
  </div>
</div>
<div class="sr">
<?php $icons = ['Nginx'=>'nx','PHP-FPM'=>'pp','MariaDB'=>'my']; ?>
<?php foreach ($services as $name => $s): $is_running = $status[$name]; ?>
<div class="sc">
  <div class="sc-h">
    <div class="sc-ic <?= $icons[$name]??'nx' ?>"><?= $name==='Nginx'?'<i class="fas fa-bolt"></i>':($name==='PHP-FPM'?'<i class="fas fa-server"></i>':'<i class="fas fa-database"></i>') ?></div>
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
      <div class="sc-ic tn"><i class="fas fa-cloud"></i></div>
      <div class="st" style="margin:0">Cloudflare Tunnel</div>
      <span class="bdg <?= $ts['running'] ? 'on' : 'off' ?>"><span class="dt"></span><?= $ts['running'] ? 'Running' : 'Stopped' ?></span>
    </div>
    <div class="df ac g2 fw">
      <form method="post" style="display:inline"><?= csrf() ?><input type="hidden" name="action" value="tunnel_start"><button type="submit" class="btn btn-st btn-s" <?= $ts['running'] ? 'disabled' : '' ?>>▶ Start</button></form>
      <form method="post" style="display:inline"><?= csrf() ?><input type="hidden" name="action" value="tunnel_stop"><button type="submit" class="btn btn-sp btn-s" onclick="return confirm('Stop tunnel?')" <?= !$ts['running'] ? 'disabled' : '' ?>>■ Stop</button></form>
      <form method="post" style="display:inline"><?= csrf() ?><input type="hidden" name="action" value="tunnel_restart"><button type="submit" class="btn btn-rs btn-s" onclick="return confirm('Restart tunnel?')">↻ Restart</button></form>
      <a href="?tab=cloudflare" class="btn btn-p btn-s"><i class="fas fa-list"></i> Logs</a>
    </div>
  </div>
  <?php if (!empty($ts['urls'])): ?>
  <div class="turl">
    <?php foreach ($ts['urls'] as $url): ?>
    <div class="tunnel-url-card" style="background:rgba(15,23,42,.5);border:1px solid rgba(148,163,184,.06);padding:12px 14px;border-radius:var(--rs)">
      <div style="font-size:16px;color:var(--text3);text-transform:uppercase;letter-spacing:.8px;font-weight:600;margin-bottom:3px">Public URL</div>
      <div class="df ac g2">
        <a href="https://<?= htmlspecialchars($url) ?>" target="_blank" style="color:var(--blue);font-size:16px;font-weight:600;text-decoration:none;flex:1;word-break:break-all"><?= htmlspecialchars($url) ?></a>
        <button onclick="copyUrl('<?= htmlspecialchars($url) ?>')" style="background:rgba(59,130,246,.12);color:var(--blue);border:1px solid rgba(59,130,246,.2);border-radius:5px;padding:2px 7px;cursor:pointer;font-size:16px;font-weight:600;font-family:inherit;flex-shrink:0">Copy</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="ig" style="margin-top:12px;margin-bottom:0">
    <div class="ii"><div class="l">Tunnel ID</div><div class="v" style="color:var(--text2)"><?= htmlspecialchars($ts['tunnel_id'] ?? 'N/A') ?></div></div>
    <div class="ii"><div class="l">Last Connected</div><div class="v" style="color:var(--text2)"><?= htmlspecialchars($ts['last_connected'] ?: 'Never') ?></div></div>
  </div>
  <?php if (!empty($tunnelHealth['issues'])): ?>
  <div class="mt2">
    <?php foreach ($tunnelHealth['issues'] as $issue): ?>
    <div style="background:rgba(239,68,68,.1);color:var(--red);padding:7px 10px;border-radius:var(--rs);margin-bottom:3px"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($issue) ?></div>
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
      <select name="log" class="sel" style="width:auto;margin-bottom:0;padding:7px 10px" onchange="this.form.submit()">
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

<div id="updateModal" class="umod" style="display:none">
  <div class="umod-bg" onclick="closeUpdate()"></div>
  <div class="umod-c">
    <div class="umod-h"><i class="fas fa-sync-alt"></i> System Update</div>
    <div class="umod-b">
      <div id="updateStatus" style="color:var(--text2);margin-bottom:12px">Starting...</div>
      <div id="updateBarWrap" style="background:rgba(51,65,85,.4);border-radius:99px;height:6px;overflow:hidden;margin-bottom:14px">
        <div id="updateBar" style="width:0%;height:100%;background:linear-gradient(90deg,#3b82f6,#6366f1);border-radius:99px;transition:width .3s"></div>
      </div>
      <div id="updateProgress" style="max-height:240px;overflow-y:auto;line-height:1.8;color:var(--text2);font-family:monospace"></div>
    </div>
    <div class="umod-f">
      <button onclick="closeUpdate()" class="btn btn-w btn-s" id="updateCloseBtn" disabled>Close</button>
    </div>
  </div>
</div>

<style>
.umod{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center}
.umod-bg{position:absolute;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px)}
.umod-c{position:relative;background:var(--bg2);border:1px solid rgba(148,163,184,.08);border-radius:var(--r);width:480px;max-width:90vw;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.5)}
.umod-h{padding:14px 18px;font-weight:700;color:var(--text);border-bottom:1px solid rgba(148,163,184,.06)}
.umod-h i{margin-right:6px;color:var(--blue)}
.umod-b{padding:16px 18px;overflow-y:auto;flex:1}
.umod-f{padding:10px 18px;border-top:1px solid rgba(148,163,184,.06);display:flex;justify-content:flex-end;gap:8px}
</style>

<script>
let updateSource = null;
function startUpdate() {
  document.getElementById('updateBtn').disabled = true;
  document.getElementById('updateModal').style.display = 'flex';
  document.getElementById('updateStatus').textContent = 'Starting update...';
  document.getElementById('updateBar').style.width = '0%';
  document.getElementById('updateProgress').innerHTML = '';
  document.getElementById('updateCloseBtn').disabled = true;

  updateSource = new EventSource('?tab=update&action=stream&csrf_token=<?= htmlspecialchars($csrf_token) ?>');
  updateSource.addEventListener('start', function(e) {
    document.getElementById('updateStatus').textContent = e.data;
  });
  updateSource.addEventListener('total', function(e) {
    document.getElementById('updateStatus').textContent = 'Found ' + e.data + ' files to update.';
  });
  updateSource.addEventListener('progress', function(e) {
    var d = JSON.parse(e.data);
    var pct = Math.round(d.current / d.total * 100);
    document.getElementById('updateBar').style.width = pct + '%';
    document.getElementById('updateStatus').textContent = 'Downloading ' + d.current + '/' + d.total + '...';
    var el = document.createElement('div');
    el.style.color = d.status === 'ok' ? 'var(--green)' : 'var(--red)';
    el.innerHTML = (d.status === 'ok' ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>') + ' ' + d.file;
    document.getElementById('updateProgress').appendChild(el);
  });
  updateSource.addEventListener('done', function(e) {
    var d = JSON.parse(e.data);
    document.getElementById('updateStatus').textContent = d.message;
    document.getElementById('updateBar').style.width = '100%';
    document.getElementById('updateCloseBtn').disabled = false;
    document.getElementById('updateBtn').disabled = false;
    updateSource.close();
  });
  updateSource.addEventListener('error', function() {
    if (updateSource.readyState === 2) return;
    document.getElementById('updateStatus').textContent = 'Connection lost. Reload page and try again.';
    document.getElementById('updateCloseBtn').disabled = false;
    document.getElementById('updateBtn').disabled = false;
  });
}
function closeUpdate() {
  if (updateSource) updateSource.close();
  document.getElementById('updateModal').style.display = 'none';
  location.reload();
}
window.addEventListener('beforeunload', function() { if (updateSource) updateSource.close(); });
</script>
