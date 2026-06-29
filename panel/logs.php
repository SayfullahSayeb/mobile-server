<div class="sec">
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0">All Logs <span id="log-count" class="tm ts" style="font-weight:400">0 lines</span></div>
    <div class="df ac g2 fw">
      <select id="log-filter" class="btn btn-s btn-o" style="background:transparent;border:1px solid var(--border);color:var(--text2);padding:4px 8px;border-radius:var(--rs);font-size:12px;cursor:pointer">
        <option value="all">All</option>
        <option value="Panel">Panel</option>
        <option value="Nginx">Nginx</option>
        <option value="PHP-FPM">PHP-FPM</option>
        <option value="MariaDB">MariaDB</option>
        <option value="Cloudflared">Cloudflared</option>
      </select>
      <button class="btn btn-s btn-d" id="clearBtn"><i class="fas fa-trash-alt"></i> Clear</button>
      <button class="btn btn-s btn-p" id="refreshBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
    </div>
  </div>
  <div class="tb-wrap" style="max-height:calc(var(--vh, 100vh) - 220px);overflow-y:auto" id="log-scroll">
    <pre id="log-content" style="margin:0;font-family:'SF Mono','Fira Code',Consolas,monospace;font-size:12px;line-height:1.6;color:var(--text2);white-space:pre-wrap;word-break:break-all"></pre>
  </div>
</div>

<script>
(function() {
  function setVh() {
    document.documentElement.style.setProperty('--vh', window.innerHeight + 'px');
  }
  setVh();
  window.addEventListener('resize', setVh);

  var allLogs = [];
  var currentFilter = 'all';
  var levelColors = { error: '#ff7b72', warn: '#d29922', info: '#8b949e' };

  function escapeHtml(str) {
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  function renderLogs() {
    var filtered = currentFilter === 'all' ? allLogs : allLogs.filter(function(l) { return l.svc === currentFilter; });
    document.getElementById('log-count').textContent = filtered.length + ' lines';
    var el = document.getElementById('log-content');
    var html = '';
    for (var i = filtered.length - 1; i >= 0; i--) {
      var log = filtered[i];
      html += '<span style="color:' + (levelColors[log.level] || levelColors.info) + '">' + escapeHtml(log.text) + '</span>\n';
    }
    el.innerHTML = html;
  }

  function refreshLogs() {
    fetch('?raw_logs=json')
      .then(function(r) { return r.json(); })
      .then(function(data) { allLogs = data || []; renderLogs(); })
      .catch(function() {
        document.getElementById('log-content').innerHTML = '<span style="color:#ff7b72">Failed to load logs. Check connection and try again.</span>';
      });
  }

  document.getElementById('log-filter').addEventListener('change', function() { currentFilter = this.value; renderLogs(); });
  document.getElementById('refreshBtn').addEventListener('click', refreshLogs);
  document.getElementById('clearBtn').addEventListener('click', function() {
    if (!confirm('Clear all logs?')) return;
    fetch('?clear_logs=1').then(function() {
      allLogs = [];
      renderLogs();
      setTimeout(refreshLogs, 500);
    }).catch(function() {
      alert('Failed to clear logs');
    });
  });

  refreshLogs();
})();
</script>
