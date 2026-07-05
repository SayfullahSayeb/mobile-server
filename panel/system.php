<div class="sec" style="padding:24px">
<?php
$os = trim(@shell_exec('getprop ro.build.version.release 2>/dev/null') ?: 'Android');
$os_codename = trim(@shell_exec('getprop ro.build.version.codename 2>/dev/null') ?: '');
$device_man = trim(@shell_exec('getprop ro.product.manufacturer 2>/dev/null') ?: '');
$device_model = trim(@shell_exec('getprop ro.product.model 2>/dev/null') ?: '');
$kernel = trim(@shell_exec('uname -r 2>/dev/null') ?: 'N/A');
$arch = trim(@shell_exec('uname -m 2>/dev/null') ?: 'N/A');
$shell = trim(@shell_exec('echo $SHELL 2>/dev/null') ?: getenv('SHELL') ?: 'bash');

$pkg_count = trim(@shell_exec('dpkg --get-selections 2>/dev/null | grep -v deinstall | wc -l') ?: '?');
if ($pkg_count === '?' || $pkg_count === '0') {
    $pkg_count = trim(@shell_exec('pkg list-installed 2>/dev/null | wc -l') ?: '?');
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

$uptime_ts = trim(@shell_exec('cat /proc/uptime 2>/dev/null | awk \'{print $1}\'') ?: '0');
$up_d = $uptime_ts > 0 ? floor($uptime_ts / 86400) : 0;
$up_h = $uptime_ts > 0 ? floor(($uptime_ts % 86400) / 3600) : 0;
$up_m = $uptime_ts > 0 ? floor(($uptime_ts % 3600) / 60) : 0;
$uptime_str = ($up_d > 0 ? "{$up_d}d " : '') . "{$up_h}h {$up_m}m";

$df_out = @shell_exec("df -B1 " . escapeshellarg(HOME_DIR) . " 2>/dev/null");
$disk_s = 'N/A';
if ($df_out) {
    $dl = explode("\n", trim($df_out));
    if (count($dl) >= 2) {
        $dp = preg_split('/\s+/', $dl[1]);
        if (count($dp) >= 4) $disk_s = number_format((float)$dp[2] / 1073741824, 1) . "G / " . number_format((float)$dp[1] / 1073741824, 1) . "G";
    }
}

$battery = 'N/A';
$bat = @file_get_contents('/sys/class/power_supply/battery/capacity');
if ($bat !== false) {
    $bat_pct = (int)trim($bat);
    $bat_st = trim(@file_get_contents('/sys/class/power_supply/battery/status') ?: 'Unknown');
    $battery = "$bat_pct% (" . strtolower($bat_st) . ")";
}

$ip_local = $ip_addr ?? '127.0.0.1';
$ip_public = trim(@shell_exec('curl -s ifconfig.me 2>/dev/null') ?: '');

$host = $hostname ?: gethostname();
$device = trim("$device_man $device_model") ?: 'N/A';
$os_str = "Android $os" . ($os_codename ? " ($os_codename)" : '');

// Color values for neofetch-like display
$c = fn($label) => "\033[38;5;39m"; // blue for labels
$cv = fn($val) => "\033[38;5;15m";  // white for values
$rs = "\033[0m";
?>
  <pre style="margin:0;font-size:13px;line-height:1.8;font-family:'SF Mono','Fira Code',Consolas,monospace;color:var(--text2);overflow-x:auto;white-space:pre"><span style="color:var(--blue)"><?= htmlspecialchars($host) ?></span><span style="color:var(--text3)">@mobile-server</span>
<span style="color:var(--text3)">--------------------------</span>
<span style="color:var(--green)">           .---. </span>        <span style="color:var(--blue)">OS</span>:        <?= htmlspecialchars($os_str) ?>

<span style="color:var(--green)">          /_____\ </span>       <span style="color:var(--blue)">Host</span>:      <?= htmlspecialchars($device) ?>

<span style="color:var(--green)">         /  O  O\ </span>       <span style="color:var(--blue)">Kernel</span>:    <?= htmlspecialchars($kernel) ?> (<?= htmlspecialchars($arch) ?>)

<span style="color:var(--green)">        /   ▲    \ </span>       <span style="color:var(--blue)">Uptime</span>:    <?= htmlspecialchars($uptime_str) ?>

<span style="color:var(--green)">       /  ────┐  \ </span>       <span style="color:var(--blue)">Packages</span>:  <?= htmlspecialchars($pkg_count) ?>

<span style="color:var(--green)">      /         \ </span>        <span style="color:var(--blue)">Shell</span>:     <?= htmlspecialchars(basename($shell)) ?>

<span style="color:var(--green)">     /  │     │  \ </span>        <span style="color:var(--blue)">CPU</span>:       <?= htmlspecialchars($cpu) ?><?= $cores ? " ($cores cores)" : '' ?>

<span style="color:var(--green)">    /   │     │   \ </span>       <span style="color:var(--blue)">Memory</span>:    <?= htmlspecialchars($mem_str) ?>

<span style="color:var(--green)">   /    │     │    \ </span>      <span style="color:var(--blue)">Disk</span>:      <?= htmlspecialchars($disk_s) ?>

<span style="color:var(--green)">  /     └───┘┘     \ </span>      <span style="color:var(--blue)">Battery</span>:   <?= htmlspecialchars($battery) ?>

<span style="color:var(--green)"> /_________________\ </span>     <span style="color:var(--blue)">Local IP</span>:  <?= htmlspecialchars($ip_local) ?>

<span style="color:var(--green)">        |  |         </span>     <?php if ($ip_public): ?><span style="color:var(--blue)">Public IP</span>:  <?= htmlspecialchars($ip_public) ?><?php endif; ?>
<span style="color:var(--green)">        |  |         </span>
<span style="color:var(--green)">        |  |         </span>     <span style="color:var(--blue)">PHP</span>:       <?= htmlspecialchars(PHP_VERSION) ?>
<span style="color:var(--green)">       /    \        </span>     <span style="color:var(--blue)">Nginx</span>:     <?= htmlspecialchars(trim(@shell_exec('nginx -v 2>&1') ?: 'N/A')) ?>
<span style="color:var(--green)">      /      \       </span>     <span style="color:var(--blue)">MariaDB</span>:   <?= htmlspecialchars(trim(@shell_exec('mariadb --version 2>&1') ?: 'N/A')) ?></pre>
</div>
