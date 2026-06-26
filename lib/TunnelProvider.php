<?php

interface TunnelProvider {
    public function name(): string;
    public function isInstalled(): bool;
    public function install(): array;
    public function isAuthenticated(): bool;
    public function login(): array;
    public function logout(): bool;
    public function loginStatus(): string;
    public function createTunnel(string $name): array;
    public function deleteTunnel(string $tunnelId): array;
    public function listTunnels(): array;
    public function start(string $tunnelId): array;
    public function stop(string $tunnelId): array;
    public function restart(string $tunnelId): array;
    public function status(string $tunnelId): array;
    public function logs(int $lines = 100): string;
    public function clearLogs(): bool;
    public function getLogPath(): string;
    public function writeTunnelConfig(string $tunnelId, array $hostnames): bool;
    public function cleanupPid(string $tunnelId): void;
    public function addHostname(string $tunnelId, string $hostname, string $target): array;
    public function removeHostname(string $tunnelId, string $hostname): array;
}
