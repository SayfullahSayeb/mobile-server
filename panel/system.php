<div class="sec" style="padding:24px">
<?php
$os = trim((string)(@shell_exec('getprop ro.build.version.release 2>/dev/null') ?: 'Android'));
$os_codename = trim((string)(@shell_exec('getprop ro.build.version.codename 2>/dev/null') ?: ''));
$os_sdk = trim((string)(@shell_exec('getprop ro.build.version.sdk 2>/dev/null') ?: ''));
$device_man = trim((string)(@shell_exec('getprop ro.product.manufacturer 2>/dev/null') ?: ''));
$device_model = trim((string)(@shell_exec('getprop ro.product.model 2>/dev/null') ?: ''));
$kernel = trim((string)(@shell_exec('uname -r 2>/dev/null') ?: 'N/A'));
$arch = trim((string)(@shell_exec('uname -m 2>/dev/null') ?: 'N/A'));
$shell = getenv('SHELL') ?: trim((string)(@shell_exec('echo $SHELL 2>/dev/null') ?: 'bash'));
$terminal = getenv('TERM') ?: trim((string)(@shell_exec('echo $TERM 2>/dev/null') ?: 'xterm'));

$pkg_count = trim((string)(@shell_exec('dpkg --get-selections 2>/dev/null | grep -v deinstall | wc -l') ?: ''));
if (!$pkg_count || $pkg_count === '0') {
    $pkg_count = trim((string)(@shell_exec('pkg list-installed 2>/dev/null | wc -l') ?: '?'));
}

$cpu_raw = @file_get_contents('/proc/cpuinfo');
$cpu = 'N/A';
$cores = 0;
if ($cpu_raw) {
    preg_match('/^model name\s+:\s+(.+)$/m', $cpu_raw, $m);
    $cpu = $m[1] ?? '';
    preg_match_all('/^processor\s+:\s+\d+$/m', $cpu_raw, $c);
    $cores = count($c[0] ?? []);
    if (!$cpu) {
        preg_match('/^Hardware\s+:\s+(.+)$/m', $cpu_raw, $m2);
        $cpu = $m2[1] ?? '';
    }
    if (!$cpu) {
        preg_match('/^CPU implementer\s+:\s+(.+)$/m', $cpu_raw, $m3);
        preg_match('/^CPU part\s+:\s+(.+)$/m', $cpu_raw, $m4);
        if ($m3 && $m4) $cpu = trim($m3[1]) . ':' . trim($m4[1]);
    }
    if (!$cpu) $cpu = 'N/A';
}

$mem = @file_get_contents('/proc/meminfo');
$mem_total = 0; $mem_avail = 0;
if ($mem) {
    preg_match('/^MemTotal:\s+(\d+)/m', $mem, $mt);
    preg_match('/^MemAvailable:\s+(\d+)/m', $mem, $ma);
    $mem_total = (int)($mt[1] ?? 0);
    $mem_avail = (int)($ma[1] ?? 0);
}
$mem_used_kb = $mem_total - $mem_avail;
$mem_str = $mem_total > 0 ? number_format($mem_used_kb / 1024) . "MiB / " . number_format($mem_total / 1024) . "MiB" : 'N/A';

$uptime_ts = trim((string)(@shell_exec('cat /proc/uptime 2>/dev/null | awk \'{print $1}\'') ?: '0'));
$up_d = (float)$uptime_ts > 0 ? floor((float)$uptime_ts / 86400) : 0;
$up_h = (float)$uptime_ts > 0 ? floor(((float)$uptime_ts % 86400) / 3600) : 0;
$up_m = (float)$uptime_ts > 0 ? floor(((float)$uptime_ts % 3600) / 60) : 0;
$uptime_str = ($up_d > 0 ? "{$up_d}d " : '') . "{$up_h}h {$up_m}m";
if (!$uptime_ts || $uptime_ts === '0' || $uptime_ts === '0.00') {
    $up_raw = trim((string)(@shell_exec('uptime -p 2>/dev/null') ?: ''));
    if ($up_raw) $uptime_str = preg_replace('/^up\s+/', '', $up_raw);
}

$df_out = @shell_exec("df -B1 " . escapeshellarg(HOME_DIR) . " 2>/dev/null");
$disk_s = 'N/A';
if ($df_out) {
    $dl = explode("\n", trim($df_out));
    if (count($dl) >= 2) {
        $dp = preg_split('/\s+/', $dl[1]);
        if (count($dp) >= 4) {
            $disk_s = number_format((float)$dp[2] / 1073741824, 1) . "G / " . number_format((float)$dp[1] / 1073741824, 1) . "G";
        }
    }
}

$battery = 'N/A';
$bat = @file_get_contents('/sys/class/power_supply/battery/capacity');
if ($bat !== false) {
    $bat_pct = (int)trim($bat);
    $bat_st = trim((string)(@file_get_contents('/sys/class/power_supply/battery/status') ?: 'Unknown'));
    $battery = "$bat_pct% (" . strtolower($bat_st) . ")";
} else {
    $bat_json = trim((string)(@shell_exec('termux-battery-status 2>/dev/null') ?: ''));
    if ($bat_json) {
        $bat_data = json_decode($bat_json, true);
        if ($bat_data && isset($bat_data['percentage'])) {
            $battery = $bat_data['percentage'] . '%' . (isset($bat_data['status']) ? ' (' . strtolower($bat_data['status']) . ')' : '');
        }
    }
}

$ip_local = $ip_addr ?? '127.0.0.1';
$ip_public = trim((string)(@shell_exec('curl -s ifconfig.me 2>/dev/null') ?: ''));

$host = $hostname ?: gethostname();
$device = trim("$device_man $device_model") ?: 'N/A';
$os_str = "Android $os" . ($os_codename ? " ($os_codename)" : '');
$nginx_ver = trim((string)(@shell_exec('nginx -v 2>&1') ?: 'N/A'));
$phpfpm_ver = trim((string)(@shell_exec('php-fpm -v 2>&1 | head -1') ?: ''));
$mariadb_ver = trim((string)(@shell_exec('mariadb --version 2>&1 | head -1') ?: ''));
$cloudflared_ver = trim((string)(@shell_exec('cloudflared --version 2>&1 | head -1') ?: ''));

$extract_ver = function($raw) {
    if (!$raw || $raw === 'N/A') return 'N/A';
    if (preg_match('/(\d+\.\d+[\.\d]*)/', $raw, $m)) return $m[1];
    return $raw;
};
$php_ver = PHP_VERSION;
$nginx_ver_s = $extract_ver($nginx_ver);
$phpfpm_ver_s = $extract_ver($phpfpm_ver);
$mariadb_ver_s = $extract_ver($mariadb_ver);
$cloudflared_ver_s = $extract_ver($cloudflared_ver);

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
