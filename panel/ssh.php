<div class="sec" style="padding:0;overflow:hidden;height:calc(var(--vh, 100vh) - 140px);min-height:300px;display:flex;flex-direction:column">
  <div id="term-container" style="flex:1;min-height:0;padding:8px;background:#0d1117;position:relative">
    <div id="term-status" style="display:flex;align-items:center;justify-content:center;height:100%;color:#8b949e;font-size:15px;gap:10px;flex-direction:column">
      <i class="fas fa-terminal"></i> Terminal ready
      <div id="term-loading" style="font-size:13px;color:#64748b;margin-top:4px">Connecting...</div>
    </div>
    <div id="term-error" style="display:none;align-items:center;justify-content:center;height:100%;color:#ff7b72;font-size:14px;gap:8px;flex-direction:column;padding:20px;text-align:center">
      <i class="fas fa-exclamation-triangle" style="font-size:24px"></i>
      <div id="term-error-msg" style="font-weight:600">Failed to connect</div>
      <div style="font-size:13px;color:#8b949e"><?= htmlspecialchars($ip_addr ?? '') ?>:8023</div>
      <button onclick="location.reload()" class="btn btn-p btn-s" style="margin-top:8px">Retry</button>
    </div>
  </div>
</div>

<?php
$wsToken = bin2hex(random_bytes(16));
$wsTokenFile = sys_get_temp_dir() . '/ws_' . session_id() . '.token';
@file_put_contents($wsTokenFile, $wsToken);
@chmod($wsTokenFile, 0600);
$wsHost = $ip_addr ?? '127.0.0.1';
?>

<script>
(function() {
  'use strict';
  function setVh() {
    document.documentElement.style.setProperty('--vh', window.innerHeight + 'px');
  }
  setVh();
  window.addEventListener('resize', setVh);

  var WS_HOST = '<?= htmlspecialchars($wsHost) ?>';
  var WS_PORT = 8023;
  var WS_TOKEN = '<?= htmlspecialchars($wsToken) ?>';
  var SID = '<?= htmlspecialchars(session_id()) ?>';
  var CSRF_TOKEN = '<?= htmlspecialchars($csrf_token ?? '') ?>';
  var term = null;
  var fitAddon = null;
  var ws = null;
  var wsReconnectTimer = null;
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

  function setLoading(msg) {
    if (termLoading) termLoading.textContent = msg;
  }

  function startWsServer(cb) {
    setLoading('Starting terminal server...');
    fetch('panel/ssh_exec.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'action=start_ws&csrf_token=' + encodeURIComponent(CSRF_TOKEN)
    }).then(function(r) { return r.json(); }).then(function(data) {
      cb(data.ok);
    }).catch(function() {
      cb(false);
    });
  }

  function connectWs() {
    if (ws) {
      try { ws.close(); } catch(e) {}
      ws = null;
    }
    var url = 'ws://' + WS_HOST + ':' + WS_PORT + '/?token=' + WS_TOKEN + '&sid=' + encodeURIComponent(SID);
    setLoading('Connecting...');
    try {
      ws = new WebSocket(url);
    } catch(e) {
      setLoading('Connection failed. Starting server...');
      startWsServer(function(ok) {
        if (!ok) { showError('Failed to start terminal server'); return; }
        setTimeout(function() {
          try {
            ws = new WebSocket(url);
            bindWs();
          } catch(e) { showError('Connection failed: ' + e.message); }
        }, 500);
      });
      return;
    }
    bindWs();
  }

  function bindWs() {
    if (!ws) return;
    ws.onopen = function() {
      setLoading('Connected');
      if (term) {
        term.focus();
        if (fitAddon) setTimeout(function() { try { fitAddon.fit(); } catch(e) {} }, 50);
      }
    };
    ws.onmessage = function(ev) {
      if (term) {
        term.write(ev.data);
      }
    };
    ws.onerror = function() {
      setLoading('Connection error');
    };
    ws.onclose = function() {
      if (term) {
        term.writeln('\r\n\x1b[31mConnection closed\x1b[0m');
      }
    };
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

      term.onData(function(data) {
        if (ws && ws.readyState === WebSocket.OPEN) {
          ws.send(data);
        }
      });

      connectWs();
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
