<?php
$run = trim($_GET['run'] ?? '');
if ($run):
@set_time_limit(0);
?>
<style>
.term-out{font-family:'SF Mono','Fira Code',Consolas,monospace;font-size:14px;line-height:1.7;background:var(--bg-body);color:var(--text);padding:18px;border:1px solid var(--border);border-radius:var(--r);overflow:auto;max-height:calc(100vh - 180px);white-space:pre-wrap;word-break:break-all}
.term-out .prompt{color:var(--blue)}
.term-out .success{color:var(--green)}
.term-out .error{color:var(--red)}
</style>
<div class="sec" style="padding:0">
<div class="df jb ac fw g2" style="padding:12px 18px;border-bottom:1px solid var(--border)">
  <div class="st" style="margin-bottom:0"><i class="fas fa-terminal"></i> Running: <?= htmlspecialchars($run) ?></div>
  <a href="/terminal" class="btn btn-s btn-p"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<div class="term-out"><?php
putenv('HOME=' . (getenv('HOME') ?: '/data/data/com.termux/files/home'));
$cmd = str_replace("\r", '', $run);
echo '<span class="prompt">$</span> ' . htmlspecialchars($cmd) . "\n";
exec("$cmd 2>&1", $out, $rc);
echo htmlspecialchars(implode("\n", $out));
echo "\n\n";
if ($rc === 0) {
    echo '<span class="success">✓ Done</span>';
} else {
    echo '<span class="error">✗ Failed (exit ' . $rc . ')</span>';
}
?></div>
</div>
<?php else:
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$hostParts = explode(':', $host);
$ttydHost = $hostParts[0] . ':7681';
?>
<div class="sec" style="padding:0;overflow:hidden;height:calc(100vh - 140px);min-height:300px">
<iframe src="http://<?= htmlspecialchars($ttydHost) ?>" style="width:100%;height:100%;border:none" title="Terminal"></iframe>
</div>
<?php endif; ?>
