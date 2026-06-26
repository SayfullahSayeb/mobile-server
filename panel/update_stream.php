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

$target_dir = dirname(__DIR__);
$tmp_dir = '/tmp/mobile-server-update-' . bin2hex(random_bytes(4));
$repo_url = 'https://github.com/SayfullahSayeb/mobile-server.git';

if (!is_dir($tmp_dir)) @mkdir($tmp_dir, 0755, true);
exec("git clone --depth 1 " . escapeshellarg($repo_url) . " " . escapeshellarg($tmp_dir) . " 2>&1", $raw, $rc);

if ($rc !== 0) {
    send('progress', json_encode([
        'file' => 'git clone',
        'status' => 'fail',
        'current' => 1,
        'total' => 1
    ]));
    send('done', json_encode([
        'success' => false,
        'message' => 'Failed to clone repository. Check internet connection.'
    ]));
    exit;
}

$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmp_dir, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    $relPath = substr($file->getPathname(), strlen($tmp_dir) + 1);
    if (str_starts_with($relPath, '.git') || str_starts_with($relPath, '.git/')) continue;
    if ($file->isDir()) continue;
    $files[] = $relPath;
}

$all_ok = true;
$count = 0;
$total = count($files);
send('total', (string)$total);

foreach ($files as $relPath) {
    $count++;
    $dest = $target_dir . '/' . $relPath;
    $destDir = dirname($dest);
    if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
    if (@copy($tmp_dir . '/' . $relPath, $dest)) {
        send('progress', json_encode([
            'file' => $relPath,
            'status' => 'ok',
            'current' => $count,
            'total' => $total
        ]));
    } else {
        $all_ok = false;
        send('progress', json_encode([
            'file' => $relPath,
            'status' => 'fail',
            'current' => $count,
            'total' => $total
        ]));
    }
}

exec("rm -rf " . escapeshellarg($tmp_dir) . " 2>&1");

sleep(1);
send('done', json_encode([
    'success' => $all_ok,
    'message' => 'Update ' . ($all_ok ? 'completed successfully!' : 'completed with errors.')
]));
