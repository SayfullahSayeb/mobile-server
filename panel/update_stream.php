<?php
if (!defined('HOME_DIR')) { echo "error: direct access not allowed"; exit; }
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    header('Content-Type: text/plain');
    echo 'error: Unauthorized';
    exit;
}
$token = $_GET['csrf_token'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    header('Content-Type: text/plain');
    echo 'error: Invalid token';
    exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
ob_implicit_flush(true);
while (ob_get_level() > 0) ob_end_flush();

function send($type, $data) {
    echo "event: $type\ndata: $data\n\n";
    flush();
}

send('start', 'Starting update...');
sleep(1);

$base_url = 'https://raw.githubusercontent.com/SayfullahSayeb/mobile-server/main';
$target_dir = dirname(__DIR__);
$lib_dir = $target_dir . '/lib';
if (!is_dir($lib_dir)) @mkdir($lib_dir, 0755, true);
$home_server = (getenv('HOME') ?: '/data/data/com.termux/files/home') . '/server';
$elfinder_dir = $target_dir . '/elfinder';
if (!is_dir($elfinder_dir)) @mkdir($elfinder_dir, 0755, true);

$files = [
    'index.php' => $target_dir . '/index.php',
    'control.php' => $target_dir . '/control.php',
    'install.sh' => $home_server . '/install.sh',
    'lib/TunnelProvider.php' => $lib_dir . '/TunnelProvider.php',
    'lib/CloudflareTunnelProvider.php' => $lib_dir . '/CloudflareTunnelProvider.php',
    'lib/TunnelManager.php' => $lib_dir . '/TunnelManager.php',
    'elfinder/panel.php' => $elfinder_dir . '/panel.php',
    'elfinder/connector.php' => $elfinder_dir . '/connector.php',
    'panel/header.php' => $target_dir . '/panel/header.php',
    'panel/dashboard.php' => $target_dir . '/panel/dashboard.php',
    'panel/cloudflare.php' => $target_dir . '/panel/cloudflare.php',
    'panel/sites.php' => $target_dir . '/panel/sites.php',
    'panel/wordpress.php' => $target_dir . '/panel/wordpress.php',
    'panel/update.php' => $target_dir . '/panel/update.php',
    'panel/login.php' => $target_dir . '/panel/login.php',
    'panel/footer.php' => $target_dir . '/panel/footer.php',
    'panel/control.css' => $target_dir . '/panel/control.css',
    'panel/update_stream.php' => $target_dir . '/panel/update_stream.php',
];

$all_ok = true;
$count = 0;
$total = count($files);
send('total', (string)$total);

foreach ($files as $remote => $local) {
    $count++;
    $dir = dirname($local);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $url = $base_url . '/' . $remote;
    exec("curl -sL " . escapeshellarg($url) . " -o " . escapeshellarg($local) . " 2>&1", $raw, $rc);
    if ($rc === 0) {
        send('progress', json_encode([
            'file' => $remote,
            'status' => 'ok',
            'current' => $count,
            'total' => $total
        ]));
    } else {
        $all_ok = false;
        send('progress', json_encode([
            'file' => $remote,
            'status' => 'fail',
            'current' => $count,
            'total' => $total
        ]));
    }
}

sleep(1);
send('done', json_encode([
    'success' => $all_ok,
    'message' => 'Update ' . ($all_ok ? 'completed successfully!' : 'completed with errors.')
]));
