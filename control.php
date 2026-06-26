<?php
declare(strict_types=1);

$configDir = getenv('HOME') ? getenv('HOME') . '/server/configs' : '/data/data/com.termux/files/home/server/configs';
$secretFile = $configDir . '/secret.php';

if (is_file($secretFile)) {
    require_once $secretFile;
}

if (!defined('CONTROL_PASSWORD_HASH')) {
    define('CONTROL_PASSWORD_HASH', password_hash('admin', PASSWORD_BCRYPT));
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
define('SITES_JSON', CONFIG_DIR . '/sites.json');
define('PHP_SOCKET', (function () {
    $s = trim(@exec('grep "^listen =" /data/data/com.termux/files/usr/etc/php-fpm.d/www.conf 2>/dev/null | awk "{print \$3}"') ?: '');
    return $s ?: 'unix:/data/data/com.termux/files/usr/var/run/php-fpm.sock';
})());

require_once __DIR__ . '/lib/TunnelProvider.php';
require_once __DIR__ . '/lib/CloudflareTunnelProvider.php';
require_once __DIR__ . '/lib/TunnelManager.php';

$tunnelProvider = new CloudflareTunnelProvider();
$tunnelManager = new TunnelManager($tunnelProvider, CONFIG_DIR);

if (isset($_GET['tab']) && $_GET['tab'] === 'update' && isset($_GET['action']) && $_GET['action'] === 'stream') {
    include __DIR__ . '/panel/update_stream.php';
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

function installWordPress(string $sitePath, string $siteName, string $wpUser, string $wpPass, string $wpEmail, string $wpTitle): array {
    global $db_user, $db_pass;
    $zip = HOME_DIR . '/server/wp.zip';
    exec("curl -sL https://wordpress.org/latest.zip -o " . escapeshellarg($zip) . " 2>&1", $raw, $rc);
    if ($rc !== 0) return [false, 'Failed to download WordPress'];
    exec("unzip -qo " . escapeshellarg($zip) . " -d " . escapeshellarg(dirname($sitePath)) . " 2>&1", $raw, $rc2);
    if ($rc2 !== 0) { @unlink($zip); return [false, 'Failed to extract WordPress']; }
    $wp_temp = dirname($sitePath) . '/wordpress';
    if (is_dir($wp_temp)) {
        exec("cp -r " . escapeshellarg($wp_temp . '/.') . " " . escapeshellarg($sitePath . '/') . " 2>/dev/null; rm -rf " . escapeshellarg($wp_temp));
    }
    $db_name = 'wp_' . str_replace('-', '_', $siteName);
    exec("mariadb -e " . escapeshellarg("CREATE DATABASE IF NOT EXISTS `$db_name`") . " 2>&1", $raw, $rc3);
    exec("mariadb -e " . escapeshellarg("CREATE USER IF NOT EXISTS '$db_user'@'localhost' IDENTIFIED BY '$db_pass'") . " 2>&1", $raw, $rc4);
    exec("mariadb -e " . escapeshellarg("GRANT ALL PRIVILEGES ON `$db_name`.* TO '$db_user'@'localhost'; FLUSH PRIVILEGES") . " 2>&1", $raw, $rc5);
    $wp_config = @file_get_contents($sitePath . '/wp-config-sample.php');
    if ($wp_config) {
        $wp_config = str_replace(
            ["'database_name_here'", "'username_here'", "'password_here'"],
            ["'$db_name'", "'$db_user'", "'$db_pass'"],
            $wp_config
        );
        foreach (['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT'] as $key) {
            $val = bin2hex(random_bytes(16));
            $wp_config = preg_replace("/define\('$key',\s*'[^']*'\);/", "define('$key', '$val');", $wp_config);
        }
        file_put_contents($sitePath . '/wp-config.php', $wp_config);
    }
    @chmod($sitePath, 0755);
    @unlink($zip);
    return [true, ''];
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

function generateNginxBlock(string $domain, int $port, string $path): string {
    $real = realpath($path);
    if (!$real) return '';
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
    if (str_contains($conf, $includeLine)) return true;
    $conf = str_replace('http {', "http {\n    $includeLine", $conf);
    file_put_contents(NGINX_CONF, $conf);
    return true;
}

function restartNginx(): bool {
    exec('pkill nginx 2>/dev/null; sleep 1; nginx 2>&1', $raw, $rc);
    return $rc === 0;
}

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
$db_pass = bin2hex(random_bytes(12));

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

if ($logged_in) {
    $action = $_POST['action'] ?? '';

    if ($action && $_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrfToken();

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
            $domain = trim($_POST['site_domain'] ?? '');
            $type = trim($_POST['site_type'] ?? 'static');
            if (!$domain) $domain = $name . '.test';
            if (!preg_match('/^[a-z0-9_-]+$/', $name)) {
                $flash = ['error', 'Invalid site name (use a-z, 0-9, -, _)'];
            } elseif (!in_array($type, ['static', 'wordpress'], true)) {
                $flash = ['error', 'Invalid site type'];
            } else {
                $siteDir = SITES_DIR . '/' . $name;
                $publicHtml = $siteDir . '/public_html';
                if (is_dir($publicHtml)) {
                    $flash = ['error', "Site '$name' already exists"];
                } else {
                    @mkdir($publicHtml, 0755, true);
                    $port = getNextPort();
                    $config = getSitesConfig();
                    $config[$name] = [
                        'domain' => $domain,
                        'port' => $port,
                        'path' => $publicHtml,
                        'enabled' => true,
                        'type' => $type,
                        'created' => date('Y-m-d H:i:s')
                    ];
                    saveSitesConfig($config);
                    if (!is_dir(NGINX_SITES_DIR)) @mkdir(NGINX_SITES_DIR, 0755, true);
                    $block = generateNginxBlock($domain, $port, $publicHtml);
                    file_put_contents(NGINX_SITES_DIR . '/' . $name . '.conf', $block);
                    rewriteNginxMainConfig();
                    $wpResult = [true, ''];
                    if ($type === 'wordpress') {
                        $wpUser = trim($_POST['wp_user'] ?? 'admin');
                        $wpPass = trim($_POST['wp_pass'] ?? '');
                        $wpEmail = trim($_POST['wp_email'] ?? 'admin@localhost.local');
                        $wpTitle = trim($_POST['wp_title'] ?? 'My Site');
                        if (!$wpPass || strlen($wpPass) < 6) {
                            $flash = ['error', 'WordPress password must be at least 6 characters'];
                        } else {
                            $wpResult = installWordPress($publicHtml, $name, $wpUser, $wpPass, $wpEmail, $wpTitle);
                        }
                    } else {
                        file_put_contents($publicHtml . '/index.php', "<?php\necho '<h1>Welcome to $name</h1>';\n");
                    }
                    restartNginx();
                    if (!isset($flash)) {
                        $url = "http://$domain:$port";
                        $wpSuffix = $type === 'wordpress' ? ' (<a href=\'' . $url . '/wp-admin/install.php\' style=\'color:#3b82f6\'>Complete setup</a>)' : '';
                        $flash = [$wpResult[0] ? 'success' : 'error', ($wpResult[0] ? ucfirst($type) : 'Static') . " site '$name' created — <a href='$url' target='_blank' style='color:#3b82f6'>$url</a>" . ($wpResult[0] ? $wpSuffix : '') . ($wpResult[0] ? '' : ($wpResult[1] ? '<br>' . $wpResult[1] : ''))];
                    }
                }
            }
        } elseif ($action === 'delete_site') {
            $name = trim($_POST['site_name'] ?? '');
            if ($name && preg_match('/^[a-z0-9_-]+$/', $name)) {
                $publicHtml = SITES_DIR . '/' . $name . '/public_html';
                $legacy = DEFAULT_SITE_DIR . '/' . $name;
                $target = is_dir($publicHtml) ? $publicHtml : (is_dir($legacy) ? $legacy : null);
                if ($target) {
                    $fullPath = dirname($target) === $publicHtml ? dirname($target) : $target;
                    exec("rm -rf " . escapeshellarg($fullPath), $raw, $rc);
                    $config = getSitesConfig();
                    unset($config[$name]);
                    saveSitesConfig($config);
                    @unlink(NGINX_SITES_DIR . '/' . $name . '.conf');
                    restartNginx();
                    $flash = [$rc === 0 ? 'success' : 'error', $rc === 0 ? "Site '$name' deleted" : "Failed to delete '$name'"];
                } else {
                    $flash = ['error', "Site '$name' not found"];
                }
            }
        } elseif ($action === 'toggle_site') {
            $name = trim($_POST['site_name'] ?? '');
            $config = getSitesConfig();
            if (isset($config[$name])) {
                $config[$name]['enabled'] = !$config[$name]['enabled'];
                saveSitesConfig($config);
                $target = NGINX_SITES_DIR . '/' . $name . '.conf';
                if ($config[$name]['enabled']) {
                    file_put_contents($target, generateNginxBlock($config[$name]['domain'], $config[$name]['port'], $config[$name]['path']));
                } else {
                    @unlink($target);
                }
                restartNginx();
                $flash = ['success', "Site '$name' " . ($config[$name]['enabled'] ? 'enabled' : 'disabled')];
            } else {
                $flash = ['error', "Site '$name' not found in config"];
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
                file_put_contents($target, generateNginxBlock($domain, $config[$name]['port'], $config[$name]['path']));
                restartNginx();
                $flash = ['success', "Site '$name' updated"];
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
                    if (!is_dir($site_path)) @mkdir($site_path, 0755, true);
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
                                exec("cp -r " . escapeshellarg($wp_temp . '/.') . " " . escapeshellarg($site_path . '/') . " 2>/dev/null; rm -rf " . escapeshellarg($wp_temp));
                            }
                            $db_name = 'wp_' . str_replace('-', '_', $site_name);
                            exec("mariadb -e " . escapeshellarg("CREATE DATABASE IF NOT EXISTS `$db_name`") . " 2>&1", $raw, $rc3);
                            exec("mariadb -e " . escapeshellarg("CREATE USER IF NOT EXISTS '$db_user'@'localhost' IDENTIFIED BY '$db_pass'") . " 2>&1", $raw, $rc4);
                            exec("mariadb -e " . escapeshellarg("GRANT ALL PRIVILEGES ON `$db_name`.* TO '$db_user'@'localhost'; FLUSH PRIVILEGES") . " 2>&1", $raw, $rc5);
                            $wp_config = @file_get_contents($site_path . '/wp-config-sample.php');
                            if ($wp_config) {
                                @exec("curl -sL https://api.wordpress.org/secret-key/1.1/salt/ 2>/dev/null");
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
                            @chmod($site_path, 0755);
                            @unlink($zip);
                            $flash = ['success', "WordPress installed! Site: <a href='/$site_name/wp-admin/install.php' style='color:#3b82f6'>/$site_name/wp-admin/install.php</a>"];
                        }
                    }
                }
            }
        } elseif ($action === 'tunnel_install') {
            $r = $tunnelManager->install();
            $flash = [$r['success'] ? 'success' : 'error', $r['message']];
        } elseif ($action === 'tunnel_login') {
            $r = $tunnelManager->login();
            if (!empty($r['url'])) {
                $_SESSION['tunnel_login_url'] = $r['url'];
            }
            $flash = [$r['success'] ? 'success' : 'error', $r['message']];
        } elseif ($action === 'tunnel_logout') {
            $ok = $tunnelManager->logout();
            $flash = [$ok ? 'success' : 'error', $ok ? 'Logged out of Cloudflare' : 'Logout failed'];
        } elseif ($action === 'tunnel_create') {
            $name = trim($_POST['tunnel_name'] ?? '');
            if (!$name || !preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
                $flash = ['error', 'Invalid tunnel name (letters, numbers, hyphens, underscores)'];
            } else {
                $r = $tunnelManager->createTunnel($name);
                $flash = [$r['success'] ? 'success' : 'error', $r['message']];
            }
        } elseif ($action === 'tunnel_delete') {
            $id = $_POST['tunnel_id'] ?? '';
            if ($id) {
                $r = $tunnelManager->deleteTunnel($id);
                $flash = [$r['success'] ? 'success' : 'error', $r['message']];
            }
        } elseif ($action === 'tunnel_start') {
            $r = $tunnelManager->start();
            $flash = [$r['success'] ? 'success' : 'error', $r['message']];
        } elseif ($action === 'tunnel_stop') {
            $r = $tunnelManager->stop();
            $flash = [$r['success'] ? 'success' : 'error', $r['message']];
        } elseif ($action === 'tunnel_restart') {
            $r = $tunnelManager->restart();
            $flash = [$r['success'] ? 'success' : 'error', $r['message']];
        } elseif ($action === 'tunnel_add_hostname') {
            $hostname = trim($_POST['hostname'] ?? '');
            $target = trim($_POST['target'] ?? '');
            if (!$hostname || !$target) {
                $flash = ['error', 'Please provide both hostname and target'];
            } elseif (!preg_match('/^[a-zA-Z0-9.-]+(\.[a-zA-Z]{2,})+$/', $hostname)) {
                $flash = ['error', 'Invalid hostname (e.g., example.com)'];
            } else {
                $r = $tunnelManager->addHostname($hostname, $target);
                $flash = [$r['success'] ? 'success' : 'error', $r['message']];
            }
        } elseif ($action === 'tunnel_remove_hostname') {
            $hostname = $_POST['hostname'] ?? '';
            if ($hostname) {
                $r = $tunnelManager->removeHostname($hostname);
                $flash = [$r['success'] ? 'success' : 'error', $r['message']];
            }
        } elseif ($action === 'tunnel_clear_logs') {
            $ok = $tunnelManager->clearLogs();
            $flash = [$ok ? 'success' : 'error', $ok ? 'Logs cleared' : 'Failed to clear logs'];
        } elseif ($action === 'tunnel_set_autostart') {
            $enabled = !empty($_POST['auto_start']);
            $tunnelManager->setAutoStart($enabled);
            $flash = ['success', 'Auto-start ' . ($enabled ? 'enabled' : 'disabled')];
        } elseif ($action === 'tunnel_select') {
            $id = $_POST['tunnel_id'] ?? '';
            $name = $_POST['tunnel_name'] ?? '';
            if ($id) {
                $tunnelManager->setActiveTunnel($id, $name);
                $flash = ['success', 'Active tunnel set to: ' . htmlspecialchars($name)];
            }
        } elseif ($action === 'update_system') {
            $base_url = 'https://raw.githubusercontent.com/SayfullahSayeb/mobile-server/main';
            $target_dir = __DIR__;
            $lib_dir = $target_dir . '/lib';
            if (!is_dir($lib_dir)) @mkdir($lib_dir, 0755, true);
            $home_server = HOME_DIR . '/server';
            $elfinder_dir = $target_dir . '/elfinder';
            if (!is_dir($elfinder_dir)) @mkdir($elfinder_dir, 0755, true);
            $panel_dir = $target_dir . '/panel';
            if (!is_dir($panel_dir)) @mkdir($panel_dir, 0755, true);
            $files = [
                'index.php' => $target_dir . '/index.php',
                'control.php' => $target_dir . '/control.php',
                'install.sh' => $home_server . '/install.sh',
                'lib/TunnelProvider.php' => $lib_dir . '/TunnelProvider.php',
                'lib/CloudflareTunnelProvider.php' => $lib_dir . '/CloudflareTunnelProvider.php',
                'lib/TunnelManager.php' => $lib_dir . '/TunnelManager.php',
                'elfinder/panel.php' => $elfinder_dir . '/panel.php',
                'elfinder/connector.php' => $elfinder_dir . '/connector.php',
                'panel/header.php' => $panel_dir . '/header.php',
                'panel/dashboard.php' => $panel_dir . '/dashboard.php',
                'panel/cloudflare.php' => $panel_dir . '/cloudflare.php',
                'panel/sites.php' => $panel_dir . '/sites.php',
                'panel/wordpress.php' => $panel_dir . '/wordpress.php',
                'panel/update.php' => $panel_dir . '/update.php',
                'panel/login.php' => $panel_dir . '/login.php',
                'panel/footer.php' => $panel_dir . '/footer.php',
                'panel/control.css' => $panel_dir . '/control.css',
                'panel/update_stream.php' => $panel_dir . '/update_stream.php',
            ];
            $results = [];
            $all_ok = true;
            foreach ($files as $remote => $local) {
                $dir = dirname($local);
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $url = $base_url . '/' . $remote;
                exec("curl -sL " . escapeshellarg($url) . " -o " . escapeshellarg($local) . " 2>&1", $raw, $rc);
                if ($rc === 0) {
                    $results[] = '<i class="fas fa-check"></i> Updated ' . $remote;
                } else {
                    $all_ok = false;
                    $results[] = '<i class="fas fa-times"></i> Failed to download ' . $remote;
                }
            }
            $flash_type = $all_ok ? 'success' : 'error';
            $flash_msg = 'Update ' . ($all_ok ? 'completed successfully!' : 'completed with errors.') . '<br>' . implode('<br>', $results);
            $flash = [$flash_type, $flash_msg];
        }
        if (true) {
            header('Location: ?tab=' . urlencode($tab));
            exit;
        }
    }

    $status = [];
    foreach ($services as $name => $s) {
        @exec('pgrep -x ' . escapeshellarg($s['process']), $out, $code);
        $status[$name] = $code === 0;
    }

    $hostname   = gethostname();
    $uptime     = trim(@shell_exec('uptime -p 2>/dev/null') ?: 'N/A');
    $php_ver    = phpversion();
    $server_time = date('Y-m-d H:i:s');
    @exec("ip -4 -o addr show wlan0 2>/dev/null | awk '{print \$4}' | cut -d/ -f1", $ip_out, $ip_rc);
    $ip_addr = ($ip_rc === 0 && !empty($ip_out)) ? $ip_out[0] : (trim(@shell_exec('hostname -I 2>/dev/null') ?: 'N/A'));

    $tunnelManager->checkAutoStart();

    $tunnelStatus = $tunnelManager->status();
    $tunnelInstalled = $tunnelManager->isInstalled();
    $tunnelAuthenticated = $tunnelManager->isAuthenticated();
    $tunnelLoginStatus = $tunnelManager->loginStatus();
    $tunnelLoginUrl = $_SESSION['tunnel_login_url'] ?? '';
    if ($tunnelLoginStatus === 'completed') {
        $_SESSION['tunnel_login_url'] = '';
    }
    $tunnelHealth = $tunnelManager->healthStatus();
    $tunnelHostnames = $tunnelManager->getHostnames();
    $tunnelAutoStart = $tunnelManager->isAutoStartEnabled();

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
