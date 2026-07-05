<?php $csrf = $_SESSION['csrf_token'] ?? ''; ?>

<style>
*{box-sizing:border-box}
#term-wrap{background:#0d1117;display:flex;flex-direction:column;height:100%;font-family:'SF Mono','Fira Code','Cascadia Code',Consolas,monospace;font-size:15px}
#term-out{flex:1;overflow-y:auto;padding:10px;color:#c9d1d9;white-space:pre-wrap;word-break:break-all;line-height:1.5;scrollbar-width:thin;scrollbar-color:#30363d transparent}
#term-out::-webkit-scrollbar{width:6px}
#term-out::-webkit-scrollbar-thumb{background:#30363d;border-radius:3px}
.term-line{padding:0;min-height:1.2em}
.term-line.err{color:#ff7b72}
.term-inp-row{display:flex;align-items:center;padding:6px 10px;background:#161b22;border-top:1px solid #30363d}
.term-prompt{color:#3fb950;white-space:pre;user-select:none;flex-shrink:0}
.term-inp{flex:1;background:transparent;border:none;outline:none;color:#c9d1d9;font:inherit;caret-color:#c9d1d9;margin-left:2px}
.term-inp::placeholder{color:#484f58}
</style>
<div class="sec" style="padding:0;overflow:hidden;height:calc(100vh - 140px);min-height:300px;display:flex;flex-direction:column">
  <div id="term-wrap">
    <div id="term-out"></div>
    <div class="term-inp-row">
      <span class="term-prompt" id="term-prompt">$ </span>
      <input class="term-inp" id="term-inp" type="text" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" placeholder="Type a command...">
    </div>
  </div>
</div>

<script>
(function() {
  var CSRF = '<?= htmlspecialchars($csrf) ?>';
  var out = document.getElementById('term-out');
  var inp = document.getElementById('term-inp');
  var promptEl = document.getElementById('term-prompt');
  var promptText = '$ ';
  var history = [];
  var histIdx = -1;
  var busy = false;

  function ansiToHtml(s) {
    var map = {
      '0':'','1':'font-weight:bold','3':'font-style:italic','4':'text-decoration:underline',
      '30':'color:#484f58','31':'color:#ff7b72','32':'color:#3fb950','33':'color:#d29922',
      '34':'color:#58a6ff','35':'color:#bc8cff','36':'color:#39c5cf','37':'color:#b1bac4',
      '90':'color:#6e7681','91':'color:#ffa198','92':'color:#56d364','93':'color:#e3b341',
      '94':'color:#79c0ff','95':'color:#d2a8ff','96':'color:#56d4dd','97':'color:#f0f6fc',
    };
    return s.replace(/\x1b\[([0-9;]*)m/g, function(_, c) {
      if (!c) return '</span>';
      var styles = c.split(';').map(function(n) { return map[n] || ''; }).filter(Boolean).join(';');
      return styles ? '<span style="' + styles + '">' : '</span>';
    }).replace(/\x1b\[[0-9;]*[a-zA-Z]/g, '');
  }

  function write(text, cls) {
    if (!text) return;
    var lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
    for (var i = 0; i < lines.length; i++) {
      var d = document.createElement('div');
      d.className = 'term-line' + (cls ? ' ' + cls : '');
      d.innerHTML = ansiToHtml(lines[i]) || '\u00A0';
      out.appendChild(d);
    }
    out.scrollTop = out.scrollHeight;
  }

  function setPrompt(p) {
    promptText = p || '$ ';
    promptEl.textContent = promptText.replace(/\x1b\[[0-9;]*[a-zA-Z]/g, '');
  }

  function submit() {
    var cmd = inp.value;
    if (!cmd.trim()) { inp.value = ''; return; }
    if (cmd.trim()) {
      history.push(cmd);
      if (history.length > 500) history.shift();
    }
    histIdx = -1;
    busy = true;
    inp.disabled = true;

    write(promptText + cmd);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'panel/ssh_exec.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.responseType = 'json';
    xhr.onload = function() {
      if (xhr.status === 200 && xhr.response) {
        write(xhr.response.output || '');
        setPrompt(xhr.response.prompt);
      } else {
        write('Error: HTTP ' + xhr.status, 'err');
      }
      inp.value = '';
      inp.disabled = false;
      busy = false;
      inp.focus();
    };
    xhr.onerror = function() {
      write('Network error', 'err');
      inp.value = '';
      inp.disabled = false;
      busy = false;
      inp.focus();
    };
    xhr.timeout = 60000;
    xhr.ontimeout = function() {
      write('Command timed out (60s)', 'err');
      inp.value = '';
      inp.disabled = false;
      busy = false;
      inp.focus();
    };
    xhr.send('cmd=' + encodeURIComponent(cmd) + '&csrf_token=' + encodeURIComponent(CSRF));
  }

  inp.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      if (!busy) submit();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (history.length === 0) return;
      if (histIdx === -1) histIdx = history.length - 1;
      else if (histIdx > 0) histIdx--;
      else return;
      inp.value = history[histIdx];
    } else if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (histIdx === -1) return;
      histIdx++;
      if (histIdx >= history.length) { histIdx = -1; inp.value = ''; return; }
      inp.value = history[histIdx];
    } else if (e.key === 'c' && (e.ctrlKey || e.metaKey)) {
      if (busy) {
        busy = false;
        inp.disabled = false;
      }
      inp.value = '';
    } else if (e.key === 'l' && (e.ctrlKey || e.metaKey)) {
      e.preventDefault();
      out.innerHTML = '';
    }
  });

  // click terminal → focus input
  out.addEventListener('click', function() { inp.focus(); });
  inp.focus();
})();
</script>
