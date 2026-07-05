<?php
putenv('PATH=/data/data/com.termux/files/usr/bin:' . getenv('PATH'));
session_start();

header('Content-Type: application/json');

if (empty($_SESSION['authenticated'])) {
    echo json_encode(['ok' => false, 'output' => 'Auth failed']);
    exit;
}

$cmd = trim($_POST['cmd'] ?? '');
if (!$cmd) {
    echo json_encode(['ok' => true, 'output' => '', 'prompt' => '$ ']);
    exit;
}

$output = '';
exec($cmd . ' 2>&1', $raw, $rc);
$output = implode("\n", $raw);

$user = @exec('whoami') ?: 'user';
echo json_encode([
    'ok' => true,
    'output' => $output,
    'prompt' => "\033[32m$user\033[0m$ "
]);
