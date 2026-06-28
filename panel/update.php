<div class="sec">
  <div class="st"><i class="fas fa-sync-alt"></i> System Update</div>
  <div class="st3">Update your Mobile Server installation from the latest GitHub source. This will download and replace core files while preserving your sites, databases, and configuration.</div>
  <div style="background:rgba(15,23,42,.4);padding:14px;border-radius:var(--rs);margin-bottom:14px">
    <div class="st2" style="color:var(--text);margin-top:0">Files to Update</div>
    <ul style="color:var(--text2);line-height:1.8;padding-left:18px">
      <li>index.php — public status dashboard</li>
      <li>control.php — admin control panel</li>
      <li>panel/*.php — panel UI files</li>
      <li>filemanager/* — file manager</li>
      <li>lib/*.php — core libraries</li>
      <li>install.sh — installation script</li>
    </ul>
  </div>
  <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.15);border-radius:var(--rs);padding:12px;margin-bottom:14px">
    <div style="color:var(--orange);font-weight:500"><i class="fas fa-exclamation-triangle"></i> Your sites, databases, and other data will not be affected.</div>
  </div>
  <button onclick="startUpdate()" class="btn btn-p btn-l" id="updateBtn"><i class="fas fa-download"></i> Update from GitHub</button>
</div>

<div id="updateModal" class="umod" style="display:none">
  <div class="umod-bg" onclick="closeUpdate()"></div>
  <div class="umod-c">
    <div class="umod-h">System Update</div>
    <div class="umod-b">
      <div id="updateStatus" style="color:var(--text2);margin-bottom:12px">Starting...</div>
      <div id="updateBarWrap" style="background:rgba(51,65,85,.4);border-radius:99px;height:6px;overflow:hidden;margin-bottom:14px">
        <div id="updateBar" style="width:0%;height:100%;background:linear-gradient(90deg,#3b82f6,#6366f1);border-radius:99px;transition:width .3s"></div>
      </div>
      <div id="updateProgress" style="max-height:240px;overflow-y:auto;line-height:1.8;color:var(--text2);font-family:monospace"></div>
    </div>
    <div class="umod-f">
      <button onclick="closeUpdate()" class="btn btn-w btn-s" id="updateCloseBtn" disabled>Close</button>
    </div>
  </div>
</div>

<style>
.umod{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center}
.umod-bg{position:absolute;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px)}
.umod-c{position:relative;background:var(--bg2);border:1px solid rgba(148,163,184,.08);border-radius:var(--r);width:480px;max-width:90vw;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.5)}
.umod-h{padding:14px 18px;font-weight:700;color:var(--text);border-bottom:1px solid rgba(148,163,184,.06)}
.umod-b{padding:16px 18px;overflow-y:auto;flex:1}
.umod-f{padding:10px 18px;border-top:1px solid rgba(148,163,184,.06);display:flex;justify-content:flex-end;gap:8px}
</style>

<script>
let updateSource = null;
function startUpdate() {
  document.getElementById('updateBtn').disabled = true;
  document.getElementById('updateModal').style.display = 'flex';
  document.getElementById('updateStatus').textContent = 'Starting update...';
  document.getElementById('updateBar').style.width = '0%';
  document.getElementById('updateProgress').innerHTML = '';
  document.getElementById('updateCloseBtn').disabled = true;

  updateSource = new EventSource('?tab=update&action=stream&csrf_token=<?= htmlspecialchars($csrf_token) ?>');
  updateSource.addEventListener('start', function(e) {
    document.getElementById('updateStatus').textContent = e.data;
  });
  updateSource.addEventListener('total', function(e) {
    document.getElementById('updateStatus').textContent = 'Found ' + e.data + ' files to update.';
  });
  updateSource.addEventListener('progress', function(e) {
    var d = JSON.parse(e.data);
    var pct = Math.round(d.current / d.total * 100);
    document.getElementById('updateBar').style.width = pct + '%';
    document.getElementById('updateStatus').textContent = 'Downloading ' + d.current + '/' + d.total + '...';
    var el = document.createElement('div');
    el.style.color = d.status === 'ok' ? 'var(--green)' : 'var(--red)';
    el.innerHTML = (d.status === 'ok' ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>') + ' ' + d.file;
    document.getElementById('updateProgress').appendChild(el);
  });
  updateSource.addEventListener('done', function(e) {
    var d = JSON.parse(e.data);
    document.getElementById('updateStatus').textContent = d.message;
    document.getElementById('updateBar').style.width = '100%';
    document.getElementById('updateCloseBtn').disabled = false;
    document.getElementById('updateBtn').disabled = false;
    updateSource.close();
  });
  updateSource.addEventListener('error', function() {
    if (updateSource.readyState === 2) return;
    document.getElementById('updateStatus').textContent = 'Connection lost. Please try again.';
    document.getElementById('updateCloseBtn').disabled = false;
    document.getElementById('updateBtn').disabled = false;
  });
}
function closeUpdate() {
  if (updateSource) updateSource.close();
  document.getElementById('updateModal').style.display = 'none';
}
window.addEventListener('beforeunload', function() { if (updateSource) updateSource.close(); });
</script>
