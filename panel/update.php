<div class="section">
<h2>System Update</h2>
<p style="color:#64748b;font-size:13px;margin-bottom:16px">Update your Mobile Server installation from the latest GitHub source. This will download and replace core files while preserving your sites, databases, and configuration.</p>
<div style="background:rgba(15,23,42,.4);padding:16px;border-radius:12px;margin-bottom:16px">
<h3 style="font-size:14px;font-weight:600;margin-bottom:8px;color:#e2e8f0">Files to Update</h3>
<ul style="color:#94a3b8;font-size:13px;line-height:1.8;padding-left:20px">
<li>index.php — public status dashboard</li>
<li>control.php — admin control panel</li>
<li>elfinder/panel.php — file manager UI</li>
<li>elfinder/connector.php — file manager API</li>
<li>lib/TunnelProvider.php — tunnel interface</li>
<li>lib/CloudflareTunnelProvider.php — Cloudflare tunnel provider</li>
<li>lib/TunnelManager.php — tunnel manager</li>
<li>install.sh — installation script</li>
</ul>
</div>
<div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.15);border-radius:12px;padding:14px;margin-bottom:16px">
<p style="color:#f59e0b;font-size:12px;font-weight:500">⚠ Your sites, databases, tunnel config, and other data will not be affected.</p>
</div>
<form method="post" onsubmit="return confirm('Update system files from GitHub? This will overwrite core files.')">
<input type="hidden" name="action" value="update_system">
<button type="submit" class="btn-form">Update from GitHub</button>
</form>
</div>
