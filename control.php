<?php
declare(strict_types=1);

putenv('PATH=/data/data/com.termux/files/usr/bin:' . getenv('PATH'));

$configDir = getenv('HOME') ? getenv('HOME') . '/server/configs' : '/data/data/com.termux/files/home/server/configs';
$secretFile = $configDir . '/secret.php';

if (is_file($secretFile)) {
    require_once $secretFile;
}

if (!defined('CONTROL_PASSWORD_HASH')) {
    define('CONTROL_PASSWORD_HASH', password_hash('', PASSWORD_BCRYPT));
}

session_start();
setcookie(session_name(), session_id(), [
    'expires' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
    $_SESSION['created'] = time();
}

if (isset($_SESSION['created']) && (time() - $_SESSION['created'] > 86400)) {
    session_destroy();
    session_start();
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
    $_SESSION['created'] = time();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verifyCsrfToken(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        echo 'CSRF token mismatch';
        exit;
    }
}

define('HOME_DIR', getenv('HOME') ?: '/data/data/com.termux/files/home');
define('SITES_DIR', HOME_DIR . '/server/sites');
define('DEFAULT_SITE_DIR', SITES_DIR . '/default/public_html');
define('LOG_DIR', HOME_DIR . '/server/logs');
define('CONFIG_DIR', HOME_DIR . '/server/configs');
define('LOG_MAX_LINES', 200);
define('NGINX_CONF', '/data/data/com.termux/files/usr/etc/nginx/nginx.conf');
define('NGINX_SITES_DIR', CONFIG_DIR . '/nginx-sites');
define('SSL_DIR', HOME_DIR . '/server/ssl');
define('SITES_JSON', CONFIG_DIR . '/sites.json');
define('SERVER_JSON', CONFIG_DIR . '/server.json');
define('WP_CONFIG_TEMPLATE', __DIR__ . '/lib/templates/wp-config.php');
define('PHP_SOCKET', (function () {
    $s = trim(@exec('grep "^listen =" /data/data/com.termux/files/usr/etc/php-fpm.d/www.conf 2>/dev/null | awk "{print \$3}"') ?: '');
    if ($s && strpos($s, 'unix:') !== 0 && strpos($s, ':') === false) {
        $s = 'unix:' . $s;
    }
    return $s ?: 'unix:/data/data/com.termux/files/usr/var/run/php-fpm.sock';
})());

function panelLog(string $message): void {
    @mkdir(LOG_DIR, 0755, true);
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    @file_put_contents(LOG_DIR . '/panel.log', $line, FILE_APPEND | LOCK_EX);
}

require_once __DIR__ . '/lib/WordPressInstaller.php';

if (isset($_GET['tab']) && $_GET['tab'] === 'update' && isset($_GET['action']) && $_GET['action'] === 'stream') {
    include __DIR__ . '/panel/update_stream.php';
    exit;
}

// Progress tracking for long operations
define('PROGRESS_DIR', HOME_DIR . '/server/tmp');
define('CF_TUNNELS_FILE', CONFIG_DIR . '/cloudflared_tunnels.json');

function cfTunnelsLoad(): array {
    if (!is_file(CF_TUNNELS_FILE)) return [];
    $data = json_decode(file_get_contents(CF_TUNNELS_FILE), true);
    return is_array($data) ? $data : [];
}

function cfTunnelsSave(array $tunnels): void {
    $dir = dirname(CF_TUNNELS_FILE);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    file_put_contents(CF_TUNNELS_FILE, json_encode($tunnels, JSON_PRETTY_PRINT));
}

function cfTunnelLogFile(string $site): string {
    return LOG_DIR . '/cf_tunnel_' . $site . '.log';
}

function setProgress(string $siteName, string $step, int $pct, string $status = 'working'): void {
    @mkdir(PROGRESS_DIR, 0755, true);
    @file_put_contents(PROGRESS_DIR . '/progress_' . $siteName . '.json', json_encode([
        'step' => $step,
        'pct' => $pct,
        'status' => $status,
        'time' => time()
    ]));
}

function clearProgress(string $siteName): void {
    @unlink(PROGRESS_DIR . '/progress_' . $siteName . '.json');
}

// Progress poll endpoint
if (isset($_GET['wp_progress'])) {
    header('Content-Type: application/json');
    $site = preg_replace('/[^a-z0-9_-]/', '', $_GET['wp_progress']);
    $file = PROGRESS_DIR . '/progress_' . $site . '.json';
    if (is_file($file)) {
        echo file_get_contents($file);
    } else {
        echo json_encode(['step' => 'unknown', 'pct' => 0, 'status' => 'unknown']);
    }
    exit;
}

// Raw panel log endpoint (plain text)
if (isset($_GET['raw_logs']) && $_GET['raw_logs'] !== 'json') {
    header('Content-Type: text/plain; charset=utf-8');
    $logDir = LOG_DIR;
    $prefix = '/data/data/com.termux/files/usr';
    $logFiles = [
        $logDir . '/panel.log',
        $logDir . '/nginx.log',
        $prefix . '/var/log/nginx/error.log',
        $logDir . '/php-fpm.log',
        $prefix . '/var/log/php-fpm.log',
        $logDir . '/mariadb.log',
        $prefix . '/var/log/mariadb.log',
    ];
    $cfLogs = glob($logDir . '/cf_tunnel_*.log');
    if ($cfLogs) $logFiles = array_merge($logFiles, $cfLogs);

    foreach ($logFiles as $f) {
        if (is_file($f)) {
            echo "=== " . basename($f) . " ===\n";
            echo @file_get_contents($f) . "\n\n";
        }
    }
    exit;
}

// Raw panel log endpoint (JSON)
if (isset($_GET['raw_logs']) && $_GET['raw_logs'] === 'json') {
    $home = getenv('HOME') ?: '/data/data/com.termux/files/home';
    $logDir = $home . '/server/logs';
    $prefix = '/data/data/com.termux/files/usr';
    $services = [
        ['label' => 'Panel',       'paths' => [$logDir . '/panel.log']],
        ['label' => 'Nginx',       'paths' => [$logDir . '/nginx.log', $prefix . '/var/log/nginx/error.log']],
        ['label' => 'PHP-FPM',     'paths' => [$logDir . '/php-fpm.log', $prefix . '/var/log/php-fpm.log']],
        ['label' => 'MariaDB',     'paths' => [$logDir . '/mariadb.log', $prefix . '/var/log/mariadb.log', $prefix . '/var/lib/mysql/error.log']],
        ['label' => 'Cloudflared', 'paths' => glob($logDir . '/cf_tunnel_*.log') ?: []],
    ];
    $maxLines = 300;
    $allLines = [];
    foreach ($services as $info) {
        foreach ($info['paths'] as $p) {
            if (!is_file($p)) continue;
            $lines = @file($p);
            if (!$lines) continue;
            $lines = array_slice($lines, -$maxLines);
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '') continue;
                $level = 'info';
                $upper = strtoupper($trimmed);
                if (preg_match('/\b(emerg|alert|critical|error|fail)\b/i', $upper)) $level = 'error';
                elseif (preg_match('/\b(warning|warn)\b/i', $upper)) $level = 'warn';
                $allLines[] = ['svc' => $info['label'], 'level' => $level, 'text' => $trimmed];
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode($allLines);
    exit;
}

// Clear logs endpoint
if (isset($_GET['clear_logs'])) {
    $home = getenv('HOME') ?: '/data/data/com.termux/files/home';
    $logDir = $home . '/server/logs';
    $prefix = '/data/data/com.termux/files/usr';
    // Clear panel-owned logs
    $targets = glob($logDir . '/*.log') ?: [];
    // Also clear system logs we read from
    $targets[] = $prefix . '/var/log/nginx/error.log';
    $targets[] = $prefix . '/var/log/php-fpm.log';
    $targets[] = $prefix . '/var/log/mariadb.log';
    $targets[] = $prefix . '/var/lib/mysql/error.log';
    foreach ($targets as $f) {
        if ($f && is_file($f)) {
            @file_put_contents($f, '');
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// Cloudflared tunnel status poll endpoint
if (isset($_GET['cf_tunnel_status'])) {
    header('Content-Type: application/json');
    $site = preg_replace('/[^a-z0-9_-]/', '', $_GET['cf_tunnel_status']);
    if (!$site) {
        echo json_encode(['running' => false, 'url' => '']);
        exit;
    }
    $tunnels = cfTunnelsLoad();
    if (!isset($tunnels[$site])) {
        echo json_encode(['running' => false, 'url' => '']);
        exit;
    }
    $t = $tunnels[$site];
    $running = false;
    if (!empty($t['pid'])) {
        exec("kill -0 " . (int)$t['pid'] . " 2>/dev/null", $null, $rc);
        $running = $rc === 0;
    }
    $url = $t['url'] ?? '';
    if (!$url && $running) {
        $logFile = cfTunnelLogFile($site);
        if (is_file($logFile)) {
            $content = file_get_contents($logFile);
            if (preg_match('/https:\/\/[a-z0-9-]+\.trycloudflare\.com/', $content, $m)) {
                $url = $m[0];
                $tunnels[$site]['url'] = $url;
                cfTunnelsSave($tunnels);
            }
        }
    }
    if (!$running) {
        @unlink(cfTunnelLogFile($site));
    }
    echo json_encode(['running' => $running, 'url' => $url]);
    exit;
}

// Site health check endpoint
if (isset($_GET['site_health'])) {
    header('Content-Type: application/json');
    if ($_GET['site_health'] === 'all') {
        echo json_encode(checkAllSitesHealth());
    } else {
        $site = preg_replace('/[^a-z0-9_-]/', '', $_GET['site_health']);
        if (!$site) {
            echo json_encode(['status' => 'unknown']);
            exit;
        }
        $config = getSitesConfig();
        if (!isset($config[$site])) {
            echo json_encode(['status' => 'unknown']);
            exit;
        }
        echo json_encode(checkSiteHealth($site, $config[$site]));
    }
    exit;
}

function getSitesConfig(): array {
    if (!is_file(SITES_JSON)) return [];
    $data = json_decode(file_get_contents(SITES_JSON), true);
    return is_array($data) ? $data : [];
}

function saveSitesConfig(array $config): void {
    $dir = dirname(SITES_JSON);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    file_put_contents(SITES_JSON, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function getServerConfig(): array {
    $defaults = [
        'DB_HOST'     => '127.0.0.1',
        'DB_ROOT_USER'=> 'root',
        'DB_ROOT_PASS'=> '',
    ];
    if (!is_file(SERVER_JSON)) return $defaults;
    $data = json_decode(@file_get_contents(SERVER_JSON), true);
    if (!is_array($data)) return $defaults;
    return array_merge($defaults, $data);
}

function saveServerConfig(array $config): void {
    $dir = dirname(SERVER_JSON);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    file_put_contents(SERVER_JSON, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function getNextPort(): int {
    $used = [8080];
    $config = getSitesConfig();
    foreach ($config as $s) {
        if (!empty($s['port'])) $used[] = (int)$s['port'];
    }
    for ($p = 8081; $p <= 8999; $p++) {
        if (!in_array($p, $used, true)) return $p;
    }
    return 0;
}

function isPortListening(int $port): bool {
    if ($port < 1 || $port > 65535) return false;
    exec("ss -tlnp 2>/dev/null | grep -q ':$port ' || netstat -tlnp 2>/dev/null | grep -q ':$port '", $out, $rc);
    return $rc === 0;
}

function checkSiteHealth(string $name, array $site): array {
    $port = (int)($site['port'] ?? 0);
    $enabled = !empty($site['enabled']);
    $confFile = NGINX_SITES_DIR . '/' . $name . '.conf';
    $confExists = is_file($confFile);
    $listening = $port > 0 ? isPortListening($port) : false;
    $pathExists = !empty($site['path']) && is_dir($site['path']);

    $status = 'unknown';
    $reason = '';

    if (!$enabled) {
        $status = 'disabled';
    } elseif (!$pathExists) {
        $status = 'error';
        $reason = 'Site directory missing';
    } elseif (!$confExists) {
        $status = 'error';
        $reason = 'Nginx config missing';
    } elseif (!$listening) {
        $status = 'down';
        $reason = 'Port not listening';
    } else {
        $status = 'running';
    }

    return [
        'status'    => $status,
        'listening' => $listening,
        'conf'      => $confExists,
        'path'      => $pathExists,
        'reason'    => $reason,
    ];
}

function checkAllSitesHealth(): array {
    $config = getSitesConfig();
    $results = [];
    foreach ($config as $name => $site) {
        $results[$name] = checkSiteHealth($name, $site);
    }
    return $results;
}

function generateNginxBlock(string $domain, int $port, string $path): string {
    if ($port < 1 || $port > 65535) return '';
    $real = realpath($path);
    if (!$real || !is_dir($real)) return '';
    $phpSocket = PHP_SOCKET;
    return "server {\n"
        . "    listen $port;\n"
        . "    server_name $domain www.$domain;\n"
        . "    root $real;\n"
        . "    index index.php index.html;\n"
        . "    location / { try_files \$uri \$uri/ /index.php?\$query_string; }\n"
        . "    location ~ \\.php$ {\n"
        . "        include fastcgi.conf;\n"
        . "        fastcgi_pass $phpSocket;\n"
        . "        fastcgi_index index.php;\n"
        . "        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;\n"
        . "    }\n"
        . "}\n";
}

function rewriteNginxMainConfig(): bool {
    if (!is_file(NGINX_CONF)) return false;
    $conf = file_get_contents(NGINX_CONF);
    $includeLine = 'include ' . NGINX_SITES_DIR . '/*.conf;';
    if (preg_match('/include\s+' . preg_quote(NGINX_SITES_DIR, '/') . '/', $conf)) return true;
    $conf = preg_replace('/^http\s*\{[^\n]*/m', "$0\n    $includeLine", $conf, 1, $count);
    if ($count === 0) return false;
    file_put_contents(NGINX_CONF, $conf);
    return true;
}

function restartNginx(): bool {
    exec('nginx -t 2>&1', $raw, $rc);
    if ($rc !== 0) {
        error_log('nginx -t: ' . implode("\n", $raw));
        return false;
    }
    // Stop and start — retry if first attempt fails
    for ($attempt = 0; $attempt < 2; $attempt++) {
        exec('nginx -s stop 2>/dev/null; sleep 1; nginx 2>&1', $raw, $rc);
        if ($rc === 0) break;
        exec('pkill nginx 2>/dev/null; sleep 1; nginx 2>&1', $raw, $rc);
        if ($rc === 0) break;
    }
    sleep(1);
    exec('pgrep nginx 2>/dev/null', $pout, $prc);
    if ($prc !== 0) {
        // Last resort: try starting nginx one more time
        exec('nginx 2>&1', $raw, $rc);
        sleep(1);
        exec('pgrep nginx 2>/dev/null', $pout, $prc);
    }
    return $prc === 0;
}

function reloadNginx(): bool {
    exec('nginx -t 2>&1', $raw, $rc);
    if ($rc !== 0) {
        error_log('nginx -t: ' . implode("\n", $raw));
        return false;
    }
    exec('pgrep nginx 2>/dev/null', $pout, $prc);
    if ($prc !== 0) {
        // Not running — start it, fall back to restartNginx if that fails
        exec('nginx 2>&1', $raw, $rc);
        if ($rc !== 0) {
            error_log('nginx start failed: ' . implode("\n", $raw));
            return restartNginx();
        }
    } else {
        // Running — reload gracefully
        exec('nginx -s reload 2>&1', $raw, $rc);
        if ($rc !== 0) {
            panelLog('nginx -s reload failed: ' . implode("\n", $raw));
            // Termux nginx often fails signal handling — force restart
            exec('pkill nginx 2>/dev/null; sleep 1; nginx 2>&1', $raw, $rc);
            if ($rc !== 0) {
                panelLog('pkill + nginx start also failed: ' . implode("\n", $raw));
            }
        }
    }
    sleep(1);
    exec('pgrep nginx 2>/dev/null', $pout, $prc);
    if ($prc !== 0) {
        panelLog('nginx not running after reload — trying restartNginx');
        return restartNginx();
    }
    return true;
}

$services = [
    'Nginx'   => ['process' => 'nginx',   'start' => 'nginx',                            'stop' => 'nginx -s stop',    'log' => ''],
    'PHP-FPM' => ['process' => 'php-fpm', 'start' => 'php-fpm',                          'stop' => 'pkill php-fpm',    'log' => ''],
    'MariaDB' => ['process' => 'mariadbd','start' => 'mariadbd-safe >/dev/null 2>&1 &', 'stop' => 'pkill mariadbd',    'log' => ''],
];

$log_files = [];
foreach (['nginx', 'php-fpm', 'mariadb'] as $l) {
    $p = LOG_DIR . "/$l.log";
    $log_files[$l] = is_file($p) ? $p : null;
}

$logged_in = !empty($_SESSION['authenticated']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $password = $_POST['password'] ?? '';
    if (password_verify($password, CONTROL_PASSWORD_HASH)) {
        $_SESSION['authenticated'] = true;
        session_regenerate_id(true);
        $logged_in = true;
        header('Location: ?');
        exit;
    }
    $login_err = 'Invalid password';
    sleep(1);
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    session_destroy();
    header('Location: ?');
    exit;
}

$tab = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'dashboard');

// Detect device IP (one fast command, PHP fallback)
@exec("ip -4 -o addr show wlan0 2>/dev/null | awk '{print $4}' | cut -d/ -f1", $ip_out, $ip_rc);
$ip_addr = ($ip_rc === 0 && !empty($ip_out)) ? $ip_out[0] : gethostbyname(gethostname());
if (!$ip_addr || !filter_var($ip_addr, FILTER_VALIDATE_IP)) {
    $ip_addr = 'localhost';
}

if ($logged_in) {
    $action = $_POST['action'] ?? '';

    // Handle flash messages from previous redirect
    $flash = null;
    if (isset($_SESSION['_flash'])) {
        $flash = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
    }

    if ($action && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrfToken();

        if ($action === 'restart_all') {
            $cmds = [];
            foreach ($services as $s) { $cmds[] = $s['stop'] . ' 2>/dev/null'; }
            $cmds[] = 'sleep 1';
            foreach ($services as $s) { $cmds[] = $s['start']; }
            exec(implode('; ', $cmds) . ' 2>&1', $raw, $rc);
            $flash = ['success', 'All services restarted'];
            panelLog('Restarted all services');
        } elseif (isset($services[$_POST['service'] ?? ''])) {
            $s = $services[$_POST['service']];
            $svc = $_POST['service'];
            if ($action === 'start')   $cmd = $s['start'];
            if ($action === 'stop')    $cmd = $s['stop'];
            if ($action === 'restart') $cmd = $s['stop'] . ' 2>/dev/null; sleep 1; ' . $s['start'];
            if (isset($cmd)) {
                exec($cmd . ' 2>&1', $raw, $rc);
                $flash = [$rc === 0 ? 'success' : 'error', ucfirst($action) . " $svc " . ($rc === 0 ? 'done' : 'failed')];
                panelLog(ucfirst($action) . " $svc: " . ($rc === 0 ? 'done' : 'failed'));
            }
        } elseif ($action === 'create_site') {
            panelLog("[create_site] POST received — name=" . ($_POST['site_name'] ?? '') . " type=" . ($_POST['site_type'] ?? ''));
            $name = trim($_POST['site_name'] ?? '');
            $domain = trim($_POST['site_domain'] ?? '');
            if (!$domain) $domain = $ip_addr ?? 'localhost';
            $type = trim($_POST['site_type'] ?? 'static');
            if (!preg_match('/^[a-z0-9_-]+$/', $name)) {
                $flash = ['error', 'Invalid site name (use a-z, 0-9, -, _)'];
            } elseif (!in_array($type, ['static', 'wordpress'], true)) {
                $flash = ['error', 'Invalid site type'];
            } elseif (isset(getSitesConfig()[$name])) {
                $flash = ['error', "Site '$name' already exists in config"];
            } else {
                $siteDir = SITES_DIR . '/' . $name;
                $publicHtml = $siteDir . '/public_html';
                if (is_dir($siteDir)) {
                    exec("rm -rf " . escapeshellarg($siteDir) . " 2>/dev/null");
                }
                $port = getNextPort();
                    if ($port === 0) {
                        $flash = ['error', 'No available port (all 8081-8999 are in use)'];
                    } else {
                        if (!is_dir(NGINX_SITES_DIR)) @mkdir(NGINX_SITES_DIR, 0755, true);
                        @mkdir($publicHtml, 0755, true);
                        $block = generateNginxBlock($domain, $port, $publicHtml);
                        if ($block === '') {
                            $flash = ['error', "Failed to generate nginx config for '$name' — check directory permissions"];
                        } else {
                            $config = getSitesConfig();
                            $wpResult = ['success' => true];

                            if ($type === 'wordpress') {
                                // Ensure MariaDB is running before WordPress installation
                                exec("pgrep mariadbd 2>/dev/null || pgrep mysqld 2>/dev/null", $mdbOut, $mdbRc);
                                if ($mdbRc !== 0) {
                                    setProgress($name, 'Starting MariaDB...', 5);
                                    exec('mariadbd-safe >/dev/null 2>&1 &');
                                    sleep(3);
                                }
                                setProgress($name, 'Starting WordPress installation...', 10);
                                $installer = new WordPressInstaller($name, getServerConfig());
                                $wpResult = $installer->createWebsite($domain, $port);
                            } else {
                                setProgress($name, 'Creating static site...', 10);
                                @mkdir($publicHtml, 0755, true);
                                file_put_contents($publicHtml . '/index.php', "<?php\necho '<h1>Welcome to " . htmlspecialchars($name, ENT_QUOTES) . "</h1>';\n");
                                file_put_contents(NGINX_SITES_DIR . '/' . $name . '.conf', $block);
                                rewriteNginxMainConfig();
                                clearProgress($name);
                            }

                            $config[$name] = [
                                'domain' => $domain,
                                'port' => $port,
                                'path' => $publicHtml,
                                'enabled' => true,
                                'type' => $type,
                                'created' => date('Y-m-d H:i:s'),
                                'db_name' => $wpResult['credentials']['db_name'] ?? '',
                                'db_user' => $wpResult['credentials']['db_user'] ?? '',
                                'db_pass' => $wpResult['credentials']['db_pass'] ?? '',
                                'table_prefix' => $wpResult['credentials']['prefix'] ?? '',
                                'status' => $wpResult['success'] ? ($type === 'wordpress' ? 'pending_setup' : 'active') : 'error',
                            ];
                            saveSitesConfig($config);

                            if (!isset($flash) && $wpResult['success']) {
                                setProgress($name, 'Reloading nginx...', 95);
                                if (!reloadNginx()) {
                                    $flash = ['error', "Site '$name' created but nginx failed to reload. Check 'nginx -t'."];
                                }
                            }

                            if (!isset($flash)) {
                                $url = "http://$domain:$port";
                                $label = ucfirst($type);
                                $wpSuffix = $wpResult['success'] && $type === 'wordpress' ? ' (<a href=\'' . $url . '/wp-admin/install.php\' style=\'color:#3b82f6\'>Complete setup</a>)' : '';
                                $flash = [$wpResult['success'] ? 'success' : 'error', "$label site '$name' created — <a href='$url' target='_blank' style='color:#3b82f6'>$url</a>" . $wpSuffix . ($wpResult['success'] ? '' : ($wpResult['message'] ? '<br>' . htmlspecialchars($wpResult['message']) : ''))];
                                if ($wpResult['success']) { setProgress($name, 'Done!', 100, 'done'); }
                                panelLog("Created $type site '$name' at $domain:$port");
                            }
                        }
                    }
            }
        } elseif ($action === 'delete_site') {
            $name = trim($_POST['site_name'] ?? '');
            if ($name && preg_match('/^[a-z0-9_-]+$/', $name)) {
                $config = getSitesConfig();
                if (!isset($config[$name])) {
                    $flash = ['error', "Site '$name' not found in config"];
                } else {
                    $dbName = $config[$name]['db_name'] ?? '';
                    $dbUser = $config[$name]['db_user'] ?? '';
                    unset($config[$name]);
                    saveSitesConfig($config);
                    @unlink(NGINX_SITES_DIR . '/' . $name . '.conf');

                    // Always delete database and user
                    if ($dbName && $dbUser) {
                        WordPressInstaller::deleteDatabase($dbName, $dbUser);
                    } else {
                        $dbBase = str_replace('-', '_', $name);
                        WordPressInstaller::deleteDatabase($dbBase, $dbBase);
                    }

                    // Always delete site files
                    $siteDir = SITES_DIR . '/' . $name;
                    if (is_dir($siteDir)) {
                        exec("rm -rf " . escapeshellarg($siteDir) . " 2>/dev/null");
                    }

                    $remaining = glob(NGINX_SITES_DIR . '/*.conf');
                    if (empty($remaining)) {
                        @mkdir(NGINX_SITES_DIR, 0755, true);
                        $placeholder = NGINX_SITES_DIR . '/_placeholder.conf';
                        if (!is_file($placeholder)) {
                            file_put_contents($placeholder, "# Placeholder — no sites configured\n");
                        }
                    }
                    $reloadOk = reloadNginx();
                    if (!$reloadOk) {
                        $reloadOk = reloadNginx();
                    }
                    $flash = ['success', "Site '$name' deleted"];
                    if (!$reloadOk) {
                        panelLog("Deleted site '$name' — nginx reload warning: run 'nginx -t' to check config");
                    }
                    panelLog("Deleted site '$name'");
                }
            } else {
                $flash = ['error', "Invalid site name"];
            }
        } elseif ($action === 'toggle_site') {
            $name = trim($_POST['site_name'] ?? '');
            $config = getSitesConfig();
            if (isset($config[$name])) {
                $config[$name]['enabled'] = !$config[$name]['enabled'];
                saveSitesConfig($config);
                $target = NGINX_SITES_DIR . '/' . $name . '.conf';
                if ($config[$name]['enabled']) {
                    $block = generateNginxBlock($config[$name]['domain'], $config[$name]['port'], $config[$name]['path']);
                    if ($block === '') {
                        $flash = ['error', "Failed to generate config for '$name' — path missing"];
                    } else {
                        file_put_contents($target, $block);
                    }
                } else {
                    @unlink($target);
                }
                if (!isset($flash)) {
                    $restartOk = reloadNginx();
                    if (!$restartOk) { $restartOk = restartNginx(); }
                    $flash = ['success', "Site '$name' " . ($config[$name]['enabled'] ? 'enabled' : 'disabled')];
                    panelLog(($config[$name]['enabled'] ? 'Enabled' : 'Disabled') . " site '$name'");
                }
            } else {
                $flash = ['error', "Site '$name' not found in config"];
            }
        } elseif ($action === 'auto_disable_broken') {
            $config = getSitesConfig();
            $disabled = [];
            foreach ($config as $name => $site) {
                if (empty($site['enabled'])) continue;
                $health = checkSiteHealth($name, $site);
                if ($health['status'] === 'down' || $health['status'] === 'error') {
                    $config[$name]['enabled'] = false;
                    $confFile = NGINX_SITES_DIR . '/' . $name . '.conf';
                    @unlink($confFile);
                    $disabled[] = $name;
                    panelLog("Auto-disabled broken site '$name': " . ($health['reason'] ?: $health['status']));
                }
            }
            if (!empty($disabled)) {
                saveSitesConfig($config);
                reloadNginx();
                $flash = ['success', 'Auto-disabled ' . count($disabled) . ' broken site(s): ' . implode(', ', $disabled)];
            } else {
                $flash = ['success', 'All enabled sites are healthy'];
            }
        } elseif ($action === 'edit_site') {
            $name = trim($_POST['site_name_orig'] ?? '');
            $domain = trim($_POST['site_domain'] ?? '');
            $type = trim($_POST['site_type'] ?? 'static');
            $config = getSitesConfig();
            if (isset($config[$name])) {
                $config[$name]['domain'] = $domain;
                $config[$name]['type'] = $type;
                saveSitesConfig($config);
                $target = NGINX_SITES_DIR . '/' . $name . '.conf';
                $block = generateNginxBlock($domain, $config[$name]['port'], $config[$name]['path']);
                if ($block === '') {
                    $flash = ['error', "Failed to generate config for '$name' — path missing"];
                } else {
                    file_put_contents($target, $block);
                    $restartOk = reloadNginx();
                    if (!$restartOk) { $restartOk = restartNginx(); }
                    $flash = ['success', "Site '$name' updated"];
                    panelLog("Updated site '$name'");
                }
            } else {
                $flash = ['error', "Site '$name' not found"];
            }
        } elseif ($action === 'update_hosts') {
            $hostsPath = '/data/data/com.termux/files/usr/etc/hosts';
            $uid = function_exists('posix_getuid') ? posix_getuid() : (int)@exec('id -u 2>/dev/null');
            $isRoot = ($uid === 0);
            if (!$isRoot) {
                $flash = ['error', 'Cannot modify hosts file without root. Run: tsu -c "nano /etc/hosts"'];
            } else {
                $config = getSitesConfig();
                $entries = [];
                $ip = trim(@shell_exec("ip -4 -o addr show wlan0 2>/dev/null | awk '{print \$4}' | cut -d/ -f1") ?: '127.0.0.1');
                foreach ($config as $name => $site) {
                    if (!empty($site['domain']) && !empty($site['enabled'])) {
                        $entries[] = "$ip {$site['domain']} www.{$site['domain']}";
                    }
                }
                if (!empty($entries)) {
                    $content = file_get_contents($hostsPath);
                    $content = preg_replace('/\n# Mobile Server Sites\n.*?(?=\n# |\n$|$)/s', '', $content);
                    $content = trim($content) . "\n# Mobile Server Sites\n" . implode("\n", $entries) . "\n";
                    file_put_contents($hostsPath, $content);
                    $flash = ['success', 'Hosts file updated. Add these entries manually if not root: <br>' . implode('<br>', array_map('htmlspecialchars', $entries))];
                } else {
                    $flash = ['info', 'No sites with domains to add'];
                }
            }
        } elseif ($action === 'restart_nginx') {
            $ok = restartNginx();
            $flash = [$ok ? 'success' : 'error', $ok ? 'Nginx restarted' : 'Failed to restart Nginx'];
            panelLog('Nginx restart: ' . ($ok ? 'done' : 'failed'));
        } elseif ($action === 'nginx_test') {
            exec('nginx -t 2>&1', $raw, $rc);
            $_SESSION['nginx_diag'] = implode("\n", $raw);
            $flash = [$rc === 0 ? 'success' : 'error', 'nginx -t ' . ($rc === 0 ? 'passed' : 'failed')];
            panelLog('nginx -t: ' . ($rc === 0 ? 'passed' : 'failed'));
        } elseif ($action === 'check_ports') {
            exec("ss -tlnp 2>/dev/null || netstat -tlnp 2>/dev/null", $raw, $rc);
            $ports = $raw ? implode("\n", $raw) : 'No output (ss/netstat not available)';
            $_SESSION['nginx_diag'] = $ports;
            $flash = ['success', 'Port check complete. See diagnostics below.'];
            panelLog('Checked listening ports');
        } elseif ($action === 'update_system') {
            $target_dir = __DIR__;
            $repo_url = 'https://github.com/SayfullahSayeb/mobile-server.git';
            $hash_file = CONFIG_DIR . '/.update_hash';
            $results = [];
            $all_ok = true;

            exec("git ls-remote $repo_url HEAD 2>/dev/null", $remote_raw, $remote_rc);
            if ($remote_rc === 0 && !empty($remote_raw[0])) {
                $latest_hash = strtok($remote_raw[0], " \t");
                $stored_hash = @file_get_contents($hash_file);
                if (trim($stored_hash) === $latest_hash) {
                    $results[] = '<i class="fas fa-check"></i> Already up to date (latest: ' . substr($latest_hash, 0, 8) . ')';
                    $flash_type = 'success';
                    $flash_msg = 'Update check completed.<br>' . implode('<br>', $results);
                    $flash = [$flash_type, $flash_msg];
                } else {
                    exec("cd " . escapeshellarg($target_dir) . " && git fetch origin 2>&1", $raw, $rc);
                    if ($rc === 0) {
                        $results[] = '<i class="fas fa-check"></i> Fetched latest changes';
                        exec("cd " . escapeshellarg($target_dir) . " && git pull 2>&1", $raw2, $rc2);
                        if ($rc2 === 0) {
                            $all_ok = true;
                            $results[] = '<i class="fas fa-check"></i> Pulled commit ' . substr($latest_hash, 0, 8);
                        } else {
                            $all_ok = false;
                            $results[] = '<i class="fas fa-times"></i> Pull failed: ' . implode(' ', $raw2);
                        }
                    } else {
                        $all_ok = false;
                        $results[] = '<i class="fas fa-times"></i> Fetch failed: ' . implode(' ', $raw);
                    }
                    if ($all_ok) file_put_contents($hash_file, $latest_hash);
                    $flash_type = $all_ok ? 'success' : 'error';
                    $flash_msg = 'Update ' . ($all_ok ? 'completed successfully!' : 'completed with errors.') . '<br>' . implode('<br>', $results);
                    $flash = [$flash_type, $flash_msg];
                }
            } else {
                $flash = ['error', 'Failed to check for updates — no internet connection?'];
            }
        } elseif ($action === 'setup_https') {
            @mkdir(SSL_DIR, 0755, true);
            $cert = SSL_DIR . '/cert.pem';
            $key = SSL_DIR . '/key.pem';
            $ip = $ip_addr ?? 'localhost';
            $ssl_generated = false;
            // Try openssl CLI first, then fallback to PHP openssl
            exec("openssl req -x509 -newkey rsa:2048 -keyout " . escapeshellarg($key) . " -out " . escapeshellarg($cert) . " -days 3650 -nodes -subj '/CN=$ip' 2>&1", $raw, $rc);
            if ($rc === 0) {
                $ssl_generated = true;
            } else {
                // Fallback: generate cert with PHP's openssl extension
                if (function_exists('openssl_pkey_new') && function_exists('openssl_csr_new') && function_exists('openssl_csr_sign')) {
                    $pkey = @openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
                    if ($pkey) {
                        $csr = @openssl_csr_new(['commonName' => $ip, 'organizationName' => 'Mobile Server'], $pkey);
                        if ($csr) {
                            $cacert = @openssl_csr_sign($csr, null, $pkey, 3650);
                            if ($cacert) {
                                @openssl_csr_export($csr, $csrStr);
                                @openssl_pkey_export($pkey, $pkeyStr);
                                @openssl_x509_export($cacert, $certStr);
                                if ($pkeyStr && $certStr) {
                                    file_put_contents($key, $pkeyStr);
                                    file_put_contents($cert, $certStr);
                                    $ssl_generated = true;
                                }
                            }
                        }
                    }
                }
                if (!$ssl_generated) {
                    $flash = ['error', 'Failed to generate SSL certificate. Install openssl: pkg install openssl in Termux'];
                    panelLog('HTTPS setup failed');
                }
            }
            if ($ssl_generated) {
                // Write SSL server block as a separate config in the sites include dir
                $sslConf = NGINX_SITES_DIR . '/_ssl.conf';
                $sslBlock = "server {\n"
                    . "    listen 8443 ssl;\n"
                    . "    server_name $ip;\n"
                    . "    ssl_certificate $cert;\n"
                    . "    ssl_certificate_key $key;\n"
                    . "    ssl_protocols TLSv1.2 TLSv1.3;\n"
                    . "    root " . DEFAULT_SITE_DIR . ";\n"
                    . "    index index.php index.html;\n"
                    . "    location / { try_files \$uri \$uri/ /index.php?\$query_string; }\n"
                    . "    location ~ \\.php$ {\n"
                    . "        include fastcgi.conf;\n"
                    . "        fastcgi_pass " . PHP_SOCKET . ";\n"
                    . "        fastcgi_index index.php;\n"
                    . "        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;\n"
                    . "    }\n"
                    . "}\n";
                if (!is_dir(NGINX_SITES_DIR)) @mkdir(NGINX_SITES_DIR, 0755, true);
                file_put_contents($sslConf, $sslBlock);
                $ok = reloadNginx();
                if (!$ok) $ok = restartNginx();
                $msg = 'HTTPS enabled at https://' . $ip . ':8443' . ($ok ? '' : ' (nginx reload failed)');
                $flash = [$ok ? 'success' : 'error', $msg];
                panelLog('HTTPS setup: ' . ($ok ? 'done' : 'failed'));
            }
        } elseif ($action === 'cf_tunnel_start') {
            $site = preg_replace('/[^a-z0-9_-]/', '', $_POST['site'] ?? '');
            if (!$site) {
                $flash = ['error', 'Invalid site name'];
            } else {
                $tunnels = cfTunnelsLoad();
                if (!empty($tunnels[$site]['pid'])) {
                    exec("kill " . (int)$tunnels[$site]['pid'] . " 2>/dev/null");
                }
                $port = 8080;
                $config = getSitesConfig();
                if (isset($config[$site]['port']) && $config[$site]['port'] > 0) {
                    $port = (int)$config[$site]['port'];
                }
                $logFile = cfTunnelLogFile($site);
                @unlink($logFile);
                exec("cloudflared tunnel --url http://localhost:$port > " . escapeshellarg($logFile) . " 2>&1 & echo $!", $pout, $prc);
                $pid = (int)($pout[0] ?? 0);
                if ($pid > 0) {
                    $tunnels[$site] = ['pid' => $pid, 'port' => $port, 'url' => '', 'started' => time()];
                    cfTunnelsSave($tunnels);
                    $flash = ['success', 'Cloudflare tunnel starting for ' . $site];
                    panelLog("CF tunnel started for $site (PID $pid)");
                } else {
                    $flash = ['error', 'Failed to start cloudflared tunnel'];
                }
            }
        } elseif ($action === 'cf_tunnel_stop') {
            $site = preg_replace('/[^a-z0-9_-]/', '', $_POST['site'] ?? '');
            if ($site) {
                $tunnels = cfTunnelsLoad();
                if (!empty($tunnels[$site]['pid'])) {
                    exec("kill " . (int)$tunnels[$site]['pid'] . " 2>/dev/null");
                }
                unset($tunnels[$site]);
                cfTunnelsSave($tunnels);
                @unlink(cfTunnelLogFile($site));
                $flash = ['success', 'Cloudflare tunnel stopped for ' . $site];
                panelLog("CF tunnel stopped for $site");
            }
        } elseif ($action === 'disable_https') {
            $sslConf = NGINX_SITES_DIR . '/_ssl.conf';
            if (is_file($sslConf)) {
                @unlink($sslConf);
                reloadNginx();
                $flash = ['success', 'HTTPS disabled'];
                panelLog('HTTPS disabled');
            } else {
                $flash = ['error', 'HTTPS is not enabled'];
            }
        }
        // AJAX responses for create_site / delete_site
        if (!empty($_POST['ajax'])) {
            if ($action === 'create_site') {
                header('Content-Type: application/json');
                $msg = $flash ? strip_tags($flash[1]) : 'Unknown error';
                $isSuccess = $flash && $flash[0] === 'success';
                $res = ['success' => $isSuccess, 'message' => $msg];
                if ($isSuccess && !empty($url)) {
                    $res['url'] = $url;
                }
                echo json_encode($res);
                exit;
            }
            if ($action === 'delete_site') {
                header('Content-Type: application/json');
                if ($flash && $flash[0] !== 'success') {
                    echo json_encode(['success' => false, 'message' => strip_tags($flash[1])]);
                } else {
                    echo json_encode(['success' => true, 'message' => strip_tags($flash[1] ?? 'Site deleted')]);
                }
                exit;
            }
            if ($action === 'edit_site') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => $flash && $flash[0] === 'success',
                    'message' => $flash ? strip_tags($flash[1]) : 'Updated',
                ]);
                exit;
            }
        }

        if (true) {
            if (isset($flash)) {
                $_SESSION['_flash'] = $flash;
            }
            header('Location: ?tab=' . urlencode($tab));
            exit;
        }
    }

    $https_enabled = is_file(SSL_DIR . '/cert.pem') && is_file(SSL_DIR . '/key.pem');
    $status = [];
    $serviceMap = ['nginx' => 'Nginx', 'php-fpm' => 'PHP-FPM', 'mariadbd' => 'MariaDB', 'mysqld' => 'MariaDB'];
    @exec("for p in nginx php-fpm mariadbd mysqld; do pgrep \"\$p\" >/dev/null 2>&1 && echo \"\$p:1\"; done", $pout, $prc);
    foreach ($pout as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2 && isset($serviceMap[$parts[0]])) {
            $status[$serviceMap[$parts[0]]] = true;
        }
    }
    foreach ($services as $name => $s) {
        if (!isset($status[$name])) {
            @exec("pgrep -x '" . $s['process'] . "' 2>/dev/null || pgrep '" . $s['process'] . "' 2>/dev/null || pidof '" . $s['process'] . "' 2>/dev/null", $out, $code);
            $status[$name] = $code === 0;
        }
    }

    $hostname   = gethostname();
    $uptime     = trim(@shell_exec('uptime -p 2>/dev/null') ?: 'N/A');
    $php_ver    = phpversion();
    $server_time = date('Y-m-d H:i:s');

    $csrf_token = $_SESSION['csrf_token'];
}
?>
<?php if (!$logged_in): ?>
<?php
$login_err = $login_err ?? '';
include __DIR__ . '/panel/login.php';
?>
<?php exit; endif; ?>
<?php include __DIR__ . '/panel/header.php'; ?>

<?php
$tabFile = __DIR__ . '/panel/' . $tab . '.php';
if (is_file($tabFile)) {
    include $tabFile;
} else {
    include __DIR__ . '/panel/dashboard.php';
}
?>
<?php include __DIR__ . '/panel/footer.php'; ?>
