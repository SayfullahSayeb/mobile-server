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
  <div id="flash-placeholder" class="flash" style="display:none"></div>
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0">Manage Sites</div>
    <div class="df ac g2 fw">
      <button class="btn btn-p btn-l" onclick="document.getElementById('siteModal').classList.add('show')"><i class="fas fa-plus"></i> Add New</button>
    </div>
  </div>
  <?php if (!empty($allSites)): ?>
  <div class="tb-wrap">
  <table class="stbl">
    <thead>
      <tr>
        <th>Name</th>
        <th>Directory</th>
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
        <td class="td-dir"><a href="/filemanager/panel.php?p=<?= urlencode($name) ?>/public_html" target="_blank" class="sd" title="<?= htmlspecialchars($site['path']) ?>"><i class="fas fa-folder"></i> <?= htmlspecialchars($name) ?>/public_html</a></td>
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
        </td>
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
      <div id="sourceSection">
        <div class="st2" style="margin:6px 0 8px">Site Source</div>
        <div class="df ac g3 mb1" id="siteSourceGroup">
          <label class="rl" style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;padding:6px 10px;background:rgba(15,23,42,.4);border:1px solid rgba(148,163,184,.08);border-radius:var(--rs);transition:all .15s">
            <input type="radio" name="site_source" value="empty" checked style="accent-color:var(--blue);width:15px;height:15px;cursor:pointer">
            <span>Empty Directory</span>
          </label>
          <label class="rl" style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;padding:6px 10px;background:rgba(15,23,42,.4);border:1px solid rgba(148,163,184,.08);border-radius:var(--rs);transition:all .15s">
            <input type="radio" name="site_source" value="git" style="accent-color:var(--blue);width:15px;height:15px;cursor:pointer">
            <span>Clone Git Repository</span>
          </label>
        </div>
      </div>
      <div id="gitRepoUrlGroup" style="display:none">
        <div class="st2" style="margin:6px 0 8px">Git Repository URL</div>
        <input type="url" name="git_repo_url" id="gitRepoUrl" class="inp" placeholder="e.g. https://github.com/user/repo.git">
      </div>
      <div class="st2" style="margin:6px 0 8px">Site Name</div>
      <input type="text" name="site_name" id="siteName" class="inp" placeholder="e.g. myapp" required pattern="[a-z0-9_\-]+" title="Letters, numbers, hyphens, underscores only">
      <input type="hidden" name="site_domain" id="siteDomain" value="">
      <div class="modal-footer">
        <button type="button" class="btn btn-d" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-p" id="submitBtn">Create Site</button>
      </div>
    </form>
  </div>
</div>

<div id="progressModal" class="modal">
  <div class="modal-bg" onclick="closeProgressModal()"></div>
  <div class="modal-content" style="max-width:420px;text-align:center">
    <div class="modal-header" style="border:none;padding-bottom:0">
      <span class="modal-title" id="progressTitle"><i class="fas fa-cog fa-spin"></i> Creating Site...</span>
      <span class="modal-close" onclick="closeProgressModal()">&times;</span>
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
    <form method="post" id="deleteForm" onsubmit="return submitDeleteForm(this)">
      <?= csrf() ?>
      <input type="hidden" name="action" value="delete_site">
      <input type="hidden" name="site_name" id="deleteSiteName" value="">
      <p style="margin:0 0 12px;font-size:14px;color:var(--text2)" id="deleteSiteLabel"></p>
      <p style="margin:0 0 12px;font-size:12px;color:var(--red)"><i class="fas fa-exclamation-triangle"></i> Database, database user, and all site files will be permanently deleted.</p>
      <div class="modal-footer" style="margin-top:14px">
        <button type="button" class="btn btn-d" onclick="closeDeleteModal()">Cancel</button>
        <button type="submit" class="btn btn-d" style="background:var(--red);color:#fff">Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
function closeModal() {
  document.getElementById('siteModal').classList.remove('show');
  document.getElementById('siteForm').reset();
  document.getElementById('formAction').value = 'create_site';
  document.getElementById('modalTitle').textContent = 'Add New Site';
  document.getElementById('submitBtn').textContent = 'Create Site';
  document.getElementById('siteName').disabled = false;
  document.getElementById('siteNameOrig').value = '';
  document.querySelector('input[name="site_type"][value="static"]').checked = true;
  document.querySelector('input[name="site_source"][value="empty"]').checked = true;
  document.getElementById('gitRepoUrlGroup').style.display = 'none';
  document.getElementById('gitRepoUrl').value = '';
  document.getElementById('sourceSection').style.display = '';
}
function showDeleteModal(name) {
  document.getElementById('deleteSiteName').value = name;
  document.getElementById('deleteSiteLabel').textContent = 'Delete "' + name + '"?';
  document.getElementById('deleteModal').classList.add('show');
}
function closeDeleteModal() {
  document.getElementById('deleteModal').classList.remove('show');
}
function closeProgressModal() {
  document.getElementById('progressModal').classList.remove('show');
  document.getElementById('submitBtn').disabled = false;
}

// Toggle git repo URL field
document.querySelectorAll('input[name="site_source"]').forEach(function(r) {
  r.addEventListener('change', function() {
    document.getElementById('gitRepoUrlGroup').style.display = this.value === 'git' ? '' : 'none';
  });
});

// Toggle source section based on site type
document.querySelectorAll('input[name="site_type"]').forEach(function(r) {
  r.addEventListener('change', function() {
    document.getElementById('sourceSection').style.display = this.value === 'static' ? '' : 'none';
    if (this.value !== 'static') {
      document.querySelector('input[name="site_source"][value="empty"]').checked = true;
      document.getElementById('gitRepoUrlGroup').style.display = 'none';
    }
  });
});
function editSite(name, domain, type) {
  document.getElementById('formAction').value = 'edit_site';
  document.getElementById('modalTitle').textContent = 'Edit Site: ' + name;
  document.getElementById('submitBtn').textContent = 'Update Site';
  document.getElementById('siteName').value = name;
  document.getElementById('siteNameOrig').value = name;
  document.getElementById('siteDomain').value = domain === '' ? '' : domain;
  var radio = document.querySelector('input[name="site_type"][value="' + (type || 'static') + '"]');
  if (radio) radio.checked = true;
  document.getElementById('sourceSection').style.display = 'none';
  document.getElementById('siteModal').classList.add('show');
}
function submitSiteForm(form) {
  var type = document.querySelector('input[name="site_type"]:checked').value;
  var isWp = type === 'wordpress';
  var editMode = document.getElementById('formAction').value === 'edit_site';
  var siteName = document.getElementById('siteName').value;
  var data = new FormData(form);
  data.append('ajax', '1');
  var modal = document.getElementById('progressModal');
  var bar = document.getElementById('progressBar');
  var status = document.getElementById('progressStatus');
  var pct = document.getElementById('progressPct');

  modal.classList.add('show');
  var isGit = document.querySelector('input[name="site_source"]:checked') && document.querySelector('input[name="site_source"]:checked').value === 'git';
  var actionLabel = editMode ? 'Updating' : 'Creating';
  var typeLabel = isWp ? 'WordPress' : (isGit ? 'Static from Git' : 'Static');
  document.getElementById('progressTitle').innerHTML = '<i class="fas fa-cog fa-spin"></i> ' + actionLabel + ' ' + typeLabel + ' Site...';
  document.getElementById('submitBtn').disabled = true;

  status.textContent = editMode ? 'Saving...' : (isWp ? 'Preparing...' : isGit ? 'Cloning repository...' : 'Creating site...');

  // Poll real progress from server (create only)
  var pollInterval = null;
  if (!editMode && isWp && siteName) {
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
  } else if (!editMode) {
    // Static or git site (create only): animate a simple progress
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
  }).then(function(r) { return r.json(); }).then(function(resp) {
    if (pollInterval) clearInterval(pollInterval);
    if (resp.success) {
      bar.style.width = '100%';
      pct.textContent = '100%';
      status.textContent = 'Done! Reloading...';
      setTimeout(function() { location.reload(); }, 600);
    } else {
      document.getElementById('progressTitle').innerHTML = '<i class="fas fa-exclamation-triangle" style="color:var(--red)"></i> Installation Failed';
      status.textContent = resp.message || 'An error occurred';
      document.getElementById('submitBtn').disabled = false;
    }
  }).catch(function(err) {
    if (pollInterval) clearInterval(pollInterval);
    document.getElementById('progressTitle').innerHTML = '<i class="fas fa-exclamation-triangle" style="color:var(--red)"></i> Request Failed';
    status.textContent = 'Network error: ' + err;
    document.getElementById('submitBtn').disabled = false;
  });

  return false;
}

// ── AJAX delete site ──────────────────────────────────────────────
function submitDeleteForm(form) {
  if (!confirm('Delete this site?')) return false;
  var name = document.getElementById('deleteSiteName').value;
  closeDeleteModal();

  var flash = document.getElementById('flash-placeholder');
  flash.className = 'flash suc';
  flash.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Deleting ' + name + '...';
  flash.style.display = '';

  var data = new FormData(form);
  data.append('ajax', '1');

  fetch(window.location.href, {
    method: 'POST',
    body: data
  }).then(function(r) { return r.json(); }).then(function(resp) {
    if (resp.success) {
      flash.className = 'flash suc';
      flash.innerHTML = '<i class="fas fa-check-circle"></i> ' + (resp.message || 'Site deleted');
      setTimeout(function() { location.reload(); }, 800);
    } else {
      flash.className = 'flash err';
      flash.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + (resp.message || 'Delete failed');
      document.getElementById('submitBtn').disabled = false;
    }
  }).catch(function(err) {
    flash.className = 'flash err';
    flash.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Network error: ' + err;
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
        if (d.running && d.url) {
          el.innerHTML = '<a href="' + d.url + '" target="_blank" style="color:var(--blue);font-size:12px;word-break:break-all;display:inline-block;max-width:180px">' + d.url + '</a>';
          setTimeout(function() { pollCfTunnel(site, el, td); }, 15000);
        } else if (d.running) {
          el.innerHTML = '<span class="tm ts" style="color:var(--text3)"><i class="fas fa-spinner fa-pulse"></i> Starting...</span>';
          setTimeout(function() { pollCfTunnel(site, el, td); }, 3000);
        } else if (d.url) {
          el.innerHTML = '<span style="color:var(--orange);font-size:12px">' + d.url + '<br><span style="color:var(--red)">(stopped)</span></span>';
          var stopForm = td.querySelector('form');
          if (stopForm) stopForm.parentNode.innerHTML = '';
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
