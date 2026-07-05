<style>
#term-wrapper { background:#0d1117; }
.xterm { height:100%; padding:8px; }
.xterm-viewport { scrollbar-width:thin; scrollbar-color:#30363d transparent; }
</style>
<div class="sec" style="padding:0;overflow:hidden;height:calc(100vh - 140px);min-height:300px;display:flex;flex-direction:column">
  <div id="term-wrapper" style="flex:1;min-height:0;position:relative">
    <div id="term-loading" style="display:flex;align-items:center;justify-content:center;height:100%;color:#64748b;font-size:14px">
      <i class="fas fa-spinner fa-spin" style="margin-right:8px"></i> Loading terminal...
    </div>
  </div>
</div>

<?php $csrf = $_SESSION['csrf_token'] ?? ''; ?>

<script>
(function() {
  'use strict';
  var CSRF = '<?= htmlspecialchars($csrf) ?>';
  var wrapper = document.getElementById('term-wrapper');
  var loading = document.getElementById('term-loading');

  var term, fitAddon;
  var buf = '';
  var promptVisLen = 2; // visible length of current prompt
  var promptRaw = '\x1b[32m$\x1b[0m ';
  var history = [];
  var histIdx = -1;
  var execBusy = false;

  function stripAnsi(s) {
    return s.replace(/\x1b\[[0-9;]*[a-zA-Z]/g, '');
  }

  function visLen(s) {
    return stripAnsi(s).length;
  }

  function writeFirstPrompt() {
    term.write(promptRaw);
    buf = '';
    promptVisLen = visLen(promptRaw);
  }

  function writePrompt() {
    term.write('\r\n' + promptRaw);
    buf = '';
    promptVisLen = visLen(promptRaw);
  }

  function getCursorAbs() {
    return promptVisLen + buf.length;
  }

  function redraw() {
    var cols = term.cols;
    var display = promptRaw + buf;
    var totalVis = visLen(display);
    var cursorAbs = getCursorAbs();

    term.write('\r\x1b[K');
    term.write(display);

    var totalRows = Math.ceil(totalVis / cols) || 1;
    var cursorRow = Math.floor(cursorAbs / cols);
    var cursorCol = cursorAbs % cols;
    var moveUp = totalRows - 1 - cursorRow;
    if (moveUp > 0) term.write('\x1b[' + moveUp + 'A');
    if (cursorCol > 0) term.write('\x1b[' + cursorCol + 'C');
  }

  function insertAtCursor(ch) {
    if (execBusy) return;
    if (buf.length < 4096) {
      buf = buf.slice(0, cursorPos()) + ch + buf.slice(cursorPos());
    }
    redraw();
  }

  function cursorPos() {
    return buf.length;
  }

  function handleKey(key, ev) {
    if (execBusy && key !== '\x03') return;
    if (ev && ev.preventDefault) ev.preventDefault();
    var p = key.length === 1 && ev.key !== 'Backspace' && !ev.ctrlKey && !ev.altKey && key.charCodeAt(0) >= 0x20 && key.charCodeAt(0) < 0x7f;
    if (p) { insertAtCursor(key); return; }
    switch (key) {
      case '\r': submit(); break;
      case '\x7f': case '\b':
        if (buf.length > 0) { buf = buf.slice(0, -1); redraw(); }
        break;
      case '\x1b[A': historyUp(); break;
      case '\x1b[B': historyDown(); break;
      case '\x03':
        if (execBusy) {
          execBusy = false;
          term.write('^C\r\n' + promptRaw);
          buf = '';
        } else {
          term.write('^C\r\n' + promptRaw);
          buf = '';
        }
        break;
      case '\t':
        for (var i = 0; i < 4; i++) insertAtCursor(' ');
        break;
      case '\x04':
        if (buf === '') { buf = 'exit'; submit(); }
        break;
      case '\x0c':
        term.write('\x1bc' + promptRaw);
        buf = '';
        break;
      case '\x15': buf = ''; redraw(); break;
      case '\x1b[3~':
        buf = buf.slice(0, -1);
        redraw();
        break;
    }
  }

  function historyUp() {
    if (history.length === 0) return;
    if (histIdx === -1) histIdx = history.length - 1;
    else if (histIdx > 0) histIdx--;
    else return;
    buf = history[histIdx];
    redraw();
  }

  function historyDown() {
    if (histIdx === -1) return;
    histIdx++;
    if (histIdx >= history.length) { histIdx = -1; buf = ''; redraw(); return; }
    buf = history[histIdx];
    redraw();
  }

  function submit() {
    var cmd = buf;
    if (cmd.trim() !== '') {
      history.push(cmd);
      if (history.length > 1000) history.shift();
    }
    histIdx = -1;
    execBusy = true;
    term.write('\r\n');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'panel/ssh_exec.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.responseType = 'json';
    xhr.onload = function() {
      if (xhr.status === 200 && xhr.response) {
        var d = xhr.response;
        if (d.output) term.write(d.output);
        var p = d.prompt || promptRaw;
        promptRaw = p;
        term.write(promptRaw);
        buf = '';
        promptVisLen = visLen(promptRaw);
      } else {
        term.write('\r\n\x1b[31mError: HTTP ' + xhr.status + '\x1b[0m\r\n' + promptRaw);
        buf = '';
      }
      execBusy = false;
      term.focus();
    };
    xhr.onerror = function() {
      term.write('\x1b[31mNetwork error\x1b[0m\r\n' + promptRaw);
      buf = '';
      execBusy = false;
      term.focus();
    };
    xhr.timeout = 30000;
    xhr.ontimeout = function() {
      term.write('\x1b[31mCommand timed out (30s)\x1b[0m\r\n' + promptRaw);
      buf = '';
      execBusy = false;
      term.focus();
    };
    xhr.send('cmd=' + encodeURIComponent(cmd) + '&csrf_token=' + encodeURIComponent(CSRF));
  }

  function init() {
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.min.css';
    document.head.appendChild(link);

    var scriptUrls = [
      'https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js',
      'https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.min.js',
    ];

    function loadAll(idx) {
      if (idx >= scriptUrls.length) { boot(); return; }
      var s = document.createElement('script');
      s.src = scriptUrls[idx];
      s.onload = function() { loadAll(idx + 1); };
      s.onerror = function() {
        var fb = document.createElement('script');
        fb.src = scriptUrls[idx].replace('cdn.jsdelivr.net/npm/', 'unpkg.com/');
        fb.onload = function() { loadAll(idx + 1); };
        fb.onerror = function() { loading.innerHTML = '<span style="color:#ff7b72">Failed to load terminal library</span>'; };
        document.head.appendChild(fb);
      };
      document.head.appendChild(s);
    }

    function boot() {
      if (typeof Terminal === 'undefined') {
        loading.innerHTML = '<span style="color:#ff7b72">Terminal library not available</span>';
        return;
      }
      loading.style.display = 'none';
      var container = document.createElement('div');
      container.style.cssText = 'width:100%;height:100%';
      wrapper.appendChild(container);
      fitAddon = new FitAddon.FitAddon();
      term = new Terminal({
        cursorBlink: true, cursorStyle: 'block', fontSize: 14,
        fontFamily: "'SF Mono','Fira Code','Cascadia Code','JetBrains Mono','Noto Mono',Consolas,monospace",
        theme: {
          background: '#0d1117', foreground: '#c9d1d9', cursor: '#c9d1d9', selectionBackground: '#3b82f622',
          black: '#484f58', red: '#ff7b72', green: '#3fb950', yellow: '#d29922',
          blue: '#58a6ff', magenta: '#bc8cff', cyan: '#39c5cf', white: '#b1bac4',
          brightBlack: '#6e7681', brightRed: '#ffa198', brightGreen: '#56d364',
          brightYellow: '#e3b341', brightBlue: '#79c0ff', brightMagenta: '#d2a8ff',
          brightCyan: '#56d4dd', brightWhite: '#f0f6fc',
        },
        allowTransparency: true, allowProposedApi: true,
      });
      term.loadAddon(fitAddon);
      term.open(container);
      term.onKey(function(ev) { handleKey(ev.key, ev.domEvent); });
      term.attachCustomKeyEventHandler(function(e) {
        if (e.type === 'keydown' && e.ctrlKey && e.key === 'v') {
          navigator.clipboard.readText().then(function(t) {
            for (var i = 0; i < t.length; i++) {
              handleKey(t[i], {ctrlKey: false, altKey: false, preventDefault: function(){}});
            }
          });
          return false;
        }
        return true;
      });
      container.addEventListener('click', function() { term.focus(); });
      fitAddon.fit();
      window.addEventListener('resize', function() { try { fitAddon.fit(); } catch(e) {} });
      writeFirstPrompt();
    }

    loadAll(0);
  }

  init();
})();
</script>
