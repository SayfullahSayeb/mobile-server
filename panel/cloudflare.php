<?php
$tunnelList = $tunnelInstalled && $tunnelAuthenticated ? $tunnelManager->listTunnels() : [];
$tunnelId = $tunnelManager->getActiveTunnelId();
$tunnelName = $tunnelManager->getActiveTunnelName();
?>

<?php if (!$tunnelInstalled): ?>
<div class="sec">
  <div class="st"><i class="fas fa-cloud"></i> Cloudflare Tunnel</div>
  <div class="st3">Cloudflare Tunnel (cloudflared) creates a secure tunnel from your server to the Cloudflare edge network, allowing you to expose local websites to the internet without port forwarding.</div>
  <form method="post">
    <?= csrf() ?>
    <input type="hidden" name="action" value="tunnel_install">
    <button type="submit" class="btn btn-p btn-l"><i class="fas fa-download"></i> Install Cloudflare Tunnel</button>
  </form>
</div>

<?php elseif (!$tunnelAuthenticated): ?>
<div class="sec">
  <div class="st"><i class="fas fa-cloud"></i> Cloudflare Authentication</div>
  <div class="st3">Authenticate with your Cloudflare account to create and manage tunnels.</div>

  <?php if ($tunnelLoginStatus === 'pending'): ?>
  <div style="background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.2);border-radius:var(--r);padding:14px;margin-bottom:14px">
    <div style="color:var(--orange);font-weight:600;margin-bottom:6px"><i class="fas fa-hourglass-half"></i> Authentication in Progress</div>
    <?php if ($tunnelLoginUrl): ?>
    <div class="ts tm mb2">Open this URL in a browser to authenticate with Cloudflare:</div>
    <div style="background:rgba(15,23,42,.5);padding:8px 12px;border-radius:var(--rs);word-break:break-all;color:var(--blue);font-family:monospace;margin-bottom:8px"><?= htmlspecialchars($tunnelLoginUrl) ?></div>
    <?php else: ?>
    <div class="ts tm mb2">Waiting for Cloudflare login URL... Click "Check Status" to refresh.</div>
    <?php endif; ?>
    <div class="ts tm mb2">After authenticating, click "Check Status" below.</div>
    <form method="post">
      <?= csrf() ?>
      <input type="hidden" name="action" value="tunnel_login">
      <button type="submit" class="btn btn-w">⟳ Check Authentication Status</button>
    </form>
  </div>
  <?php else: ?>
  <form method="post">
    <?= csrf() ?>
    <input type="hidden" name="action" value="tunnel_login">
    <button type="submit" class="btn btn-p btn-l"><i class="fas fa-key"></i> Login to Cloudflare</button>
  </form>
  <?php endif; ?>
</div>

<?php else: ?>

<div class="sec">
  <div class="df jb ac fw g2">
    <div class="st" style="margin-bottom:0"><i class="fas fa-key"></i> Authentication</div>
    <form method="post" style="display:inline">
      <input type="hidden" name="action" value="tunnel_logout">
      <button type="submit" class="btn btn-d btn-s" onclick="return confirm('Logout from Cloudflare? This will remove stored credentials.')"><i class="fas fa-times"></i> Logout</button>
    </form>
  </div>
  <div style="color:var(--green);margin-top:6px"><i class="fas fa-check"></i> Authenticated with Cloudflare</div>
</div>

<div class="sec">
  <div class="st"><i class="fas fa-sync-alt"></i> Tunnel Management</div>

  <?php if (!empty($tunnelList)): ?>
  <div class="st2">Existing Tunnels</div>
  <?php foreach ($tunnelList as $t): $isActive = ($t['id'] === $tunnelId); ?>
  <div class="di">
    <div>
      <div class="sn"><?= htmlspecialchars($t['name']) ?></div>
      <div style="color:var(--text3);margin-top:2px">ID: <?= htmlspecialchars(substr($t['id'], 0, 8)) ?>... | Status: <?= htmlspecialchars($t['status'] ?: 'unknown') ?></div>
    </div>
    <div class="df ac g2">
      <?php if ($isActive): ?>
      <span class="be on">Active</span>
      <?php else: ?>
      <form method="post" style="display:inline">
        <?= csrf() ?>
        <input type="hidden" name="action" value="tunnel_select">
        <input type="hidden" name="tunnel_id" value="<?= htmlspecialchars($t['id']) ?>">
        <input type="hidden" name="tunnel_name" value="<?= htmlspecialchars($t['name']) ?>">
        <button type="submit" class="btn btn-o btn-s">Select</button>
      </form>
      <?php endif; ?>
      <form method="post" style="display:inline" onsubmit="return confirm('Delete tunnel &#39;<?= htmlspecialchars($t['name']) ?>&#39;?')">
        <?= csrf() ?>
        <input type="hidden" name="action" value="tunnel_delete">
        <input type="hidden" name="tunnel_id" value="<?= htmlspecialchars($t['id']) ?>">
        <button type="submit" class="btn btn-d btn-s"><i class="fas fa-trash-alt"></i> Delete</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <div class="st2">Create New Tunnel</div>
  <div style="background:rgba(15,23,42,.4);padding:14px;border-radius:var(--rs)">
    <form method="post" class="fr">
      <?= csrf() ?>
      <input type="hidden" name="action" value="tunnel_create">
      <input type="text" name="tunnel_name" class="inp" placeholder="Tunnel name (e.g., my-server)" required pattern="[a-zA-Z0-9_-]+" title="Letters, numbers, hyphens, underscores">
      <button type="submit" class="btn btn-p"><i class="fas fa-plus"></i> Create Tunnel</button>
    </form>
  </div>
</div>

<?php if ($tunnelId): $ts = $tunnelStatus; ?>
<div class="sec">
  <div class="df jb ac fw g3">
    <div class="df ac g3">
      <div class="st" style="margin-bottom:0"><i class="fas fa-cloud"></i> <?= htmlspecialchars($tunnelName ?: $tunnelId) ?></div>
      <span class="bdg <?= $ts['running'] ? 'on' : 'off' ?>"><span class="dt"></span><?= $ts['running'] ? 'Running' : 'Stopped' ?></span>
    </div>
    <div class="df ac g2 fw">
      <form method="post"><?= csrf() ?><input type="hidden" name="action" value="tunnel_start"><button type="submit" class="btn btn-st btn-s" <?= $ts['running'] ? 'disabled' : '' ?>>▶ Start</button></form>
      <form method="post"><?= csrf() ?><input type="hidden" name="action" value="tunnel_stop"><button type="submit" class="btn btn-sp btn-s" onclick="return confirm('Stop tunnel?')" <?= !$ts['running'] ? 'disabled' : '' ?>>■ Stop</button></form>
      <form method="post"><?= csrf() ?><input type="hidden" name="action" value="tunnel_restart"><button type="submit" class="btn btn-rs btn-s" onclick="return confirm('Restart tunnel?')">↻ Restart</button></form>
    </div>
  </div>
  <?php if ($ts['running'] && !empty($ts['urls'])): ?>
  <div class="mt2">
    <div class="st2">Public URLs</div>
    <?php foreach ($ts['urls'] as $url): ?>
    <div class="df ac g2" style="background:rgba(15,23,42,.4);padding:8px 12px;border-radius:var(--rs);margin-bottom:5px">
      <a href="https://<?= htmlspecialchars($url) ?>" target="_blank" style="color:var(--blue);font-weight:600;text-decoration:none;flex:1;word-break:break-all"><?= htmlspecialchars($url) ?></a>
      <button onclick="copyUrl('<?= htmlspecialchars($url) ?>')" style="background:rgba(59,130,246,.12);color:var(--blue);border:1px solid rgba(59,130,246,.2);border-radius:5px;padding:3px 8px;cursor:pointer;font-weight:600;font-family:inherit">Copy</button>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="sec">
  <div class="st"><i class="fas fa-globe"></i> Hostname Mapping</div>
  <div class="st3">Map a public hostname to a local website. Requires the domain to be in your Cloudflare account.</div>

  <?php if (!empty($tunnelHostnames)): ?>
  <div class="st2">Mapped Hostnames</div>
  <?php foreach ($tunnelHostnames as $hostname => $target): ?>
  <div class="di">
    <div>
      <div class="sn"><?= htmlspecialchars($hostname) ?></div>
      <div style="color:var(--text3);margin-top:2px">→ <?= htmlspecialchars($target) ?></div>
    </div>
    <form method="post" style="display:inline" onsubmit="return confirm('Remove hostname <?= htmlspecialchars($hostname) ?>?')">
      <?= csrf() ?>
      <input type="hidden" name="action" value="tunnel_remove_hostname">
      <input type="hidden" name="hostname" value="<?= htmlspecialchars($hostname) ?>">
      <button type="submit" class="btn btn-d btn-s"><i class="fas fa-trash-alt"></i> Remove</button>
    </form>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <div class="st2">Add Hostname</div>
  <div style="background:rgba(15,23,42,.4);padding:14px;border-radius:var(--rs)">
    <form method="post" class="fr3">
      <?= csrf() ?>
      <input type="hidden" name="action" value="tunnel_add_hostname">
      <input type="text" name="hostname" class="inp" placeholder="e.g., app.mydomain.com" required>
      <input type="text" name="target" class="inp" placeholder="e.g., http://127.0.0.1:8080" value="http://127.0.0.1:8080">
      <button type="submit" class="btn btn-p"><i class="fas fa-plus"></i> Add Hostname</button>
    </form>
    <div class="fh">Target should be the local URL of your website (e.g., http://127.0.0.1:8080)</div>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php if ($tunnelInstalled && $tunnelAuthenticated): ?>
<div class="sec">
  <div class="st"><i class="fas fa-cog"></i> Auto-Start</div>
  <form method="post">
    <?= csrf() ?>
    <input type="hidden" name="action" value="tunnel_set_autostart">
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--text)">
      <input type="checkbox" name="auto_start" value="1" <?= $tunnelAutoStart ? 'checked' : '' ?> onchange="this.form.submit()" style="width:16px;height:16px;accent-color:var(--blue);cursor:pointer">
      Automatically start tunnel when Mobile Server starts
    </label>
  </form>
</div>

<div class="sec">
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0"><i class="fas fa-list"></i> Tunnel Logs</div>
    <div class="df g2">
      <form method="get">
        <input type="hidden" name="tab" value="cloudflare">
        <input type="hidden" name="refresh" value="1">
        <button type="submit" class="btn btn-w btn-s">⟳ Refresh</button>
      </form>
      <form method="post">
        <?= csrf() ?>
        <input type="hidden" name="action" value="tunnel_clear_logs">
        <button type="submit" class="btn btn-d btn-s" onclick="return confirm('Clear tunnel logs?')"><i class="fas fa-trash-alt"></i> Clear</button>
      </form>
      <a href="<?= htmlspecialchars($tunnelManager->getLogPath()) ?>" download class="btn btn-o btn-s"><i class="fas fa-download"></i> Download</a>
    </div>
  </div>
  <pre class="lv"><?php
  $logContent = $tunnelManager->logs(LOG_MAX_LINES);
  echo htmlspecialchars($logContent ?: 'Log is empty');
  ?></pre>
</div>

<div class="sec">
  <div class="st"><i class="fas fa-heart"></i> Health Status</div>
  <div class="ig" style="margin-bottom:0">
    <div class="ii">
      <div class="l">Tunnel Status</div>
      <div class="v" style="color:<?= $tunnelHealth['status'] === 'running' ? 'var(--green)' : 'var(--red)' ?>">
        <?= $tunnelHealth['status'] === 'running' ? '<i class="fas fa-check"></i> Running' : ($tunnelHealth['status'] === 'stopped' ? '<i class="fas fa-times"></i> Stopped' : 'N/A') ?>
      </div>
    </div>
    <?php if (!empty($tunnelHealth['issues'])): ?>
    <div style="background:rgba(15,23,42,.5);border:1px solid rgba(239,68,68,.2);padding:12px;border-radius:var(--rs);grid-column:1/-1">
      <div style="color:var(--red);text-transform:uppercase;letter-spacing:.8px;font-weight:600">Issues</div>
      <?php foreach ($tunnelHealth['issues'] as $issue): ?>
      <div style="color:var(--red);margin-top:4px">• <?= htmlspecialchars($issue) ?></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
