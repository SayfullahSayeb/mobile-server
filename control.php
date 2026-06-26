<?php
session_start();

define('CONTROL_PASSWORD', 'admin');
define('HOME_DIR', getenv('HOME') ?: '/data/data/com.termux/files/home');
define('SITES_DIR', HOME_DIR . '/server/sites');
define('DEFAULT_SITE_DIR', SITES_DIR . '/default/public_html');
define('LOG_DIR', HOME_DIR . '/server/logs');
define('CONFIG_DIR', HOME_DIR . '/server/configs');
define('LOG_MAX_LINES', 200);

$services = [
    'Nginx'   => ['process' => 'nginx',   'start' => 'nginx',                            'stop' => 'pkill nginx',    'log' => ''],
    'PHP-FPM' => ['process' => 'php-fpm', 'start' => 'php-fpm',                          'stop' => 'pkill php-fpm',  'log' => ''],
    'MariaDB' => ['process' => 'mariadbd','start' => 'mariadbd-safe >/dev/null 2>&1 &', 'stop' => 'pkill mariadbd',  'log' => ''],
];

$log_files = [];
foreach (['nginx', 'php-fpm', 'mariadb'] as $l) {
    $p = LOG_DIR . "/$l.log";
    $log_files[$l] = is_file($p) ? $p : null;
}

$db_user = 'wp_user';
$db_pass = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 16);

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
    header('Location: ?');
    exit;
}

$tab = $_GET['tab'] ?? 'dashboard';

if ($logged_in) {
    $action = $_POST['action'] ?? '';

    if ($action && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'restart_all') {
            $cmds = [];
            foreach ($services as $s) { $cmds[] = $s['stop'] . ' 2>/dev/null'; }
            $cmds[] = 'sleep 1';
            foreach ($services as $s) { $cmds[] = $s['start']; }
            exec(implode('; ', $cmds) . ' 2>&1', $raw, $rc);
            $flash = ['success', 'All services restarted'];
        } elseif (isset($services[$_POST['service'] ?? ''])) {
            $s = $services[$_POST['service']];
            $svc = $_POST['service'];
            if ($action === 'start')   $cmd = $s['start'];
            if ($action === 'stop')    $cmd = $s['stop'];
            if ($action === 'restart') $cmd = $s['stop'] . ' 2>/dev/null; sleep 1; ' . $s['start'];
            if (isset($cmd)) {
                exec($cmd . ' 2>&1', $raw, $rc);
                $flash = [$rc === 0 ? 'success' : 'error', ucfirst($action) . " $svc " . ($rc === 0 ? 'done' : 'failed')];
            }
        } elseif ($action === 'create_site') {
            $name = trim($_POST['site_name'] ?? '');
            if ($name && preg_match('/^[a-z0-9_-]+$/', $name)) {
                $path = DEFAULT_SITE_DIR . '/' . $name;
                if (!is_dir($path)) {
                    mkdir($path, 0755, true);
                    file_put_contents($path . '/index.php', "<?php\nheader('Location: /');\n");
                    $flash = ['success', "Site '$name' created"];
                } else {
                    $flash = ['error', "Site '$name' already exists"];
                }
            } else {
                $flash = ['error', 'Invalid site name (use a-z, 0-9, -, _)'];
            }
        } elseif ($action === 'delete_site') {
            $name = trim($_POST['site_name'] ?? '');
            if ($name && preg_match('/^[a-z0-9_-]+$/', $name)) {
                $path = DEFAULT_SITE_DIR . '/' . $name;
                if (is_dir($path)) {
                    exec("rm -rf " . escapeshellarg($path), $raw, $rc);
                    $flash = [$rc === 0 ? 'success' : 'error', $rc === 0 ? "Site '$name' deleted" : "Failed to delete '$name'"];
                } else {
                    $flash = ['error', "Site '$name' not found"];
                }
            }
        } elseif ($action === 'wp_install') {
            $site_name = trim($_POST['wp_site'] ?? '');
            $wp_user = trim($_POST['wp_user'] ?? 'admin');
            $wp_pass = trim($_POST['wp_pass'] ?? '');
            $wp_email = trim($_POST['wp_email'] ?? 'admin@localhost.local');
            $wp_title = trim($_POST['wp_title'] ?? 'My Site');

            if (!$site_name || !preg_match('/^[a-z0-9_-]+$/', $site_name)) {
                $flash = ['error', 'Invalid site name'];
            } elseif (!$wp_pass || strlen($wp_pass) < 6) {
                $flash = ['error', 'Password must be at least 6 characters'];
            } else {
                $site_path = DEFAULT_SITE_DIR . '/' . $site_name;
                if (is_dir($site_path) && glob($site_path . '/*')) {
                    $flash = ['error', "Site dir '$site_name' is not empty"];
                } else {
                    if (!is_dir($site_path)) mkdir($site_path, 0755, true);
                    $zip = HOME_DIR . '/server/wp.zip';
                    exec("curl -sL https://wordpress.org/latest.zip -o " . escapeshellarg($zip) . " 2>&1", $raw, $rc);
                    if ($rc !== 0) {
                        $flash = ['error', 'Failed to download WordPress'];
                    } else {
                        exec("unzip -qo " . escapeshellarg($zip) . " -d " . escapeshellarg(dirname($site_path)) . " 2>&1", $raw, $rc2);
                        if ($rc2 !== 0) {
                            $flash = ['error', 'Failed to extract WordPress'];
                        } else {
                            $wp_temp = dirname($site_path) . '/wordpress';
                            if (is_dir($wp_temp)) {
                                exec("shopt -s dotglob; mv " . escapeshellarg($wp_temp . '/*') . " " . escapeshellarg($site_path . '/') . " 2>/dev/null; rm -rf " . escapeshellarg($wp_temp));
                            }
                            $db_name = 'wp_' . str_replace('-', '_', $site_name);
                            exec("mariadb -e \"CREATE DATABASE IF NOT EXISTS $db_name\" 2>&1", $raw, $rc3);
                            exec("mariadb -e \"CREATE USER IF NOT EXISTS '$db_user'@'localhost' IDENTIFIED BY '$db_pass'\" 2>&1", $raw, $rc4);
                            exec("mariadb -e \"GRANT ALL PRIVILEGES ON $db_name.* TO '$db_user'@'localhost'; FLUSH PRIVILEGES\" 2>&1", $raw, $rc5);

                            $wp_config = file_get_contents($site_path . '/wp-config-sample.php');
                            if ($wp_config) {
                                $salts = exec("curl -sL https://api.wordpress.org/secret-key/1.1/salt/ 2>/dev/null");
                                $wp_config = str_replace(
                                    ["'database_name_here'", "'username_here'", "'password_here'"],
                                    ["'$db_name'", "'$db_user'", "'$db_pass'"],
                                    $wp_config
                                );
                                foreach (['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT'] as $key) {
                                    $val = bin2hex(random_bytes(16));
                                    $wp_config = preg_replace("/define\('$key',\s*'[^']*'\);/", "define('$key', '$val');", $wp_config);
                                }
                                file_put_contents($site_path . '/wp-config.php', $wp_config);
                            }
                            exec("chmod 755 " . escapeshellarg($site_path));
                            @unlink($zip);
                            $flash = ['success', "WordPress installed! Site: <a href='/$site_name/wp-admin/install.php' style='color:#3b82f6'>/$site_name/wp-admin/install.php</a> | DB: $db_name | DB User: $db_user | DB Pass: $db_pass"];
                        }
                    }
                }
            }
        } elseif ($action === 'fm_upload') {
            if (!empty($_FILES['fm_file']['name'])) {
                $target = $_POST['fm_path'] ?? DEFAULT_SITE_DIR;
                $dest = $target . '/' . basename($_FILES['fm_file']['name']);
                if (move_uploaded_file($_FILES['fm_file']['tmp_name'], $dest)) {
                    $flash = ['success', 'File uploaded'];
                } else {
                    $flash = ['error', 'Upload failed'];
                }
            }
        } elseif ($action === 'fm_mkdir') {
            $dir = trim($_POST['fm_newdir'] ?? '');
            $base = $_POST['fm_path'] ?? DEFAULT_SITE_DIR;
            if ($dir && preg_match('/^[a-zA-Z0-9_ -]+$/', $dir)) {
                $path = $base . '/' . $dir;
                if (!is_dir($path)) { mkdir($path, 0755); $flash = ['success', "Dir '$dir' created"]; }
                else { $flash = ['error', "Dir '$dir' exists"]; }
            } else { $flash = ['error', 'Invalid name']; }
        } elseif ($action === 'fm_delete') {
            $target = $_POST['fm_target'] ?? '';
            if ($target && str_starts_with(realpath($target), realpath(DEFAULT_SITE_DIR))) {
                if (is_file($target)) { unlink($target); $flash = ['success', 'File deleted']; }
                elseif (is_dir($target)) { exec("rm -rf " . escapeshellarg($target)); $flash = ['success', 'Dir deleted']; }
            } else { $flash = ['error', 'Invalid target']; }
        }
        if ($action !== 'fm_upload' && $action !== 'fm_mkdir' && $action !== 'fm_delete') {
            header('Location: ?tab=' . urlencode($tab));
            exit;
        }
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
?>
<?php if (!$logged_in): ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Server Login</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:linear-gradient(135deg,#0f172a,#1e293b);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px;color:#fff}
.card{background:rgba(30,41,59,.7);backdrop-filter:blur(20px);border:1px solid rgba(148,163,184,.1);border-radius:24px;padding:40px;width:100%;max-width:400px;box-shadow:0 25px 80px rgba(0,0,0,.5)}
h1{text-align:center;font-size:26px;font-weight:700;letter-spacing:-.5px;margin-bottom:4px}
.sub{text-align:center;color:#64748b;font-size:14px;margin-bottom:28px}
label{display:block;font-size:13px;color:#94a3b8;margin-bottom:6px;font-weight:500}
input[type=password]{width:100%;padding:14px 16px;border:1px solid #334155;border-radius:12px;background:#0f172a;color:#fff;font-size:15px;outline:none;transition:border-color .2s}
input[type=password]:focus{border-color:#3b82f6}
button{width:100%;padding:14px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;margin-top:20px;transition:transform .2s,box-shadow .2s;box-shadow:0 4px 20px rgba(59,130,246,.3)}
button:hover{transform:translateY(-1px);box-shadow:0 8px 30px rgba(59,130,246,.4)}
.err{background:rgba(239,68,68,.15);color:#ef4444;padding:12px;border-radius:10px;text-align:center;margin-bottom:16px;font-size:14px;font-weight:500}
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
body{background:linear-gradient(135deg,#0f172a,#1e293b);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#fff;padding:24px;min-height:100vh}
.wrap{max-width:960px;margin:0 auto}
header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px}
header h1{font-size:24px;font-weight:700;letter-spacing:-.5px}
header a{color:#94a3b8;text-decoration:none;font-size:13px;padding:8px 18px;border:1px solid rgba(148,163,184,.15);border-radius:10px;transition:background .2s;font-weight:500}
header a:hover{background:rgba(148,163,184,.1)}
.tabs{display:flex;gap:4px;margin-bottom:24px;flex-wrap:wrap}
.tabs a{padding:10px 20px;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;transition:all .2s;color:#94a3b8;background:rgba(30,41,59,.3)}
.tabs a:hover{background:rgba(30,41,59,.6);color:#e2e8f0}
.tabs a.active{background:rgba(59,130,246,.2);color:#60a5fa}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-bottom:28px}
.card{background:rgba(30,41,59,.6);backdrop-filter:blur(16px);border:1px solid rgba(148,163,184,.08);border-radius:18px;padding:24px;box-shadow:0 8px 32px rgba(0,0,0,.25);transition:transform .2s}
.card:hover{transform:translateY(-2px)}
.card-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.card-top h2{font-size:16px;font-weight:600}
.badge{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600}
.badge.on{background:rgba(34,197,94,.15);color:#22c55e}
.badge.off{background:rgba(239,68,68,.15);color:#ef4444}
.badge .dot{width:6px;height:6px;border-radius:50%}
.badge.on .dot{background:#22c55e;box-shadow:0 0 8px rgba(34,197,94,.5)}
.badge.off .dot{background:#ef4444;box-shadow:0 0 8px rgba(239,68,68,.5)}
.actions{display:flex;gap:8px}
.actions form{flex:1}
.actions button{width:100%;padding:10px;border:none;border-radius:10px;font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;font-family:inherit}
.btn-start{background:rgba(34,197,94,.15);color:#22c55e}
.btn-start:hover{background:rgba(34,197,94,.25)}
.btn-stop{background:rgba(239,68,68,.15);color:#ef4444}
.btn-stop:hover{background:rgba(239,68,68,.25)}
.btn-restart{background:rgba(245,158,11,.15);color:#f59e0b}
.btn-restart:hover{background:rgba(245,158,11,.25)}
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:28px}
.info-item{background:rgba(15,23,42,.5);border:1px solid rgba(148,163,184,.06);padding:16px;border-radius:12px}
.info-item .label{font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.8px;font-weight:600}
.info-item .value{font-size:14px;font-weight:600;margin-top:5px;word-break:break-all}
.logs{background:rgba(30,41,59,.6);backdrop-filter:blur(16px);border:1px solid rgba(148,163,184,.08);border-radius:18px;padding:24px;margin-bottom:24px}
.logs-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px}
.logs-header h2{font-size:16px;font-weight:600}
.logs-header select{padding:8px 14px;border:1px solid rgba(51,65,85,.6);border-radius:10px;background:rgba(15,23,42,.6);color:#e2e8f0;font-size:13px;outline:none;cursor:pointer;font-family:inherit}
.logs-header select:focus{border-color:#3b82f6}
pre{background:rgba(15,23,42,.6);padding:16px;border-radius:12px;font-size:12px;max-height:300px;overflow:auto;color:#94a3b8;line-height:1.6;white-space:pre-wrap;word-break:break-all;font-family:'SF Mono','Fira Code','Cascadia Code',monospace}
.flash{padding:14px 20px;border-radius:12px;margin-bottom:20px;font-weight:600;font-size:14px}
.flash.success{background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.2)}
.flash.error{background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.2)}
.top-bar{display:flex;justify-content:flex-end;margin-bottom:16px;gap:8px}
.top-bar button{padding:10px 22px;background:rgba(245,158,11,.12);color:#f59e0b;border:1px solid rgba(245,158,11,.2);border-radius:10px;cursor:pointer;font-weight:600;font-size:13px;transition:all .2s;font-family:inherit}
.top-bar button:hover{background:rgba(245,158,11,.22)}
footer{text-align:center;color:#475569;font-size:13px;padding:12px 0}
.section{background:rgba(30,41,59,.6);backdrop-filter:blur(16px);border:1px solid rgba(148,163,184,.08);border-radius:18px;padding:24px;margin-bottom:24px}
.section h2{font-size:16px;font-weight:600;margin-bottom:16px}
.section h3{font-size:14px;font-weight:600;margin:16px 0 10px;color:#94a3b8}
input[type=text],input[type=email],input[type=password]{width:100%;padding:12px 14px;border:1px solid rgba(51,65,85,.6);border-radius:10px;background:rgba(15,23,42,.6);color:#e2e8f0;font-size:14px;outline:none;font-family:inherit;transition:border-color .2s;margin-bottom:12px}
input:focus{border-color:#3b82f6}
input::placeholder{color:#475569}
.btn-form{padding:10px 22px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;border:none;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;font-family:inherit}
.btn-form:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(59,130,246,.3)}
.btn-danger{padding:6px 14px;background:rgba(239,68,68,.15);color:#ef4444;border:1px solid rgba(239,68,68,.2);border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;font-family:inherit}
.btn-danger:hover{background:rgba(239,68,68,.25)}
.site-item{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;background:rgba(15,23,42,.4);border-radius:10px;margin-bottom:8px}
.site-item a{color:#60a5fa;text-decoration:none;font-weight:500;font-size:14px}
.site-item a:hover{text-decoration:underline}
.site-item .info{color:#64748b;font-size:12px}
.form-row{display:flex;gap:8px;align-items:flex-end}
.form-row input{flex:1;margin-bottom:0}
.form-row .btn-form{margin-bottom:0;white-space:nowrap}
table.fm{width:100%;border-collapse:collapse;font-size:13px}
table.fm td{padding:10px 12px;border-bottom:1px solid rgba(51,65,85,.3)}
table.fm tr:hover{background:rgba(15,23,42,.3)}
table.fm .icon{color:#64748b;margin-right:8px}
table.fm a{color:#e2e8f0;text-decoration:none}
table.fm a:hover{color:#60a5fa}
.fm-actions{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;align-items:center}
.fm-actions form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.fm-actions input[type=text]{width:auto;margin-bottom:0;padding:8px 12px;font-size:12px}
.fm-actions .btn-form{padding:8px 16px;font-size:12px}
.breadcrumb{color:#64748b;font-size:13px;margin-bottom:14px;word-break:break-all}
.breadcrumb a{color:#60a5fa;text-decoration:none}
.breadcrumb a:hover{text-decoration:underline}
::-webkit-scrollbar{width:6px}
::-webkit-scrollbar-thumb{background:#334155;border-radius:3px}
@media(max-width:600px){body{padding:14px}.card{padding:18px}.grid{grid-template-columns:1fr}.info-grid{grid-template-columns:1fr 1fr}}
</style>
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
<a href="?tab=files" class="<?= $tab==='files'?'active':'' ?>">File Manager</a>
</div>

<?php if (!empty($flash)): ?>
<div class="flash <?= $flash[0] ?>"><?= $flash[1] ?></div>
<?php endif; ?>

<?php if ($tab === 'dashboard'): ?>
<div class="top-bar">
<form method="post"><input type="hidden" name="action" value="restart_all"><button type="submit" onclick="return confirm('Restart all services?')">Restart All</button></form>
</div>
<div class="grid">
<?php foreach ($services as $name => $s): $is_running = $status[$name]; ?>
<div class="card">
<div class="card-top">
<h2><?= htmlspecialchars($name) ?></h2>
<span class="badge <?= $is_running ? 'on' : 'off' ?>"><span class="dot"></span><?= $is_running ? 'Running' : 'Stopped' ?></span>
</div>
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
<div class="info-item"><div class="label">Web Server</div><div class="value">Nginx :8080</div></div>
</div>
<div class="logs">
<div class="logs-header">
<h2>Log Viewer</h2>
<form method="get"><select name="log" onchange="this.form.submit()">
<option value="">Select a log...</option>
<?php foreach ($log_files as $name => $path): ?>
<option value="<?= $name ?>" <?= ($_GET['log'] ?? '') === $name ? 'selected' : '' ?>><?= ucfirst($name) ?>.log<?= $path ? '' : ' (empty)' ?></option>
<?php endforeach; ?>
</select></form>
</div>
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

<?php elseif ($tab === 'sites'): ?>
<div class="section">
<h2>Manage Sites</h2>
<?php
$sites = glob(DEFAULT_SITE_DIR . '/*', GLOB_ONLYDIR);
$sites = $sites ? array_map('basename', $sites) : [];
?>
<?php foreach ($sites as $site): ?>
<div class="site-item">
<div>
<a href="/<?= urlencode($site) ?>" target="_blank"><?= htmlspecialchars($site) ?></a>
<div class="info"><?= DEFAULT_SITE_DIR . '/' . htmlspecialchars($site) ?></div>
</div>
<form method="post" onsubmit="return confirm('Delete site &#39;<?= htmlspecialchars($site) ?>&#39;?')">
<input type="hidden" name="action" value="delete_site">
<input type="hidden" name="site_name" value="<?= htmlspecialchars($site) ?>">
<button type="submit" class="btn-danger">Delete</button>
</form>
</div>
<?php endforeach; ?>
<?php if (empty($sites)): ?>
<p style="color:#64748b;font-size:14px">No sites yet. Create one below.</p>
<?php endif; ?>
</div>
<div class="section">
<h2>Create New Site</h2>
<form method="post" class="form-row">
<input type="hidden" name="action" value="create_site">
<input type="text" name="site_name" placeholder="Site name (e.g. myapp)" required pattern="[a-z0-9_-]+">
<button type="submit" class="btn-form">Create</button>
</form>
</div>

<?php elseif ($tab === 'wordpress'): ?>
<div class="section">
<h2>One-Click WordPress Install</h2>
<p style="color:#64748b;font-size:13px;margin-bottom:16px">Creates a site directory, downloads WordPress, sets up MariaDB, and prepares wp-config.</p>
<form method="post">
<input type="hidden" name="action" value="wp_install">
<input type="text" name="wp_site" placeholder="Site name (e.g. myblog)" required pattern="[a-z0-9_-]+">
<input type="text" name="wp_title" placeholder="Site title (e.g. My Blog)">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
<input type="text" name="wp_user" placeholder="Admin username" value="admin" required>
<input type="password" name="wp_pass" placeholder="Admin password" required>
</div>
<input type="email" name="wp_email" placeholder="Admin email" value="admin@localhost.local">
<button type="submit" class="btn-form">Install WordPress</button>
</form>
</div>

<?php elseif ($tab === 'files'): ?>
<?php
$fm_path = $_GET['path'] ?? DEFAULT_SITE_DIR;
// Security: ensure path is under DEFAULT_SITE_DIR
$real_base = realpath(DEFAULT_SITE_DIR);
$real_path = realpath($fm_path);
if (!$real_path || !str_starts_with($real_path, $real_base)) {
    $real_path = $real_base;
    $fm_path = $real_base;
}
$items = glob($fm_path . '/*');
usort($items, function($a, $b) {
    $a_is_dir = is_dir($a);
    $b_is_dir = is_dir($b);
    if ($a_is_dir !== $b_is_dir) return $a_is_dir ? -1 : 1;
    return strcasecmp(basename($a), basename($b));
});
?>
<div class="section">
<h2>File Manager</h2>
<div class="breadcrumb">
<a href="?tab=files&path=<?= urlencode(DEFAULT_SITE_DIR) ?>">root</a>
<?php
$parts = explode('/', trim(substr($fm_path, strlen(DEFAULT_SITE_DIR)), '/'));
$cumulative = DEFAULT_SITE_DIR;
foreach ($parts as $p) {
    if (!$p) continue;
    $cumulative .= '/' . $p;
    echo ' / <a href="?tab=files&path=' . urlencode($cumulative) . '">' . htmlspecialchars($p) . '</a>';
}
?>
</div>
<div class="fm-actions">
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="action" value="fm_upload">
<input type="hidden" name="fm_path" value="<?= htmlspecialchars($fm_path) ?>">
<input type="file" name="fm_file" required style="color:#94a3b8;font-size:12px;font-family:inherit">
<button type="submit" class="btn-form">Upload</button>
</form>
<form method="post">
<input type="hidden" name="action" value="fm_mkdir">
<input type="hidden" name="fm_path" value="<?= htmlspecialchars($fm_path) ?>">
<input type="text" name="fm_newdir" placeholder="Dir name" required style="width:140px;padding:8px 12px;font-size:12px;margin-bottom:0">
<button type="submit" class="btn-form">New Dir</button>
</form>
</div>
<table class="fm">
<?php if ($fm_path !== DEFAULT_SITE_DIR): ?>
<tr>
<td colspan="4"><span class="icon">📁</span><a href="?tab=files&path=<?= urlencode(dirname($fm_path)) ?>">..</a></td>
</tr>
<?php endif; ?>
<?php foreach ($items as $item):
$name = basename($item);
$is_dir = is_dir($item);
$size = $is_dir ? '-' : (filesize($item) > 1048576 ? round(filesize($item)/1048576,1).' MB' : (filesize($item) > 1024 ? round(filesize($item)/1024,1).' KB' : filesize($item).' B'));
$mod = date('Y-m-d H:i', filemtime($item));
?>
<tr>
<td><span class="icon"><?= $is_dir ? '📁' : '📄' ?></span><?php if ($is_dir): ?><a href="?tab=files&path=<?= urlencode($item) ?>"><?= htmlspecialchars($name) ?></a><?php else: ?><?= htmlspecialchars($name) ?><?php endif; ?></td>
<td style="color:#64748b;text-align:right"><?= $size ?></td>
<td style="color:#475569;text-align:right;font-size:11px"><?= $mod ?></td>
<td style="text-align:right">
<form method="post" onsubmit="return confirm('Delete <?= $is_dir?'dir':'file' ?> &#39;<?= htmlspecialchars($name) ?>&#39;?')" style="display:inline">
<input type="hidden" name="action" value="fm_delete">
<input type="hidden" name="fm_target" value="<?= htmlspecialchars($item) ?>">
<button type="submit" class="btn-danger" style="padding:4px 10px;font-size:11px">Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>

<footer>Mobile Server — Nginx &bull; PHP-FPM &bull; MariaDB</footer>
</div>
</body>
</html>
