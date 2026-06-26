<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Server Control Panel</title>
<link rel="stylesheet" href="panel/control.css">
</head>
<body>
<div class="wrap">
<header>
<h1>Control Panel</h1>
<a href="?logout=1">Logout</a>
</header>

<div class="tabs">
<a href="?tab=dashboard" class="<?= $tab==='dashboard'?'active':'' ?>">Dashboard</a>
<a href="?tab=sites" class="<?= $tab==='sites'?'active':'' ?>">Sites</a>
<a href="?tab=wordpress" class="<?= $tab==='wordpress'?'active':'' ?>">WordPress</a>
<a href="?tab=cloudflare" class="<?= $tab==='cloudflare'?'active':'' ?>">Cloudflare</a>
<a href="?tab=update" class="<?= $tab==='update'?'active':'' ?>">Update</a>
<a href="?tab=files" class="<?= $tab==='files'?'active':'' ?>">File Manager</a>
</div>

<?php if (!empty($flash)): ?>
<div class="flash <?= $flash[0] ?>"><?= $flash[1] ?></div>
<?php endif; ?>
