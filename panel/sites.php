<?php
$config = getSitesConfig();
$allSites = [];
$dirs = glob(SITES_DIR . '/*', GLOB_ONLYDIR);
if ($dirs) {
    foreach ($dirs as $d) {
        $name = basename($d);
        $publicHtml = $d . '/public_html';
        if (is_dir($publicHtml)) {
            $allSites[$name] = [
                'name' => $name,
                'domain' => $config[$name]['domain'] ?? ($name . '.test'),
                'port' => $config[$name]['port'] ?? 0,
                'path' => $publicHtml,
                'enabled' => $config[$name]['enabled'] ?? true,
                'created' => $config[$name]['created'] ?? ''
            ];
        }
    }
}
$legacy = glob(DEFAULT_SITE_DIR . '/*', GLOB_ONLYDIR);
if ($legacy) {
    foreach ($legacy as $d) {
        $name = basename($d);
        if (!isset($allSites[$name])) {
            $allSites[$name] = [
                'name' => $name,
                'domain' => '',
                'port' => 0,
                'path' => $d,
                'enabled' => true,
                'created' => '',
                'legacy' => true
            ];
        }
    }
}
ksort($allSites);
?>
<div class="section">
<h2 style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
<span>Manage Sites</span>
<span style="font-size:12px;font-weight:400;color:#64748b"><?= count($allSites) ?> site<?= count($allSites) !== 1 ? 's' : '' ?></span>
</h2>
<?php if (!empty($allSites)): ?>
<?php foreach ($allSites as $name => $site): ?>
<div class="domain-item">
<div>
<div class="site-name"><?= htmlspecialchars($name) ?></div>
<div class="site-meta">
<?php if (!empty($site['domain']) && $site['port']): ?>
<a href="http://<?= htmlspecialchars($site['domain']) ?>:<?= $site['port'] ?>" target="_blank" class="site-domain"><?= htmlspecialchars($site['domain']) ?>:<?= $site['port'] ?></a>
<?php else: ?>
<a href="/<?= urlencode($name) ?>" target="_blank" class="site-domain" style="color:#94a3b8">/<?= htmlspecialchars($name) ?></a>
<?php endif; ?>
<span class="site-port"><?= htmlspecialchars($site['path']) ?></span>
<?php if (!empty($site['legacy'])): ?><span style="font-size:11px;color:#64748b">(legacy)</span><?php endif; ?>
</div>
</div>
<div class="actions">
<?php if (!empty($site['domain']) && $site['port']): ?>
<span class="badge-enable <?= $site['enabled'] ? 'on' : 'off' ?>"><?= $site['enabled'] ? 'Enabled' : 'Disabled' ?></span>
<form method="post" style="display:inline">
<input type="hidden" name="action" value="toggle_site">
<input type="hidden" name="site_name" value="<?= htmlspecialchars($name) ?>">
<button type="submit" class="btn-warning"><?= $site['enabled'] ? 'Disable' : 'Enable' ?></button>
</form>
<?php endif; ?>
<form method="post" style="display:inline" onsubmit="return confirm('Delete site &#39;<?= htmlspecialchars($name) ?>&#39; and all its files?')">
<input type="hidden" name="action" value="delete_site">
<input type="hidden" name="site_name" value="<?= htmlspecialchars($name) ?>">
<button type="submit" class="btn-danger">Delete</button>
</form>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<p style="color:#64748b;font-size:14px;padding:12px 0">No sites yet. Create one below.</p>
<?php endif; ?>
</div>
<div class="section">
<h2>Create New Site</h2>
<p style="color:#64748b;font-size:13px;margin-bottom:14px">Creates a site with its own domain and port (like Laragon). Access via <strong>http://domain:port</strong></p>
<form method="post" class="form-row-3">
<input type="hidden" name="action" value="create_site">
<input type="text" name="site_name" placeholder="Site name (e.g. myapp)" required pattern="[a-z0-9_-]+" title="Letters, numbers, hyphens, underscores only">
<input type="text" name="site_domain" placeholder="Domain (e.g. myapp.test)" value="">
<button type="submit" class="btn-form">Create Site</button>
</form>
<div style="margin-top:6px;font-size:11px;color:#475569">Domain defaults to <strong>sitename.test</strong> if left empty</div>
</div>
<div class="section">
<h2>Nginx &amp; Hosts</h2>
<div style="display:flex;gap:8px;flex-wrap:wrap">
<form method="post">
<input type="hidden" name="action" value="restart_nginx">
<button type="submit" class="btn-form">Restart Nginx</button>
</form>
<form method="post">
<input type="hidden" name="action" value="update_hosts">
<button type="submit" class="btn-warning">Update Hosts File</button>
</form>
</div>
<div style="margin-top:12px;font-size:12px;color:#64748b;line-height:1.6">
<strong>Note:</strong> To use custom domains locally, add these entries to your device's hosts file (requires root on Android):<br>
<code style="background:rgba(15,23,42,.6);padding:8px 12px;border-radius:6px;display:inline-block;margin-top:6px;color:#94a3b8">
<?php
foreach ($allSites as $s) {
    if (!empty($s['domain']) && $s['port']) {
        echo htmlspecialchars("{$ip_addr} {$s['domain']} www.{$s['domain']}\n");
    }
}
?>
</code>
</div>
</div>
