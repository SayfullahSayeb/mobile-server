<style>
.sys-wrap{display:flex;gap:32px;flex-wrap:wrap}
.sys-art{flex-shrink:0}
.sys-art pre{margin:0;font-size:13px;line-height:1.8;font-family:'SF Mono','Fira Code',Consolas,monospace;color:var(--green)}
.sys-data{flex:1;min-width:200px}
.sys-data pre{margin:0;font-size:13px;line-height:1.8;font-family:'SF Mono','Fira Code',Consolas,monospace;color:var(--text2)}
.sys-host{margin-bottom:4px}
.sys-host span{color:var(--blue);font-weight:600}
.sys-host .sep{color:var(--text3)}
.sys-line{display:flex;align-items:baseline;gap:8px;padding:1px 0}
.sys-key{color:var(--blue);white-space:nowrap;min-width:100px}
.sys-val{color:var(--text2)}
</style>
<div class="sec" style="padding:24px">
<?php
@set_time_limit(4);

$tos = 'timeout 2 ';

// ---- fast data ----
$os = trim((string)(@shell_exec($tos . 'getprop ro.build.version.release 2>/dev/null') ?: 'Android'));
$os_codename = trim((string)(@shell_exec($tos . 'getprop ro.build.version.codename 2>/dev/null') ?: ''));
$os_sdk = trim((string)(@shell_exec($tos . 'getprop ro.build.version.sdk 2>/dev/null') ?: ''));
$device_man = trim((string)(@shell_exec($tos . 'getprop ro.product.manufacturer 2>/dev/null') ?: ''));
$device_model = trim((string)(@shell_exec($tos . 'getprop ro.product.model 2>/dev/null') ?: ''));
$kernel = trim((string)(@shell_exec($tos . 'uname -r 2>/dev/null') ?: 'N/A'));
$arch = trim((string)(@shell_exec($tos . 'uname -m 2>/dev/null') ?: 'N/A'));

$cpu_raw = @file_get_contents('/proc/cpuinfo');
$cpu = 'N/A'; $cores = 0;
if ($cpu_raw) {
    if (!preg_match('/^model name\s+:\s+(.+)$/m', $cpu_raw, $m))
        preg_match('/^Hardware\s+:\s+(.+)$/m', $cpu_raw, $m);
    $cpu = $m[1] ?? 'N/A';
    preg_match_all('/^processor\s+:\s+\d+$/m', $cpu_raw, $c);
    $cores = count($c[0] ?? []);
}

$mem = @file_get_contents('/proc/meminfo');
$mem_total = 0; $mem_avail = 0;
if ($mem) {
    preg_match('/^MemTotal:\s+(\d+)/m', $mem, $mt);
    preg_match('/^MemAvailable:\s+(\d+)/m', $mem, $ma);
    $mem_total = (int)($mt[1] ?? 0);
    $mem_avail = (int)($ma[1] ?? 0);
}
$mem_str = $mem_total > 0
    ? number_format(($mem_total - $mem_avail) / 1024) . "MiB / " . number_format($mem_total / 1024) . "MiB"
    : 'N/A';

$uptime_ts = @file_get_contents('/proc/uptime');
$up_d = $up_h = $up_m = 0;
if ($uptime_ts !== false) {
    $secs = (float)strtok($uptime_ts, " \t");
    $up_d = floor($secs / 86400);
    $up_h = floor(($secs % 86400) / 3600);
    $up_m = floor(($secs % 3600) / 60);
}
$uptime_str = ($up_d > 0 ? "{$up_d}d " : '') . "{$up_h}h {$up_m}m";

$disk_total = @disk_total_space(HOME_DIR);
$disk_free = @disk_free_space(HOME_DIR);
$disk_s = 'N/A';
if ($disk_total > 0) {
    $disk_s = number_format(($disk_total - $disk_free) / 1073741824, 1) . "G / " . number_format($disk_total / 1073741824, 1) . "G";
}

// ---- single exec for pkg count + versions ----
$pkg_count = '?';
$nginx_ver_s = 'N/A'; $phpfpm_ver_s = 'N/A'; $mariadb_ver_s = 'N/A'; $cloudflared_ver_s = 'N/A';
exec($tos . 'dpkg -l 2>/dev/null | wc -l; '
    . 'echo ___N___; nginx -v 2>&1; '
    . 'echo ___P___; php-fpm -v 2>&1 | head -1; '
    . 'echo ___M___; mariadb --version 2>&1 | head -1; '
    . 'echo ___C___; cloudflared --version 2>&1 | head -1', $combined, $rc);
$section = '';
foreach ($combined as $line) {
    if ($line === '___N___') { $section = 'n'; continue; }
    if ($line === '___P___') { $section = 'p'; continue; }
    if ($line === '___M___') { $section = 'm'; continue; }
    if ($line === '___C___') { $section = 'c'; continue; }
    if ($section === '') { $pkg_count = $line; continue; }
    $v = preg_match('/(\d+\.\d+[\.\d]*)/', $line, $mv) ? $mv[1] : 'N/A';
    if ($section === 'n') $nginx_ver_s = $v;
    if ($section === 'p') $phpfpm_ver_s = $v;
    if ($section === 'm') $mariadb_ver_s = $v;
    if ($section === 'c') $cloudflared_ver_s = $v;
}

// Battery
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

$host = $hostname ?: gethostname();
$device = trim("$device_man $device_model") ?: 'N/A';
$os_str = "Android $os" . ($os_codename ? " ($os_codename)" : '');
$php_ver = PHP_VERSION;
$ip_local = $ip_addr ?? '127.0.0.1';
$shell_path = getenv('SHELL') ?: 'bash';

$data = [
    'OS' => "$os_str (SDK $os_sdk)",
    'Host' => $device,
    'Kernel' => "$kernel ($arch)",
    'Uptime' => $uptime_str,
    'Packages' => $pkg_count,
    'Shell' => basename($shell_path),
    'CPU' => $cpu . ($cores ? " ($cores cores)" : ''),
    'Memory' => $mem_str,
    'Disk' => $disk_s,
    'Battery' => $battery,
    'Local IP' => $ip_local,
    'PHP' => $php_ver,
    'PHP-FPM' => $phpfpm_ver_s,
    'Nginx' => $nginx_ver_s,
    'MariaDB' => $mariadb_ver_s,
    'Cloudflared' => $cloudflared_ver_s,
];

$art = [
    '       ..--..       ',
    '     /  O  O \     ',
    '    |    ..    |    ',
    '    |   \'\'    |    ',
    '     \  --  /     ',
    '   ___/    \___   ',
    '  /  |      |  \  ',
    ' /   |      |   \ ',
    '/    |      |    \',
    '\    \____/    /',
    ' \            / ',
    '  \  |    |  /  ',
    '   \ |    | /   ',
    '    \|    |/    ',
    '     |    |     ',
    '     |    |     ',
    '     |    |     ',
    '    /      \    ',
    '   /        \   ',
];
?>
<div class="sys-wrap">
  <div class="sys-art">
    <pre><?= htmlspecialchars(implode("\n", $art)) ?></pre>
  </div>
  <div class="sys-data">
    <div class="sys-host"><span><?= htmlspecialchars($host) ?></span><span class="sep">@mobile-server</span></div>
    <hr style="border-color:var(--border);margin:4px 0 8px">
    <?php foreach ($data as $k => $v): ?>
    <div class="sys-line"><span class="sys-key"><?= htmlspecialchars($k) ?>:</span><span class="sys-val"><?= htmlspecialchars($v) ?></span></div>
    <?php endforeach; ?>
  </div>
</div>
</div>
