<div class="sec">
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0">Tunnel</div>
  </div>
  <?php
  $cfTunnels = cfTunnelsLoad();
  $panelTunnel = $cfTunnels['_panel'] ?? null;
  $panelRunning = false;
  $panelUrl = '';
  if ($panelTunnel && !empty($panelTunnel['pid'])) {
      exec("kill -0 " . (int)$panelTunnel['pid'] . " 2>/dev/null", $null, $rc);
      $panelRunning = $rc === 0;
      $panelUrl = $panelTunnel['url'] ?? '';
      if (!$panelUrl && $panelRunning) {
          $logFile = LOG_DIR . '/cf_tunnel__panel.log';
          if (is_file($logFile)) {
              $content = @file_get_contents($logFile);
              if (preg_match('/https:\/\/[a-z0-9-]+\.trycloudflare\.com/', $content, $m)) {
                  $panelUrl = $m[0];
                  $cfTunnels['_panel']['url'] = $panelUrl;
                  cfTunnelsSave($cfTunnels);
              }
          }
      }
      if (!$panelRunning) @unlink(LOG_DIR . '/cf_tunnel__panel.log');
  }
  ?>
  <div class="ig">
    <div class="ii">
      <div class="l">Status</div>
      <div class="v">
        <span class="bdg <?= $panelRunning ? 'on' : 'off' ?>"><span class="dt"></span><?= $panelRunning ? 'Running' : 'Stopped' ?></span>
      </div>
    </div>
    <?php if ($panelRunning && $panelUrl): ?>
    <div class="ii"><div class="l">URL</div><div class="v"><a href="<?= htmlspecialchars($panelUrl) ?>" target="_blank" style="color:var(--blue);word-break:break-all"><?= htmlspecialchars($panelUrl) ?></a></div></div>
    <?php endif; ?>
  </div>
  <div class="df ac g2 mt2">
    <form method="post" style="display:inline">
      <?= csrf() ?>
      <input type="hidden" name="action" value="<?= $panelRunning ? 'cf_tunnel_stop' : 'cf_tunnel_start' ?>">
      <input type="hidden" name="site" value="_panel">
      <input type="hidden" name="tunnel_port" value="8080">
      <button type="submit" class="btn <?= $panelRunning ? 'btn-d' : 'btn-p' ?>">
        <i class="fas <?= $panelRunning ? 'fa-stop' : 'fa-play' ?>"></i>
        <?= $panelRunning ? 'Stop Tunnel' : 'Start Tunnel' ?>
      </button>
    </form>
    <?php if ($panelRunning && $panelUrl): ?>
    <a href="<?= htmlspecialchars($panelUrl) ?>" target="_blank" class="btn btn-p"><i class="fas fa-external-link-alt"></i> Open Panel via Tunnel</a>
    <?php endif; ?>
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
