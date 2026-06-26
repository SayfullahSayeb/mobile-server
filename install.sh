#!/data/data/com.termux/files/usr/bin/bash

echo "Installing Mobile Server..."

# Update packages (continue even if this fails)
pkg update -y 2>/dev/null || true
pkg upgrade -y 2>/dev/null || true

# Install required packages
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
    tar 2>/dev/null || {
    echo "[!] Package installation failed. Retrying with full output..."
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
}

mkdir -p \
    ~/server/sites/default/public_html \
    ~/server/backups \
    ~/server/logs \
    ~/server/configs

echo "[*] Setting up File Manager..."

# File Manager (elFinder)
mkdir -p ~/server/sites/default/public_html/elfinder

download_file() {
    local url="$1"
    local dest="$2"
    if ! curl -fsSL "$url" -o "$dest"; then
        echo "[!] Warning: Failed to download $url"
        return 1
    fi
}

download_file "https://raw.githubusercontent.com/SayfullahSayeb/mobile-server/main/elfinder/panel.php" \
    ~/server/sites/default/public_html/elfinder/panel.php

download_file "https://raw.githubusercontent.com/SayfullahSayeb/mobile-server/main/elfinder/connector.php" \
    ~/server/sites/default/public_html/elfinder/connector.php

ELFINDER_VER="2.1.69"
ELDEST=~/server/sites/default/public_html/elfinder/php
echo "[*] Installing elFinder PHP library..."
rm -rf /tmp/elfinder.zip /tmp/elFinder-$ELFINDER_VER/ 2>/dev/null
curl -fsSL --retry 5 "https://github.com/Studio-42/elFinder/archive/refs/tags/$ELFINDER_VER.zip" -o /tmp/elfinder.zip 2>/dev/null ||
    wget -q "https://github.com/Studio-42/elFinder/archive/refs/tags/$ELFINDER_VER.zip" -O /tmp/elfinder.zip 2>/dev/null
if [ -f /tmp/elfinder.zip ] && unzip -qo /tmp/elfinder.zip -d /tmp/ 2>/dev/null; then
    rm -rf "$ELDEST" 2>/dev/null
    cp -r "/tmp/elFinder-$ELFINDER_VER/php" "$ELDEST" 2>/dev/null
fi
if [ ! -f "$ELDEST/autoload.php" ]; then
    echo "[!] elFinder download failed. Downloading individual files..."
    mkdir -p "$ELDEST/plugins" "$ELDEST/libs" "$ELDEST/editors"
    for f in autoload.php elFinder.php elFinderConnector.class.php elFinderVolumeDriver.class.php elFinderVolumeLocalFileSystem.class.php elFinderSession.php elFinderSessionInterface.php elFinderPlugin.php; do
        curl -fsSL "https://raw.githubusercontent.com/Studio-42/elFinder/$ELFINDER_VER/php/$f" -o "$ELDEST/$f" 2>/dev/null
    done
fi
[ -f "$ELDEST/autoload.php" ] && echo "[*] elFinder ready." || echo "[!] Warning: elFinder missing. File manager won't work."

download_file "https://sayfullahsayeb.github.io/mobile-server/index.php" \
    ~/server/sites/default/public_html/index.php

download_file "https://sayfullahsayeb.github.io/mobile-server/control.php" \
    ~/server/sites/default/public_html/control.php

download_file "https://raw.githubusercontent.com/SayfullahSayeb/mobile-server/main/mobile-server" \
    "$PREFIX/bin/mobile-server"

if [ -f "$PREFIX/bin/mobile-server" ]; then
    chmod +x "$PREFIX/bin/mobile-server"
fi

echo "[*] Downloading library files..."

mkdir -p ~/server/sites/default/public_html/lib
download_file "https://raw.githubusercontent.com/SayfullahSayeb/mobile-server/main/lib/TunnelProvider.php" \
    ~/server/sites/default/public_html/lib/TunnelProvider.php
download_file "https://raw.githubusercontent.com/SayfullahSayeb/mobile-server/main/lib/CloudflareTunnelProvider.php" \
    ~/server/sites/default/public_html/lib/CloudflareTunnelProvider.php
download_file "https://raw.githubusercontent.com/SayfullahSayeb/mobile-server/main/lib/TunnelManager.php" \
    ~/server/sites/default/public_html/lib/TunnelManager.php

echo "[*] Downloading panel files..."

mkdir -p ~/server/sites/default/public_html/panel
for panel_file in login.php header.php footer.php dashboard.php files.php sites.php wordpress.php cloudflare.php update.php control.css; do
    download_file "https://raw.githubusercontent.com/SayfullahSayeb/mobile-server/main/panel/$panel_file" \
        ~/server/sites/default/public_html/panel/$panel_file
done

echo "[*] Configuring PHP-FPM and Nginx..."

PHP_SOCKET=""
if [ -f "$PREFIX/etc/php-fpm.d/www.conf" ]; then
    PHP_SOCKET=$(grep '^listen =' "$PREFIX/etc/php-fpm.d/www.conf" | awk '{print $3}')
fi
if [ -z "$PHP_SOCKET" ]; then
    PHP_SOCKET="/data/data/com.termux/files/usr/var/run/php-fpm.sock"
    echo "[!] Warning: Could not detect PHP socket. Using default: $PHP_SOCKET"
fi

cat > "$PREFIX/etc/nginx/nginx.conf" <<EOF
worker_processes 1;
events { worker_connections 1024; }
http {
include mime.types;
default_type application/octet-stream;
sendfile on;
server_tokens off;
add_header X-Content-Type-Options nosniff;
add_header X-Frame-Options SAMEORIGIN;
add_header X-XSS-Protection "1; mode=block";
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
location ~ /\.(git|ht) { deny all; }
}
}
EOF

echo "[*] Setting up control panel password..."

CONTROL_PASS_FILE=~/server/.panel_password
CONTROL_PASS=""

if [ -f "$CONTROL_PASS_FILE" ]; then
    CONTROL_PASS=$(cat "$CONTROL_PASS_FILE")
    echo "[*] Using existing control panel password."
else
    CONTROL_PASS=$(tr -dc 'A-Za-z0-9' </dev/urandom 2>/dev/null | head -c 16)
    if [ -z "$CONTROL_PASS" ]; then
        CONTROL_PASS="admin$(date +%s)"
    fi
    echo "$CONTROL_PASS" > "$CONTROL_PASS_FILE" 2>/dev/null || true
    chmod 600 "$CONTROL_PASS_FILE" 2>/dev/null || true
fi

PHP_HASH=$(php -r "echo password_hash('$CONTROL_PASS', PASSWORD_BCRYPT);" 2>/dev/null || echo "")
if [ -z "$PHP_HASH" ]; then
    echo "[!] Warning: Failed to generate password hash. Control panel may not work."
fi
mkdir -p ~/server/configs
cat > ~/server/configs/secret.php <<EOFPHP
<?php
define('CONTROL_PASSWORD_HASH', '$PHP_HASH');
EOFPHP
chmod 600 ~/server/configs/secret.php 2>/dev/null || true

echo "[*] Setting up MariaDB..."

if [ ! -d "$PREFIX/var/lib/mysql/mysql" ]; then
    if mariadb-install-db --user=$(whoami) 2>/dev/null; then
        echo "[*] MariaDB installed successfully."
    else
        echo "[!] Warning: MariaDB install-db failed. You may need to run it manually."
    fi
else
    echo "[*] MariaDB already initialized."
fi

echo "[*] Setting up SSH password..."

SSH_PASS_FILE=~/server/.ssh_password
SSH_PASS=""

if [ -f "$SSH_PASS_FILE" ]; then
    SSH_PASS=$(cat "$SSH_PASS_FILE")
    echo "[*] Using existing SSH password."
else
    SSH_PASS=$(tr -dc 'A-Za-z0-9' </dev/urandom 2>/dev/null | head -c 12)
    if [ -z "$SSH_PASS" ]; then
        SSH_PASS="mobile123"
    fi
    echo "$SSH_PASS" > "$SSH_PASS_FILE" 2>/dev/null || true
    chmod 600 "$SSH_PASS_FILE" 2>/dev/null || true
    if command -v passwd &>/dev/null; then
        if echo -e "$SSH_PASS\n$SSH_PASS" | passwd 2>/dev/null; then
            echo "[*] SSH password set successfully."
        else
            echo "[!] Warning: Failed to set password via passwd."
            echo "[!] You can set it manually later with: passwd"
        fi
    else
        echo "[!] Warning: passwd command not found. Install termux-auth: pkg install termux-auth"
    fi
fi

echo "[*] Starting services..."

# Start SSH
if pgrep sshd >/dev/null; then
    echo "[*] SSH already running."
else
    if sshd 2>/dev/null; then
        echo "[*] SSH started."
    else
        echo "[!] Warning: Failed to start SSH."
    fi
fi

# Start PHP-FPM
pkill php-fpm 2>/dev/null || true
if php-fpm 2>/dev/null; then
    echo "[*] PHP-FPM started."
else
    echo "[!] Warning: Failed to start PHP-FPM."
fi

# Start MariaDB
pkill mariadbd 2>/dev/null || true
mariadbd-safe >/dev/null 2>&1 &
sleep 2
echo "[*] MariaDB started (background)."

# Start Nginx
pkill nginx 2>/dev/null || true
if nginx 2>/dev/null; then
    echo "[*] Nginx started."
else
    echo "[!] Warning: Failed to start Nginx. Check config: nginx -t"
fi

echo "[*] Detecting IP..."

USER=$(whoami)
IP=""

# Try common interface names
for iface in wlan0 eth0 uap0 wlan1 rmnet0 cc0; do
    IP=$(ip -4 -o addr show "$iface" 2>/dev/null | awk '{print $4}' | cut -d/ -f1)
    [ -n "$IP" ] && break
done

# Fallback to hostname
if [ -z "$IP" ]; then
    IP=$(hostname -I 2>/dev/null | awk '{print $1}')
fi

if [ -z "$IP" ]; then
    IP="127.0.0.1"
    echo "[!] Warning: Could not detect network IP. Using localhost."
fi

clear

echo "========================================"
echo "     Mobile Server Setup Complete!"
echo "========================================"
echo
if [ -n "$SSH_PASS" ]; then
    echo "SSH Access:"
    echo "  ssh -p 8022 ${USER}@${IP}"
    echo
    echo "Password:"
    echo "  $SSH_PASS"
    echo
fi
echo "Website:"
echo "  http://${IP}:8080"
echo
echo "Panel Password:"
echo "  $CONTROL_PASS"
echo
echo "========================================"
