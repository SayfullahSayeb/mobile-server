<div class="sec">
  <div class="st" style="margin-bottom:0">System Info</div>
  <div style="display:flex;gap:22px;align-items:center;flex-wrap:wrap;margin-top:16px">
    <div style="width:80px;height:80px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:#fff;flex-shrink:0">MS</div>
    <div>
      <div style="font-size:22px;font-weight:700"><?= htmlspecialchars($hostname ?: gethostname()) ?></div>
      <div style="color:var(--text3);font-size:14px;margin-top:2px">Mobile Server</div>
    </div>
    <div style="margin-left:auto;text-align:right;color:var(--text3);font-size:13px;line-height:1.6">
      <?php
      $uptime = trim(@shell_exec('uptime -p 2>/dev/null') ?: 'N/A');
      $uptime_ts = trim(@shell_exec('cat /proc/uptime 2>/dev/null | awk \'{print $1}\'') ?: '0');
      $uptime_days = $uptime_ts > 0 ? floor($uptime_ts / 86400) : 0;
      $uptime_hrs = $uptime_ts > 0 ? floor(($uptime_ts % 86400) / 3600) : 0;
      $uptime_min = $uptime_ts > 0 ? floor(($uptime_ts % 3600) / 60) : 0;
      ?>
      <div>Uptime: <?= $uptime_days > 0 ? "{$uptime_days}d " : '' ?><?= $uptime_hrs ?>h <?= $uptime_min ?>m</div>
      <div>Loaded: <?= date('Y-m-d H:i:s') ?></div>
    </div>
  </div>
</div>

<?php
// OS info
$os = trim(@shell_exec('getprop ro.build.version.release 2>/dev/null') ?: 'Android');
$os_codename = trim(@shell_exec('getprop ro.build.version.codename 2>/dev/null') ?: '');
$os_sdk = trim(@shell_exec('getprop ro.build.version.sdk 2>/dev/null') ?: '');
$device = trim(@shell_exec('getprop ro.product.manufacturer 2>/dev/null') ?: '');
$model = trim(@shell_exec('getprop ro.product.model 2>/dev/null') ?: '');
$kernel = trim(@shell_exec('uname -r 2>/dev/null') ?: 'N/A');
$arch = trim(@shell_exec('uname -m 2>/dev/null') ?: 'N/A');
$shell = trim(@shell_exec('echo $SHELL 2>/dev/null') ?: getenv('SHELL') ?: 'bash');
$terminal = trim(@shell_exec('echo $TERM 2>/dev/null') ?: getenv('TERM') ?: 'xterm');

// Packages
$pkg_count = trim(@shell_exec('dpkg --get-selections 2>/dev/null | grep -v deinstall | wc -l') ?: '?');
if ($pkg_count === '?' || $pkg_count === '0') {
    $pkg_count = trim(@shell_exec('pkg list-installed 2>/dev/null | wc -l') ?: '?');
}

// CPU
$cpu_info = '';
$cpu_cores = 0;
$cpu = @file_get_contents('/proc/cpuinfo');
if ($cpu) {
    preg_match('/^model name\s+:\s+(.+)$/m', $cpu, $m);
    $cpu_model = $m[1] ?? '';
    preg_match_all('/^processor\s+:\s+\d+$/m', $cpu, $cores);
    $cpu_cores = count($cores[0] ?? []);
    if (!$cpu_model) {
        preg_match('/^Hardware\s+:\s+(.+)$/m', $cpu, $m2);
        $cpu_model = $m2[1] ?? '';
    }
    if (!$cpu_model) {
        preg_match('/^CPU implementer\s+:\s+(.+)$/m', $cpu, $m3);
        preg_match('/^CPU part\s+:\s+(.+)$/m', $cpu, $m4);
        if ($m3 && $m4) $cpu_model = trim($m3[1]) . ':' . trim($m4[1]);
    }
    $cpu_info = $cpu_model ?: 'N/A';
} else {
    $cpu_info = trim(@shell_exec('getprop ro.product.board 2>/dev/null') ?: 'N/A');
}

// Memory
$mem_total = 0;
$mem_avail = 0;
$mem = @file_get_contents('/proc/meminfo');
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

// Disk
$disk_total = 0;
$disk_used = 0;
$disk_pct = 0;
$df = @shell_exec("df -B1 " . escapeshellarg(HOME_DIR) . " 2>/dev/null");
if ($df) {
    $lines = explode("\n", trim($df));
    if (count($lines) >= 2) {
        $parts = preg_split('/\s+/', $lines[1]);
        if (count($parts) >= 5) {
            $disk_total = (float)$parts[1];
            $disk_used = (float)$parts[2];
            $disk_pct = $parts[4] ? (int)str_replace('%', '', $parts[4]) : 0;
        }
    }
}
$disk_total_gb = $disk_total > 0 ? number_format($disk_total / 1073741824, 1) : '?';
$disk_used_gb = $disk_used > 0 ? number_format($disk_used / 1073741824, 1) : '?';

// Battery
$battery = '';
$bat_file = @file_get_contents('/sys/class/power_supply/battery/capacity');
if ($bat_file !== false) {
    $bat_pct = (int)trim($bat_file);
    $bat_status = trim(@file_get_contents('/sys/class/power_supply/battery/status') ?: 'Unknown');
    $battery = "$bat_pct% ($bat_status)";
} else {
    $battery = trim(@shell_exec('termux-battery-status 2>/dev/null | grep -o "\"percentage\":[0-9]*" | cut -d: -f2') ?: '');
    if ($battery) $battery .= '%';
    else $battery = 'N/A';
}

// IP
$ip_local = $ip_addr ?? trim(@shell_exec("hostname -I 2>/dev/null | awk '{print \$1}'") ?: '127.0.0.1');
$ip_public = trim(@shell_exec('curl -s ifconfig.me 2>/dev/null') ?: '');
?>

<div class="sec">
  <div class="st" style="margin-bottom:0">OS & Hardware</div>
  <div class="ig" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
    <div class="ii"><div class="l">OS</div><div class="v">Android <?= htmlspecialchars($os) ?><?= $os_codename ? " ($os_codename)" : '' ?></div></div>
    <div class="ii"><div class="l">SDK</div><div class="v"><?= htmlspecialchars($os_sdk ?: 'N/A') ?></div></div>
    <div class="ii"><div class="l">Device</div><div class="v"><?= htmlspecialchars(trim("$device $model") ?: 'N/A') ?></div></div>
    <div class="ii"><div class="l">Kernel</div><div class="v"><?= htmlspecialchars($kernel) ?></div></div>
    <div class="ii"><div class="l">Architecture</div><div class="v"><?= htmlspecialchars($arch) ?></div></div>
    <div class="ii"><div class="l">CPU</div><div class="v" style="font-size:14px"><?= htmlspecialchars($cpu_info) ?><?= $cpu_cores ? " ($cpu_cores cores)" : '' ?></div></div>
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
    <div class="ii"><div class="l">Nginx</div><div class="v"><?= htmlspecialchars(trim(@shell_exec('nginx -v 2>&1') ?: 'N/A')) ?></div></div>
    <div class="ii"><div class="l">MariaDB</div><div class="v"><?= htmlspecialchars(trim(@shell_exec('mariadb --version 2>&1') ?: 'N/A')) ?></div></div>
    <div class="ii"><div class="l">Local IP</div><div class="v"><?= htmlspecialchars($ip_local) ?></div></div>
    <?php if ($ip_public): ?>
    <div class="ii"><div class="l">Public IP</div><div class="v"><?= htmlspecialchars($ip_public) ?></div></div>
    <?php endif; ?>
  </div>
</div>

<div class="sec">
  <div class="st" style="margin-bottom:0"><?= htmlspecialchars(ucfirst($hostname ?: 'localhost')) ?></div>
  <pre style="margin:0;color:var(--text2);font-size:13px;line-height:1.7;font-family:'SF Mono','Fira Code',Consolas,monospace;overflow-x:auto;white-space:pre">
<?php
$lines = [
    "OS:       Android $os" . ($os_codename ? " ($os_codename)" : ''),
    "Device:   " . trim("$device $model") ?: 'N/A',
    "Kernel:   $kernel ($arch)",
    "Uptime:   " . ($uptime_days > 0 ? "{$uptime_days}d " : '') . "{$uptime_hrs}h {$uptime_min}m",
    "Packages: $pkg_count",
    "Shell:    " . basename($shell),
    "CPU:      " . ($cpu_info ?: 'N/A') . ($cpu_cores ? " ($cpu_cores cores)" : ''),
    "Memory:   " . ($mem_used_kb > 0 ? number_format($mem_used_kb / 1024) . "MiB / " . number_format($mem_total / 1024) . "MiB" : 'N/A'),
    "Disk:     " . ($disk_used_gb !== '?' ? "{$disk_used_gb}G / {$disk_total_gb}G" : 'N/A'),
    "Battery:  $battery",
    "Local IP: $ip_local",
];
if ($ip_public) $lines[] = "Public:   $ip_public";
echo implode("\n", $lines);
?>
  </pre>
</div>
