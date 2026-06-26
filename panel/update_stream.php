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

function send($type, $data): void {
    echo "event: $type\ndata: " . str_replace("\n", "\\n", $data) . "\n\n";
    flush();
}

function sendLine(string $text): void {
    send('line', $text);
}

function runCmd(string $cmd): int {
    sendLine('$ ' . $cmd);
    $handle = popen($cmd . ' 2>&1', 'r');
    if (!$handle) {
        sendLine('Error: failed to run command');
        return -1;
    }
    while (!feof($handle)) {
        $line = fgets($handle);
        if ($line !== false) {
            sendLine(rtrim($line, "\r\n"));
        }
    }
    return pclose($handle);
}

$target_dir = dirname(__DIR__);
$tmp_dir = '/tmp/mobile-server-update-' . bin2hex(random_bytes(4));
$repo_url = 'https://github.com/SayfullahSayeb/mobile-server.git';
$hash_file = CONFIG_DIR . '/.update_hash';

sendLine('Checking for updates...');

exec("git ls-remote $repo_url HEAD 2>/dev/null", $remote_raw, $remote_rc);
if ($remote_rc !== 0) {
    sendLine('');
    sendLine('Failed to reach GitHub. Check your internet connection.');
    sendLine('You can manually update by running in Termux:');
    sendLine('  cd ~/mobile-server && git pull');
    send('done', json_encode(['success' => false, 'message' => 'Network error — see output above']));
    exit;
}

$latest_hash = strtok($remote_raw[0], " \t");
$stored_hash = @file_get_contents($hash_file);

if (trim($stored_hash) === $latest_hash) {
    $short = substr($latest_hash, 0, 8);
    sendLine("Already up to date (commit $short)");
    send('done', json_encode(['success' => true, 'message' => 'Already up to date']));
    exit;
}

sendLine("Latest commit: $latest_hash");

if (!is_dir($tmp_dir)) @mkdir($tmp_dir, 0755, true);
$rc = runCmd("git clone --depth 1 " . escapeshellarg($repo_url) . " " . escapeshellarg($tmp_dir));

if ($rc !== 0) {
    sendLine('');
    sendLine('Clone failed. Check your internet connection.');
    sendLine('You can manually update by running in Termux:');
    sendLine('  cd ~/mobile-server && git pull');
    @exec("rm -rf " . escapeshellarg($tmp_dir) . " 2>/dev/null");
    send('done', json_encode(['success' => false, 'message' => 'Clone failed — see output above']));
    exit;
}

$short = substr($latest_hash, 0, 8);
sendLine("Cloned commit $short. Copying files...");
sendLine('');

$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmp_dir, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    $relPath = substr($file->getPathname(), strlen($tmp_dir) + 1);
    if (strpos($relPath, '.git') === 0 || strpos($relPath, '.git/') === 0) continue;
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
        sendLine("FAILED: $relPath");
    }
}

file_put_contents($hash_file, $latest_hash);
exec("rm -rf " . escapeshellarg($tmp_dir) . " 2>&1");

sendLine('');
sendLine($all_ok ? 'Update completed successfully!' : 'Update completed with errors.');

send('done', json_encode([
    'success' => $all_ok,
    'message' => 'Update ' . ($all_ok ? 'completed successfully!' : 'completed with errors.')
]));
