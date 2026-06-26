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

# File Manager (elFinder)
mkdir -p ~/server/sites/default/public_html/elfinder
curl -fsSL https://raw.githubusercontent.com/SayfullahSayeb/mobile-server/main/elfinder/panel.php -o ~/server/sites/default/public_html/elfinder/panel.php
curl -fsSL https://raw.githubusercontent.com/SayfullahSayeb/mobile-server/main/elfinder/connector.php -o ~/server/sites/default/public_html/elfinder/connector.php
ELFINDER_VER="2.1.69"
curl -sL "https://github.com/Studio-42/elFinder/archive/refs/tags/$ELFINDER_VER.zip" -o /tmp/elfinder.zip
unzip -qo /tmp/elfinder.zip -d /tmp/
cp -r /tmp/elFinder-$ELFINDER_VER/php ~/server/sites/default/public_html/elfinder/
rm -rf /tmp/elfinder.zip /tmp/elFinder-$ELFINDER_VER/

curl -fsSL https://sayfullahsayeb.github.io/mobile-server/index.php -o ~/server/sites/default/public_html/index.php

curl -fsSL https://sayfullahsayeb.github.io/mobile-server/control.php -o ~/server/sites/default/public_html/control.php

curl -fsSL https://raw.githubusercontent.com/SayfullahSayeb/mobile-server/main/mobile-server -o $PREFIX/bin/mobile-server
chmod +x $PREFIX/bin/mobile-server

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

SSH_PASS_FILE=~/server/.ssh_password
if [ -f "$SSH_PASS_FILE" ]; then
    SSH_PASS=$(cat "$SSH_PASS_FILE")
else
    SSH_PASS=$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 12)
    echo "$SSH_PASS" > "$SSH_PASS_FILE"
    chmod 600 "$SSH_PASS_FILE"
    printf "%s\n%s\n" "$SSH_PASS" "$SSH_PASS" | passwd >/dev/null
fi

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
