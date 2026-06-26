<?php
declare(strict_types=1);

$home = getenv('HOME') ?: '/data/data/com.termux/files/home';
$logDir = $home . '/server/logs';
$services = [
    'nginx'   => ['icon' => 'fa-bolt',   'color' => '#22c55e'],
    'php-fpm' => ['icon' => 'fa-server', 'color' => '#8b5cf6'],
    'mariadb' => ['icon' => 'fa-database','color' => '#f59e0b'],
];

$maxLines = 200;
$allLines = [];

foreach ($services as $name => $info) {
    $path = $logDir . '/' . $name . '.log';
    if (!is_file($path)) continue;
    $lines = file($path);
    if (!$lines) continue;
    $lines = array_slice($lines, -$maxLines);
    foreach ($lines as $i => $line) {
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
            'svc'   => $name,
            'color' => $info['color'],
            'icon'  => $info['icon'],
            'level' => $level,
            'text'  => $trimmed,
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($allLines);
