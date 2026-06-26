<?php
$config = getSitesConfig();
$allSites = [];
$dirs = glob(SITES_DIR . '/*', GLOB_ONLYDIR);
$exclude = ['default', 'filemanager', 'lib', 'panel'];
if ($dirs) {
    foreach ($dirs as $d) {
        $name = basename($d);
        if (in_array($name, $exclude)) continue;
        $publicHtml = $d . '/public_html';
        if (is_dir($publicHtml)) {
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
ksort($allSites);
?>
<div class="sec">
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0">Manage Sites</div>
    <div class="df ac g2">
      <span class="ts tm"><?= count($allSites) ?> site<?= count($allSites) !== 1 ? 's' : '' ?></span>
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
          <?php if (!empty($site['domain']) && $site['port']): ?>
          <a href="http://<?= htmlspecialchars($site['domain']) ?>:<?= $site['port'] ?>" target="_blank" class="sd"><?= htmlspecialchars($site['domain']) ?>:<?= $site['port'] ?></a>
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
          <form method="post" style="display:inline" onsubmit="return confirm('Delete site &#39;<?= htmlspecialchars($name) ?>&#39; and all its files?')">
            <?= csrf() ?>
            <input type="hidden" name="action" value="delete_site">
            <input type="hidden" name="site_name" value="<?= htmlspecialchars($name) ?>">
            <button type="submit" class="btn btn-s btn-d" title="Delete"><i class="fas fa-trash-alt"></i></button>
          </form>
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
    <form method="post" id="siteForm">
      <?= csrf() ?>
      <input type="hidden" name="action" id="formAction" value="create_site">
      <input type="hidden" name="site_name_orig" id="siteNameOrig" value="">
      <input type="text" name="site_name" id="siteName" class="inp" placeholder="Site name (e.g. myapp)" required pattern="[a-z0-9_-]+" title="Letters, numbers, hyphens, underscores only">
      <input type="text" name="site_domain" id="siteDomain" class="inp" placeholder="Domain (e.g. myapp.test) — defaults to sitename.test">
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
      <div id="wpFields" class="wp-fields" style="display:none">
        <div class="st2" style="margin:6px 0 10px">WordPress Configuration</div>
        <input type="text" name="wp_title" id="wpTitle" class="inp" placeholder="Site title (e.g. My Blog)">
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
</script>
