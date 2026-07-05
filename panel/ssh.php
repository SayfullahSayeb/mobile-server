<div class="sec" style="padding:0;overflow:hidden;height:calc(var(--vh, 100vh) - 140px);min-height:300px;display:flex;flex-direction:column">
  <div id="term-container" style="flex:1;min-height:0;padding:8px;background:#0d1117;position:relative">
    <div id="term-status" style="display:flex;align-items:center;justify-content:center;height:100%;color:#8b949e;font-size:15px;gap:10px;flex-direction:column">
      <i class="fas fa-terminal"></i> Terminal ready
      <div id="term-loading" style="font-size:13px;color:#64748b;margin-top:4px">Loading xterm.js...</div>
    </div>
    <div id="term-error" style="display:none;align-items:center;justify-content:center;height:100%;color:#ff7b72;font-size:14px;gap:8px;flex-direction:column;padding:20px;text-align:center">
      <i class="fas fa-exclamation-triangle" style="font-size:24px"></i>
      <div id="term-error-msg" style="font-weight:600">Failed to load terminal</div>
      <div style="font-size:13px;color:#8b949e">Check your internet connection or try a different browser.</div>
      <button onclick="location.reload()" class="btn btn-p btn-s" style="margin-top:8px">Retry</button>
    </div>
  </div>
</div>

<script>
(function() {
  'use strict';
  function setVh() {
    document.documentElement.style.setProperty('--vh', window.innerHeight + 'px');
  }
  setVh();
  window.addEventListener('resize', setVh);

  var CSRF_TOKEN = '<?= htmlspecialchars($csrf_token ?? '') ?>';
  var term = null;
  var fitAddon = null;
  var prompt = '';
  var inputLine = '';
  var cursorPos = 0;
  var cmdHistory = [];
  var historyIdx = -1;
  var savedInput = '';
  var scriptsLoaded = { xterm: false, fit: false };
  var initCalled = false;

  var termContainer = document.getElementById('term-container');
  var termStatus = document.getElementById('term-status');
  var termLoading = document.getElementById('term-loading');
  var termError = document.getElementById('term-error');
  var termErrorMsg = document.getElementById('term-error-msg');

  function showError(msg) {
    if (termStatus) termStatus.style.display = 'none';
    if (termError) {
      termError.style.display = 'flex';
      if (termErrorMsg) termErrorMsg.textContent = msg;
    }
  }

  function postData(data) {
    data.csrf_token = CSRF_TOKEN;
    var parts = [];
    for (var k in data) {
      parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k]));
    }
    return fetch('panel/ssh_exec.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: parts.join('&')
    }).then(function(r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
  }

  function pollOutput(runId, offset, termWrite, cb) {
    var timeout = 30000;
    var startTime = Date.now();
    function poll() {
      if (Date.now() - startTime > timeout) {
        cb(new Error('Command timed out'));
        return;
      }
      postData({action: 'poll', run_id: runId, offset: offset}).then(function(r) {
        if (r.output) {
          termWrite(r.output.replace(/\n/g, '\r\n'));
          offset = r.offset;
        }
        if (r.done) {
          cb(null, offset);
        } else {
          setTimeout(poll, 120);
        }
      }).catch(function(e) {
        cb(e);
      });
    }
    poll();
  }

  function doExec(cmd) {
    if (cmd.trim()) {
      cmdHistory.push(cmd);
    }
    historyIdx = cmdHistory.length;
    savedInput = '';
    inputLine = '';
    cursorPos = 0;
    term.write('\r\n$ ' + cmd + '\r\n');
    postData({action: 'start', cmd: cmd}).then(function(r) {
      if (r.run_id && !r.done) {
        var termWrite = function(text) { term.write(text); };
        pollOutput(r.run_id, 0, termWrite, function(err, offset) {
          if (err) {
            term.writeln('\x1b[31mError: ' + err.message + '\x1b[0m');
          }
          prompt = r.prompt;
          term.write(prompt);
        });
      } else {
        if (r.output) term.write(r.output.replace(/\n/g, '\r\n'));
        if (r.output && !r.output.endsWith('\n')) term.writeln('');
        prompt = r.prompt;
        term.write(prompt);
      }
    }).catch(function() {
      term.writeln('\x1b[31mError executing command\x1b[0m');
      term.write(prompt);
    });
  }

  function initTerminal() {
    if (initCalled) return;
    initCalled = true;
    try {
      if (typeof Terminal === 'undefined') throw new Error('xterm.js not loaded');
      if (typeof FitAddon === 'undefined') throw new Error('xterm-addon-fit not loaded');

      if (termStatus) termStatus.style.display = 'none';
      fitAddon = new FitAddon.FitAddon();
      term = new Terminal({
        cursorBlink: true,
        cursorStyle: 'block',
        fontSize: 14,
        fontFamily: "'SF Mono','Fira Code','Cascadia Code','JetBrains Mono','Noto Mono',Consolas,monospace",
        theme: {
          background: '#0d1117', foreground: '#c9d1d9', cursor: '#c9d1d9', selectionBackground: '#3b82f622',
          black: '#484f58', red: '#ff7b72', green: '#3fb950', yellow: '#d29922',
          blue: '#58a6ff', magenta: '#bc8cff', cyan: '#39c5cf', white: '#b1bac4',
          brightBlack: '#6e7681', brightRed: '#ffa198', brightGreen: '#56d364',
          brightYellow: '#e3b341', brightBlue: '#79c0ff', brightMagenta: '#d2a8ff',
          brightCyan: '#56d4dd', brightWhite: '#f0f6fc',
        },
        allowTransparency: true,
      });
      term.loadAddon(fitAddon);
      term.open(termContainer);
      term.focus();

      setTimeout(function() { try { fitAddon.fit(); } catch(e) {} }, 50);
      window.addEventListener('resize', function() { if (fitAddon) { try { fitAddon.fit(); } catch(e) {} } });

      postData({action: 'start', cmd: ''}).then(function(r) {
        prompt = r.prompt;
        term.write(prompt);
        var autoCmd = new URLSearchParams(window.location.search).get('cmd');
        if (autoCmd) doExec(autoCmd);
      }).catch(function() {
        term.writeln('\x1b[31mWarning: shell unavailable, but terminal is ready\x1b[0m');
        prompt = '\x1b[32m$\x1b[0m ';
        term.write(prompt);
      });

      term.attachCustomKeyEventHandler(function(ev) {
        if (ev.type !== 'keydown') return true;
        if (ev.ctrlKey && ev.key === 'c') {
          if (term.hasSelection()) {
            document.execCommand('copy');
            term.clearSelection();
            return false;
          }
          return true;
        }
        return true;
      });

      if (typeof term.onData === 'function') {
        term.onData(function(text) {
          if (text.length > 1) {
            inputLine += text;
            cursorPos += text.length;
            term.write(text);
          }
        });
      }

      term.onKey(function(e) {
        var ev = e.domEvent;
        if (ev.altKey || ev.ctrlKey || ev.metaKey) {
          if (ev.ctrlKey && ev.key === 'c' && !term.hasSelection()) {
            inputLine = '';
            cursorPos = 0;
            term.writeln('^C');
            term.write(prompt);
          }
          return;
        }
        if (ev.key === 'ArrowUp') {
          if (cmdHistory.length === 0) return;
          if (historyIdx === cmdHistory.length) savedInput = inputLine;
          if (historyIdx > 0) {
            historyIdx--;
            var prev = cmdHistory[historyIdx];
            term.write('\r' + prompt + ' '.repeat(inputLine.length) + '\r' + prompt + prev);
            inputLine = prev;
            cursorPos = prev.length;
          }
          return;
        }
        if (ev.key === 'ArrowDown') {
          if (historyIdx === cmdHistory.length) return;
          historyIdx++;
          var next = historyIdx < cmdHistory.length ? cmdHistory[historyIdx] : savedInput;
          term.write('\r' + prompt + ' '.repeat(inputLine.length) + '\r' + prompt + next);
          inputLine = next;
          cursorPos = next.length;
          return;
        }
        if (ev.key === 'Enter') {
          if (inputLine.trim() === '') {
            term.writeln('');
            term.write(prompt);
            return;
          }
          term.writeln('');
          var cmd = inputLine;
          doExec(cmd);
        } else if (ev.key === 'Backspace') {
          if (cursorPos > 0) {
            inputLine = inputLine.slice(0, -1);
            cursorPos--;
            term.write('\b \b');
          }
        } else if (ev.key.length === 1) {
          inputLine += ev.key;
          cursorPos++;
          term.write(ev.key);
        }
      });
    } catch (e) {
      showError('Terminal error: ' + e.message);
    }
  }

  function loadXterm() {
    if (typeof Terminal !== 'undefined' && typeof FitAddon !== 'undefined') {
      initTerminal();
      return;
    }
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.min.css';
    link.crossOrigin = 'anonymous';
    document.head.appendChild(link);

    var urls = [
      'https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js',
      'https://unpkg.com/xterm@5.3.0/lib/xterm.js',
      'https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.js'
    ];
    var fitUrls = [
      'https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.min.js',
      'https://unpkg.com/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.js',
      'https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.js'
    ];

    function loadScript(urls, idx, cb) {
      if (idx >= urls.length) { cb(false); return; }
      var s = document.createElement('script');
      s.src = urls[idx];
      s.onload = function() { cb(true); };
      s.onerror = function() { loadScript(urls, idx + 1, cb); };
      document.head.appendChild(s);
    }

    loadScript(urls, 0, function(ok) {
      scriptsLoaded.xterm = ok;
      if (!ok) { showError('Failed to load xterm.js from any CDN'); return; }
      loadScript(fitUrls, 0, function(ok2) {
        scriptsLoaded.fit = ok2;
        if (!ok2) { showError('Failed to load xterm-addon-fit from any CDN'); return; }
        initTerminal();
      });
    });
  }

  var loadTimer = setTimeout(function() {
    if (!initCalled) showError('Terminal loading timed out. Check your connection.');
  }, 15000);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() { clearTimeout(loadTimer); loadXterm(); });
  } else {
    clearTimeout(loadTimer);
    loadXterm();
  }
})();
</script>
