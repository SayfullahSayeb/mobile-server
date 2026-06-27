<?php

class CloudflareTunnelProvider implements TunnelProvider {
    private $homeDir;
    private $prefix;
    private $cloudflaredDir;
    private $logFile;

    public function __construct($homeDir = null, $prefix = null) {
        $this->homeDir = $homeDir ?: (getenv('HOME') ?: '/data/data/com.termux/files/home');
        $this->prefix = $prefix ?: (getenv('PREFIX') ?: '/data/data/com.termux/files/usr');
        $this->cloudflaredDir = $this->homeDir . '/.cloudflared';
        $this->logFile = $this->homeDir . '/server/logs/cloudflare-tunnel.log';
    }

    public function name(): string {
        return 'Cloudflare Tunnel';
    }

    private function binary(): string {
        return $this->prefix . '/bin/cloudflared';
    }

    private function ensureDir(string $dir): void {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    public function isInstalled(): bool {
        return is_file($this->binary());
    }

    public function install(): array {
        if ($this->isInstalled()) {
            return ['success' => true, 'message' => 'Already installed'];
        }

        exec('uname -m 2>/dev/null', $arch, $rc);
        $archMap = [
            'aarch64' => 'arm64',
            'armv8l'  => 'arm64',
            'armv7l'  => 'arm',
            'arm'     => 'arm',
            'x86_64'  => 'amd64',
            'amd64'   => 'amd64',
            'i686'    => '386',
            'i386'    => '386',
        ];
        $cfArch = $archMap[trim($arch[0] ?? '')] ?? 'amd64';

        $url = "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-{$cfArch}";
        $bin = $this->binary();
        $binDir = dirname($bin);

        $this->ensureDir(dirname($this->logFile));

        if (!is_dir($binDir)) {
            return ['success' => false, 'message' => 'Binary directory not found: ' . $binDir];
        }

        exec("curl -sL " . escapeshellarg($url) . " -o " . escapeshellarg($bin) . " 2>&1", $out, $rc);
        if ($rc !== 0) {
            return ['success' => false, 'message' => 'Download failed: ' . implode("\n", $out)];
        }

        exec("chmod +x " . escapeshellarg($bin) . " 2>&1", $out, $rc);
        if ($rc !== 0) {
            return ['success' => false, 'message' => 'chmod failed: ' . implode("\n", $out)];
        }

        return ['success' => true, 'message' => 'Cloudflare Tunnel installed successfully'];
    }

    private function getCertFile(): string {
        return $this->cloudflaredDir . '/cert.pem';
    }

    public function isAuthenticated(): bool {
        return is_file($this->getCertFile());
    }

    private function getLoginPidFile(): string {
        return $this->cloudflaredDir . '/login.pid';
    }

    private function getLoginOutputFile(): string {
        return $this->cloudflaredDir . '/login_output.txt';
    }

    public function login(): array {
        $certFile = $this->getCertFile();
        if (is_file($certFile)) {
            return ['success' => true, 'url' => '', 'message' => 'Already authenticated'];
        }

        if (!$this->isInstalled()) {
            return ['success' => false, 'url' => '', 'message' => 'Cloudflare Tunnel is not installed. Install it first.'];
        }

        $bin = $this->binary();
        $this->ensureDir($this->cloudflaredDir);

        $loginPidFile = $this->getLoginPidFile();
        $outputFile = $this->getLoginOutputFile();

        if (is_file($loginPidFile)) {
            $pid = (int)file_get_contents($loginPidFile);
            if ($this->isProcessRunning($pid)) {
                $url = $this->extractLoginUrl($outputFile);
                return ['success' => true, 'url' => $url, 'message' => 'Login in progress. Open the URL in a browser.'];
            }
            @unlink($loginPidFile);
        }

        @unlink($outputFile);

        $cmd = "nohup " . escapeshellarg($bin) . " tunnel login > " . escapeshellarg($outputFile) . " 2>&1 & echo $!";
        exec($cmd, $out, $rc);

        if ($rc !== 0 || empty($out)) {
            return ['success' => false, 'url' => '', 'message' => 'Failed to start login process'];
        }

        $pid = (int)$out[0];
        file_put_contents($loginPidFile, (string)$pid);

        // Poll for the login URL with retries (up to ~10 seconds)
        $url = '';
        for ($i = 0; $i < 5; $i++) {
            sleep(2);
            $url = $this->extractLoginUrl($outputFile);
            if ($url) break;
            if (!$this->isProcessRunning($pid)) {
                // Process exited — check output for error
                $output = is_file($outputFile) ? file_get_contents($outputFile) : '';
                if ($output && stripos($output, 'error') !== false) {
                    $errorMsg = trim(substr($output, 0, 500));
                    $errorMsg = preg_replace('/https?:\/\/[^\s]+/i', '', $errorMsg);
                    return ['success' => false, 'url' => '', 'message' => 'Login failed: ' . $errorMsg];
                }
                return ['success' => false, 'url' => '', 'message' => 'Login process exited unexpectedly. Try again.'];
            }
        }

        return ['success' => true, 'url' => $url, 'message' => $url ? 'Open the URL in a browser to authenticate.' : 'Login started. Check status in a few seconds.'];

    }

    private function extractLoginUrl(string $outputFile): string {
        if (!is_file($outputFile)) return '';
        $content = file_get_contents($outputFile);
        if (preg_match('/https:\/\/[^\s\n\r]+/', $content, $matches)) {
            return $matches[0];
        }
        return '';
    }

    private function isProcessRunning(int $pid): bool {
        exec("kill -0 $pid 2>/dev/null", $out, $rc);
        return $rc === 0;
    }

    public function logout(): bool {
        $certFile = $this->getCertFile();
        if (is_file($certFile)) {
            @unlink($certFile);
        }

        $loginPidFile = $this->getLoginPidFile();
        if (is_file($loginPidFile)) {
            $pid = (int)file_get_contents($loginPidFile);
            exec("kill $pid 2>/dev/null");
            @unlink($loginPidFile);
        }

        $credsFiles = glob($this->cloudflaredDir . '/*.json');
        foreach ($credsFiles as $f) {
            @unlink($f);
        }

        @unlink($this->getLoginOutputFile());

        return !is_file($certFile);
    }

    public function loginStatus(): string {
        if (is_file($this->getCertFile())) return 'completed';

        $loginPidFile = $this->getLoginPidFile();
        if (is_file($loginPidFile)) {
            $pid = (int)file_get_contents($loginPidFile);
            if ($this->isProcessRunning($pid)) return 'pending';
            if (is_file($this->getCertFile())) return 'completed';
            @unlink($loginPidFile);
        }

        return 'none';
    }

    public function createTunnel(string $name): array {
        $bin = $this->binary();
        exec(escapeshellarg($bin) . " tunnel create " . escapeshellarg($name) . " 2>&1", $out, $rc);
        $output = implode("\n", $out);

        if ($rc !== 0) {
            return ['success' => false, 'tunnel_id' => '', 'message' => $output];
        }

        if (preg_match('/id\s+([a-f0-9-]+)/i', $output, $matches)) {
            return ['success' => true, 'tunnel_id' => $matches[1], 'message' => $output];
        }

        return ['success' => true, 'tunnel_id' => '', 'message' => $output];
    }

    public function deleteTunnel(string $tunnelId): array {
        $bin = $this->binary();
        exec(escapeshellarg($bin) . " tunnel delete -f " . escapeshellarg($tunnelId) . " 2>&1", $out, $rc);
        $this->cleanupPid($tunnelId);
        return ['success' => $rc === 0, 'message' => implode("\n", $out)];
    }

    public function listTunnels(): array {
        $bin = $this->binary();

        exec(escapeshellarg($bin) . " tunnel list --output json 2>/dev/null", $out, $rc);
        if ($rc === 0 && !empty($out)) {
            $data = json_decode(implode('', $out), true);
            if (is_array($data)) {
                $tunnels = [];
                foreach ($data as $t) {
                    $tunnels[] = [
                        'id'     => $t['id'] ?? '',
                        'name'   => $t['name'] ?? '',
                        'created' => $t['createdAt'] ?? $t['created_at'] ?? '',
                        'status' => $t['status'] ?? ($t['conns'][0]['status'] ?? 'inactive'),
                    ];
                }
                return $tunnels;
            }
        }

        exec(escapeshellarg($bin) . " tunnel list 2>&1", $out, $rc);
        if ($rc !== 0 || empty($out)) return [];

        $tunnels = [];
        foreach ($out as $line) {
            if (preg_match('/^([a-f0-9-]{36})\s{2,}(\S+)/', $line, $m)) {
                $tunnels[] = [
                    'id'      => $m[1],
                    'name'    => $m[2],
                    'created' => '',
                    'status'  => '',
                ];
            }
        }
        return $tunnels;
    }

    private function getPidFile(string $tunnelId): string {
        return $this->cloudflaredDir . '/tunnel_' . preg_replace('/[^a-f0-9-]/', '', $tunnelId) . '.pid';
    }

    public function start(string $tunnelId): array {
        $this->ensureDir(dirname($this->logFile));

        $bin = $this->binary();
        $pidFile = $this->getPidFile($tunnelId);

        if (is_file($pidFile)) {
            $pid = (int)file_get_contents($pidFile);
            if ($this->isProcessRunning($pid)) {
                return ['success' => true, 'message' => 'Tunnel is already running'];
            }
            @unlink($pidFile);
        }

        $cmd = "nohup " . escapeshellarg($bin) . " tunnel run " . escapeshellarg($tunnelId)
             . " > " . escapeshellarg($this->logFile) . " 2>&1 & echo $!";
        exec($cmd, $out, $rc);

        if ($rc !== 0 || empty($out)) {
            return ['success' => false, 'message' => 'Failed to start tunnel process'];
        }

        $pid = (int)$out[0];
        file_put_contents($pidFile, (string)$pid);
        sleep(1);

        if (!$this->isProcessRunning($pid)) {
            return ['success' => false, 'message' => 'Tunnel exited immediately. Check logs.'];
        }

        return ['success' => true, 'message' => 'Tunnel started (PID: ' . $pid . ')'];
    }

    public function stop(string $tunnelId): array {
        $pidFile = $this->getPidFile($tunnelId);
        $stopped = false;

        if (is_file($pidFile)) {
            $pid = (int)file_get_contents($pidFile);
            exec("kill $pid 2>/dev/null", $out, $rc);
            if ($rc === 0) $stopped = true;
            @unlink($pidFile);
        }

        exec("pkill -f 'cloudflared tunnel run " . addslashes($tunnelId) . "' 2>/dev/null", $out, $rc);
        if ($rc === 0) $stopped = true;

        return ['success' => $stopped, 'message' => $stopped ? 'Tunnel stopped' : 'Tunnel was not running'];
    }

    public function restart(string $tunnelId): array {
        $this->stop($tunnelId);
        sleep(2);
        return $this->start($tunnelId);
    }

    public function status(string $tunnelId): array {
        $pidFile = $this->getPidFile($tunnelId);
        $running = false;
        $pid = null;

        if (is_file($pidFile)) {
            $pid = (int)file_get_contents($pidFile);
            $running = $this->isProcessRunning($pid);
        }

        if (!$running) {
            exec("pgrep -f 'cloudflared tunnel run " . addslashes($tunnelId) . "' 2>/dev/null", $pids, $rc);
            $running = $rc === 0 && !empty($pids);
            if ($running) {
                $pid = (int)$pids[0];
                file_put_contents($pidFile, (string)$pid);
            }
        }

        $result = [
            'running'        => $running,
            'pid'            => $pid,
            'tunnel_id'      => $tunnelId,
            'urls'           => [],
            'last_connected' => '',
            'uptime'         => '',
        ];

        if ($running && is_file($this->logFile)) {
            $lines = file($this->logFile);
            $recent = $lines ? array_slice($lines, -100) : [];

            foreach ($recent as $line) {
                if (preg_match('/Registered tunnel connection/i', $line) || preg_match('/Connection ' . $tunnelId . '/i', $line)) {
                    $result['last_connected'] = $this->extractTimestamp($line);
                }
                if (preg_match('/https:\/\/[a-z0-9-]+\.trycloudflare\.com/', $line, $m)) {
                    if (!in_array($m[0], $result['urls'])) {
                        $result['urls'][] = $m[0];
                    }
                }
            }
        }

        return $result;
    }

    private function extractTimestamp(string $line): string {
        if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})/', $line, $m)) {
            return $m[1];
        }
        if (preg_match('/^(\w{3}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2})/', $line, $m)) {
            return $m[1];
        }
        return '';
    }

    public function logs(int $lines = 100): string {
        if (!is_file($this->logFile)) return '';
        $content = file($this->logFile);
        $content = $content ? array_slice($content, -$lines) : [];
        return implode('', $content);
    }

    public function clearLogs(): bool {
        if (is_file($this->logFile)) {
            $f = @fopen($this->logFile, 'w');
            if ($f) {
                fclose($f);
                return true;
            }
        }
        return true;
    }

    public function getLogPath(): string {
        return $this->logFile;
    }

    public function writeTunnelConfig(string $tunnelId, array $hostnames): bool {
        $this->ensureDir($this->cloudflaredDir);

        $credsFile = $this->cloudflaredDir . '/' . $tunnelId . '.json';
        if (!is_file($credsFile)) {
            $files = glob($this->cloudflaredDir . '/*.json');
            foreach ($files as $f) {
                $data = json_decode(file_get_contents($f), true);
                if (isset($data['TunnelID']) && $data['TunnelID'] === $tunnelId) {
                    $credsFile = $f;
                    break;
                }
            }
        }

        $configFile = $this->cloudflaredDir . '/config.yml';
        $yaml = "tunnel: " . $tunnelId . "\n";
        $yaml .= "credentials-file: " . $credsFile . "\n";
        $yaml .= "ingress:\n";

        foreach ($hostnames as $hostname => $target) {
            $yaml .= "  - hostname: " . $hostname . "\n";
            $yaml .= "    service: " . $target . "\n";
        }

        $yaml .= "  - service: http_status:404\n";

        return file_put_contents($configFile, $yaml) !== false;
    }

    public function cleanupPid(string $tunnelId): void {
        $pidFile = $this->getPidFile($tunnelId);
        if (is_file($pidFile)) {
            @unlink($pidFile);
        }
    }

    public function addHostname(string $tunnelId, string $hostname, string $target): array {
        $bin = $this->binary();
        exec(escapeshellarg($bin) . " tunnel route dns " . escapeshellarg($tunnelId) . " " . escapeshellarg($hostname) . " 2>&1", $out, $rc);
        return ['success' => $rc === 0, 'message' => implode("\n", $out)];
    }

    public function removeHostname(string $tunnelId, string $hostname): array {
        return ['success' => false, 'message' => 'DNS record removal is not supported via cloudflared CLI. Please remove the DNS record from the Cloudflare Dashboard.'];
    }
}
