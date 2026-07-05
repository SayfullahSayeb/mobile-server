<?php
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$hostParts = explode(':', $host);
$ttydHost = $hostParts[0] . ':7681';
?>
<div class="sec" style="padding:0;overflow:hidden;height:calc(100vh - 140px);min-height:300px">
<iframe src="http://<?= htmlspecialchars($ttydHost) ?>" style="width:100%;height:100%;border:none" title="Terminal"></iframe>
</div>
