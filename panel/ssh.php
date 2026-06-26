<div class="sec" style="padding:0;overflow:hidden;height:calc(100vh - 140px);display:flex;flex-direction:column">
  <div id="term-container" style="flex:1;min-height:0;padding:8px;background:#0d1117">
    <div id="term-status" style="display:flex;align-items:center;justify-content:center;height:100%;color:#8b949e;font-size:15px;gap:10px">
      <i class="fas fa-terminal"></i> Terminal ready
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.min.css" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.min.js" crossorigin="anonymous"></script>
<script>
(function() {
  const CSRF_TOKEN = '<?= htmlspecialchars($csrf_token ?? '') ?>';
  let term = null;
  let fitAddon = null;
  let prompt = '';
  let inputLine = '';
  let cursorPos = 0;

  function execCmd(cmd) {
    return fetch('panel/ssh_exec.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'cmd=' + encodeURIComponent(cmd) + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN)
    }).then(r => r.json());
  }

  function initTerminal() {
    const container = document.getElementById('term-container');
    document.getElementById('term-status').style.display = 'none';
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
    term.open(container);
    term.focus();
    setTimeout(() => { try { fitAddon.fit(); } catch(e) {} }, 50);
    window.addEventListener('resize', function() { if (fitAddon) { try { fitAddon.fit(); } catch(e) {} } });

    term.writeln('Mobile Server Terminal - type commands and press Enter');
    term.writeln('');

    execCmd('').then(function(r) {
      prompt = r.prompt;
      term.write(prompt);
    });

    term.onKey(function(e) {
      const ev = e.domEvent;
      if (ev.altKey || ev.ctrlKey || ev.metaKey) {
        if (ev.ctrlKey && ev.key === 'c') {
          inputLine = '';
          cursorPos = 0;
          term.writeln('^C');
          term.write(prompt);
        }
        return;
      }

      if (ev.key === 'Enter') {
        if (inputLine.trim() === '') {
          term.writeln('');
          term.write(prompt);
          return;
        }
        term.writeln('');
        const cmd = inputLine;
        inputLine = '';
        cursorPos = 0;
        execCmd(cmd).then(function(r) {
          if (r.output) term.write(r.output.replace(/\n/g, '\r\n'));
          if (!r.output.endsWith('\n')) term.writeln('');
          prompt = r.prompt;
          term.write(prompt);
        }).catch(function() {
          term.writeln('\x1b[31mError executing command\x1b[0m');
          term.write(prompt);
        });
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
  }

  initTerminal();
})();
</script>
