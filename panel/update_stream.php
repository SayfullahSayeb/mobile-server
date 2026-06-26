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

function send(string $type, string $data): void {
    echo "event: $type\ndata: " . str_replace("\n", "\\n", $data) . "\n\n";
    flush();
}

function runCmd(string $cmd): int {
    send('line', '$ ' . $cmd);
    $handle = popen($cmd . ' 2>&1', 'r');
    if (!$handle) {
        send('line', 'Error: failed to run command');
        return -1;
    }
    while (!feof($handle)) {
        $line = fgets($handle);
        if ($line !== false) {
            send('line', rtrim($line, "\r\n"));
        }
    }
    return pclose($handle);
}

$server_dir = dirname(__DIR__);
$hash_file = CONFIG_DIR . '/.update_hash';
$repo_url = 'https://github.com/SayfullahSayeb/mobile-server.git';

send('line', 'Checking for updates...');

exec("git ls-remote $repo_url HEAD 2>/dev/null", $remote_raw, $remote_rc);
if ($remote_rc !== 0) {
    send('line', '');
    send('line', 'Failed to reach GitHub. Check your internet connection.');
    send('done', json_encode(['success' => false, 'message' => 'Network error — see output above']));
    exit;
}

$latest_hash = strtok($remote_raw[0], " \t");
$stored_hash = @file_get_contents($hash_file);

if (trim($stored_hash) === $latest_hash) {
    $short = substr($latest_hash, 0, 8);
    send('line', "Already up to date (commit $short)");
    send('done', json_encode(['success' => true, 'message' => 'Already up to date']));
    exit;
}

send('line', "Latest commit: $latest_hash");
send('line', '');

$rc = runCmd("cd " . escapeshellarg($server_dir) . " && git fetch origin 2>&1");

if ($rc !== 0) {
    send('line', '');
    send('line', 'Fetch failed. Check your internet connection.');
    send('done', json_encode(['success' => false, 'message' => 'Fetch failed — see output above']));
    exit;
}

send('line', '');
send('line', 'Pulling latest changes...');
$rc = runCmd("cd " . escapeshellarg($server_dir) . " && git pull 2>&1");

if ($rc !== 0) {
    send('line', '');
    send('line', 'Pull failed. Resolve conflicts manually in Termux:');
    send('line', '  cd ~/mobile-server && git pull');
    send('done', json_encode(['success' => false, 'message' => 'Pull failed — see output above']));
    exit;
}

file_put_contents($hash_file, $latest_hash);

send('line', '');
send('line', 'Update completed successfully!');
send('done', json_encode(['success' => true, 'message' => 'Update completed successfully!']));
