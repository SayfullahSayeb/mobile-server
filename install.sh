#!/data/data/com.termux/files/usr/bin/bash

set -e

echo "========================================"
echo "      Mobile Server Installer v0.2"
echo "========================================"

echo
echo "[1/8] Updating packages..."
pkg update -y
pkg upgrade -y

echo
echo "[2/8] Installing packages..."
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

echo
echo "[3/8] Creating directories..."

mkdir -p ~/server/sites/default/public_html
mkdir -p ~/server/backups
mkdir -p ~/server/logs
mkdir -p ~/server/configs

echo
echo "[4/8] Creating default website..."

cat > ~/server/sites/default/public_html/index.php <<'EOF'
<?php
echo "<h1>🎉 Mobile Server</h1>";
echo "<p>Installation successful.</p>";
echo "<hr>";
echo "<strong>PHP Version:</strong> " . phpversion();
EOF

echo
echo "[5/8] Configuring Nginx..."

PHP_SOCKET=$(grep '^listen =' $PREFIX/etc/php-fpm.d/www.conf | awk '{print $3}')

cp $PREFIX/etc/nginx/nginx.conf $PREFIX/etc/nginx/nginx.conf.bak

cat > $PREFIX/etc/nginx/nginx.conf <<EOF
worker_processes 1;

events {
    worker_connections 1024;
}

http {

    include       mime.types;
    default_type  application/octet-stream;

    sendfile on;
    keepalive_timeout 65;

    server {

        listen 8080;
        server_name localhost;

        root /data/data/com.termux/files/home/server/sites/default/public_html;
        index index.php index.html;

        location / {
            try_files \$uri \$uri/ /index.php?\$query_string;
        }

        location ~ \.php$ {
            include fastcgi.conf;
            fastcgi_pass unix:${PHP_SOCKET};
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        }

        location ~ /\.ht {
            deny all;
        }
    }
}
EOF

echo
echo "[6/8] Initializing MariaDB..."

if [ ! -d "$PREFIX/var/lib/mysql/mysql" ]; then
    mariadb-install-db --user=$(whoami)
fi

echo
echo "[7/8] Starting services..."

pgrep sshd >/dev/null || sshd

pkill php-fpm 2>/dev/null || true
php-fpm

pkill mariadbd 2>/dev/null || true
mariadbd-safe >/dev/null 2>&1 &

sleep 3

pkill nginx 2>/dev/null || true
nginx

echo
echo "[8/8] Checking services..."

echo
echo "SSH:"
pgrep -a sshd || true

echo
echo "Nginx:"
pgrep -a nginx || true

echo
echo "PHP-FPM:"
pgrep -a php-fpm || true

echo
echo "MariaDB:"
pgrep -a mariadbd || true

USER_NAME=$(whoami)

IP_ADDR=$(ip -4 -o addr show wlan0 2>/dev/null | awk '{print $4}' | cut -d/ -f1)

if [ -z "$IP_ADDR" ]; then
    IP_ADDR=$(ifconfig wlan0 2>/dev/null | sed -n 's/.*inet \([0-9.]*\).*/\1/p')
fi

echo
echo "========================================"
echo "      Installation Complete"
echo "========================================"

echo "SSH:"
echo "ssh -p 8022 ${USER_NAME}@${IP_ADDR}"

echo
echo "Website:"
echo "http://${IP_ADDR}:8080"

echo
echo "Document Root:"
echo "~/server/sites/default/public_html"

echo
echo "========================================"
