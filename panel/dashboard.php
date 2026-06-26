<div class="df jb ac fw g2 mb4">
  <div class="st" style="margin-bottom:0">Service Status</div>
  <div class="df ac g2 fw">
    <form method="post" style="display:inline">
      <?= csrf() ?>
      <input type="hidden" name="action" value="restart_all">
      <button type="submit" class="btn btn-w btn-s" onclick="return confirm('Restart all services?')">↻ Restart All</button>
    </form>
    <button class="btn btn-p btn-s" id="updateBtn" onclick="showUpdateModal()"><i class="fas fa-sync-alt"></i> Update</button>
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

<div id="updateModal" class="modal">
  <div class="modal-bg" onclick="closeUpdateModal()"></div>
  <div class="modal-content" style="max-width:650px">
    <div class="modal-header">
      <span class="modal-title"><i class="fas fa-sync-alt"></i> Updating Mobile Server</span>
      <span class="modal-close" onclick="closeUpdateModal()">&times;</span>
    </div>
    <div id="updateLog" class="lv" style="max-height:350px;overflow-y:auto;font-size:13px;line-height:1.6;padding:12px;font-family:'JetBrains Mono','Fira Code',monospace;white-space:pre-wrap"></div>
    <div class="modal-footer" id="updateFooter" style="display:none">
      <button class="btn btn-p" onclick="location.reload()">Close &amp; Reload</button>
    </div>
  </div>
</div>

<script>
var token = <?= json_encode($csrf_token) ?>;

function sshExec(cmd) {
  return fetch('panel/ssh_exec.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'cmd=' + encodeURIComponent(cmd) + '&csrf_token=' + encodeURIComponent(token)
  }).then(function(r) { return r.json(); });
}

function showUpdateModal() {
  var modal = document.getElementById('updateModal');
  var log = document.getElementById('updateLog');
  modal.classList.add('show');
  log.innerHTML = '';
  document.getElementById('updateBtn').disabled = true;

  function addLine(text) {
    var d = document.createElement('div');
    d.textContent = text;
    log.appendChild(d);
    log.scrollTop = log.scrollHeight;
  }

  addLine('$ cd ~/mobile-server && git fetch origin && git pull');

  sshExec('cd ~/mobile-server && git fetch origin 2>&1').then(function(r1) {
    addLine(r1.output);

    if (r1.output.indexOf('fatal') !== -1 || r1.output.indexOf('Could not') !== -1) {
      addLine('');
      addLine('Fetch failed. Check your internet connection.');
      addLine('You can manually update by running in Termux:');
      addLine('  cd ~/mobile-server && git pull');
      document.getElementById('updateBtn').disabled = false;
      document.getElementById('updateFooter').style.display = 'flex';
      return;
    }

    return sshExec('cd ~/mobile-server && git pull 2>&1');
  }).then(function(r2) {
    if (!r2) return;
    addLine(r2.output);
    addLine('');

    if (r2.output.indexOf('Already up to date') !== -1) {
      addLine('Already up to date!');
    } else if (r2.output.indexOf('Updating') !== -1 || r2.output.indexOf('Fast-forward') !== -1) {
      addLine('Update completed!');
    } else if (r2.output.indexOf('fatal') !== -1 || r2.output.indexOf('error') !== -1) {
      addLine('Pull failed. Resolve conflicts manually in Termux:');
      addLine('  cd ~/mobile-server && git pull');
    } else {
      addLine('Done.');
    }

    document.getElementById('updateBtn').disabled = false;
    document.getElementById('updateFooter').style.display = 'flex';
  });
}

function closeUpdateModal() {
  document.getElementById('updateModal').classList.remove('show');
}
</script>

