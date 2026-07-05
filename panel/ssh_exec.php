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

$action = $_POST['action'] ?? 'exec';

// Cleanup old run files (>5 minutes)
$runDir = sys_get_temp_dir() . '/ssh_runs';
if (is_dir($runDir)) {
    foreach (glob($runDir . '/*') as $f) {
        if (time() - filemtime($f) > 300) @unlink($f);
    }
}

if ($action === 'start') {
    $cmd = trim($_POST['cmd'] ?? '');
    $runDir = sys_get_temp_dir() . '/ssh_runs';
    @mkdir($runDir, 0755, true);
    $runId = bin2hex(random_bytes(8));
    $outFile = $runDir . '/' . $runId . '.out';
    $pidFile = $runDir . '/' . $runId . '.pid';
    $escapedCwd = escapeshellarg($_SESSION['ssh_cwd']);

    if ($cmd === '') {
        $user = trim(@exec('whoami') ?: 'u0_aXXX');
        $host = trim(@exec('hostname -s 2>/dev/null') ?: 'localhost');
        $displayDir = str_replace($home, $_SESSION['ssh_cwd'], $newCwd ?? $_SESSION['ssh_cwd']);
        $prompt = "\x1b[32m$user@$host\x1b[0m:\x1b[34m$displayDir\x1b[0m$ ";
        header('Content-Type: application/json');
        echo json_encode(['run_id' => '', 'prompt' => $prompt, 'done' => true]);
        exit;
    }

    if (preg_match('/^\s*cd\s+(\S+)\s*$/', $cmd)) {
        $parts = preg_split('/\s+/', $cmd, 2);
        $target = isset($parts[1]) ? trim($parts[1]) : $home;
        if ($target === '' || $target === '~') $target = $home;
        elseif ($target === '-') $target = $_SESSION['ssh_prev_cwd'] ?? $home;
        elseif (strpos($target, '~') === 0) $target = $home . substr($target, 1);
        $absTarget = $target;
        if (!str_starts_with($target, '/')) $absTarget = $_SESSION['ssh_cwd'] . '/' . $target;
        $absTarget = realpath($absTarget);
        if ($absTarget !== false && is_dir($absTarget)) {
            $_SESSION['ssh_prev_cwd'] = $_SESSION['ssh_cwd'];
            $_SESSION['ssh_cwd'] = $absTarget;
            $output = '';
        } else {
            $output = "cd: $target: No such directory\n";
        }
        $user = trim(@exec('whoami') ?: 'u0_aXXX');
        $host = trim(@exec('hostname -s 2>/dev/null') ?: 'localhost');
        $displayDir = str_replace($home, $_SESSION['ssh_cwd'], $_SESSION['ssh_cwd']);
        $prompt = "\x1b[32m$user@$host\x1b[0m:\x1b[34m$displayDir\x1b[0m$ ";
        header('Content-Type: application/json');
        echo json_encode(['run_id' => '', 'output' => $output, 'prompt' => $prompt, 'done' => true]);
        exit;
    }

    $fullCmd = "cd $escapedCwd && " . $cmd . ' > ' . escapeshellarg($outFile) . ' 2>&1 & echo $!';
    $pid = trim(@shell_exec($fullCmd) ?: '0');
    file_put_contents($pidFile, $pid);

    $user = trim(@exec('whoami') ?: 'u0_aXXX');
    $host = trim(@exec('hostname -s 2>/dev/null') ?: 'localhost');
    $displayDir = str_replace($home, $_SESSION['ssh_cwd'], $_SESSION['ssh_cwd']);
    $prompt = "\x1b[32m$user@$host\x1b[0m:\x1b[34m$displayDir\x1b[0m$ ";

    header('Content-Type: application/json');
    echo json_encode(['run_id' => $runId, 'prompt' => $prompt, 'done' => false]);
    exit;
}

if ($action === 'start_ws') {
    $home = getenv('HOME') ?: '/data/data/com.termux/files/home';
    $wsPidFile = $home . '/server/.ssh_ws.pid';
    if (is_file($wsPidFile)) {
        $wsPid = (int)trim(@file_get_contents($wsPidFile));
        if ($wsPid > 0) {
            exec("kill -0 $wsPid 2>/dev/null", $null, $rc);
            if ($rc === 0) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'running' => true]);
                exit;
            }
        }
    }
    $wsScript = __DIR__ . '/ssh_server.php';
    if (is_file($wsScript)) {
        exec("php " . escapeshellarg($wsScript) . " > /dev/null 2>&1 &");
        usleep(300000);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'running' => false]);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Server script not found']);
    exit;
}

if ($action === 'poll') {
    $runId = $_POST['run_id'] ?? '';
    $offset = (int)($_POST['offset'] ?? 0);
    $runDir = sys_get_temp_dir() . '/ssh_runs';
    $outFile = $runDir . '/' . $runId . '.out';
    $pidFile = $runDir . '/' . $runId . '.pid';

    $output = '';
    $running = true;
    if (is_file($outFile)) {
        $total = filesize($outFile);
        if ($offset < $total) {
            $fh = fopen($outFile, 'rb');
            fseek($fh, $offset);
            $output = stream_get_contents($fh);
            fclose($fh);
            $offset = $total;
        }
    }
    if (is_file($pidFile)) {
        $pid = trim(file_get_contents($pidFile));
        if ($pid) {
            exec("kill -0 $pid 2>/dev/null", $null, $rc);
            $running = $rc === 0;
        } else {
            $running = false;
        }
    } else {
        $running = false;
    }
    if (!$running) {
        @unlink($pidFile);
    }

    header('Content-Type: application/json');
    echo json_encode(['output' => $output, 'offset' => $offset, 'done' => !$running]);
    exit;
}

$cmd = trim($_POST['cmd'] ?? '');
$output = '';
$newCwd = $_SESSION['ssh_cwd'];

if ($cmd === '') {
    // Just return current prompt
} elseif (preg_match('/^\s*cd\s+(\S+)\s*$/', $cmd)) {
    $parts = preg_split('/\s+/', $cmd, 2);
    $target = isset($parts[1]) ? trim($parts[1]) : $home;
    if ($target === '' || $target === '~') $target = $home;
    elseif ($target === '-') $target = $_SESSION['ssh_prev_cwd'] ?? $home;
    elseif (strpos($target, '~') === 0) $target = $home . substr($target, 1);
    $absTarget = $target;
    if (!str_starts_with($target, '/')) $absTarget = $_SESSION['ssh_cwd'] . '/' . $target;
    $absTarget = realpath($absTarget);
    if ($absTarget !== false && is_dir($absTarget)) {
        $_SESSION['ssh_prev_cwd'] = $_SESSION['ssh_cwd'];
        $_SESSION['ssh_cwd'] = $absTarget;
        $newCwd = $absTarget;
    } else {
        $output = "cd: $target: No such directory\n";
    }
} else {
    $escapedCwd = escapeshellarg($_SESSION['ssh_cwd']);
    $fullCmd = "cd $escapedCwd && " . $cmd . ' 2>&1';
    exec($fullCmd, $raw, $rc);
    $output = implode("\n", $raw);
    if ($output !== '') $output .= "\n";
}

$user = trim(@exec('whoami') ?: 'u0_aXXX');
$host = trim(@exec('hostname -s 2>/dev/null') ?: 'localhost');
$displayDir = str_replace($home, $newCwd, $newCwd);
$prompt = "\x1b[32m$user@$host\x1b[0m:\x1b[34m$displayDir\x1b[0m$ ";

header('Content-Type: application/json');
echo json_encode(['output' => $output, 'prompt' => $prompt]);
