<?php
$tunnelList = $tunnelInstalled && $tunnelAuthenticated ? $tunnelManager->listTunnels() : [];
$tunnelId = $tunnelManager->getActiveTunnelId();
$tunnelName = $tunnelManager->getActiveTunnelName();
?>

<?php if (!$tunnelInstalled): ?>
<div class="section">
<h2>Cloudflare Tunnel</h2>
<p style="color:#64748b;font-size:13px;margin-bottom:16px">Cloudflare Tunnel (cloudflared) creates a secure tunnel from your server to the Cloudflare edge network, allowing you to expose local websites to the internet without port forwarding.</p>
<form method="post">
<input type="hidden" name="action" value="tunnel_install">
<button type="submit" class="btn-form">Install Cloudflare Tunnel</button>
</form>
</div>

<?php elseif (!$tunnelAuthenticated): ?>
<div class="section">
<h2>Cloudflare Authentication</h2>
<p style="color:#64748b;font-size:13px;margin-bottom:16px">Authenticate with your Cloudflare account to create and manage tunnels.</p>

<?php if ($tunnelLoginStatus === 'pending'): ?>
<div style="background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.2);border-radius:12px;padding:16px;margin-bottom:16px">
<p style="color:#f59e0b;font-size:13px;font-weight:600;margin-bottom:8px">Authentication in Progress</p>
<?php if ($tunnelLoginUrl): ?>
<p style="color:#94a3b8;font-size:12px;margin-bottom:8px">Open this URL in a browser to authenticate with Cloudflare:</p>
<div style="background:rgba(15,23,42,.5);padding:10px 14px;border-radius:8px;word-break:break-all;font-size:12px;color:#60a5fa;font-family:monospace;margin-bottom:8px"><?= htmlspecialchars($tunnelLoginUrl) ?></div>
<?php endif; ?>
<p style="color:#64748b;font-size:12px">After authenticating, click "Check Status" below.</p>
<form method="post" style="margin-top:10px">
<input type="hidden" name="action" value="tunnel_login">
<button type="submit" class="btn-warning">Check Authentication Status</button>
</form>
</div>
<?php else: ?>
<form method="post">
<input type="hidden" name="action" value="tunnel_login">
<button type="submit" class="btn-form">Login to Cloudflare</button>
</form>
<?php endif; ?>

</div>

<?php else: ?>

<div class="section">
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
<h2 style="margin:0">Authentication</h2>
<form method="post" style="display:inline">
<input type="hidden" name="action" value="tunnel_logout">
<button type="submit" class="btn-danger" onclick="return confirm('Logout from Cloudflare? This will remove stored credentials.')">Logout</button>
</form>
</div>
<p style="color:#22c55e;font-size:13px;margin-top:8px">✓ Authenticated with Cloudflare</p>
</div>

<div class="section">
<h2>Tunnel Management</h2>

<?php if (!empty($tunnelList)): ?>
<h3>Existing Tunnels</h3>
<?php foreach ($tunnelList as $t): $isActive = ($t['id'] === $tunnelId); ?>
<div class="domain-item">
<div>
<div class="site-name"><?= htmlspecialchars($t['name']) ?></div>
<div style="font-size:11px;color:#64748b;margin-top:2px">ID: <?= htmlspecialchars(substr($t['id'], 0, 8)) ?>... | Status: <?= htmlspecialchars($t['status'] ?: 'unknown') ?></div>
</div>
<div class="actions">
<?php if ($isActive): ?>
<span class="badge-enable on">Active</span>
<?php else: ?>
<form method="post" style="display:inline">
<input type="hidden" name="action" value="tunnel_select">
<input type="hidden" name="tunnel_id" value="<?= htmlspecialchars($t['id']) ?>">
<input type="hidden" name="tunnel_name" value="<?= htmlspecialchars($t['name']) ?>">
<button type="submit" class="btn-ok">Select</button>
</form>
<?php endif; ?>
<form method="post" style="display:inline" onsubmit="return confirm('Delete tunnel &#39;<?= htmlspecialchars($t['name']) ?>&#39;?')">
<input type="hidden" name="action" value="tunnel_delete">
<input type="hidden" name="tunnel_id" value="<?= htmlspecialchars($t['id']) ?>">
<button type="submit" class="btn-danger">Delete</button>
</form>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<h3>Create New Tunnel</h3>
<div style="background:rgba(15,23,42,.4);padding:16px;border-radius:12px">
<form method="post" class="form-row">
<input type="hidden" name="action" value="tunnel_create">
<input type="text" name="tunnel_name" placeholder="Tunnel name (e.g., my-server)" required pattern="[a-zA-Z0-9_-]+" title="Letters, numbers, hyphens, underscores">
<button type="submit" class="btn-form">Create Tunnel</button>
</form>
</div>
</div>

<?php if ($tunnelId): $ts = $tunnelStatus; ?>
<div class="section">
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
<h2 style="margin:0">Tunnel: <?= htmlspecialchars($tunnelName ?: $tunnelId) ?></h2>
<span class="badge <?= $ts['running'] ? 'on' : 'off' ?>"><span class="dot"></span><?= $ts['running'] ? 'Running' : 'Stopped' ?></span>
</div>
<div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap">
<form method="post">
<input type="hidden" name="action" value="tunnel_start">
<button type="submit" class="btn-start" style="padding:10px 22px;font-size:13px" <?= $ts['running'] ? 'disabled' : '' ?>>Start</button>
</form>
<form method="post">
<input type="hidden" name="action" value="tunnel_stop">
<button type="submit" class="btn-stop" style="padding:10px 22px;font-size:13px" onclick="return confirm('Stop tunnel?')" <?= !$ts['running'] ? 'disabled' : '' ?>>Stop</button>
</form>
<form method="post">
<input type="hidden" name="action" value="tunnel_restart">
<button type="submit" class="btn-restart" style="padding:10px 22px;font-size:13px" onclick="return confirm('Restart tunnel?')">Restart</button>
</form>
</div>
<?php if ($ts['running'] && !empty($ts['urls'])): ?>
<div style="margin-top:14px">
<h3 style="font-size:13px;color:#94a3b8;margin-bottom:8px">Public URLs</h3>
<?php foreach ($ts['urls'] as $url): ?>
<div style="display:flex;align-items:center;gap:8px;background:rgba(15,23,42,.4);padding:10px 14px;border-radius:8px;margin-bottom:6px">
<a href="https://<?= htmlspecialchars($url) ?>" target="_blank" style="color:#60a5fa;font-size:13px;font-weight:600;text-decoration:none;flex:1"><?= htmlspecialchars($url) ?></a>
<button onclick="copyUrl('<?= htmlspecialchars($url) ?>')" style="background:rgba(59,130,246,.12);color:#60a5fa;border:1px solid rgba(59,130,246,.2);border-radius:6px;padding:4px 10px;cursor:pointer;font-size:11px;font-weight:600;font-family:inherit">Copy URL</button>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<div class="section">
<h2>Hostname Mapping</h2>
<p style="color:#64748b;font-size:12px;margin-bottom:14px">Map a public hostname to a local website. Requires the domain to be in your Cloudflare account.</p>

<?php if (!empty($tunnelHostnames)): ?>
<h3>Mapped Hostnames</h3>
<?php foreach ($tunnelHostnames as $hostname => $target): ?>
<div class="domain-item">
<div>
<div class="site-name"><?= htmlspecialchars($hostname) ?></div>
<div style="font-size:11px;color:#64748b;margin-top:2px">→ <?= htmlspecialchars($target) ?></div>
</div>
<form method="post" style="display:inline" onsubmit="return confirm('Remove hostname <?= htmlspecialchars($hostname) ?>?')">
<input type="hidden" name="action" value="tunnel_remove_hostname">
<input type="hidden" name="hostname" value="<?= htmlspecialchars($hostname) ?>">
<button type="submit" class="btn-danger">Remove</button>
</form>
</div>
<?php endforeach; ?>
<?php endif; ?>

<h3>Add Hostname</h3>
<div style="background:rgba(15,23,42,.4);padding:16px;border-radius:12px">
<form method="post" class="form-row-3">
<input type="hidden" name="action" value="tunnel_add_hostname">
<input type="text" name="hostname" placeholder="e.g., app.mydomain.com" required>
<input type="text" name="target" placeholder="e.g., http://127.0.0.1:8080" value="http://127.0.0.1:8080">
<button type="submit" class="btn-form">Add Hostname</button>
</form>
<div style="margin-top:6px;font-size:11px;color:#475569">Target should be the local URL of your website (e.g., http://127.0.0.1:8080)</div>
</div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php if ($tunnelInstalled && $tunnelAuthenticated): ?>
<div class="section">
<h2>Auto-Start</h2>
<form method="post">
<input type="hidden" name="action" value="tunnel_set_autostart">
<label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px;color:#e2e8f0">
<input type="checkbox" name="auto_start" value="1" <?= $tunnelAutoStart ? 'checked' : '' ?> onchange="this.form.submit()" style="width:18px;height:18px;accent-color:#3b82f6;cursor:pointer">
Automatically start tunnel when Mobile Server starts
</label>
</form>
</div>

<div class="section">
<h2>Tunnel Logs</h2>
<div class="logs-header" style="margin-bottom:12px">
<div style="display:flex;gap:8px">
<form method="get">
<input type="hidden" name="tab" value="cloudflare">
<input type="hidden" name="refresh" value="1">
<button type="submit" class="btn-warning" style="padding:8px 18px;font-size:12px">Refresh</button>
</form>
<form method="post">
<input type="hidden" name="action" value="tunnel_clear_logs">
<button type="submit" class="btn-danger" style="padding:8px 18px;font-size:12px" onclick="return confirm('Clear tunnel logs?')">Clear</button>
</form>
<a href="<?= htmlspecialchars($tunnelManager->getLogPath()) ?>" download style="padding:8px 18px;background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.2);border-radius:8px;text-decoration:none;font-size:12px;font-weight:600">Download</a>
</div>
</div>
<pre><?php
$logContent = $tunnelManager->logs(LOG_MAX_LINES);
echo htmlspecialchars($logContent ?: 'Log is empty');
?></pre>
</div>

<div class="section">
<h2>Health Status</h2>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
<div style="background:rgba(15,23,42,.5);border:1px solid rgba(148,163,184,.06);padding:14px;border-radius:12px">
<div style="font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.8px;font-weight:600">Tunnel Status</div>
<div style="font-size:14px;font-weight:600;margin-top:5px;color:<?= $tunnelHealth['status'] === 'running' ? '#22c55e' : '#ef4444' ?>"><?= $tunnelHealth['status'] === 'running' ? '✓ Running' : ($tunnelHealth['status'] === 'stopped' ? '✗ Stopped' : 'N/A') ?></div>
</div>
<?php if (!empty($tunnelHealth['issues'])): ?>
<div style="background:rgba(15,23,42,.5);border:1px solid rgba(239,68,68,.2);padding:14px;border-radius:12px;grid-column:1/-1">
<div style="font-size:10px;color:#ef4444;text-transform:uppercase;letter-spacing:.8px;font-weight:600">Issues</div>
<?php foreach ($tunnelHealth['issues'] as $issue): ?>
<div style="font-size:13px;color:#ef4444;margin-top:5px">• <?= htmlspecialchars($issue) ?></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</div>
<?php endif; ?>
