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
$cacheFile = CONFIG_DIR . '/sys_cache.json';
$cacheTtl = 30;
$cache = null;
if (is_file($cacheFile)) {
    $cache = json_decode(@file_get_contents($cacheFile), true);
}
$host = $hostname ?: gethostname();
$ip_local = $ip_addr ?? '127.0.0.1';
$shell_path = getenv('SHELL') ?: 'bash';

if ($cache && isset($cache['time']) && (time() - $cache['time']) < $cacheTtl) {
    $data = $cache['data'];
} else {
    @set_time_limit(20);

    $os = 'Android'; $os_codename = ''; $os_sdk = ''; $device_man = ''; $device_model = '';
    $kernel = 'N/A'; $arch = 'N/A';
    $props = @shell_exec('getprop ro.build.version.release 2>/dev/null; echo _;'
        . 'getprop ro.build.version.codename 2>/dev/null; echo _;'
        . 'getprop ro.build.version.sdk 2>/dev/null; echo _;'
        . 'getprop ro.product.manufacturer 2>/dev/null; echo _;'
        . 'getprop ro.product.model 2>/dev/null; echo _;'
        . 'uname -r 2>/dev/null; echo _;'
        . 'uname -m 2>/dev/null');
    if ($props) {
        $parts = explode("_\n", $props);
        if (isset($parts[0])) $os = trim($parts[0]) ?: 'Android';
        if (isset($parts[1])) $os_codename = trim($parts[1]);
        if (isset($parts[2])) $os_sdk = trim($parts[2]);
        if (isset($parts[3])) $device_man = trim($parts[3]);
        if (isset($parts[4])) $device_model = trim($parts[4]);
        if (isset($parts[5])) $kernel = trim($parts[5]) ?: 'N/A';
        if (isset($parts[6])) $arch = trim($parts[6]) ?: 'N/A';
    }

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

    $uptime_str = 'N/A';
    $uptime_ts = @file_get_contents('/proc/uptime');
    if ($uptime_ts !== false) {
        $secs = (float)strtok($uptime_ts, " \t");
        if ($secs > 0) {
            $up_d = floor($secs / 86400);
            $up_h = floor(($secs % 86400) / 3600);
            $up_m = floor(($secs % 3600) / 60);
            $uptime_str = ($up_d > 0 ? "{$up_d}d " : '') . "{$up_h}h {$up_m}m";
        }
    }
    if ($uptime_str === 'N/A') {
        $uptime_str = trim((string)(@shell_exec('uptime -p 2>/dev/null') ?: 'N/A'));
    }

    $disk_s = 'N/A';
    $df = @shell_exec('df -k ' . escapeshellarg(HOME_DIR) . ' 2>/dev/null | tail -1');
    if ($df && preg_match('/\s+(\d+)\s+\d+\s+(\d+)\s+/', $df, $dm)) {
        $total = (int)$dm[1] * 1024;
        $avail = (int)$dm[2] * 1024;
        $disk_s = number_format(($total - $avail) / 1073741824, 1) . "G / " . number_format($total / 1073741824, 1) . "G";
    }

    $pkg_count = '?';
    $pkg_count = trim(@shell_exec('find /data/data/com.termux/files/usr/var/lib/dpkg/info -name "*.list" 2>/dev/null | wc -l') ?? '?') ?: '?';

    $to = @shell_exec('command -v timeout 2>/dev/null') ? 'timeout 3' : '';
    $ver = function($bin) use ($to) {
        $o = @shell_exec(($to ? "$to " : '') . "command -v $bin >/dev/null 2>&1 && $bin --version 2>&1 | head -1" . ($to ? '' : ' 2>/dev/null'));
        return $o && preg_match('/(\d+\.\d+[\.\d]*)/', $o, $m) ? $m[1] : 'N/A';
    };
    $nginx_ver_s = $ver('nginx');
    $phpfpm_ver_s = $ver('php-fpm');
    $mariadb_ver_s = $ver('mariadb');
    $cloudflared_ver_s = $ver('cloudflared');
    $ttyd_ver_s = $ver('ttyd');

    $device = trim("$device_man $device_model") ?: 'N/A';
    $os_str = "Android $os" . ($os_codename ? " ($os_codename)" : '');
    $php_ver = PHP_VERSION;

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
        'Local IP' => $ip_local,
        'PHP' => $php_ver,
        'PHP-FPM' => $phpfpm_ver_s,
        'Nginx' => $nginx_ver_s,
        'MariaDB' => $mariadb_ver_s,
        'ttyd' => $ttyd_ver_s,
        'Cloudflared' => $cloudflared_ver_s,
    ];

    @mkdir(CONFIG_DIR, 0755, true);
    @file_put_contents($cacheFile, json_encode(['time' => time(), 'data' => $data]));
}

$art = [
    '⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⣀⡀⠀⠀⠀⠀⠀⠄⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀',
    '⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠘⡿⠇⠀⠀⠀⠀⢻⡄⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀',
    '⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⢀⡇⠀⠀⠀⠀⡸⣞⡇⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀',
    '⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⢸⠃⠀⠀⠀⢀⣧⢿⣽⡀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀',
    '⠀⢴⣿⠆⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⢸⠀⠀⠀⠀⣼⣞⡿⣞⡅⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀',
    '⠀⠀⠈⠓⢤⡀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⣾⠀⠀⠀⣰⣟⢾⣽⢫⡿⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀',
    '⠀⠀⠀⠀⠀⠙⢦⡀⠀⠀⠀⠀⠀⠀⠀⠀⣿⣠⢤⣶⡻⣞⣿⣺⢯⣽⣳⡀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀',
    '⢠⣄⡀⠀⠀⠀⠀⠙⢦⡀⠀⠀⠀⠀⣀⣠⣤⣿⣽⣻⢾⣽⣷⣾⣽⣻⣞⣷⣳⡄⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀',
    '⠈⢻⣿⣶⣄⡀⠀⠀⠀⣉⣲⣴⢶⣞⡿⣽⣞⡷⣯⢿⡽⣞⣿⠟⠋⠁⠉⠈⠳⣟⣆⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀',
    '⠀⠀⢻⣿⣿⣿⣿⢶⣾⣿⡽⣯⣟⡾⣽⡷⣯⣟⡽⡾⣽⡯⠁⠀⠀⠀⠀⠀⠀⢮⣭⣦⡀⠀⠀⠀⠀⠀⠀⠀⠀',
    '⠀⠀⠉⢞⣿⣿⢯⡿⣿⣯⣟⣷⣯⢿⣳⣟⡷⣽⣼⣻⣽⠀⠀⠀⠀⠀⠀⠀⢀⣼⡯⡗⠋⠤⠀⠀⠀⠀⠀⠀⠀',
    '⠀⠀⠀⢾⣿⣿⣯⣽⣾⣿⣾⣗⡿⣯⡷⣯⣟⡷⣞⣼⣿⣀⠀⠀⠀⠀⢀⣠⡿⣏⡗⠈⠐⠈⠅⠀⠀⠀⠀⠀⠀',
    '⠀⠀⢀⣼⠛⠏⠉⠉⠽⢟⢿⣿⣿⣿⣿⣷⣻⢾⡽⣞⡷⠄⡹⣶⢿⣻⢿⣻⡽⢯⣼⢦⠶⠁⠈⠀⠀⠀⠀⠀⠀',
    '⠀⠀⣸⣯⠇⠀⠀⠀⠀⠀⠁⣽⣿⣿⣿⣷⣯⣿⣽⣛⡦⠀⠀⢩⣿⣹⢯⣷⢻⣟⠺⢣⡖⣘⠤⠓⠀⠀⠀⠀⠀',
    '⠀⠀⢈⣿⡃⠁⠀⠀⠀⢀⣤⣾⣟⢿⣻⣿⣿⣟⡾⣽⡳⠄⠎⢳⣯⢯⣟⡾⢯⣞⣯⣓⠉⢀⠀⠀⡄⢢⡀⠀⠀',
    '⠀⠀⣸⣷⣷⣶⣳⣶⣺⣿⣿⣳⢯⣟⣿⣿⣳⢯⠛⠅⠃⠀⠀⣴⣿⡿⣬⢶⠾⠙⣊⣥⠾⡒⠊⢁⢠⠣⣌⠀⠀',
    '⠀⠀⢺⡽⣾⡽⣯⣟⣿⡿⣯⣿⣿⣾⢿⣿⠳⢏⣈⢠⠀⠀⣰⢿⡿⣽⣉⡶⠌⠋⠉⣀⡀⠁⠀⠀⠀⣘⡐⣂⠀',
    '⠀⠀⠘⣽⣳⣟⣳⣟⣾⣽⣿⣿⣿⣿⣿⣦⣜⡻⡽⠆⠧⣴⡟⣯⢟⡳⣭⠲⠄⠐⠀⠀⠀⠈⠁⠉⠑⢊⡕⢃⠄',
    '⠀⠀⠀⠹⣿⣾⣿⣯⣿⣾⣿⣿⣿⣿⣿⣿⣿⣿⣾⢧⠀⠹⠾⡵⡞⡽⢢⣃⠐⠀⠀⠄⡐⠀⠀⠀⡘⢦⠘⣌⠀',
    '⠀⠀⠀⠐⠹⢿⣿⣿⣿⣿⣿⣿⣿⣿⣿⣿⣿⣿⢯⡏⠀⠀⠀⠀⠀⠀⠀⠀⠀⠀⣀⠒⡈⠀⡀⠄⡑⠢⣉⠴⣈',
    '⠀⠀⠀⠀⠀⣀⠻⣿⣿⣿⣿⣿⣿⣿⣿⣿⣿⢯⣏⡴⣶⣵⣢⢤⢠⡀⡄⢠⠐⡰⢌⡱⠀⡁⡀⠆⡥⠆⡥⣛⡽',
    '⠀⠀⡀⠔⠉⠀⠀⢽⣿⣿⣿⣿⣿⣿⣿⣿⣿⣿⣼⣻⢷⣯⡽⣞⣷⣻⡼⣡⢋⡔⠣⠜⡐⢐⠠⡓⣤⣙⣲⣽⣻',
    '⠀⠀⠀⠀⠀⠀⠀⠘⢿⣿⣿⣿⣿⣿⣿⣿⣿⣿⣿⣿⣿⣷⡿⣽⣞⣷⣻⡴⣣⢜⡱⣊⡕⣊⠠⡙⡰⣭⢷⣯⣿⢿',
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

<div class="df g2" style="flex-wrap:wrap">
<div class="sec" style="flex:1 1 calc(50% - 12px);min-width:280px">
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0">Tunnel</div>
  </div>
  <?php
  $cfTunnelsSys = cfTunnelsLoad();
  $panelTunnel = $cfTunnelsSys['_panel'] ?? null;
  $panelRunning = false;
  $panelUrl = '';
  if ($panelTunnel && !empty($panelTunnel['pid'])) {
      exec("kill -0 " . (int)$panelTunnel['pid'] . " 2>/dev/null", $null, $rc);
      $panelRunning = $rc === 0;
      $panelUrl = $panelTunnel['url'] ?? '';
      if (!$panelUrl && $panelRunning) {
          $logFile = LOG_DIR . '/cf_tunnel__panel.log';
          if (is_file($logFile)) {
              $content = @file_get_contents($logFile);
              if (preg_match('/https:\/\/[a-z0-9-]+\.trycloudflare\.com/', $content, $m)) {
                  $panelUrl = $m[0];
                  $cfTunnelsSys['_panel']['url'] = $panelUrl;
                  cfTunnelsSave($cfTunnelsSys);
              }
          }
      }
      if (!$panelRunning) @unlink(LOG_DIR . '/cf_tunnel__panel.log');
  }
  ?>
  <div class="df jb ac g2" style="flex-wrap:wrap">
    <div class="ig" style="flex:1;min-width:200px">
      <div class="ii">
        <div class="l">Status</div>
        <div class="v">
          <span class="bdg <?= $panelRunning ? 'on' : 'off' ?>"><span class="dt"></span><?= $panelRunning ? 'Running' : 'Stopped' ?></span>
        </div>
      </div>
      <?php if ($panelRunning && $panelUrl): ?>
      <div class="ii"><div class="l">URL</div><div class="v"><a href="<?= htmlspecialchars($panelUrl) ?>" target="_blank" style="color:var(--blue);word-break:break-all"><?= htmlspecialchars($panelUrl) ?></a></div></div>
      <?php endif; ?>
    </div>
    <div class="df ac g2">
      <form method="post" style="display:inline">
        <?= csrf() ?>
        <input type="hidden" name="action" value="<?= $panelRunning ? 'cf_tunnel_stop' : 'cf_tunnel_start' ?>">
        <input type="hidden" name="site" value="_panel">
        <input type="hidden" name="tunnel_port" value="8080">
        <button type="submit" class="btn <?= $panelRunning ? 'btn-d' : 'btn-p' ?>">
          <i class="fas <?= $panelRunning ? 'fa-stop' : 'fa-play' ?>"></i>
          <?= $panelRunning ? 'Stop Tunnel' : 'Start Tunnel' ?>
        </button>
      </form>
      <?php if ($panelRunning && $panelUrl): ?>
      <a href="<?= htmlspecialchars($panelUrl) ?>" target="_blank" class="btn btn-p"><i class="fas fa-external-link-alt"></i> Open Panel via Tunnel</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="sec" style="flex:1 1 calc(50% - 12px);min-width:280px;margin-top:0">
  <div class="df jb ac fw g2 mb2">
    <div class="st" style="margin-bottom:0">Change Password</div>
  </div>
  <form method="post" class="df ac g2" style="flex-wrap:wrap">
    <?= csrf() ?>
    <input type="hidden" name="action" value="change_password">
    <div style="position:relative;flex:1;min-width:160px">
      <input type="password" name="new_password" id="new-pass" placeholder="New password" required minlength="4" style="width:100%;padding:10px 40px 10px 14px;border:1px solid var(--border);border-radius:6px;background:var(--bg2);color:var(--text);font-size:14px;box-sizing:border-box">
      <i id="pass-toggle" class="fas fa-eye" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text3);font-size:16px" onclick="togglePass()"></i>
    </div>
    <button type="submit" class="btn btn-p"><i class="fas fa-key"></i> Change</button>
  </form>
  <script>
  function togglePass() {
    var inp = document.getElementById('new-pass');
    var icon = document.getElementById('pass-toggle');
    if (inp.type === 'password') {
      inp.type = 'text';
      icon.className = 'fas fa-eye-slash';
    } else {
      inp.type = 'password';
      icon.className = 'fas fa-eye';
    }
  }
  </script>
</div>
</div>
