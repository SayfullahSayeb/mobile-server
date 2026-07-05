<div class="sec" style="padding:24px">
<?php
@set_time_limit(5);

$tos = 'timeout 2 ';

// ---- fast data: /proc files + getprop (no subprocesses) ----
$os = trim((string)(@shell_exec($tos . 'getprop ro.build.version.release 2>/dev/null') ?: 'Android'));
$os_codename = trim((string)(@shell_exec($tos . 'getprop ro.build.version.codename 2>/dev/null') ?: ''));
$os_sdk = trim((string)(@shell_exec($tos . 'getprop ro.build.version.sdk 2>/dev/null') ?: ''));
$device_man = trim((string)(@shell_exec($tos . 'getprop ro.product.manufacturer 2>/dev/null') ?: ''));
$device_model = trim((string)(@shell_exec($tos . 'getprop ro.product.model 2>/dev/null') ?: ''));
$kernel = trim((string)(@shell_exec($tos . 'uname -r 2>/dev/null') ?: 'N/A'));
$arch = trim((string)(@shell_exec($tos . 'uname -m 2>/dev/null') ?: 'N/A'));

// CPU from /proc/cpuinfo
$cpu_raw = @file_get_contents('/proc/cpuinfo');
$cpu = 'N/A'; $cores = 0;
if ($cpu_raw) {
    preg_match('/^model name\s+:\s+(.+)$/m', $cpu_raw, $m) ?: preg_match('/^Hardware\s+:\s+(.+)$/m', $cpu_raw, $m);
    $cpu = $m[1] ?? 'N/A';
    preg_match_all('/^processor\s+:\s+\d+$/m', $cpu_raw, $c);
    $cores = count($c[0] ?? []);
}

// Memory from /proc/meminfo
$mem = @file_get_contents('/proc/meminfo');
$mem_total = 0; $mem_avail = 0;
if ($mem) {
    preg_match('/^MemTotal:\s+(\d+)/m', $mem, $mt);
    preg_match('/^MemAvailable:\s+(\d+)/m', $mem, $ma);
    $mem_total = (int)($mt[1] ?? 0);
    $mem_avail = (int)($ma[1] ?? 0);
}
$mem_str = $mem_total > 0 ? number_format(($mem_total - $mem_avail) / 1024) . "MiB / " . number_format($mem_total / 1024) . "MiB" : 'N/A';

// Uptime from /proc/uptime
$uptime_ts = @file_get_contents('/proc/uptime');
$up_d = 0; $up_h = 0; $up_m = 0;
if ($uptime_ts !== false) {
    $secs = (float)strtok($uptime_ts, " \t");
    $up_d = floor($secs / 86400);
    $up_h = floor(($secs % 86400) / 3600);
    $up_m = floor(($secs % 3600) / 60);
}
$uptime_str = ($up_d > 0 ? "{$up_d}d " : '') . "{$up_h}h {$up_m}m";
if (!$uptime_ts || $uptime_ts === '0.00') {
    $up_raw = trim((string)(@shell_exec($tos . 'uptime -p 2>/dev/null') ?: ''));
    if ($up_raw) $uptime_str = preg_replace('/^up\s+/', '', $up_raw);
}

// Disk (PHP built-in, fast)
$disk_total = @disk_total_space(HOME_DIR);
$disk_free = @disk_free_space(HOME_DIR);
$disk_s = 'N/A';
if ($disk_total > 0) {
    $disk_s = number_format(($disk_total - $disk_free) / 1073741824, 1) . "G / " . number_format($disk_total / 1073741824, 1) . "G";
}

// ---- single exec call for everything that needs a subprocess ----
$combined = '';
exec($tos . 'dpkg -l 2>/dev/null | wc -l; '
    . 'echo SHELL; echo $SHELL; '
    . 'echo TERM; echo $TERM; '
    . 'echo NGINX; nginx -v 2>&1; '
    . 'echo PHPFPM; php-fpm -v 2>&1 | head -1; '
    . 'echo MARIADB; mariadb --version 2>&1 | head -1; '
    . 'echo CF; cloudflared --version 2>&1 | head -1; '
    . 'echo PUBLICIP; curl -s --max-time 2 ifconfig.me 2>/dev/null || curl -s --max-time 2 icanhazip.com 2>/dev/null', $rawCombined, $rcCombined);

$shell = getenv('SHELL') ?: 'bash';
$terminal = getenv('TERM') ?: 'xterm';
$pkg_count = '?';
$nginx_ver_s = 'N/A';
$phpfpm_ver_s = 'N/A';
$mariadb_ver_s = 'N/A';
$cloudflared_ver_s = 'N/A';
$ip_public = '';

$section = '';
foreach ($rawCombined as $line) {
    if ($line === 'SHELL') { $section = 'SHELL'; continue; }
    if ($line === 'TERM') { $section = 'TERM'; continue; }
    if ($line === 'NGINX') { $section = 'NGINX'; continue; }
    if ($line === 'PHPFPM') { $section = 'PHPFPM'; continue; }
    if ($line === 'MARIADB') { $section = 'MARIADB'; continue; }
    if ($line === 'CF') { $section = 'CF'; continue; }
    if ($line === 'PUBLICIP') { $section = 'PUBLICIP'; continue; }
    if ($section === '') {
        $pkg_count = $line;
    } elseif ($section === 'SHELL') {
        $shell = $line;
    } elseif ($section === 'TERM') {
        $terminal = $line;
    } elseif ($section === 'NGINX') {
        $nginx_ver_s = preg_match('/(\d+\.\d+[\.\d]*)/', $line, $m) ? $m[1] : $line;
    } elseif ($section === 'PHPFPM') {
        $phpfpm_ver_s = preg_match('/(\d+\.\d+[\.\d]*)/', $line, $m) ? $m[1] : $line;
    } elseif ($section === 'MARIADB') {
        $mariadb_ver_s = preg_match('/(\d+\.\d+[\.\d]*)/', $line, $m) ? $m[1] : $line;
    } elseif ($section === 'CF') {
        $cloudflared_ver_s = preg_match('/(\d+\.\d+[\.\d]*)/', $line, $m) ? $m[1] : $line;
    } elseif ($section === 'PUBLICIP') {
        if (filter_var($line, FILTER_VALIDATE_IP)) $ip_public = $line;
    }
}

// Battery (sysfs first, fallback termux-battery-status)
$battery = 'N/A';
foreach (['/sys/class/power_supply/battery/capacity','/sys/class/power_supply/BAT0/capacity','/sys/class/power_supply/BAT1/capacity'] as $bp) {
    $bat = @file_get_contents($bp);
    if ($bat !== false) {
        $bat_pct = (int)trim($bat);
        $bat_st = trim((string)(@file_get_contents(dirname($bp) . '/status') ?: 'Unknown'));
        $battery = "$bat_pct% (" . strtolower($bat_st) . ")";
        break;
    }
}
if ($battery === 'N/A') {
    $bat_json = trim((string)(@shell_exec($tos . 'termux-battery-status 2>/dev/null') ?: ''));
    if ($bat_json) {
        $bat_data = json_decode($bat_json, true);
        if ($bat_data && isset($bat_data['percentage'])) {
            $battery = $bat_data['percentage'] . '%' . (isset($bat_data['status']) ? ' (' . strtolower($bat_data['status']) . ')' : '');
        }
    }
}

// ---- display ----
$host = $hostname ?: gethostname();
$device = trim("$device_man $device_model") ?: 'N/A';
$os_str = "Android $os" . ($os_codename ? " ($os_codename)" : '');
$php_ver = PHP_VERSION;
$ip_local = $ip_addr ?? '127.0.0.1';

$art = [
    '           .---.',
    '          /_____\\',
    '         /  O  O\\',
    '        /   ▲    \\',
    '       /  ────┐  \\',
    '      /         \\',
    '     /  │     │  \\',
    '    /   │     │   \\',
    '   /    │     │    \\',
    '  /     └───┘┘     \\',
    ' /_________________\\',
    '        |  |',
    '        |  |',
    '        |  |',
    '       /    \\',
    '      /      \\',
];

$info = [
    "<span style=\"color:var(--blue)\">OS</span>:        " . htmlspecialchars($os_str) . ' (SDK ' . htmlspecialchars($os_sdk ?: '?') . ')',
    "<span style=\"color:var(--blue)\">Host</span>:      " . htmlspecialchars($device),
    "<span style=\"color:var(--blue)\">Kernel</span>:    " . htmlspecialchars($kernel) . ' (' . htmlspecialchars($arch) . ')',
    "<span style=\"color:var(--blue)\">Uptime</span>:    " . htmlspecialchars($uptime_str),
    "<span style=\"color:var(--blue)\">Packages</span>:  " . htmlspecialchars($pkg_count),
    "<span style=\"color:var(--blue)\">Shell</span>:     " . htmlspecialchars(basename($shell)),
    "<span style=\"color:var(--blue)\">CPU</span>:       " . htmlspecialchars($cpu) . ($cores ? ' (' . $cores . ' cores)' : ''),
    "<span style=\"color:var(--blue)\">Memory</span>:    " . htmlspecialchars($mem_str),
    "<span style=\"color:var(--blue)\">Disk</span>:      " . htmlspecialchars($disk_s),
    "<span style=\"color:var(--blue)\">Battery</span>:   " . htmlspecialchars($battery),
    "<span style=\"color:var(--blue)\">Local IP</span>:  " . htmlspecialchars($ip_local),
];
if ($ip_public) $info[] = "<span style=\"color:var(--blue)\">Public IP</span>:  " . htmlspecialchars($ip_public);
$info[] = "<span style=\"color:var(--blue)\">PHP</span>:       " . htmlspecialchars($php_ver);
$info[] = "<span style=\"color:var(--blue)\">PHP-FPM</span>:   " . htmlspecialchars($phpfpm_ver_s);
$info[] = "<span style=\"color:var(--blue)\">Nginx</span>:     " . htmlspecialchars($nginx_ver_s);
$info[] = "<span style=\"color:var(--blue)\">MariaDB</span>:   " . htmlspecialchars($mariadb_ver_s);
$info[] = "<span style=\"color:var(--blue)\">Cloudflared</span>: " . htmlspecialchars($cloudflared_ver_s);

$art_width = 24;
$lines = [];
$max = max(count($art), count($info));
for ($i = 0; $i < $max; $i++) {
    $a = $i < count($art) ? $art[$i] : '';
    $a_padded = str_pad($a, $art_width);
    $a_colored = preg_replace('/^(\s*)(.+)/', '<span style="color:var(--green)">$1$2</span>', $a_padded);
    $t = $i < count($info) ? $info[$i] : '';
    if ($i === 0) {
        $lines[] = '<span style="color:var(--blue)">' . htmlspecialchars($host) . '</span><span style="color:var(--text3)">@mobile-server</span>';
        $lines[] = '<span style="color:var(--text3)">' . str_repeat('-', $art_width + 2 + 37) . '</span>';
    }
    $lines[] = $a_colored . '  ' . $t;
}
?>
  <pre style="margin:0;font-size:13px;line-height:1.8;font-family:'SF Mono','Fira Code',Consolas,monospace;color:var(--text2);overflow-x:auto;white-space:pre-wrap"><?= implode("\n", $lines) ?></pre>
</div>
