<?php
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$hostParts = explode(':', $host);
$ttydHost = $hostParts[0] . ':7681';
$run = trim($_GET['run'] ?? '');
if ($run) {
    $ttydUrl = 'http://' . htmlspecialchars($ttydHost) . '/?arg=-c&arg=' . urlencode($run);
} else {
    $ttydUrl = 'http://' . htmlspecialchars($ttydHost);
}
?>
<div class="sec" style="padding:0;overflow:hidden;height:calc(100vh - 140px);min-height:300px">
<iframe src="<?= $ttydUrl ?>" style="width:100%;height:100%;border:none" title="Terminal"></iframe>
</div>
