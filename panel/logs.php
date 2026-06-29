<div class="sec">
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0">All Logs <span id="log-count" class="tm ts" style="font-weight:400">0 lines</span></div>
    <div class="df ac g2 fw">
      <span id="log-status" class="ts tm"><i class="fas fa-check-circle" style="color:var(--green)"></i> Live</span>
      <button class="btn btn-s btn-d" onclick="clearLogs()"><i class="fas fa-trash-alt"></i> Clear</button>
      <button class="btn btn-s btn-p" onclick="refreshLogs()"><i class="fas fa-sync-alt"></i> Refresh</button>
    </div>
  </div>
  <div class="tb-wrap" style="max-height:calc(100vh - 220px);overflow-y:auto" id="log-scroll">
    <pre id="log-content" style="margin:0;font-family:'SF Mono','Fira Code',Consolas,monospace;font-size:12px;line-height:1.6;color:var(--text2);white-space:pre-wrap;word-break:break-all"></pre>
  </div>
</div>

<script>
(function() {
  let timer = null;
  let lastLineCount = 0;
  let allLines = [];

  function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  function refreshLogs() {
    const status = document.getElementById('log-status');
    status.innerHTML = '<i class="fas fa-spinner fa-pulse" style="color:var(--blue)"></i> Loading...';
    fetch('?tab=logs&raw_logs=1')
      .then(r => r.text())
      .then(text => {
        const lines = text.split('\n').filter(Boolean);
        const count = document.getElementById('log-count');
        count.textContent = lines.length + ' lines';

        if (lines.length === lastLineCount && allLines.length > 0) {
          status.innerHTML = '<i class="fas fa-check-circle" style="color:var(--green)"></i> Live';
          return;
        }

        allLines = lines;
        lastLineCount = lines.length;
        const el = document.getElementById('log-content');
        let html = '';
        for (let i = lines.length - 1; i >= 0; i--) {
          html += escapeHtml(lines[i]) + '\n';
        }
        el.textContent = html;
        status.innerHTML = '<i class="fas fa-check-circle" style="color:var(--green)"></i> Live';
      })
      .catch(() => {
        status.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:var(--orange)"></i> Error';
      });
  }

  function clearLogs() {
    if (!confirm('Clear all panel logs?')) return;
    const status = document.getElementById('log-status');
    status.innerHTML = '<i class="fas fa-spinner fa-pulse" style="color:var(--blue)"></i> Clearing...';
    fetch('?tab=logs&clear_logs=1')
      .then(() => {
        allLines = [];
        lastLineCount = 0;
        document.getElementById('log-content').textContent = '';
        document.getElementById('log-count').textContent = '0 lines';
        refreshLogs();
      });
  }

  refreshLogs();
  timer = setInterval(refreshLogs, 1000);
})();
</script>
