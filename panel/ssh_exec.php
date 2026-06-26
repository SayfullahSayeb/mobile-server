<?php
declare(strict_types=1);

putenv('PATH=/data/data/com.termux/files/usr/bin:' . getenv('PATH'));

session_start();

if (empty($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['output' => 'Unauthorized', 'prompt' => '']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(419);
    echo json_encode(['output' => 'Invalid token', 'prompt' => '']);
    exit;
}

$home = getenv('HOME') ?: '/data/data/com.termux/files/home';

if (!isset($_SESSION['ssh_cwd'])) {
    $_SESSION['ssh_cwd'] = $home;
}

$cmd = trim($_POST['cmd'] ?? '');

$output = '';
$newCwd = $_SESSION['ssh_cwd'];

if ($cmd === '') {
    // Just return current prompt
} elseif (preg_match('/^\s*cd\s/', $cmd)) {
    // Handle cd command - change directory
    $parts = preg_split('/\s+/', $cmd, 2);
    $target = isset($parts[1]) ? trim($parts[1]) : $home;
    if ($target === '' || $target === '~') {
        $target = $home;
    } elseif ($target === '-') {
        $target = $_SESSION['ssh_prev_cwd'] ?? $home;
    } elseif (strpos($target, '~') === 0) {
        $target = $home . substr($target, 1);
    }
    $absTarget = $target;
    if (!str_starts_with($target, '/')) {
        $absTarget = $_SESSION['ssh_cwd'] . '/' . $target;
    }
    $absTarget = realpath($absTarget);
    if ($absTarget !== false && is_dir($absTarget)) {
        $_SESSION['ssh_prev_cwd'] = $_SESSION['ssh_cwd'];
        $_SESSION['ssh_cwd'] = $absTarget;
        $newCwd = $absTarget;
    } else {
        $output = "cd: $target: No such directory\n";
    }
} else {
    // Execute command in current directory
    $escapedCwd = escapeshellarg($_SESSION['ssh_cwd']);
    $fullCmd = "cd $escapedCwd && " . $cmd . ' 2>&1';
    exec($fullCmd, $raw, $rc);
    $output = implode("\n", $raw);
    if ($output !== '') $output .= "\n";
}

// Build prompt
$user = trim(@exec('whoami') ?: 'u0_aXXX');
$host = trim(@exec('hostname -s 2>/dev/null') ?: 'localhost');
$displayDir = str_replace($home, '~', $newCwd);
$prompt = "\x1b[32m$user@$host\x1b[0m:\x1b[34m$displayDir\x1b[0m$ ";

header('Content-Type: application/json');
echo json_encode(['output' => $output, 'prompt' => $prompt]);
