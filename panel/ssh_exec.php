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
if (!isset($_SESSION['ssh_prev_cwd'])) {
    $_SESSION['ssh_prev_cwd'] = $home;
}

$action = $_GET['action'] ?? 'exec';
$cmd = trim($_POST['cmd'] ?? '');

// ---------------------------------------------------------------------------
// Simple exec mode (used by old non-streaming calls)
// ---------------------------------------------------------------------------
if ($action === 'exec') {
    $output = '';
    $newCwd = $_SESSION['ssh_cwd'];

    if ($cmd === '') {
        // just prompt
    } elseif ($cmd === 'cd' || preg_match('/^\s*cd\s+~?\s*$/', $cmd)) {
        $_SESSION['ssh_prev_cwd'] = $_SESSION['ssh_cwd'];
        $_SESSION['ssh_cwd'] = $home;
        $newCwd = $home;
    } elseif (preg_match('/^\s*cd\s+([^\s&|;><`\'"$()!#*?\[\]{}]+)\s*$/', $cmd, $m)) {
        $target = $m[1];
        if ($target === '~') { $target = $home; }
        elseif ($target === '-') { $target = $_SESSION['ssh_prev_cwd'] ?? $home; }
        elseif (strpos($target, '~') === 0) { $target = $home . substr($target, 1); }
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
        $escapedCwd = escapeshellarg($_SESSION['ssh_cwd']);
        $fullCmd = "cd $escapedCwd 2>/dev/null; " . $cmd . ' 2>&1';
        exec($fullCmd, $raw, $rc);
        $output = implode("\n", $raw);
        if ($output !== '') $output .= "\n";
    }

    $user = trim(@exec('whoami') ?: 'u0_aXXX');
    $host = trim(@exec('hostname -s 2>/dev/null') ?: 'localhost');
    $displayDir = str_replace($home, '~', $newCwd);
    $prompt = "\x1b[32m$user@$host\x1b[0m:\x1b[34m$displayDir\x1b[0m$ ";

    header('Content-Type: application/json');
    echo json_encode(['output' => $output, 'prompt' => $prompt]);
    exit;
}

// ---------------------------------------------------------------------------
// Streaming mode — real-time output via NDJSON
// ---------------------------------------------------------------------------
if ($action === 'run') {
    // Disable output buffering
    @ini_set('output_buffering', '0');
    @ini_set('zlib.output_compression', '0');
    @ob_end_flush();
    ob_implicit_flush(true);
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }

    header('Content-Type: application/x-ndjson');
    header('X-Accel-Buffering: no');
    header('Cache-Control: no-cache');

    $cwd = $_SESSION['ssh_cwd'];

    // ---- handle cd internally (no streaming needed) ----
    if (preg_match('/^\s*cd\s/', $cmd)) {
        $parts = preg_split('/\s+/', $cmd, 2);
        $target = isset($parts[1]) ? trim($parts[1]) : $home;
        if ($target === '' || $target === '~') { $target = $home; }
        elseif ($target === '-') { $target = $_SESSION['ssh_prev_cwd'] ?? $home; }
        elseif (strpos($target, '~') === 0) { $target = $home . substr($target, 1); }
        $absTarget = $target;
        if (!str_starts_with($target, '/')) {
            $absTarget = $cwd . '/' . $target;
        }
        $absTarget = realpath($absTarget);
        if ($absTarget !== false && is_dir($absTarget)) {
            $_SESSION['ssh_prev_cwd'] = $cwd;
            $_SESSION['ssh_cwd'] = $absTarget;
            $cwd = $absTarget;
        } else {
            echo json_encode(['t' => 'o', 'd' => "cd: $target: No such directory\n"]) . "\n";
        }
        $prompt = makePrompt($home, $cwd);
        echo json_encode(['t' => 'p', 'd' => $prompt]) . "\n";
        echo json_encode(['t' => 'd']) . "\n";
        flush();
        exit;
    }

    // ---- run command with proc_open ----
    $fullCmd = 'cd ' . escapeshellarg($cwd) . ' 2>/dev/null; ' . $cmd;
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($fullCmd, $descriptors, $pipes);

    if (!is_resource($process)) {
        echo json_encode(['t' => 'o', 'd' => "Failed to start process\n"]) . "\n";
        flush();
        exit;
    }

    stream_set_blocking($pipes[1], 0);
    stream_set_blocking($pipes[2], 0);

    $running = true;
    while ($running) {
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);

        if ($out !== '' && $out !== false) {
            echo json_encode(['t' => 'o', 'd' => $out]) . "\n";
            flush();
        }
        if ($err !== '' && $err !== false) {
            echo json_encode(['t' => 'o', 'd' => $err]) . "\n";
            flush();
        }

        $status = proc_get_status($process);
        $running = $status['running'];

        if ($running) {
            usleep(80000); // 80ms
        }
    }

    // flush remaining output
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    if ($out !== '' && $out !== false) {
        echo json_encode(['t' => 'o', 'd' => $out]) . "\n";
        flush();
    }
    if ($err !== '' && $err !== false) {
        echo json_encode(['t' => 'o', 'd' => $err]) . "\n";
        flush();
    }

    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    // update cwd from session (in case the command changed it via cd)
    $cwd = $_SESSION['ssh_cwd'];
    $prompt = makePrompt($home, $cwd);
    echo json_encode(['t' => 'p', 'd' => $prompt]) . "\n";
    echo json_encode(['t' => 'd']) . "\n";
    flush();
    exit;
}

// ---------------------------------------------------------------------------
// fallback
// ---------------------------------------------------------------------------
header('Content-Type: application/json');
echo json_encode(['output' => '', 'prompt' => makePrompt($home, $_SESSION['ssh_cwd'] ?? $home)]);

// ---------------------------------------------------------------------------
// helpers
// ---------------------------------------------------------------------------
function makePrompt(string $home, string $cwd): string {
    $user = trim(@exec('whoami') ?: 'u0_aXXX');
    $host = trim(@exec('hostname -s 2>/dev/null') ?: 'localhost');
    $displayDir = str_replace($home, '~', $cwd);
    return "\x1b[32m$user@$host\x1b[0m:\x1b[34m$displayDir\x1b[0m$ ";
}
