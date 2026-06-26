<?php
declare(strict_types=1);

$home = getenv('HOME') ?: '/data/data/com.termux/files/home';
$logDir = $home . '/server/logs';
$prefix = '/data/data/com.termux/files/usr';

$services = [
    'panel' => [
        'icon' => 'fa-cog', 'color' => '#3b82f6',
        'paths' => [$logDir . '/panel.log'],
        'label' => 'Panel'
    ],
    'nginx' => [
        'icon' => 'fa-bolt', 'color' => '#22c55e',
        'paths' => [
            $logDir . '/nginx.log',
            $prefix . '/var/log/nginx/error.log',
            $prefix . '/etc/nginx/logs/error.log',
        ],
        'label' => 'Nginx'
    ],
    'php-fpm' => [
        'icon' => 'fa-server', 'color' => '#8b5cf6',
        'paths' => [
            $logDir . '/php-fpm.log',
            $prefix . '/var/log/php-fpm.log',
        ],
        'label' => 'PHP'
    ],
    'mariadb' => [
        'icon' => 'fa-database', 'color' => '#f59e0b',
        'paths' => [
            $logDir . '/mariadb.log',
            $prefix . '/var/log/mariadb.log',
            $prefix . '/var/lib/mysql/error.log',
        ],
        'label' => 'MariaDB'
    ],
];

$maxLines = 200;
$allLines = [];

foreach ($services as $name => $info) {
    $path = null;
    foreach ($info['paths'] as $p) {
        if (is_file($p)) { $path = $p; break; }
    }
    if (!$path) {
        @touch($logDir . '/' . $name . '.log');
        continue;
    }
    $lines = @file($path);
    if (!$lines) continue;
    $lines = array_slice($lines, -$maxLines);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;
        $level = 'info';
        $upper = strtoupper($trimmed);
        if (preg_match('/\b(emerg|alert|critical|error)\b/i', $upper)) {
            $level = 'error';
        } elseif (preg_match('/\b(warning|warn)\b/i', $upper)) {
            $level = 'warn';
        } elseif (preg_match('/\b(notice|info)\b/i', $upper)) {
            $level = 'info';
        }
        $allLines[] = [
            'svc'   => $info['label'],
            'color' => $info['color'],
            'icon'  => $info['icon'],
            'level' => $level,
            'text'  => $trimmed,
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($allLines);
