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

  $('#terminal-wrap').terminal(function(command, term) {
    if (!command.trim()) return;
    return $.ajax({
      url: 'panel/ssh_exec.php',
      method: 'POST',
      data: { cmd: command, csrf_token: CSRF },
      dataType: 'json'
    }).then(function(data) {
      if (data.output) term.echo(data.output);
      if (data.prompt) term.set_prompt(data.prompt);
    }).catch(function(xhr) {
      var msg = 'HTTP ' + (xhr.status || 'error');
      try {
        var d = JSON.parse(xhr.responseText);
        msg = d.output || msg;
      } catch(e) {}
      term.error(msg);
    });
  }, {
    greetings: false,
    prompt: '\x1b[32m$\x1b[0m ',
    name: 'mobile_server_shell',
    height: '100%',
    width: '100%',
    exit: false,
    clear: true,
    keydown: function(e, term) {
      // Ctrl+C while busy
      if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
        return false; // let jQuery Terminal handle it
      }
    }
  });

  // Fix height on window resize
  function fixHeight() {
    try { $('#terminal-wrap').terminal('resize'); } catch(e) {}
  }
  $(window).on('resize', fixHeight);
  setTimeout(fixHeight, 500);
})();
</script>
