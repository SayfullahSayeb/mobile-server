<div class="sec" style="padding:24px">
<?php
$os = trim(@shell_exec('getprop ro.build.version.release 2>/dev/null') ?: 'Android');
$os_codename = trim(@shell_exec('getprop ro.build.version.codename 2>/dev/null') ?: '');
$os_sdk = trim(@shell_exec('getprop ro.build.version.sdk 2>/dev/null') ?: '');
$device_man = trim(@shell_exec('getprop ro.product.manufacturer 2>/dev/null') ?: '');
$device_model = trim(@shell_exec('getprop ro.product.model 2>/dev/null') ?: '');
$kernel = trim(@shell_exec('uname -r 2>/dev/null') ?: 'N/A');
$arch = trim(@shell_exec('uname -m 2>/dev/null') ?: 'N/A');
$shell = getenv('SHELL') ?: trim(@shell_exec('echo $SHELL 2>/dev/null') ?: 'bash');
$terminal = getenv('TERM') ?: trim(@shell_exec('echo $TERM 2>/dev/null') ?: 'xterm');

$pkg_count = trim(@shell_exec('dpkg --get-selections 2>/dev/null | grep -v deinstall | wc -l') ?: '');
if (!$pkg_count || $pkg_count === '0') {
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
$mem_total_gb = $mem_total > 0 ? number_format($mem_total / 1024 / 1024, 1) : '?';
$mem_used_gb = $mem_avail > 0 ? number_format($mem_used_kb / 1024 / 1024, 1) : '?';
$mem_pct = $mem_total > 0 ? round(($mem_used_kb / $mem_total) * 100) : 0;
$mem_str = $mem_total > 0 ? number_format($mem_used_kb / 1024) . "MiB / " . number_format($mem_total / 1024) . "MiB" : 'N/A';

$uptime_ts = trim(@shell_exec('cat /proc/uptime 2>/dev/null | awk \'{print $1}\'') ?: '0');
$up_d = $uptime_ts > 0 ? floor($uptime_ts / 86400) : 0;
$up_h = $uptime_ts > 0 ? floor(($uptime_ts % 86400) / 3600) : 0;
$up_m = $uptime_ts > 0 ? floor(($uptime_ts % 3600) / 60) : 0;
$uptime_str = ($up_d > 0 ? "{$up_d}d " : '') . "{$up_h}h {$up_m}m";
if (!$uptime_ts || $uptime_ts === '0') {
    $up_raw = trim(@shell_exec('uptime -p 2>/dev/null') ?: '');
    if ($up_raw) $uptime_str = preg_replace('/^up\s+/', '', $up_raw);
}

$df_out = @shell_exec("df -B1 " . escapeshellarg(HOME_DIR) . " 2>/dev/null");
$disk_s = 'N/A';
$disk_total_gb = '?';
$disk_used_gb = '?';
$disk_pct = 0;
if ($df_out) {
    $dl = explode("\n", trim($df_out));
    if (count($dl) >= 2) {
        $dp = preg_split('/\s+/', $dl[1]);
        if (count($dp) >= 4) {
            $dt = (float)$dp[1];
            $du = (float)$dp[2];
            $disk_total_gb = number_format($dt / 1073741824, 1);
            $disk_used_gb = number_format($du / 1073741824, 1);
            $disk_pct = $dp[3] ? (int)str_replace('%', '', $dp[3]) : 0;
            $disk_s = "{$disk_used_gb}G / {$disk_total_gb}G";
        }
    }
}

$battery = 'N/A';
$bat = @file_get_contents('/sys/class/power_supply/battery/capacity');
if ($bat !== false) {
    $bat_pct = (int)trim($bat);
    $bat_st = trim(@file_get_contents('/sys/class/power_supply/battery/status') ?: 'Unknown');
    $battery = "$bat_pct% (" . strtolower($bat_st) . ")";
} else {
    $bat_json = trim(@shell_exec('termux-battery-status 2>/dev/null'));
    if ($bat_json) {
        $bat_data = json_decode($bat_json, true);
        if ($bat_data && isset($bat_data['percentage'])) {
            $battery = $bat_data['percentage'] . '%' . (isset($bat_data['status']) ? ' (' . strtolower($bat_data['status']) . ')' : '');
        }
    }
}

$ip_local = $ip_addr ?? '127.0.0.1';
$ip_public = trim(@shell_exec('curl -s ifconfig.me 2>/dev/null') ?: '');

$host = $hostname ?: gethostname();
$device = trim("$device_man $device_model") ?: 'N/A';
$os_str = "Android $os" . ($os_codename ? " ($os_codename)" : '');
$nginx_ver = trim(@shell_exec('nginx -v 2>&1') ?: 'N/A');
$mariadb_ver = trim(@shell_exec('mariadb --version 2>&1') ?: 'N/A');
$shell_name = basename($shell);
?>
  <pre style="margin:0;font-size:13px;line-height:1.8;font-family:'SF Mono','Fira Code',Consolas,monospace;color:var(--text2);overflow-x:auto;white-space:pre-wrap"><span style="color:var(--blue)"><?= htmlspecialchars($host) ?></span><span style="color:var(--text3)">@mobile-server</span>
<span style="color:var(--text3)">---------------------------</span>
<span style="color:var(--green)">           .---.</span>          <span style="color:var(--blue)">OS</span>:        <?= htmlspecialchars($os_str) ?>
<span style="color:var(--green)">          /_____\</span>          <span style="color:var(--blue)">Host</span>:      <?= htmlspecialchars($device) ?>
<span style="color:var(--green)">         /  O  O\</span>          <span style="color:var(--blue)">Kernel</span>:    <?= htmlspecialchars($kernel) ?> (<?= htmlspecialchars($arch) ?>)
<span style="color:var(--green)">        /   ▲    \</span>          <span style="color:var(--blue)">Uptime</span>:    <?= htmlspecialchars($uptime_str) ?>
<span style="color:var(--green)">       /  ────┐  \</span>          <span style="color:var(--blue)">Packages</span>:  <?= htmlspecialchars($pkg_count) ?>
<span style="color:var(--green)">      /         \</span>           <span style="color:var(--blue)">Shell</span>:     <?= htmlspecialchars($shell_name) ?>
<span style="color:var(--green)">     /  │     │  \</span>           <span style="color:var(--blue)">CPU</span>:       <?= htmlspecialchars($cpu) ?><?= $cores ? " ($cores cores)" : '' ?>
<span style="color:var(--green)">    /   │     │   \</span>          <span style="color:var(--blue)">Memory</span>:    <?= htmlspecialchars($mem_str) ?>
<span style="color:var(--green)">   /    │     │    \</span>         <span style="color:var(--blue)">Disk</span>:      <?= htmlspecialchars($disk_s) ?>
<span style="color:var(--green)">  /     └───┘┘     \</span>         <span style="color:var(--blue)">Battery</span>:   <?= htmlspecialchars($battery) ?>
<span style="color:var(--green)"> /_________________\</span>        <span style="color:var(--blue)">Local IP</span>:  <?= htmlspecialchars($ip_local) ?>
<span style="color:var(--green)">        |  |</span>                <?php if ($ip_public): ?><span style="color:var(--blue)">Public IP</span>:  <?= htmlspecialchars($ip_public) ?><?php endif; ?>
<span style="color:var(--green)">        |  |</span>
<span style="color:var(--green)">        |  |</span>                <span style="color:var(--blue)">PHP</span>:       <?= htmlspecialchars(PHP_VERSION) ?>
<span style="color:var(--green)">       /    \</span>               <span style="color:var(--blue)">Nginx</span>:     <?= htmlspecialchars($nginx_ver) ?>
<span style="color:var(--green)">      /      \</span>              <span style="color:var(--blue)">MariaDB</span>:   <?= htmlspecialchars($mariadb_ver) ?></pre>
</div>

<div class="sec">
  <div class="st" style="margin-bottom:0">OS & Hardware</div>
  <div class="ig" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
    <div class="ii"><div class="l">OS</div><div class="v">Android <?= htmlspecialchars($os) ?><?= $os_codename ? " ($os_codename)" : '' ?></div></div>
    <div class="ii"><div class="l">SDK</div><div class="v"><?= htmlspecialchars($os_sdk ?: 'N/A') ?></div></div>
    <div class="ii"><div class="l">Device</div><div class="v"><?= htmlspecialchars($device) ?></div></div>
    <div class="ii"><div class="l">Kernel</div><div class="v"><?= htmlspecialchars($kernel) ?></div></div>
    <div class="ii"><div class="l">Architecture</div><div class="v"><?= htmlspecialchars($arch) ?></div></div>
    <div class="ii"><div class="l">CPU</div><div class="v" style="font-size:14px"><?= htmlspecialchars($cpu) ?><?= $cores ? " ($cores cores)" : '' ?></div></div>
  </div>
</div>

<div class="sec">
  <div class="st" style="margin-bottom:0">Memory & Storage</div>
  <div class="ig" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
    <div class="ii">
      <div class="l">RAM</div>
      <div class="v" style="font-size:14px"><?= $mem_used_gb ?> GB / <?= $mem_total_gb ?> GB</div>
      <div style="margin-top:6px;height:4px;background:var(--border);border-radius:2px;overflow:hidden">
        <div style="height:100%;width:<?= $mem_pct ?>%;background:<?= $mem_pct > 80 ? 'var(--red)' : ($mem_pct > 50 ? 'var(--orange)' : 'var(--green)') ?>;border-radius:2px;transition:width .3s"></div>
      </div>
      <div style="font-size:12px;color:var(--text3);margin-top:2px"><?= $mem_pct ?>% used</div>
    </div>
    <div class="ii">
      <div class="l">Disk</div>
      <div class="v" style="font-size:14px"><?= $disk_used_gb ?> GB / <?= $disk_total_gb ?> GB</div>
      <div style="margin-top:6px;height:4px;background:var(--border);border-radius:2px;overflow:hidden">
        <div style="height:100%;width:<?= $disk_pct ?>%;background:<?= $disk_pct > 80 ? 'var(--red)' : ($disk_pct > 50 ? 'var(--orange)' : 'var(--green)') ?>;border-radius:2px;transition:width .3s"></div>
      </div>
      <div style="font-size:12px;color:var(--text3);margin-top:2px"><?= $disk_pct ?>% used</div>
    </div>
    <div class="ii"><div class="l">Packages</div><div class="v"><?= htmlspecialchars($pkg_count) ?></div></div>
    <div class="ii"><div class="l">Battery</div><div class="v"><?= htmlspecialchars($battery) ?></div></div>
  </div>
</div>

<div class="sec">
  <div class="st" style="margin-bottom:0">Software & Environment</div>
  <div class="ig" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
    <div class="ii"><div class="l">Shell</div><div class="v"><?= htmlspecialchars(basename($shell)) ?></div></div>
    <div class="ii"><div class="l">Terminal</div><div class="v"><?= htmlspecialchars($terminal) ?></div></div>
    <div class="ii"><div class="l">PHP</div><div class="v"><?= htmlspecialchars(PHP_VERSION) ?></div></div>
    <div class="ii"><div class="l">Nginx</div><div class="v"><?= htmlspecialchars($nginx_ver) ?></div></div>
    <div class="ii"><div class="l">MariaDB</div><div class="v"><?= htmlspecialchars($mariadb_ver) ?></div></div>
    <div class="ii"><div class="l">Local IP</div><div class="v"><?= htmlspecialchars($ip_local) ?></div></div>
    <?php if ($ip_public): ?>
    <div class="ii"><div class="l">Public IP</div><div class="v"><?= htmlspecialchars($ip_public) ?></div></div>
    <?php endif; ?>
  </div>
</div>

<div class="sec">
  <div class="st" style="margin-bottom:0"><?= htmlspecialchars(ucfirst($host ?: 'localhost')) ?></div>
  <pre style="margin:0;color:var(--text2);font-size:13px;line-height:1.7;font-family:'SF Mono','Fira Code',Consolas,monospace;overflow-x:auto;white-space:pre">
<?php
$lines = [
    "OS:       $os_str",
    "Device:   $device",
    "Kernel:   $kernel ($arch)",
    "Uptime:   $uptime_str",
    "Packages: $pkg_count",
    "Shell:    " . basename($shell),
    "CPU:      " . $cpu . ($cores ? " ($cores cores)" : ''),
    "Memory:   $mem_str",
    "Disk:     $disk_s",
    "Battery:  $battery",
    "Local IP: $ip_local",
];
if ($ip_public) $lines[] = "Public:   $ip_public";
echo implode("\n", $lines);
?>
  </pre>
</div>
