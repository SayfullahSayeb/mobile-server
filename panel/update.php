<div class="sec">
  <div class="st"><i class="fas fa-sync-alt"></i> System Update</div>
  <div class="st3">Update your Mobile Server installation from the latest GitHub source. This will download and replace core files while preserving your sites, databases, and configuration.</div>
  <div style="background:rgba(15,23,42,.4);padding:14px;border-radius:var(--rs);margin-bottom:14px">
    <div class="st2" style="color:var(--text);margin-top:0">Files to Update</div>
    <ul style="color:var(--text2);font-size:12px;line-height:1.8;padding-left:18px">
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
  <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.15);border-radius:var(--rs);padding:12px;margin-bottom:14px">
    <div style="color:var(--orange);font-size:11px;font-weight:500"><i class="fas fa-exclamation-triangle"></i> Your sites, databases, tunnel config, and other data will not be affected.</div>
  </div>
  <form method="post" onsubmit="return confirm('Update system files from GitHub? This will overwrite core files.')">
    <?= csrf() ?>
    <input type="hidden" name="action" value="update_system">
    <button type="submit" class="btn btn-p btn-l"><i class="fas fa-download"></i> Update from GitHub</button>
  </form>
</div>
