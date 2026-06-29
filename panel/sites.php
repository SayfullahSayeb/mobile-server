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
                'created' => $config[$name]['created'] ?? '',
                'db_name' => $config[$name]['db_name'] ?? '',
                'db_user' => $config[$name]['db_user'] ?? '',
                'db_pass' => $config[$name]['db_pass'] ?? '',
                'table_prefix' => $config[$name]['table_prefix'] ?? '',
                'status' => $config[$name]['status'] ?? '',
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
            'created' => $site['created'] ?? '',
            'db_name' => $site['db_name'] ?? '',
            'db_user' => $site['db_user'] ?? '',
            'db_pass' => $site['db_pass'] ?? '',
            'table_prefix' => $site['table_prefix'] ?? '',
            'status' => $site['status'] ?? '',
        ];
    }
}
ksort($allSites);
$cfTunnels = cfTunnelsLoad();
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
        <th>Tunnel</th>
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
        <td class="td-tunnel" id="cf-<?= htmlspecialchars($name) ?>">
          <?php if (isset($cfTunnels[$name])): $t = $cfTunnels[$name]; ?>
            <span class="cf-status" data-site="<?= htmlspecialchars($name) ?>">
              <span class="tm ts" style="color:var(--text3)"><i class="fas fa-spinner fa-pulse"></i> Starting...</span>
            </span>
            <form method="post" style="display:inline">
              <?= csrf() ?>
              <input type="hidden" name="action" value="cf_tunnel_stop">
              <input type="hidden" name="site" value="<?= htmlspecialchars($name) ?>">
              <button type="submit" class="btn btn-s btn-d" title="Stop Tunnel"><i class="fas fa-times"></i></button>
            </form>
          <?php else: ?>
            <form method="post" style="display:inline">
              <?= csrf() ?>
              <input type="hidden" name="action" value="cf_tunnel_start">
              <input type="hidden" name="site" value="<?= htmlspecialchars($name) ?>">
              <button type="submit" class="btn btn-s btn-p" title="Start Cloudflare Tunnel"><i class="fas fa-cloud"></i></button>
            </form>
          <?php endif; ?>
        </td>
        <td>
          <span class="be <?= $site['enabled'] ? 'on' : 'off' ?>"><?= $site['enabled'] ? 'Enabled' : 'Disabled' ?></span>
          <?php if ($site['type'] === 'wordpress'): ?>
          <span class="ts" style="display:block;color:var(--text2);font-size:11px;margin-top:2px"><?= $site['status'] === 'pending_setup' ? 'Pending Setup' : ($site['status'] ?: '') ?></span>
          <?php endif; ?>
        </td>
        <td class="td-actions">
          <form method="post" style="display:inline">
            <?= csrf() ?>
            <input type="hidden" name="action" value="toggle_site">
            <input type="hidden" name="site_name" value="<?= htmlspecialchars($name) ?>">
            <button type="submit" class="btn btn-s <?= $site['enabled'] ? 'btn-w' : 'btn-o' ?>" title="<?= $site['enabled'] ? 'Disable' : 'Enable' ?>"><i class="fas <?= $site['enabled'] ? 'fa-pause' : 'fa-play' ?>"></i></button>
          </form>
          <button class="btn btn-s btn-w" onclick="editSite('<?= htmlspecialchars($name, ENT_QUOTES) ?>', '<?= htmlspecialchars($site['domain'], ENT_QUOTES) ?>', '<?= $site['type'] ?>')" title="Edit"><i class="fas fa-edit"></i></button>
          <?php if ($site['type'] === 'wordpress' && $site['db_name']): ?>
          <button type="button" class="btn btn-s btn-p" title="DB Info" onclick="showDbInfo('<?= htmlspecialchars($name, ENT_QUOTES) ?>')"><i class="fas fa-database"></i></button>
          <?php endif; ?>
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
          <input type="radio" name="site_type" value="static" checked style="accent-color:var(--blue);width:15px;height:15px;cursor:pointer">
          <span>Static Site</span>
        </label>
        <label class="rl" style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;padding:6px 10px;background:rgba(15,23,42,.4);border:1px solid rgba(148,163,184,.08);border-radius:var(--rs);transition:all .15s">
          <input type="radio" name="site_type" value="wordpress" style="accent-color:var(--blue);width:15px;height:15px;cursor:pointer">
          <span>WordPress Site</span>
        </label>
      </div>
      <input type="text" name="site_name" id="siteName" class="inp" placeholder="Site name (e.g. myapp)" required pattern="[a-z0-9_-]+" title="Letters, numbers, hyphens, underscores only">
      <input type="hidden" name="site_domain" id="siteDomain" value="">
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

<div id="dbModal" class="modal">
  <div class="modal-bg" onclick="closeDbModal()"></div>
  <div class="modal-content" style="max-width:420px">
    <div class="modal-header">
      <span class="modal-title">Database Details</span>
      <span class="modal-close" onclick="closeDbModal()">&times;</span>
    </div>
    <div id="dbInfoContent" style="font-size:13px;line-height:1.6">
    </div>
    <div class="modal-footer" style="margin-top:14px">
      <button type="button" class="btn btn-d" onclick="closeDbModal()">Close</button>
    </div>
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
var siteCreds = <?= json_encode(array_map(function($s) {
    return ['db_name' => $s['db_name'], 'db_user' => $s['db_user'], 'db_pass' => $s['db_pass'], 'table_prefix' => $s['table_prefix']];
}, $allSites)) ?>;

function showDbInfo(name) {
  var c = siteCreds[name];
  if (!c) return;
  document.getElementById('dbInfoContent').innerHTML =
    '<div class="st2 mb1">Database: <span style="color:var(--text1);font-weight:400">' + c.db_name + '</span></div>' +
    '<div class="st2 mb1">User: <span style="color:var(--text1);font-weight:400">' + c.db_user + '</span></div>' +
    '<div class="st2 mb1">Password: <span style="color:var(--text1);font-weight:400;font-family:monospace">' + c.db_pass + '</span></div>' +
    '<div class="st2 mb1">Prefix: <span style="color:var(--text1);font-weight:400;font-family:monospace">' + c.table_prefix + '</span></div>' +
    '<div class="st2 mb1" style="margin-top:8px;color:var(--text3);font-size:11px">Host: localhost</div>';
  document.getElementById('dbModal').classList.add('show');
}
function closeDbModal() {
  document.getElementById('dbModal').classList.remove('show');
}
function closeModal() {
  document.getElementById('siteModal').classList.remove('show');
  document.getElementById('siteForm').reset();
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
  var siteName = document.getElementById('siteName').value;
  var data = new FormData(form);
  var modal = document.getElementById('progressModal');
  var bar = document.getElementById('progressBar');
  var status = document.getElementById('progressStatus');
  var pct = document.getElementById('progressPct');

  modal.classList.add('show');
  document.getElementById('progressTitle').innerHTML = '<i class="fas fa-cog fa-spin"></i> Creating ' + (isWp ? 'WordPress' : 'Static') + ' Site...';
  document.getElementById('submitBtn').disabled = true;

  status.textContent = isWp ? 'Preparing...' : 'Creating site...';

  // Poll real progress from server
  var pollInterval = null;
  if (isWp && siteName) {
    pollInterval = setInterval(function() {
      fetch('?wp_progress=' + encodeURIComponent(siteName))
        .then(function(r) { return r.json(); })
        .then(function(p) {
          if (p.step && p.step !== 'unknown') {
            status.textContent = p.step;
          }
          if (p.status === 'error') {
            status.textContent = p.step || 'Error during installation';
            document.getElementById('progressTitle').innerHTML = '<i class="fas fa-exclamation-triangle" style="color:var(--red)"></i> Installation Failed';
          }
          if (p.pct) {
            var pc = Math.min(parseInt(p.pct), 99);
            bar.style.width = pc + '%';
            pct.textContent = pc + '%';
          }
        }).catch(function() {});
    }, 1000);
  } else {
    // Static site: animate a simple progress
    var anim = 10;
    pollInterval = setInterval(function() {
      if (anim < 90) {
        anim += Math.random() * 12;
        if (anim > 90) anim = 90;
        bar.style.width = anim + '%';
        pct.textContent = Math.round(anim) + '%';
      }
    }, 800);
  }

  fetch(window.location.href, {
    method: 'POST',
    body: data
  }).then(function(r) { return r.text(); }).then(function() {
    if (pollInterval) clearInterval(pollInterval);
    bar.style.width = '100%';
    pct.textContent = '100%';
    status.textContent = 'Done! Reloading...';
    setTimeout(function() { location.reload(); }, 600);
  }).catch(function(err) {
    if (pollInterval) clearInterval(pollInterval);
    status.textContent = 'Error: ' + err;
    document.getElementById('submitBtn').disabled = false;
  });

  return false;
}

// ── Cloudflare tunnel URL polling ──────────────────────────────────
(function() {
  var cells = document.querySelectorAll('.cf-status');
  cells.forEach(function(cell) {
    var site = cell.getAttribute('data-site');
    var td = document.getElementById('cf-' + site);
    if (!td) return;
    pollCfTunnel(site, cell, td);
  });
  function pollCfTunnel(site, el, td) {
    fetch('?cf_tunnel_status=' + encodeURIComponent(site))
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.url) {
          el.innerHTML = '<a href="' + d.url + '" target="_blank" style="color:var(--blue);font-size:12px;word-break:break-all;display:inline-block;max-width:180px">' + d.url + '</a>';
        } else if (d.running) {
          el.innerHTML = '<span class="tm ts" style="color:var(--text3)"><i class="fas fa-spinner fa-pulse"></i> Starting...</span>';
          setTimeout(function() { pollCfTunnel(site, el, td); }, 3000);
        } else {
          var stopForm = td.querySelector('form');
          if (stopForm) stopForm.parentNode.innerHTML = '';
          el.innerHTML = '<span class="tm ts" style="color:var(--red)"><i class="fas fa-exclamation-triangle"></i> Failed</span>';
        }
      }).catch(function() {
        setTimeout(function() { pollCfTunnel(site, el, td); }, 5000);
      });
  }
})();
</script>
