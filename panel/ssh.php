<?php $csrf = $_SESSION['csrf_token'] ?? ''; ?>

<style>
#terminal-wrap { width:100%; height:100%; }
#terminal-wrap .terminal { padding:10px !important; }
</style>
<div class="sec" style="padding:0;overflow:hidden;height:calc(100vh - 140px);min-height:300px;display:flex;flex-direction:column">
  <div id="terminal-wrap" style="flex:1;min-height:0"></div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery.terminal@2.46.1/js/jquery.terminal.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery.terminal@2.46.1/js/unix_formatting.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jquery.terminal@2.46.1/css/jquery.terminal.min.css">

<script>
(function() {
  var CSRF = '<?= htmlspecialchars($csrf) ?>';

  $.terminal.defaults.formatters = [$.terminal.from_ansi];

  function tryParse(line) {
    try { return JSON.parse(line); } catch(e) { return null; }
  }

  $('#terminal-wrap').terminal(function(command, term) {
    if (!command.trim()) return;

    return fetch('panel/ssh_exec.php?action=run', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'cmd=' + encodeURIComponent(command) + '&csrf_token=' + encodeURIComponent(CSRF)
    }).then(function(response) {
      if (!response.ok) throw new Error('HTTP ' + response.status);

      var reader = response.body.getReader();
      var decoder = new TextDecoder();
      var buf = '';

      function pump() {
        return reader.read().then(function(result) {
          if (result.done) {
            var last = tryParse(buf.trim());
            if (last && last.t === 'p') term.set_prompt(last.d);
            return;
          }
          buf += decoder.decode(result.value, {stream: true});
          var lines = buf.split('\n');
          buf = lines.pop() || '';
          for (var i = 0; i < lines.length; i++) {
            var line = lines[i].trim();
            if (!line) continue;
            var msg = tryParse(line);
            if (!msg) continue;
            if (msg.t === 'o' && msg.d) {
              // Remove trailing newlines for cleaner display
              var text = msg.d.replace(/\n$/, '');
              if (text) term.echo(text);
            } else if (msg.t === 'p') {
              term.set_prompt(msg.d);
            }
            // 'd' (done) has no further action — we just stop reading
          }
          return pump();
        });
      }
      return pump();
    }).catch(function(err) {
      term.error('Error: ' + err.message);
    });
  }, {
    greetings: false,
    prompt: '$ ',
    name: 'ms_term',
    height: '100%',
    exit: false,
    clear: true,
    fontSize: 16,
  });

  function fixHeight() {
    try { $('#terminal-wrap').terminal('resize'); } catch(e) {}
  }
  $(window).on('resize', fixHeight);
  setTimeout(fixHeight, 500);
})();
</script>
