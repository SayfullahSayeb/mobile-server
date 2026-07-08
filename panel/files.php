<?php
$filePath = isset($_GET['p']) ? '?p=' . urlencode($_GET['p']) : '';
?>
<iframe src="/filemanager/panel.php<?= $filePath ?>" class="ef" title="File Manager" allowfullscreen></iframe>
