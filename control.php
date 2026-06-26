<?php
session_start();

define('CONTROL_PASSWORD', 'admin');
define('LOG_DIR', getenv('HOME') . '/server/logs');
define('LOG_MAX_LINES', 200);

$services = [
    'Nginx'   => ['process' => 'nginx',   'start' => 'nginx',                            'stop' => 'pkill nginx',    'log' => ''],
    'PHP-FPM' => ['process' => 'php-fpm', 'start' => 'php-fpm',                          'stop' => 'pkill php-fpm',  'log' => ''],
    'MariaDB' => ['process' => 'mariadbd','start' => 'mariadbd-safe >/dev/null 2>&1 &', 'stop' => 'pkill mariadbd',  'log' => ''],
];

$log_files = [];
foreach (['nginx', 'php-fpm', 'mariadb'] as $l) {
    $p = LOG_DIR . "/$l.log";
    $log_files[$l] = file_exists($p) ? $p : null;
}

$logged_in = !empty($_SESSION['authenticated']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['password'] === CONTROL_PASSWORD) {
        $_SESSION['authenticated'] = true;
        $logged_in = true;
    } else {
        $login_err = 'Invalid password';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if ($logged_in) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $svc    = $_POST['service'] ?? $_GET['service'] ?? '';

    if ($action && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'restart_all') {
            $cmds = [];
            foreach ($services as $s) { $cmds[] = $s['stop'] . ' 2>/dev/null'; }
            $cmds[] = 'sleep 1';
            foreach ($services as $s) { $cmds[] = $s['start']; }
            exec(implode('; ', $cmds) . ' 2>&1', $raw, $rc);
            $flash = ['success', 'All services restarted'];
        } elseif ($svc && isset($services[$svc])) {
            $s = $services[$svc];
            $cmd = '';
            if ($action === 'start')   $cmd = $s['start'];
            if ($action === 'stop')    $cmd = $s['stop'];
            if ($action === 'restart') $cmd = $s['stop'] . ' 2>/dev/null; sleep 1; ' . $s['start'];
            if ($cmd) {
                exec($cmd . ' 2>&1', $raw, $rc);
                $flash = [$rc === 0 ? 'success' : 'error', ucfirst($action) . " $svc " . ($rc === 0 ? 'done' : 'failed')];
            }
        }
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    $status = [];
    foreach ($services as $name => $s) {
        exec('pgrep -x ' . escapeshellarg($s['process']), $out, $code);
        $status[$name] = $code === 0;
    }

    $hostname   = gethostname();
    $uptime     = trim(shell_exec('uptime -p 2>/dev/null') ?: 'N/A');
    $php_ver    = phpversion();
    $server_time = date('Y-m-d H:i:s');
    exec("ip -4 -o addr show wlan0 2>/dev/null | awk '{print \$4}' | cut -d/ -f1", $ip_out, $ip_rc);
    $ip_addr = ($ip_rc === 0 && !empty($ip_out)) ? $ip_out[0] : (trim(shell_exec('hostname -I 2>/dev/null') ?: 'N/A'));
}

if (!$logged_in):
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Server Login</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#0f172a;font-family:Arial,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px;color:#fff}
.card{background:#1e293b;border-radius:18px;padding:40px;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,.45)}
h1{text-align:center;font-size:28px;margin-bottom:6px}
.sub{text-align:center;color:#94a3b8;margin-bottom:28px;font-size:14px}
label{display:block;font-size:13px;color:#94a3b8;margin-bottom:6px}
input[type=password]{width:100%;padding:12px 14px;border:1px solid #334155;border-radius:10px;background:#0f172a;color:#fff;font-size:15px;outline:none}
input[type=password]:focus{border-color:#3b82f6}
button{width:100%;padding:12px;background:#3b82f6;color:#fff;border:none;border-radius:10px;font-size:16px;font-weight:bold;cursor:pointer;margin-top:18px}
button:hover{background:#2563eb}
.err{background:#ef444422;color:#ef4444;padding:10px;border-radius:8px;text-align:center;margin-bottom:16px;font-size:14px}
</style>
</head>
<body>
<div class="card">
<h1>Server Control</h1>
<div class="sub">Enter password to continue</div>
<?php if (!empty($login_err)): ?><div class="err"><?= htmlspecialchars($login_err) ?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="login" value="1">
<label for="pw">Password</label>
<input type="password" name="password" id="pw" placeholder="Enter password" required>
<button type="submit">Unlock</button>
</form>
</div>
</body>
</html>
<?php exit; endif; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Server Control Panel</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#0f172a;font-family:Arial,sans-serif;color:#fff;padding:20px;min-height:100vh}
.wrap{max-width:960px;margin:0 auto}
header{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;flex-wrap:wrap;gap:12px}
header h1{font-size:26px}
header a{color:#94a3b8;text-decoration:none;font-size:13px;padding:6px 14px;border:1px solid #334155;border-radius:8px}
header a:hover{background:#1e293b}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-bottom:24px}
.card{background:#1e293b;border-radius:14px;padding:22px;box-shadow:0 8px 30px rgba(0,0,0,.3)}
.card h2{font-size:16px;margin-bottom:6px;display:flex;justify-content:space-between;align-items:center}
.card h2 span{font-size:12px;font-weight:normal}
.badge{display:inline-block;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:bold}
.running{background:#22c55e22;color:#22c55e}
.stopped{background:#ef444422;color:#ef4444}
.actions{display:flex;gap:6px;margin-top:14px;flex-wrap:wrap}
.actions form{flex:1;min-width:60px}
.actions button{width:100%;padding:8px;border:none;border-radius:8px;font-size:12px;font-weight:bold;cursor:pointer}
.btn-start{background:#22c55e22;color:#22c55e}
.btn-start:hover{background:#22c55e44}
.btn-stop{background:#ef444422;color:#ef4444}
.btn-stop:hover{background:#ef444444}
.btn-restart{background:#f59e0b22;color:#f59e0b}
.btn-restart:hover{background:#f59e0b44}
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:24px}
.info-item{background:#0f172a;padding:14px 16px;border-radius:10px}
.info-item .label{font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px}
.info-item .value{font-size:15px;font-weight:bold;margin-top:4px;word-break:break-all}
.logs{background:#1e293b;border-radius:14px;padding:22px;margin-bottom:24px}
.logs h2{margin-bottom:12px;font-size:16px}
.logs select{padding:8px 12px;border:1px solid #334155;border-radius:8px;background:#0f172a;color:#fff;font-size:13px;margin-bottom:12px;outline:none}
.logs select:focus{border-color:#3b82f6}
pre{background:#0f172a;padding:14px;border-radius:10px;font-size:12px;max-height:300px;overflow:auto;color:#94a3b8;line-height:1.5;white-space:pre-wrap;word-break:break-all}
.flash{padding:12px 18px;border-radius:10px;margin-bottom:20px;font-weight:bold;font-size:14px}
.flash.success{background:#22c55e22;color:#22c55e}
.flash.error{background:#ef444422;color:#ef4444}
.restart-all{text-align:right;margin-bottom:16px}
.restart-all button{background:#f59e0b22;color:#f59e0b;border:1px solid #f59e0b44;padding:8px 20px;border-radius:8px;cursor:pointer;font-weight:bold;font-size:13px}
.restart-all button:hover{background:#f59e0b44}
footer{text-align:center;color:#475569;font-size:13px;padding:20px 0 10px}
::-webkit-scrollbar{width:6px}
::-webkit-scrollbar-thumb{background:#334155;border-radius:3px}
@media(max-width:600px){
  body{padding:12px}
  .card{padding:16px}
  .grid{grid-template-columns:1fr}
}
</style>
</head>
<body>
<div class="wrap">
<header>
<h1>Server Control</h1>
<a href="?logout=1">Logout</a>
</header>

<?php if (!empty($flash)): ?>
<div class="flash <?= $flash[0] ?>"><?= htmlspecialchars($flash[1]) ?></div>
<?php endif; ?>

<form method="post" class="restart-all">
<input type="hidden" name="action" value="restart_all">
<button type="submit" onclick="return confirm('Restart all services?')">Restart All</button>
</form>

<div class="grid">
<?php foreach ($services as $name => $s):
$is_running = $status[$name];
?>
<div class="card">
<h2>
<?= htmlspecialchars($name) ?>
<span class="badge <?= $is_running ? 'running' : 'stopped' ?>"><?= $is_running ? 'Running' : 'Stopped' ?></span>
</h2>
<div class="actions">
<?php if (!$is_running): ?>
<form method="post"><input type="hidden" name="action" value="start"><input type="hidden" name="service" value="<?= $name ?>"><button type="submit" class="btn-start">Start</button></form>
<?php else: ?>
<form method="post"><input type="hidden" name="action" value="stop"><input type="hidden" name="service" value="<?= $name ?>"><button type="submit" class="btn-stop" onclick="return confirm('Stop <?= $name ?>?')">Stop</button></form>
<?php endif; ?>
<form method="post"><input type="hidden" name="action" value="restart"><input type="hidden" name="service" value="<?= $name ?>"><button type="submit" class="btn-restart" onclick="return confirm('Restart <?= $name ?>?')">Restart</button></form>
</div>
</div>
<?php endforeach; ?>
</div>

<div class="info-grid">
<div class="info-item"><div class="label">Hostname</div><div class="value"><?= htmlspecialchars($hostname) ?></div></div>
<div class="info-item"><div class="label">IP Address</div><div class="value"><?= htmlspecialchars($ip_addr) ?></div></div>
<div class="info-item"><div class="label">Server Time</div><div class="value"><?= htmlspecialchars($server_time) ?></div></div>
<div class="info-item"><div class="label">Uptime</div><div class="value"><?= htmlspecialchars($uptime) ?></div></div>
<div class="info-item"><div class="label">PHP Version</div><div class="value"><?= htmlspecialchars($php_ver) ?></div></div>
<div class="info-item"><div class="label">Web Server</div><div class="value">Nginx on :8080</div></div>
</div>

<div class="logs">
<h2>Log Viewer</h2>
<form method="get">
<select name="log" onchange="this.form.submit()">
<option value="">-- Select log --</option>
<?php foreach ($log_files as $name => $path): ?>
<option value="<?= $name ?>" <?= ($_GET['log'] ?? '') === $name ? 'selected' : '' ?>><?= ucfirst($name) ?>.log<?= $path ? '' : ' (empty)' ?></option>
<?php endforeach; ?>
</select>
</form>
<?php
if (isset($_GET['log']) && isset($log_files[$_GET['log']]) && $log_files[$_GET['log']]) {
    $lines = file($log_files[$_GET['log']]);
    $lines = $lines ? array_slice($lines, -LOG_MAX_LINES) : [];
    echo '<pre>' . ($lines ? htmlspecialchars(implode('', $lines)) : 'Log is empty') . '</pre>';
} elseif (isset($_GET['log'])) {
    echo '<pre>Log file not found</pre>';
} else {
    echo '<pre style="color:#475569">Select a log file above</pre>';
}
?>
</div>

<footer>Mobile Server Control Panel &mdash; Nginx &bull; PHP-FPM &bull; MariaDB</footer>
</div>
</body>
</html>
