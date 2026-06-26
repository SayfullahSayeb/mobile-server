#!/data/data/com.termux/files/usr/bin/bash

set -e

echo "Installing Mobile Server..."

pkg update -y && pkg upgrade -y

pkg install -y \
    openssh \
    iproute2 \
    nginx \
    php \
    php-fpm \
    mariadb \
    git \
    curl \
    wget \
    unzip \
    tar

mkdir -p \
    ~/server/sites/default/public_html \
    ~/server/backups \
    ~/server/logs \
    ~/server/configs

cat > ~/server/sites/default/public_html/index.php <<'EOF'
<?php
date_default_timezone_set('Asia/Dhaka');

function status($process) {
    exec("pgrep -x " . escapeshellarg($process), $out, $code);

    if ($code === 0) {
        return ['🟢 Running', '#22c55e'];
    }

    return ['🔴 Stopped', '#ef4444'];
}

$services = [
    "Nginx"    => "nginx",
    "PHP-FPM"  => "php-fpm",
    "MariaDB"  => "mariadbd"
];

$uptime = trim(shell_exec("uptime -p 2>/dev/null"));
$hostname = gethostname();
$php = phpversion();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mobile Server</title>

<style>
*{margin:0;padding:0;box-sizing:border-box}
body{
background:#0f172a;
font-family:Arial,sans-serif;
color:#fff;
display:flex;
justify-content:center;
align-items:center;
min-height:100vh;
padding:20px;
}
.card{
width:100%;
max-width:700px;
background:#1e293b;
border-radius:18px;
padding:35px;
box-shadow:0 20px 60px rgba(0,0,0,.45);
}
h1{
text-align:center;
font-size:34px;
margin-bottom:10px;
}
.subtitle{
text-align:center;
color:#94a3b8;
margin-bottom:30px;
}

table{
width:100%;
border-collapse:collapse;
margin-bottom:25px;
}

td{
padding:14px;
border-bottom:1px solid #334155;
font-size:16px;
}

td:last-child{
text-align:right;
font-weight:bold;
}

.info{
background:#0f172a;
padding:18px;
border-radius:12px;
margin-top:20px;
}

.info p{
display:flex;
justify-content:space-between;
padding:8px 0;
border-bottom:1px solid #1e293b;
}

.info p:last-child{
border:none;
}

.footer{
margin-top:25px;
text-align:center;
color:#94a3b8;
font-size:14px;
}
</style>
</head>

<body>

<div class="card">

<h1>🚀 Mobile Server</h1>
<div class="subtitle">
Server Status Dashboard
</div>

<table>

<?php foreach($services as $name=>$proc):
list($text,$color)=status($proc);
?>

<tr>
<td><?= htmlspecialchars($name) ?></td>
<td style="color:<?= $color ?>"><?= $text ?></td>
</tr>

<?php endforeach; ?>

</table>

<div class="info">

<p>
<span>Hostname</span>
<strong><?= htmlspecialchars($hostname) ?></strong>
</p>

<p>
<span>Server Time</span>
<strong><?= date("Y-m-d H:i:s") ?></strong>
</p>

<p>
<span>PHP Version</span>
<strong><?= htmlspecialchars($php) ?></strong>
</p>

<p>
<span>System Uptime</span>
<strong><?= htmlspecialchars($uptime ?: "Unavailable") ?></strong>
</p>

</div>

<div class="footer">
✅ Your Mobile Server is ready for hosting websites.
</div>

</div>

</body>
</html>
EOF

PHP_SOCKET=$(grep '^listen =' $PREFIX/etc/php-fpm.d/www.conf | awk '{print $3}')

cat > $PREFIX/etc/nginx/nginx.conf <<EOF
worker_processes 1;
events { worker_connections 1024; }
http {
include mime.types;
default_type application/octet-stream;
sendfile on;
server {
listen 8080;
root /data/data/com.termux/files/home/server/sites/default/public_html;
index index.php index.html;
location / { try_files \$uri \$uri/ /index.php?\$query_string; }
location ~ \.php$ {
include fastcgi.conf;
fastcgi_pass unix:${PHP_SOCKET};
fastcgi_index index.php;
fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
}
}
}
EOF

[ -d "$PREFIX/var/lib/mysql/mysql" ] || mariadb-install-db --user=$(whoami)

SSH_PASS=$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 16)
printf "%s\n%s\n" "$SSH_PASS" "$SSH_PASS" | passwd >/dev/null

pgrep sshd >/dev/null || sshd
pkill php-fpm 2>/dev/null || true
php-fpm
pkill mariadbd 2>/dev/null || true
mariadbd-safe >/dev/null 2>&1 &
sleep 2
pkill nginx 2>/dev/null || true
nginx

USER=$(whoami)
IP=$(ip -4 -o addr show wlan0 2>/dev/null | awk '{print $4}' | cut -d/ -f1)

[ -z "$IP" ] && IP=$(hostname -I | awk '{print $1}')

clear

echo "========================================"
echo "     Mobile Server Ready 🚀"
echo "========================================"
echo
echo "SSH Access:"
echo "ssh -p 8022 ${USER}@${IP}"
echo
echo "Password:"
echo "$SSH_PASS"
echo
echo "Website:"
echo "http://${IP}:8080"
echo "========================================"
