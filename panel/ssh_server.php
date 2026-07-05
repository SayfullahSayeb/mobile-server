<?php
declare(strict_types=1);

$home = getenv('HOME') ?: '/data/data/com.termux/files/home';
$host = '0.0.0.0';
$port = 8023;
$pidFile = $home . '/server/.ssh_ws.pid';
$logFile = $home . '/server/logs/ssh_ws.log';
@mkdir(dirname($logFile), 0755, true);

if (@is_file($pidFile)) {
    $pid = (int)trim(@file_get_contents($pidFile));
    if ($pid > 0) {
        exec("kill -0 $pid 2>/dev/null", $null, $rc);
        if ($rc === 0) exit(0);
    }
}

@file_put_contents($pidFile, (string)getmypid());
@chmod($pidFile, 0600);

$server = @stream_socket_server("tcp://$host:$port", $errno, $errstr, STREAM_SERVER_LISTEN | STREAM_SERVER_BIND);
if (!$server) {
    @file_put_contents($logFile, date('c') . " Failed to bind: $errstr\n", FILE_APPEND);
    exit(1);
}

stream_set_blocking($server, false);

$clients = [];
$processes = [];

while (true) {
    $read = array_merge([$server], array_keys($clients));
    $write = null;
    $except = null;

    if (@stream_select($read, $write, $except, 1) < 1) {
        if (empty($clients)) {
            $runtime = time() - ($_SERVER['REQUEST_TIME'] ?? time());
            if ($runtime > 30) break;
        }
        continue;
    }

    if (in_array($server, $read ?? [])) {
        $client = @stream_socket_accept($server, 0);
        if ($client) {
            stream_set_blocking($client, false);
            $clients[(int)$client] = ['socket' => $client, 'handshake' => false, 'buf' => '', 'authed' => false];
        }
    }

    foreach (($read ?? []) as $sock) {
        if ($sock === $server) continue;
        $id = (int)$sock;
        if (!isset($clients[$id])) continue;

        $data = @fread($sock, 16384);
        if ($data === false || $data === '') {
            cleanup($id, $clients, $processes);
            continue;
        }

        $c = &$clients[$id];

        if (!$c['handshake']) {
            $c['buf'] .= $data;
            if (strpos($c['buf'], "\r\n\r\n") !== false) {
                $authOk = false;
                if (preg_match('/Sec-WebSocket-Key:\s(.+)\r\n/i', $c['buf'], $m)) {
                    $key = trim($m[1]);
                    $token = '';
                    if (preg_match('/[?&]token=([a-f0-9]+)/i', $c['buf'], $tm)) {
                        $token = $tm[1];
                    }
                    if (preg_match('/[?&]sid=([a-zA-Z0-9,-]+)/i', $c['buf'], $sm)) {
                        $sid = $sm[1];
                        $tokenFile = sys_get_temp_dir() . '/ws_' . $sid . '.token';
                        if (is_file($tokenFile)) {
                            $stored = trim(@file_get_contents($tokenFile));
                            if ($stored && $stored === $token) {
                                $authOk = true;
                                @unlink($tokenFile);
                            } else {
                                @unlink($tokenFile);
                            }
                        }
                    }
                    if (!$authOk) {
                        $resp = "HTTP/1.1 403 Forbidden\r\n\r\n";
                        fwrite($sock, $resp);
                        cleanup($id, $clients, $processes);
                        continue;
                    }
                    $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
                    $resp = "HTTP/1.1 101 Switching Protocols\r\n"
                        . "Upgrade: websocket\r\n"
                        . "Connection: Upgrade\r\n"
                        . "Sec-WebSocket-Accept: $accept\r\n\r\n";
                    fwrite($sock, $resp);
                    $c['handshake'] = true;
                    $c['buf'] = '';
                    $processes[$id] = startBash();
                } else {
                    cleanup($id, $clients, $processes);
                }
            }
        } else {
            $frame = decodeFrame($data);
            if ($frame === null) continue;
            if ($frame['opcode'] === 8) {
                cleanup($id, $clients, $processes);
            } elseif ($frame['opcode'] === 9) {
                fwrite($sock, encodeFrame($frame['payload'], 0xA));
            } elseif ($frame['opcode'] === 1 && isset($processes[$id])) {
                $input = $frame['payload'];
                @fwrite($processes[$id]['stdin'], $input);
                @fflush($processes[$id]['stdin']);
            }
        }
    }

    foreach ($processes as $cid => $proc) {
        $out = @fread($proc['stdout'], 8192);
        if ($out !== false && $out !== '') {
            if (isset($clients[$cid])) {
                @fwrite($clients[$cid]['socket'], encodeFrame($out, 0x1));
                @fflush($clients[$cid]['socket']);
            }
        }
        $err = @fread($proc['stderr'], 8192);
        if ($err !== false && $err !== '') {
            if (isset($clients[$cid])) {
                @fwrite($clients[$cid]['socket'], encodeFrame($err, 0x1));
                @fflush($clients[$cid]['socket']);
            }
        }
        $status = @proc_get_status($proc['process']);
        if ($status !== false && !$status['running']) {
            cleanup($cid, $clients, $processes);
        }
    }
}

if (!empty($clients)) {
    foreach (array_keys($clients) as $id) {
        cleanup($id, $clients, $processes);
    }
}
fclose($server);
@unlink($pidFile);

function startBash(): ?array {
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $env = ['TERM' => 'xterm-256color', 'PATH' => getenv('PATH') ?: '/data/data/com.termux/files/usr/bin'];
    $process = @proc_open('bash --login', $descriptors, $pipes, null, $env);
    if (!is_resource($process)) return null;
    stream_set_blocking($pipes[0], false);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    return ['process' => $process, 'stdin' => $pipes[0], 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
}

function decodeFrame(string $data): ?array {
    $len = strlen($data);
    if ($len < 2) return null;
    $b1 = ord($data[0]);
    $b2 = ord($data[1]);
    $opcode = $b1 & 0x0F;
    $masked = ($b2 & 0x80) !== 0;
    $payloadLen = $b2 & 0x7F;
    $offset = 2;
    if ($payloadLen === 126) {
        if ($len < 4) return null;
        $payloadLen = unpack('n', substr($data, 2, 2))[1];
        $offset = 4;
    } elseif ($payloadLen === 127) {
        if ($len < 10) return null;
        $payloadLen = unpack('J', substr($data, 2, 8))[1];
        $offset = 10;
    }
    $maskKey = '';
    if ($masked) {
        if ($len < $offset + 4) return null;
        $maskKey = substr($data, $offset, 4);
        $offset += 4;
    }
    if ($len < $offset + $payloadLen) return null;
    $payload = substr($data, $offset, $payloadLen);
    if ($masked) {
        for ($i = 0; $i < $payloadLen; $i++) {
            $payload[$i] = chr(ord($payload[$i]) ^ ord($maskKey[$i % 4]));
        }
    }
    return ['opcode' => $opcode, 'payload' => $payload];
}

function encodeFrame(string $payload, int $opcode = 0x1): string {
    $len = strlen($payload);
    $frame = chr(0x80 | $opcode);
    if ($len <= 125) {
        $frame .= chr($len);
    } elseif ($len <= 65535) {
        $frame .= chr(126) . pack('n', $len);
    } else {
        $frame .= chr(127) . pack('J', $len);
    }
    return $frame . $payload;
}

function cleanup(int $id, array &$clients, array &$processes): void {
    if (isset($clients[$id])) {
        @fclose($clients[$id]['socket']);
        unset($clients[$id]);
    }
    if (isset($processes[$id])) {
        @fclose($processes[$id]['stdin']);
        @fclose($processes[$id]['stdout']);
        @fclose($processes[$id]['stderr']);
        @proc_close($processes[$id]['process']);
        unset($processes[$id]);
    }
}
