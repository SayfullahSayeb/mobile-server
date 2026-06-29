<div class="sec">
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0">HTTPS</div>
  </div>
  <div class="ig">
    <div class="ii">
      <div class="l">Status</div>
      <div class="v">
        <span class="bdg <?= $https_enabled ? 'on' : 'off' ?>"><span class="dt"></span><?= $https_enabled ? 'Enabled' : 'Disabled' ?></span>
      </div>
    </div>
    <?php if ($https_enabled): ?>
    <div class="ii"><div class="l">URL</div><div class="v"><a href="https://<?= htmlspecialchars($ip_addr) ?>:8443" target="_blank" style="color:var(--blue)">https://<?= htmlspecialchars($ip_addr) ?>:8443</a></div></div>
    <div class="ii"><div class="l">Certificate</div><div class="v" style="color:var(--text2)">Self-signed (10 years)</div></div>
    <?php endif; ?>
  </div>
  <div class="df ac g2 mt2">
    <form method="post">
      <?= csrf() ?>
      <input type="hidden" name="action" value="<?= $https_enabled ? 'disable_https' : 'setup_https' ?>">
      <button type="submit" class="btn <?= $https_enabled ? 'btn-d' : 'btn-p' ?>">
        <i class="fas <?= $https_enabled ? 'fa-toggle-off' : 'fa-toggle-on' ?>"></i>
        <?= $https_enabled ? 'Disable HTTPS' : 'Enable HTTPS' ?>
      </button>
    </form>
    <?php if ($https_enabled): ?>
    <a href="https://<?= htmlspecialchars($ip_addr) ?>:8443" target="_blank" class="btn btn-p"><i class="fas fa-external-link-alt"></i> Open Panel via HTTPS</a>
    <?php endif; ?>
  </div>
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
    <div class="ii"><div class="l">PHP Version</div><div class="v"><?= htmlspecialchars($php_ver) ?></div></div>
  </div>
</div>

<div class="sec">
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0">Diagnostics</div>
    <div class="df ac g2 fw">
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
