<?php
date_default_timezone_set('Asia/Dhaka');

// Route phpMyAdmin requests to the Termux phpMyAdmin installation
$pmaPath = '/data/data/com.termux/files/usr/share/phpmyadmin';
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/phpmyadmin') === 0) {
    if (is_dir($pmaPath)) {
        $reqPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $relPath = substr($reqPath, strlen('/phpmyadmin'));
        if (!$relPath || $relPath === '/') {
            $_SERVER['SCRIPT_NAME'] = '/phpmyadmin/index.php';
            $_SERVER['SCRIPT_FILENAME'] = $pmaPath . '/index.php';
            chdir($pmaPath);
            require $pmaPath . '/index.php';
            exit;
        }
        $file = $pmaPath . '/' . ltrim($relPath, '/');
        if (is_file($file)) {
            $mime = mime_content_type($file) ?: 'application/octet-stream';
            header('Content-Type: ' . $mime);
            readfile($file);
            exit;
        }
    }
    http_response_code(404);
    echo 'phpMyAdmin not found. Install: pkg install phpmyadmin';
    exit;
}

function status($process) {
    exec("pgrep -x " . escapeshellarg($process), $out, $code);
    return $code === 0;
}

$services = [
    "Nginx"   => "nginx",
    "PHP-FPM" => "php-fpm",
    "MariaDB" => "mariadbd"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mobile Server</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{
background:linear-gradient(135deg,#0f172a,#1e293b);
font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
color:#fff;
display:flex;
justify-content:center;
align-items:center;
min-height:100vh;
padding:20px;
}
.card{
width:100%;
max-width:480px;
background:rgba(30,41,59,.7);
backdrop-filter:blur(20px);
-webkit-backdrop-filter:blur(20px);
border:1px solid rgba(148,163,184,.1);
border-radius:24px;
padding:40px;
box-shadow:0 25px 80px rgba(0,0,0,.5);
}
h1{
text-align:center;
font-size:28px;
font-weight:700;
letter-spacing:-.5px;
margin-bottom:4px;
}
.subtitle{
text-align:center;
color:#64748b;
margin-bottom:32px;
}
.item{
display:flex;
justify-content:space-between;
align-items:center;
padding:16px 0;
border-bottom:1px solid rgba(51,65,85,.5);
}
.item:last-child{border:none}
.name{
font-weight:500;
color:#e2e8f0;
}
.status{
display:flex;
align-items:center;
gap:8px;
font-weight:600;
}
.dot{
width:8px;height:8px;border-radius:50%;display:inline-block
}
.dot.on{background:#22c55e;box-shadow:0 0 12px rgba(34,197,94,.5)}
.dot.off{background:#ef4444;box-shadow:0 0 12px rgba(239,68,68,.5)}
.footer{
margin-top:32px;
text-align:center;
}
.footer a{
display:inline-flex;
align-items:center;
gap:8px;
padding:12px 28px;
background:linear-gradient(135deg,#3b82f6,#2563eb);
color:#fff;
text-decoration:none;
border-radius:12px;
font-weight:600;
transition:transform .2s,box-shadow .2s;
box-shadow:0 4px 20px rgba(59,130,246,.3);
}
.footer a:hover{
transform:translateY(-2px);
box-shadow:0 8px 30px rgba(59,130,246,.4);
}
</style>
</head>
<body>
<div class="card">
<h1>Server Status</h1>
<div class="subtitle">Mobile Server Dashboard</div>
<?php foreach($services as $name=>$proc):
$on=status($proc);
?>
<div class="item">
<span class="name"><?= htmlspecialchars($name) ?></span>
<span class="status">
<span class="dot <?= $on?'on':'off' ?>"></span>
<?= $on?'Running':'Stopped' ?>
</span>
</div>
<?php endforeach; ?>
<div class="footer">
<a href="control.php">Control Panel &rarr;</a>
</div>
</div>
</body>
</html>
