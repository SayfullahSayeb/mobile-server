<?php
$run = trim($_GET['run'] ?? '');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$hostParts = explode(':', $host);
$panelHost = $hostParts[0];
?>
<style>
.term-w{background:#0d1117;color:#e6edf3;padding:18px;font-family:'SF Mono','Fira Code',Consolas,monospace;font-size:14px;line-height:1.6;overflow:auto;max-height:calc(100vh - 180px);white-space:pre-wrap;word-break:break-all;border-radius:0 0 var(--r) var(--r);margin:0}
.term-w .prompt{color:#58a6ff}
.term-w .ok{color:#3fb950}
.term-w .fail{color:#f85149}
</style>
<?php if ($run):
@set_time_limit(0);
putenv('HOME=' . (getenv('HOME') ?: '/data/data/com.termux/files/home'));
$cmd = str_replace("\r", '', $run);
?>
<div class="sec" style="padding:0">
<div class="df jb ac g2" style="padding:12px 18px;border-bottom:1px solid var(--border);background:var(--bg-sidebar);border-radius:var(--r) var(--r) 0 0">
  <span><i class="fas fa-terminal"></i> Running: <?= htmlspecialchars($run) ?></span>
  <a href="/terminal" class="btn btn-s btn-p"><i class="fas fa-times"></i> Close</a>
</div>
<pre class="term-w"><span class="prompt">$</span> <?= htmlspecialchars($cmd) . "\n" ?><?php
$process = proc_open("$cmd 2>&1", [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
], $pipes);
if (is_resource($process)) {
    fclose($pipes[0]);
    while (!feof($pipes[1])) {
        echo htmlspecialchars(fgets($pipes[1]));
        ob_flush();
        flush();
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    $rc = proc_close($process);
}
echo "\n\n";
if ($rc === 0) {
    echo '<span class="ok">✔ Done (exit 0)</span>';
} else {
    echo '<span class="fail">✘ Failed (exit ' . $rc . ')</span>';
}
?></pre>
</div>
<?php else: ?>
<div class="sec" style="padding:0;overflow:hidden;height:calc(100vh - 140px);min-height:300px">
<iframe src="http://<?= $panelHost ?>:7681/" style="width:100%;height:100%;border:none" title="Terminal"></iframe>
</div>
<?php endif; ?>
