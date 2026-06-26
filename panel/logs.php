<div class="sec">
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0">All Logs <span id="log-count" class="tm ts" style="font-weight:400">0 lines</span></div>
    <div class="df ac g2 fw">
      <span id="log-status" class="ts tm"><i class="fas fa-check-circle" style="color:var(--green)"></i> Live</span>
      <button class="btn btn-s btn-p" onclick="refreshLogs()"><i class="fas fa-sync-alt"></i> Refresh</button>
    </div>
  </div>
  <div class="tb-wrap" style="max-height:calc(100vh - 220px);overflow-y:auto" id="log-scroll">
  <table class="stbl" id="log-table">
    <thead>
      <tr>
        <th style="width:90px">Service</th>
        <th style="width:70px">Level</th>
        <th>Message</th>
      </tr>
    </thead>
    <tbody id="log-body">
      <tr><td colspan="3" class="tm ts tc" style="padding:20px">Loading logs...</td></tr>
    </tbody>
  </table>
  </div>
</div>

<script>
(function() {
  let timer = null;

  function levelBadge(level) {
    const map = { error: {bg:'rgba(239,68,68,.14)',c:'#ef4444',l:'error'}, warn: {bg:'rgba(245,158,11,.14)',c:'#f59e0b',l:'warn'}, info: {bg:'rgba(148,163,184,.14)',c:'#94a3b8',l:'info'} };
    const m = map[level] || map.info;
    return '<span class="be" style="background:' + m.bg + ';color:' + m.c + '">' + m.l + '</span>';
  }

  function svcBadge(svc) {
    const map = { Panel: {bg:'rgba(59,130,246,.14)',c:'#3b82f6',l:'Panel'}, Nginx: {bg:'rgba(34,197,94,.14)',c:'#22c55e',l:'Nginx'}, PHP: {bg:'rgba(139,92,246,.14)',c:'#8b5cf6',l:'PHP'}, MariaDB: {bg:'rgba(245,158,11,.14)',c:'#f59e0b',l:'MariaDB'} };
    const m = map[svc] || {bg:'rgba(148,163,184,.14)',c:'#94a3b8',l:svc};
    return '<span class="be" style="background:' + m.bg + ';color:' + m.c + '">' + m.l + '</span>';
  }

  function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  function renderLogs(data) {
    const tbody = document.getElementById('log-body');
    const count = document.getElementById('log-count');
    if (!data || data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="3" class="tm ts tc" style="padding:20px">No log entries found</td></tr>';
      count.textContent = '0 lines';
      return;
    }
    const lines = data.slice(-300);
    let html = '';
    for (let i = lines.length - 1; i >= 0; i--) {
      const r = lines[i];
      html += '<tr>'
        + '<td>' + svcBadge(r.svc) + '</td>'
        + '<td>' + levelBadge(r.level) + '</td>'
        + '<td style="word-break:break-all;font-family:\'SF Mono\',\'Fira Code\',Consolas,monospace;font-size:13px;line-height:1.5;color:var(--text2)">' + escapeHtml(r.text) + '</td>'
        + '</tr>';
    }
    tbody.innerHTML = html;
    count.textContent = data.length + ' lines';
  }

  function refreshLogs() {
    const status = document.getElementById('log-status');
    status.innerHTML = '<i class="fas fa-spinner fa-pulse" style="color:var(--blue)"></i> Loading...';
    fetch('panel/logs_data.php')
      .then(r => r.json())
      .then(data => {
        renderLogs(data);
        status.innerHTML = '<i class="fas fa-check-circle" style="color:var(--green)"></i> Live';
      })
      .catch(() => {
        status.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:var(--orange)"></i> Error';
      });
  }

  refreshLogs();
  timer = setInterval(refreshLogs, 3000);
})();
</script>
