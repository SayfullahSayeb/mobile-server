<div class="top-bar">
<form method="post"><input type="hidden" name="action" value="restart_all"><button type="submit" onclick="return confirm('Restart all services?')">Restart All</button></form>
</div>
<div class="grid">
<?php foreach ($services as $name => $s): $is_running = $status[$name]; ?>
<div class="card">
<div class="card-top">
<h2><?= htmlspecialchars($name) ?></h2>
<span class="badge <?= $is_running ? 'on' : 'off' ?>"><span class="dot"></span><?= $is_running ? 'Running' : 'Stopped' ?></span>
</div>
<div class="actions">
<?php if (!$is_running): ?>
<form method="post"><input type="hidden" name="action" value="start"><input type="hidden" name="service" value="<?= $name ?>"><button type="submit" class="btn-start">Start</button></form>
<?php else: ?>
<form method="post"><input type="hidden" name="action" value="stop"><input type="hidden" name="service" value="<?= $name ?>"><button type="submit" class="btn-stop" onclick="return confirm('Stop <?= $name ?>?')">Stop</button></form>
<?php endif; ?>
<form method="post"><input type="hidden" name="action" value="restart"><input type="hidden" name="service" value="<?= $name ?>"><button type="submit" class="btn-restart" onclick="return confirm('Restart <?= $name ?>?')">Restart</button></form>
</div>
</div>
<?php endforeach; ?>
</div>

<?php if ($tunnelInstalled && $tunnelAuthenticated && $tunnelManager->hasActiveTunnel()): $ts = $tunnelStatus; ?>
<div class="section" style="margin-bottom:20px">
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
<div style="display:flex;align-items:center;gap:12px">
<h2 style="margin:0">Cloudflare Tunnel</h2>
<span class="badge <?= $ts['running'] ? 'on' : 'off' ?>"><span class="dot"></span><?= $ts['running'] ? 'Running' : 'Stopped' ?></span>
</div>
<div class="actions" style="flex-wrap:wrap">
<form method="post" style="display:inline">
<input type="hidden" name="action" value="tunnel_start">
<button type="submit" class="btn-start" style="padding:8px 18px;font-size:12px" <?= $ts['running'] ? 'disabled' : '' ?>>Start</button>
</form>
<form method="post" style="display:inline">
<input type="hidden" name="action" value="tunnel_stop">
<button type="submit" class="btn-stop" style="padding:8px 18px;font-size:12px" onclick="return confirm('Stop tunnel?')" <?= !$ts['running'] ? 'disabled' : '' ?>>Stop</button>
</form>
<form method="post" style="display:inline">
<input type="hidden" name="action" value="tunnel_restart">
<button type="submit" class="btn-restart" style="padding:8px 18px;font-size:12px" onclick="return confirm('Restart tunnel?')">Restart</button>
</form>
<a href="?tab=cloudflare" style="padding:8px 18px;background:rgba(59,130,246,.12);color:#60a5fa;border:1px solid rgba(59,130,246,.2);border-radius:8px;text-decoration:none;font-size:12px;font-weight:600">Logs</a>
</div>
</div>
<?php if (!empty($ts['urls'])): ?>
<div style="margin-top:14px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
<?php foreach ($ts['urls'] as $url): ?>
<div style="background:rgba(15,23,42,.5);border:1px solid rgba(148,163,184,.06);padding:12px 16px;border-radius:12px">
<div style="font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.8px;font-weight:600;margin-bottom:4px">Public URL</div>
<div style="display:flex;align-items:center;gap:8px">
<a href="https://<?= htmlspecialchars($url) ?>" target="_blank" style="color:#60a5fa;font-size:13px;font-weight:600;text-decoration:none"><?= htmlspecialchars($url) ?></a>
<button onclick="copyUrl('<?= htmlspecialchars($url) ?>')" style="background:rgba(59,130,246,.12);color:#60a5fa;border:1px solid rgba(59,130,246,.2);border-radius:6px;padding:2px 8px;cursor:pointer;font-size:11px;font-weight:600;font-family:inherit">Copy</button>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-top:14px">
<div style="background:rgba(15,23,42,.5);border:1px solid rgba(148,163,184,.06);padding:12px;border-radius:12px">
<div style="font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.8px;font-weight:600">Tunnel ID</div>
<div style="font-size:12px;font-weight:600;margin-top:4px;word-break:break-all;color:#94a3b8"><?= htmlspecialchars($ts['tunnel_id']) ?></div>
</div>
<div style="background:rgba(15,23,42,.5);border:1px solid rgba(148,163,184,.06);padding:12px;border-radius:12px">
<div style="font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.8px;font-weight:600">Last Connected</div>
<div style="font-size:12px;font-weight:600;margin-top:4px;color:#94a3b8"><?= htmlspecialchars($ts['last_connected'] ?: 'N/A') ?></div>
</div>
</div>
<?php if (!empty($tunnelHealth['issues'])): ?>
<div style="margin-top:10px">
<?php foreach ($tunnelHealth['issues'] as $issue): ?>
<div style="background:rgba(239,68,68,.1);color:#ef4444;padding:8px 12px;border-radius:8px;font-size:12px;margin-bottom:4px"><?= htmlspecialchars($issue) ?></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<?php endif; ?>

<div class="info-grid">
<div class="info-item"><div class="label">Hostname</div><div class="value"><?= htmlspecialchars($hostname) ?></div></div>
<div class="info-item"><div class="label">IP Address</div><div class="value"><?= htmlspecialchars($ip_addr) ?></div></div>
<div class="info-item"><div class="label">Server Time</div><div class="value"><?= htmlspecialchars($server_time) ?></div></div>
<div class="info-item"><div class="label">Uptime</div><div class="value"><?= htmlspecialchars($uptime) ?></div></div>
<div class="info-item"><div class="label">PHP Version</div><div class="value"><?= htmlspecialchars($php_ver) ?></div></div>
<div class="info-item"><div class="label">Web Server</div><div class="value">Nginx :8080</div></div>
</div>
<div class="logs">
<div class="logs-header">
<h2>Log Viewer</h2>
<form method="get"><select name="log" onchange="this.form.submit()">
<option value="">Select a log...</option>
<?php foreach ($log_files as $name => $path): ?>
<option value="<?= $name ?>" <?= ($_GET['log'] ?? '') === $name ? 'selected' : '' ?>><?= ucfirst($name) ?>.log<?= $path ? '' : ' (empty)' ?></option>
<?php endforeach; ?>
</select></form>
</div>
<?php
if (isset($_GET['log']) && isset($log_files[$_GET['log']]) && $log_files[$_GET['log']]) {
    $lines = file($log_files[$_GET['log']]);
    $lines = $lines ? array_slice($lines, -LOG_MAX_LINES) : [];
    echo '<pre>' . ($lines ? htmlspecialchars(implode('', $lines)) : 'Log is empty') . '</pre>';
} elseif (isset($_GET['log'])) {
    echo '<pre>Log file not found</pre>';
} else {
    echo '<pre style="color:#475569">Select a log file above</pre>';
}
?>
</div>
