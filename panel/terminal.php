<?php
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$hostParts = explode(':', $host);
$panelHost = $hostParts[0];
?>
<div class="sec" style="padding:0;overflow:hidden;height:calc(100vh - 140px);min-height:300px">
<iframe src="http://<?= $panelHost ?>:7681/" style="width:100%;height:100%;border:none" title="Terminal"></iframe>
</div>
