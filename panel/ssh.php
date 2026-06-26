<div class="sec" style="padding:0;overflow:hidden;height:calc(100vh - 140px);display:flex;flex-direction:column">
  <div id="term-container" style="flex:1;min-height:0;padding:8px;background:#0d1117">
    <div id="term-status" style="display:flex;align-items:center;justify-content:center;height:100%;color:#8b949e;font-size:15px;gap:10px">
      <i class="fas fa-spinner fa-pulse"></i> Connecting to terminal...
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.min.css" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.min.js" crossorigin="anonymous"></script>
<script>
(function() {
  const CSRF_TOKEN = '<?= htmlspecialchars($csrf_token ?? '') ?>';
  const WS_PORT = 8023;
  let ws = null;
  let term = null;
  let fitAddon = null;
  let reconnectTimer = null;

  function startServer(cb) {
    fetch('?tab=ssh', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'action=start_ssh_ws&csrf_token=' + encodeURIComponent(CSRF_TOKEN)
    })
      .then(r => r.text())
      .then(() => { if (cb) cb(); })
      .catch(() => { if (cb) cb(); });
  }

  function connect() {
    const status = document.getElementById('term-status');
    if (ws && ws.readyState === WebSocket.OPEN) return;

    try {
      ws = new WebSocket('ws://127.0.0.1:' + WS_PORT);
    } catch(e) {
      status.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#f59e0b"></i> Connection failed. <a href="#" onclick="location.reload();return false" style="color:#3b82f6">Retry</a>';
      return;
    }

    ws.onopen = function() {
      status.style.display = 'none';
      if (!term) initTerminal();
      term.reset();
    };

    ws.onmessage = function(e) {
      if (term) {
        if (e.data instanceof Blob) {
          e.data.arrayBuffer().then(buf => {
            term.write(new Uint8Array(buf));
          });
        } else {
          term.write(e.data);
        }
      }
    };

    ws.onclose = function() {
      status.style.display = 'flex';
      status.innerHTML = '<i class="fas fa-plug" style="color:#f59e0b"></i> Disconnected. <a href="#" onclick="location.reload();return false" style="color:#3b82f6">Reconnect</a>';
      if (term) { term.dispose(); term = null; }
      if (reconnectTimer) clearTimeout(reconnectTimer);
      reconnectTimer = setTimeout(connect, 3000);
    };

    ws.onerror = function() {
      startServer(function() {
        if (reconnectTimer) clearTimeout(reconnectTimer);
        reconnectTimer = setTimeout(connect, 1000);
      });
    };
  }

  function initTerminal() {
    const container = document.getElementById('term-container');
    fitAddon = new FitAddon.FitAddon();
    term = new Terminal({
      cursorBlink: true,
      cursorStyle: 'block',
      fontSize: 14,
      fontFamily: "'SF Mono','Fira Code','Cascadia Code','JetBrains Mono','Noto Mono',Consolas,monospace",
      theme: {
        background: '#0d1117',
        foreground: '#c9d1d9',
        cursor: '#c9d1d9',
        selectionBackground: '#3b82f622',
        black: '#484f58',
        red: '#ff7b72',
        green: '#3fb950',
        yellow: '#d29922',
        blue: '#58a6ff',
        magenta: '#bc8cff',
        cyan: '#39c5cf',
        white: '#b1bac4',
        brightBlack: '#6e7681',
        brightRed: '#ffa198',
        brightGreen: '#56d364',
        brightYellow: '#e3b341',
        brightBlue: '#79c0ff',
        brightMagenta: '#d2a8ff',
        brightCyan: '#56d4dd',
        brightWhite: '#f0f6fc',
      },
      allowTransparency: true,
    });

    term.loadAddon(fitAddon);
    term.open(container);
    term.focus();

    setTimeout(() => { try { fitAddon.fit(); } catch(e) {} }, 50);

    term.onData(function(data) {
      if (ws && ws.readyState === WebSocket.OPEN) {
        ws.send(data);
      }
    });

    window.addEventListener('resize', function() {
      if (fitAddon) { try { fitAddon.fit(); } catch(e) {} }
    });
  }

  startServer(function() {
    setTimeout(connect, 500);
  });
})();
</script>
