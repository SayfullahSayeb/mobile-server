<?php
$config = getSitesConfig();
$allSites = [];
$seen = [];
$dirs = glob(SITES_DIR . '/*', GLOB_ONLYDIR);
$exclude = ['default', 'filemanager', 'lib', 'panel'];
if ($dirs) {
    foreach ($dirs as $d) {
        $name = basename($d);
        if (in_array($name, $exclude)) continue;
        $publicHtml = $d . '/public_html';
        if (is_dir($publicHtml)) {
            $seen[$name] = true;
            $allSites[$name] = [
                'name' => $name,
                'domain' => $config[$name]['domain'] ?? ($name . '.test'),
                'port' => $config[$name]['port'] ?? 0,
                'path' => $publicHtml,
                'enabled' => $config[$name]['enabled'] ?? true,
                'type' => $config[$name]['type'] ?? 'static',
                'created' => $config[$name]['created'] ?? ''
            ];
        }
    }
}
// Include sites from config even if their directories are missing
foreach ($config as $name => $site) {
    if (!isset($seen[$name])) {
        $allSites[$name] = [
            'name' => $name,
            'domain' => $site['domain'] ?? ($name . '.test'),
            'port' => $site['port'] ?? 0,
            'path' => $site['path'] ?? '',
            'enabled' => $site['enabled'] ?? true,
            'type' => $site['type'] ?? 'static',
            'created' => $site['created'] ?? ''
        ];
    }
}
ksort($allSites);
?>
<div class="sec">
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0">Manage Sites</div>
    <div class="df ac g2 fw">
      <span class="ts tm"><?= count($allSites) ?> site<?= count($allSites) !== 1 ? 's' : '' ?></span>
      <?php if (!empty($ip_addr) && $ip_addr !== 'N/A'): ?>
      <span class="ts tm" style="color:var(--text2)"><i class="fas fa-network-wired"></i> <?= htmlspecialchars($ip_addr) ?></span>
      <?php endif; ?>
      <button class="btn btn-p btn-l" onclick="document.getElementById('siteModal').classList.add('show')"><i class="fas fa-plus"></i> Add New</button>
    </div>
  </div>
  <?php if (!empty($allSites)): ?>
  <div class="tb-wrap">
  <table class="stbl">
    <thead>
      <tr>
        <th>Name</th>
        <th>URL</th>
        <th>Type</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($allSites as $name => $site): ?>
      <tr>
        <td class="td-name"><?= htmlspecialchars($name) ?></td>
        <td>
          <?php if (!empty($site['port'])): ?>
          <a href="http://<?= htmlspecialchars($ip_addr) ?>:<?= $site['port'] ?>" target="_blank" class="sd"><?= htmlspecialchars($ip_addr) ?>:<?= $site['port'] ?></a>
          <?php else: ?>
          <span class="tm ts">/<?= htmlspecialchars($name) ?></span>
          <?php endif; ?>
        </td>
        <td><span class="ty <?= $site['type'] ?>"><?= $site['type'] === 'wordpress' ? 'WordPress' : 'Static' ?></span></td>
        <td><span class="be <?= $site['enabled'] ? 'on' : 'off' ?>"><?= $site['enabled'] ? 'Enabled' : 'Disabled' ?></span></td>
        <td class="td-actions">
          <form method="post" style="display:inline">
            <?= csrf() ?>
            <input type="hidden" name="action" value="toggle_site">
            <input type="hidden" name="site_name" value="<?= htmlspecialchars($name) ?>">
            <button type="submit" class="btn btn-s <?= $site['enabled'] ? 'btn-w' : 'btn-o' ?>" title="<?= $site['enabled'] ? 'Disable' : 'Enable' ?>"><i class="fas <?= $site['enabled'] ? 'fa-pause' : 'fa-play' ?>"></i></button>
          </form>
          <button class="btn btn-s btn-w" onclick="editSite('<?= htmlspecialchars($name, ENT_QUOTES) ?>', '<?= htmlspecialchars($site['domain'], ENT_QUOTES) ?>', '<?= $site['type'] ?>')" title="Edit"><i class="fas fa-edit"></i></button>
          <button type="button" class="btn btn-s btn-d" title="Delete" onclick="showDeleteModal('<?= htmlspecialchars($name, ENT_QUOTES) ?>')"><i class="fas fa-trash-alt"></i></button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
  <div class="tm ts" style="padding:10px 0">No sites yet. Click "Add New" to create one.</div>
  <?php endif; ?>
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

<div id="siteModal" class="modal">
  <div class="modal-bg" onclick="closeModal()"></div>
  <div class="modal-content">
    <div class="modal-header">
      <span class="modal-title" id="modalTitle">Add New Site</span>
      <span class="modal-close" onclick="closeModal()">&times;</span>
    </div>
    <form method="post" id="siteForm" onsubmit="return submitSiteForm(this)">
      <?= csrf() ?>
      <input type="hidden" name="action" id="formAction" value="create_site">
      <input type="hidden" name="site_name_orig" id="siteNameOrig" value="">
      <div class="st2" style="margin:0 0 8px">Site Type</div>
      <div class="df ac g3 mb1" id="siteTypeGroup">
        <label class="rl" style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;padding:6px 10px;background:rgba(15,23,42,.4);border:1px solid rgba(148,163,184,.08);border-radius:var(--rs);transition:all .15s">
          <input type="radio" name="site_type" value="static" checked onchange="toggleWpFields()" style="accent-color:var(--blue);width:15px;height:15px;cursor:pointer">
          <span>Static Site</span>
        </label>
        <label class="rl" style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;padding:6px 10px;background:rgba(15,23,42,.4);border:1px solid rgba(148,163,184,.08);border-radius:var(--rs);transition:all .15s">
          <input type="radio" name="site_type" value="wordpress" onchange="toggleWpFields()" style="accent-color:var(--blue);width:15px;height:15px;cursor:pointer">
          <span>WordPress Site</span>
        </label>
      </div>
      <input type="text" name="site_name" id="siteName" class="inp" placeholder="Site name (e.g. myapp)" required pattern="[a-z0-9_-]+" title="Letters, numbers, hyphens, underscores only">
      <input type="hidden" name="site_domain" id="siteDomain" value="">
      <div id="wpFields" class="wp-fields" style="display:none">
        <div class="st2" style="margin:6px 0 10px">WordPress Configuration</div>
        <div class="fr2">
          <input type="text" name="wp_user" id="wpUser" class="inp" placeholder="Admin username" value="admin">
          <input type="password" name="wp_pass" id="wpPass" class="inp" placeholder="Admin password">
        </div>
        <input type="email" name="wp_email" id="wpEmail" class="inp" placeholder="Admin email" value="admin@localhost.local">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-d" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-p" id="submitBtn">Create Site</button>
      </div>
    </form>
  </div>
</div>

<div id="progressModal" class="modal">
  <div class="modal-bg"></div>
  <div class="modal-content" style="max-width:420px;text-align:center">
    <div class="modal-header" style="border:none;padding-bottom:0">
      <span class="modal-title" id="progressTitle"><i class="fas fa-cog fa-spin"></i> Creating Site...</span>
    </div>
    <div style="margin:16px 0 8px;text-align:left;font-size:13px;color:var(--text2)" id="progressStatus">Preparing...</div>
    <div style="height:6px;background:rgba(51,65,85,.4);border-radius:4px;overflow:hidden;margin-bottom:4px">
      <div id="progressBar" style="height:100%;width:0%;background:linear-gradient(90deg,#3b82f6,#22c55e);border-radius:4px;transition:width .4s"></div>
    </div>
    <div style="font-size:11px;color:var(--text3);text-align:right" id="progressPct">0%</div>
  </div>
</div>

<div id="deleteModal" class="modal">
  <div class="modal-bg" onclick="closeDeleteModal()"></div>
  <div class="modal-content" style="max-width:400px">
    <div class="modal-header">
      <span class="modal-title">Delete Site</span>
      <span class="modal-close" onclick="closeDeleteModal()">&times;</span>
    </div>
    <form method="post" id="deleteForm">
      <?= csrf() ?>
      <input type="hidden" name="action" value="delete_site">
      <input type="hidden" name="site_name" id="deleteSiteName" value="">
      <p style="margin:0 0 12px;font-size:14px;color:var(--text2)" id="deleteSiteLabel"></p>
      <label class="rl" style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;padding:8px 10px;background:rgba(15,23,42,.4);border:1px solid rgba(148,163,184,.08);border-radius:var(--rs);transition:all .15s">
        <input type="checkbox" name="delete_files" value="1" checked id="deleteFilesCheck" style="accent-color:var(--blue);width:15px;height:15px;cursor:pointer">
        <span>Also delete site files (irreversible)</span>
      </label>
      <div class="modal-footer" style="margin-top:14px">
        <button type="button" class="btn btn-d" onclick="closeDeleteModal()">Cancel</button>
        <button type="submit" class="btn btn-d" style="background:var(--red);color:#fff">Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleWpFields() {
  var type = document.querySelector('input[name="site_type"]:checked').value;
  var wpFields = document.getElementById('wpFields');
  wpFields.style.display = type === 'wordpress' ? 'block' : 'none';
  document.getElementById('wpPass').required = type === 'wordpress';
}
function closeModal() {
  document.getElementById('siteModal').classList.remove('show');
  document.getElementById('siteForm').reset();
  document.getElementById('wpFields').style.display = 'none';
  document.getElementById('formAction').value = 'create_site';
  document.getElementById('modalTitle').textContent = 'Add New Site';
  document.getElementById('submitBtn').textContent = 'Create Site';
  document.getElementById('siteName').disabled = false;
  document.getElementById('siteNameOrig').value = '';
  document.querySelector('input[name="site_type"][value="static"]').checked = true;
}
function showDeleteModal(name) {
  document.getElementById('deleteSiteName').value = name;
  document.getElementById('deleteSiteLabel').textContent = 'Delete "' + name + '"?';
  document.getElementById('deleteFilesCheck').checked = true;
  document.getElementById('deleteModal').classList.add('show');
}
function closeDeleteModal() {
  document.getElementById('deleteModal').classList.remove('show');
}
function editSite(name, domain, type) {
  document.getElementById('formAction').value = 'edit_site';
  document.getElementById('modalTitle').textContent = 'Edit Site: ' + name;
  document.getElementById('submitBtn').textContent = 'Update Site';
  document.getElementById('siteName').value = name;
  document.getElementById('siteNameOrig').value = name;
  document.getElementById('siteDomain').value = domain === '' ? '' : domain;
  var radio = document.querySelector('input[name="site_type"][value="' + (type || 'static') + '"]');
  if (radio) radio.checked = true;
  toggleWpFields();
  document.getElementById('siteModal').classList.add('show');
}
function submitSiteForm(form) {
  var type = document.querySelector('input[name="site_type"]:checked').value;
  var isWp = type === 'wordpress';

  if (isWp && !document.getElementById('wpPass').value) {
    alert('Admin password is required for WordPress.');
    return false;
  }

  var data = new FormData(form);
  var modal = document.getElementById('progressModal');
  var bar = document.getElementById('progressBar');
  var status = document.getElementById('progressStatus');
  var pct = document.getElementById('progressPct');

  modal.classList.add('show');
  document.getElementById('progressTitle').innerHTML = '<i class="fas fa-cog fa-spin"></i> Creating ' + (isWp ? 'WordPress' : 'Static') + ' Site...';
  document.getElementById('submitBtn').disabled = true;

  // Animate bar while waiting
  var anim = 5;
  var animInterval = setInterval(function() {
    if (anim < 90) {
      anim += Math.random() * 8;
      if (anim > 90) anim = 90;
      bar.style.width = anim + '%';
      pct.textContent = Math.round(anim) + '%';
    }
  }, 1000);

  status.textContent = isWp ? 'Downloading & installing WordPress...' : 'Creating site...';

  fetch(window.location.href, {
    method: 'POST',
    body: data
  }).then(function(r) { return r.text(); }).then(function() {
    clearInterval(animInterval);
    bar.style.width = '100%';
    pct.textContent = '100%';
    status.textContent = 'Done! Reloading...';
    setTimeout(function() { location.reload(); }, 600);
  }).catch(function(err) {
    clearInterval(animInterval);
    status.textContent = 'Error: ' + err;
    document.getElementById('submitBtn').disabled = false;
  });

  return false;
}
</script>
