<?php
session_start();

if (empty($_SESSION['authenticated'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

define('HOME_DIR', getenv('HOME') ?: '/data/data/com.termux/files/home');
define('SERVER_ROOT', HOME_DIR . '/server');

if (!is_dir(SERVER_ROOT)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server root not found: ' . SERVER_ROOT]);
    exit;
}

require_once __DIR__ . '/php/autoload.php';

function elfinderAccess($attr, $path, $data, $volume, $isDir, $relpath) {
    $serverRoot = realpath(SERVER_ROOT);
    if (!$serverRoot) {
        return ($attr === 'read' || $attr === 'write') ? false : true;
    }

    $filepath = $volume->realpath($path);
    if (!$filepath) {
        return ($attr === 'read' || $attr === 'write') ? false : true;
    }

    $blocked = false;
    $realFile = realpath($filepath);

    if ($realFile === false) {
        $parentDir = dirname($filepath);
        $realParent = realpath($parentDir);
        if ($realParent === false || strpos($realParent, $serverRoot) !== 0) {
            $blocked = true;
        }
    } else {
        if (strpos($realFile, $serverRoot) !== 0) {
            $blocked = true;
        }

        if (!$blocked && is_link($filepath)) {
            $linkTarget = readlink($filepath);
            if (strpos($linkTarget, '/') !== 0) {
                $linkTarget = dirname($filepath) . '/' . $linkTarget;
            }
            $realTarget = realpath($linkTarget);
            if ($realTarget === false || strpos($realTarget, $serverRoot) !== 0) {
                $blocked = true;
            }
        }
    }

    if (!$blocked) {
        $basename = basename($filepath);
        if (preg_match('/^\./', $basename) || preg_match('/^(system|storage|data)$/i', $basename)) {
            $blocked = true;
        }
    }

    if ($blocked) {
        if ($attr === 'read' || $attr === 'write') return false;
        if ($attr === 'hidden' || $attr === 'locked') return true;
    }

    return null;
}

$serverRoot = realpath(SERVER_ROOT);
if (!$serverRoot) $serverRoot = SERVER_ROOT;

$quarantineDir = $serverRoot . '/.quarantine';
if (!is_dir($quarantineDir)) {
    @mkdir($quarantineDir, 0700, true);
}

$tmbDir = $serverRoot . '/.tmb';
if (!is_dir($tmbDir)) {
    @mkdir($tmbDir, 0700, true);
}

$volumes = [
    [
        'driver' => 'LocalFileSystem',
        'path' => $serverRoot,
        'alias' => 'Server Root',
        'mimeDetect' => 'internal',
        'tmbPath' => $tmbDir,
        'tmbPathMode' => 0700,
        'quarantine' => $quarantineDir,
        'accessControl' => 'elfinderAccess',
        'uploadMaxSize' => '256M',
        'uploadAllow' => ['image', 'text', 'video', 'audio', 'application/zip', 'application/x-gzip', 'application/x-tar', 'application/gzip', 'application/pdf', 'application/json'],
        'uploadDeny' => [],
        'uploadOrder' => 'allow,deny',
        'disabled' => [],
        'dateFormat' => 'Y-m-d H:i',
        'publishPermission' => 0644,
        'permMode' => 0755,
        'copyOverwrite' => 1,
        'copyJoin' => true,
        'attributes' => [
            [
                'pattern' => '/\.(git|tmb|quarantine)/',
                'read' => false,
                'write' => false,
                'hidden' => true,
                'locked' => true
            ],
            [
                'pattern' => '/\/\.(?!\.)/',
                'read' => false,
                'write' => false,
                'hidden' => true,
                'locked' => true
            ]
        ]
    ]
];

$sitesDir = $serverRoot . '/sites';
if (is_dir($sitesDir)) {
    $sites = glob($sitesDir . '/*', GLOB_ONLYDIR);
    sort($sites);
    foreach ($sites as $siteDir) {
        $siteName = basename($siteDir);
        $publicHtml = $siteDir . '/public_html';
        if (is_dir($publicHtml)) {
            $volumes[] = [
                'driver' => 'LocalFileSystem',
                'path' => $publicHtml,
                'alias' => $siteName,
                'mimeDetect' => 'internal',
                'tmbPath' => $publicHtml . '/.tmb',
                'tmbPathMode' => 0700,
                'quarantine' => $quarantineDir,
                'accessControl' => 'elfinderAccess',
                'uploadMaxSize' => '256M',
                'uploadAllow' => ['image', 'text', 'video', 'audio', 'application/zip', 'application/x-gzip', 'application/x-tar', 'application/gzip', 'application/pdf', 'application/json'],
                'uploadDeny' => [],
                'uploadOrder' => 'allow,deny',
                'disabled' => [],
                'dateFormat' => 'Y-m-d H:i',
                'publishPermission' => 0644,
                'permMode' => 0755,
                'copyOverwrite' => 1,
                'copyJoin' => true,
                'attributes' => [
                    [
                        'pattern' => '/\.tmb/',
                        'read' => false,
                        'write' => false,
                        'hidden' => true,
                        'locked' => true
                    ],
                    [
                        'pattern' => '/\/\.(?!\.)/',
                        'read' => false,
                        'write' => false,
                        'hidden' => true,
                        'locked' => true
                    ]
                ]
            ];
        }
    }
}

$opts = ['roots' => $volumes];

$connector = new elFinderConnector(new elFinder($opts));
$connector->run();
