<div class="sec">
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0">All Logs <span id="log-count" class="tm ts" style="font-weight:400">0 lines</span></div>
    <div class="df ac g2 fw">
      <button class="btn btn-s btn-d" id="clearBtn"><i class="fas fa-trash-alt"></i> Clear</button>
      <button class="btn btn-s btn-p" id="refreshBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
    </div>
  </div>
  <div class="tb-wrap" style="max-height:calc(100vh - 220px);overflow-y:auto" id="log-scroll">
    <pre id="log-content" style="margin:0;font-family:'SF Mono','Fira Code',Consolas,monospace;font-size:12px;line-height:1.6;color:var(--text2);white-space:pre-wrap;word-break:break-all"></pre>
  </div>
</div>

<script>
(function() {
  var lastLineCount = 0;

  function escapeHtml(str) {
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  function refreshLogs() {
    fetch('?tab=logs&raw_logs=1')
      .then(function(r) { return r.text(); })
      .then(function(text) {
        var lines = text.split('\n').filter(Boolean);
        var count = document.getElementById('log-count');
        count.textContent = lines.length + ' lines';

        if (lines.length === lastLineCount) return;

        lastLineCount = lines.length;
        var el = document.getElementById('log-content');
        var html = '';
        for (var i = lines.length - 1; i >= 0; i--) {
          html += escapeHtml(lines[i]) + '\n';
        }
        el.textContent = html;
      });
  }

  document.getElementById('refreshBtn').addEventListener('click', refreshLogs);

  document.getElementById('clearBtn').addEventListener('click', function() {
    if (!confirm('Clear all panel logs?')) return;
    fetch('?tab=logs&clear_logs=1')
      .then(function() {
        lastLineCount = 0;
        document.getElementById('log-content').textContent = '';
        document.getElementById('log-count').textContent = '0 lines';
      });
  });

  refreshLogs();
})();
</script>
