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
<div class="sec">
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0">Manage Sites</div>
    <span class="ts tm"><?= count($allSites) ?> site<?= count($allSites) !== 1 ? 's' : '' ?></span>
  </div>
  <?php if (!empty($allSites)): ?>
  <?php foreach ($allSites as $name => $site): ?>
  <div class="di">
    <div>
      <div class="sn"><?= htmlspecialchars($name) ?></div>
      <div class="sm">
        <?php if (!empty($site['domain']) && $site['port']): ?>
        <a href="http://<?= htmlspecialchars($site['domain']) ?>:<?= $site['port'] ?>" target="_blank" class="sd"><?= htmlspecialchars($site['domain']) ?>:<?= $site['port'] ?></a>
        <?php else: ?>
        <a href="/<?= urlencode($name) ?>" target="_blank" class="sd" style="color:var(--text2)">/<?= htmlspecialchars($name) ?></a>
        <?php endif; ?>
        <span class="sp"><?= htmlspecialchars($site['path']) ?></span>
        <?php if (!empty($site['legacy'])): ?><span class="ts tm">(legacy)</span><?php endif; ?>
      </div>
    </div>
    <div class="df ac g2 fw">
      <?php if (!empty($site['domain']) && $site['port']): ?>
      <span class="be <?= $site['enabled'] ? 'on' : 'off' ?>"><?= $site['enabled'] ? 'Enabled' : 'Disabled' ?></span>
      <form method="post" style="display:inline">
        <input type="hidden" name="action" value="toggle_site">
        <input type="hidden" name="site_name" value="<?= htmlspecialchars($name) ?>">
        <button type="submit" class="btn btn-w btn-s"><?= $site['enabled'] ? 'Disable' : 'Enable' ?></button>
      </form>
      <?php endif; ?>
      <form method="post" style="display:inline" onsubmit="return confirm('Delete site &#39;<?= htmlspecialchars($name) ?>&#39; and all its files?')">
        <input type="hidden" name="action" value="delete_site">
        <input type="hidden" name="site_name" value="<?= htmlspecialchars($name) ?>">
        <button type="submit" class="btn btn-d btn-s">🗑 Delete</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php else: ?>
  <div class="tm ts" style="padding:10px 0">No sites yet. Create one below.</div>
  <?php endif; ?>
</div>

<div class="sec">
  <div class="st">Create New Site</div>
  <div class="st3">Creates a site with its own domain and port (like Laragon). Access via <strong>http://domain:port</strong></div>
  <form method="post" class="fr3">
    <input type="hidden" name="action" value="create_site">
    <input type="text" name="site_name" class="inp" placeholder="Site name (e.g. myapp)" required pattern="[a-z0-9_-]+" title="Letters, numbers, hyphens, underscores only">
    <input type="text" name="site_domain" class="inp" placeholder="Domain (e.g. myapp.test)" value="">
    <button type="submit" class="btn btn-p btn-l">Create Site</button>
  </form>
  <div class="fh">Domain defaults to <strong>sitename.test</strong> if left empty</div>
</div>

<div class="sec">
  <div class="st">Nginx &amp; Hosts</div>
  <div class="df g2 fw">
    <form method="post">
      <input type="hidden" name="action" value="restart_nginx">
      <button type="submit" class="btn btn-p btn-l">↻ Restart Nginx</button>
    </form>
    <form method="post">
      <input type="hidden" name="action" value="update_hosts">
      <button type="submit" class="btn btn-w btn-l">🖊 Update Hosts File</button>
    </form>
  </div>
  <div class="mt2 ts tm" style="line-height:1.6">
    <strong>Note:</strong> To use custom domains locally, add these entries to your device's hosts file (requires root on Android):<br>
    <code style="background:rgba(15,23,42,.6);padding:7px 10px;border-radius:5px;display:inline-block;margin-top:5px;color:var(--text2)">
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
