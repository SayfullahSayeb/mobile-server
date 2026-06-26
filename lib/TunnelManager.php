<?php

class TunnelManager {
    private $provider;
    private $configPath;
    private $config;

    public function __construct(TunnelProvider $provider, string $configDir) {
        $this->provider = $provider;
        $this->configPath = $configDir . '/tunnel.json';
        $this->loadConfig();
    }

    public function provider(): TunnelProvider {
        return $this->provider;
    }

    private function loadConfig(): void {
        if (is_file($this->configPath)) {
            $this->config = json_decode(file_get_contents($this->configPath), true) ?: [];
        } else {
            $this->config = [];
        }
        if (!isset($this->config['auto_start'])) $this->config['auto_start'] = false;
        if (!isset($this->config['hostnames'])) $this->config['hostnames'] = [];
        if (!isset($this->config['tunnel_id'])) $this->config['tunnel_id'] = '';
        if (!isset($this->config['tunnel_name'])) $this->config['tunnel_name'] = '';
    }

    public function saveConfig(): void {
        $dir = dirname($this->configPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($this->configPath, json_encode($this->config, JSON_PRETTY_PRINT));
    }

    public function isInstalled(): bool {
        return $this->provider->isInstalled();
    }

    public function install(): array {
        $result = $this->provider->install();
        if ($result['success']) {
            $this->config['installed'] = true;
            $this->saveConfig();
        }
        return $result;
    }

    public function isAuthenticated(): bool {
        return $this->provider->isAuthenticated();
    }

    public function login(): array {
        return $this->provider->login();
    }

    public function logout(): bool {
        $result = $this->provider->logout();
        if ($result) {
            $this->config['authenticated'] = false;
            $this->saveConfig();
        }
        return $result;
    }

    public function loginStatus(): string {
        return $this->provider->loginStatus();
    }

    public function createTunnel(string $name): array {
        $result = $this->provider->createTunnel($name);
        if ($result['success'] && !empty($result['tunnel_id'])) {
            $this->config['tunnel_id'] = $result['tunnel_id'];
            $this->config['tunnel_name'] = $name;
            $this->saveConfig();
        }
        return $result;
    }

    public function deleteTunnel(string $tunnelId): array {
        $result = $this->provider->deleteTunnel($tunnelId);
        if ($result['success']) {
            if ($this->config['tunnel_id'] === $tunnelId) {
                $this->config['tunnel_id'] = '';
                $this->config['tunnel_name'] = '';
                $this->config['hostnames'] = [];
                $this->saveConfig();
            }
        }
        return $result;
    }

    public function listTunnels(): array {
        return $this->provider->listTunnels();
    }

    public function start(): array {
        $tunnelId = $this->config['tunnel_id'];
        if (empty($tunnelId)) {
            return ['success' => false, 'message' => 'No tunnel configured'];
        }
        $this->provider->writeTunnelConfig($tunnelId, $this->config['hostnames'] ?? []);
        return $this->provider->start($tunnelId);
    }

    public function stop(): array {
        $tunnelId = $this->config['tunnel_id'];
        if (empty($tunnelId)) {
            return ['success' => false, 'message' => 'No tunnel configured'];
        }
        return $this->provider->stop($tunnelId);
    }

    public function restart(): array {
        $tunnelId = $this->config['tunnel_id'];
        if (empty($tunnelId)) {
            return ['success' => false, 'message' => 'No tunnel configured'];
        }
        $this->provider->writeTunnelConfig($tunnelId, $this->config['hostnames'] ?? []);
        return $this->provider->restart($tunnelId);
    }

    public function status(): array {
        $tunnelId = $this->config['tunnel_id'];
        if (empty($tunnelId)) {
            return ['running' => false, 'tunnel_id' => '', 'urls' => [], 'last_connected' => '', 'pid' => null, 'uptime' => ''];
        }
        $status = $this->provider->status($tunnelId);
        $status['urls'] = array_unique(array_merge($status['urls'], array_keys($this->config['hostnames'] ?? [])));
        return $status;
    }

    public function logs(int $lines = 100): string {
        return $this->provider->logs($lines);
    }

    public function clearLogs(): bool {
        return $this->provider->clearLogs();
    }

    public function getLogPath(): string {
        return $this->provider->getLogPath();
    }

    public function getActiveTunnelId(): ?string {
        return $this->config['tunnel_id'] ?: null;
    }

    public function getActiveTunnelName(): ?string {
        return $this->config['tunnel_name'] ?: null;
    }

    public function setActiveTunnel(string $tunnelId, string $tunnelName): void {
        $this->config['tunnel_id'] = $tunnelId;
        $this->config['tunnel_name'] = $tunnelName;
        $this->saveConfig();
    }

    public function isAutoStartEnabled(): bool {
        return !empty($this->config['auto_start']);
    }

    public function setAutoStart(bool $enabled): void {
        $this->config['auto_start'] = $enabled;
        $this->saveConfig();
    }

    public function getHostnames(): array {
        return $this->config['hostnames'] ?? [];
    }

    public function addHostname(string $hostname, string $target): array {
        $tunnelId = $this->config['tunnel_id'];
        if (empty($tunnelId)) {
            return ['success' => false, 'message' => 'No tunnel configured'];
        }

        $this->config['hostnames'][$hostname] = $target;
        $this->saveConfig();
        $this->provider->writeTunnelConfig($tunnelId, $this->config['hostnames']);

        $result = $this->provider->addHostname($tunnelId, $hostname, $target);

        $status = $this->provider->status($tunnelId);
        if ($status['running']) {
            $this->provider->restart($tunnelId);
        }

        return $result;
    }

    public function removeHostname(string $hostname): array {
        $tunnelId = $this->config['tunnel_id'];
        if (empty($tunnelId)) {
            return ['success' => false, 'message' => 'No tunnel configured'];
        }

        if (!isset($this->config['hostnames'][$hostname])) {
            return ['success' => false, 'message' => 'Hostname not found'];
        }

        unset($this->config['hostnames'][$hostname]);
        $this->saveConfig();
        $this->provider->writeTunnelConfig($tunnelId, $this->config['hostnames']);

        $result = $this->provider->removeHostname($tunnelId, $hostname);

        $status = $this->provider->status($tunnelId);
        if ($status['running']) {
            $this->provider->restart($tunnelId);
        }

        return $result;
    }

    public function healthStatus(): array {
        $issues = [];

        if (!$this->provider->isInstalled()) {
            return ['healthy' => false, 'issues' => ['Cloudflare Tunnel not installed'], 'status' => 'not_installed'];
        }
        if (!$this->provider->isAuthenticated()) {
            return ['healthy' => false, 'issues' => ['Not authenticated with Cloudflare'], 'status' => 'not_authenticated'];
        }

        $tunnelId = $this->config['tunnel_id'] ?? '';
        if (empty($tunnelId)) {
            return ['healthy' => false, 'issues' => ['No tunnel configured'], 'status' => 'no_tunnel'];
        }

        exec('ping -c 1 -W 3 1.1.1.1 2>/dev/null', $out, $rc);
        if ($rc !== 0) {
            $issues[] = 'Internet unavailable';
        }

        $status = $this->provider->status($tunnelId);
        if (!$status['running']) {
            $issues[] = 'Tunnel is not running';
        }

        return [
            'healthy' => empty($issues),
            'issues'  => $issues,
            'status'  => $status['running'] ? 'running' : 'stopped',
        ];
    }

    public function checkAutoStart(): void {
        if ($this->isAutoStartEnabled()) {
            $tunnelId = $this->config['tunnel_id'] ?? '';
            if (!empty($tunnelId)) {
                $status = $this->provider->status($tunnelId);
                if (!$status['running']) {
                    $this->provider->writeTunnelConfig($tunnelId, $this->config['hostnames'] ?? []);
                    $this->provider->start($tunnelId);
                }
            }
        }
    }

    public function hasActiveTunnel(): bool {
        return !empty($this->config['tunnel_id']);
    }
}
