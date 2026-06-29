<div class="sec">
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0">Tunnel</div>
  </div>
  <?php
  $cfTunnels = cfTunnelsLoad();
  $config = getSitesConfig();
  $activeTunnels = [];
  foreach ($cfTunnels as $site => $t) {
      $running = false;
      if (!empty($t['pid'])) {
          exec("kill -0 " . (int)$t['pid'] . " 2>/dev/null", $null, $rc);
          $running = $rc === 0;
      }
      $url = $t['url'] ?? '';
      if (!$url && $running) {
          $logFile = LOG_DIR . '/cf_tunnel_' . $site . '.log';
          if (is_file($logFile)) {
              $content = @file_get_contents($logFile);
              if (preg_match('/https:\/\/[a-z0-9-]+\.trycloudflare\.com/', $content, $m)) {
                  $url = $m[0];
                  $cfTunnels[$site]['url'] = $url;
                  cfTunnelsSave($cfTunnels);
              }
          }
      }
      if (!$running) {
          @unlink(LOG_DIR . '/cf_tunnel_' . $site . '.log');
      }
      $activeTunnels[$site] = ['running' => $running, 'url' => $url, 'port' => $t['port'] ?? 8080];
  }
  ?>
  <?php if (!empty($activeTunnels)): ?>
  <div class="ig">
    <?php foreach ($activeTunnels as $site => $info): ?>
    <div class="ii" style="flex-direction:column;align-items:flex-start;gap:6px">
      <div class="df jb ac" style="width:100%">
        <div>
          <div class="l" style="margin-bottom:2px"><?= htmlspecialchars($site) ?></div>
          <?php if ($info['running'] && $info['url']): ?>
          <a href="<?= htmlspecialchars($info['url']) ?>" target="_blank" style="color:var(--blue);font-size:12px;word-break:break-all"><?= htmlspecialchars($info['url']) ?></a>
          <?php elseif ($info['running']): ?>
          <span style="color:var(--text3);font-size:12px">Starting...</span>
          <?php else: ?>
          <span style="color:var(--text3);font-size:12px">Stopped</span>
          <?php endif; ?>
        </div>
        <div class="df ac g1">
          <?php if ($info['running']): ?>
          <form method="post" style="display:inline">
            <?= csrf() ?>
            <input type="hidden" name="action" value="cf_tunnel_stop">
            <input type="hidden" name="site" value="<?= htmlspecialchars($site) ?>">
            <button type="submit" class="btn btn-s btn-d" title="Stop Tunnel"><i class="fas fa-stop"></i> Stop</button>
          </form>
          <?php else: ?>
          <form method="post" style="display:inline">
            <?= csrf() ?>
            <input type="hidden" name="action" value="cf_tunnel_start">
            <input type="hidden" name="site" value="<?= htmlspecialchars($site) ?>">
            <button type="submit" class="btn btn-s btn-p" title="Start Tunnel"><i class="fas fa-play"></i> Start</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="ig">
    <div class="ii"><div class="l">Status</div><div class="v" style="color:var(--text3)">No active tunnels. Start one from Sites tab.</div></div>
  </div>
  <?php endif; ?>
</div>

<div class="sec">
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0">System</div>
  </div>
  <div class="ig">
    <div class="ii"><div class="l">Hostname</div><div class="v"><?= htmlspecialchars($hostname) ?></div></div>
    <div class="ii"><div class="l">IP Address</div><div class="v"><?= htmlspecialchars($ip_addr) ?></div></div>
    <div class="ii"><div class="l">Server Time</div><div class="v"><?= htmlspecialchars($server_time) ?></div></div>
    <div class="ii"><div class="l">Uptime</div><div class="v"><?= htmlspecialchars($uptime) ?></div></div>
  </div>
</div>

<div class="sec">
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0">Installed Versions</div>
  </div>
  <div class="ig">
    <?php
    $versions = [];
    $cmds = [
      'Nginx'      => 'nginx -v 2>&1',
      'PHP'        => 'php -v 2>&1 | head -1',
      'PHP-FPM'    => 'php-fpm -v 2>&1 | head -1',
      'MariaDB'    => 'mariadb --version 2>&1 | head -1',
      'Cloudflared'=> 'cloudflared --version 2>&1 | head -1',
    ];
    foreach ($cmds as $name => $cmd) {
        $raw = trim(@shell_exec($cmd) ?: '');
        if ($raw) {
            // extract version number like x.y.z
            if (preg_match('/(\d+\.\d+[\.\d]*)/', $raw, $m)) {
                $versions[$name] = $m[1];
            } else {
                $versions[$name] = $raw;
            }
        }
    }
    foreach ($versions as $name => $ver): ?>
    <div class="ii"><div class="l"><?= htmlspecialchars($name) ?></div><div class="v"><?= htmlspecialchars($ver) ?></div></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="sec">
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0">Diagnostics</div>
    <div class="df ac g2 fw">
      <a href="?tab=ssh&cmd=mobile-server+update" class="btn btn-s btn-p"><i class="fas fa-sync-alt"></i> Update</a>
      <form method="post" style="display:inline">
        <?= csrf() ?>
        <input type="hidden" name="action" value="nginx_test">
        <button type="submit" class="btn btn-s btn-w"><i class="fas fa-check"></i> Test Config</button>
      </form>
      <form method="post" style="display:inline">
        <?= csrf() ?>
        <input type="hidden" name="action" value="restart_nginx">
        <button type="submit" class="btn btn-s btn-rs" onclick="return confirm('Restart Nginx?')"><i class="fas fa-sync-alt"></i> Restart Nginx</button>
      </form>
      <form method="post" style="display:inline">
        <?= csrf() ?>
        <input type="hidden" name="action" value="check_ports">
        <button type="submit" class="btn btn-s btn-p"><i class="fas fa-plug"></i> Check Ports</button>
      </form>
    </div>
  </div>
  <?php
  $diagOutput = $_SESSION['nginx_diag'] ?? '';
  if ($diagOutput):
    unset($_SESSION['nginx_diag']);
  ?>
  <div class="lv" style="white-space:pre-wrap;font-size:13px"><?= htmlspecialchars($diagOutput) ?></div>
  <?php endif; ?>
  <?php
  $nginxLog = $log_files['nginx'] ?? null;
  if ($nginxLog && is_file($nginxLog)):
    $logLines = file($nginxLog);
    $logLines = $logLines ? array_slice($logLines, -20) : [];
  ?>
  <div class="st2 mt2 mb0" style="font-size:13px;color:var(--text3)">Recent Nginx Error Log (last 20 lines)</div>
  <pre class="lv" style="max-height:150px;font-size:12px;margin-top:6px"><?= htmlspecialchars(implode('', $logLines)) ?></pre>
  <?php endif; ?>
</div>
