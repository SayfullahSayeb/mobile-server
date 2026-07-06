#!/data/data/com.termux/files/usr/bin/bash

echo "Installing Mobile Server..."

# Update packages (continue even if this fails)
pkg update -y 2>/dev/null || true
pkg upgrade -y 2>/dev/null || true

# Install required packages
pkg install -y \
    ttyd \
    iproute2 \
    nginx \
    php \
    php-fpm \
    mariadb \
    phpmyadmin \
    git \
    curl \
    wget \
    unzip \
    tar \
    cloudflared 2>/dev/null || {
    echo "[!] Package installation failed. Retrying with full output..."
    pkg update -y
    pkg install -y \
        ttyd \
        iproute2 \
        nginx \
        php \
        php-fpm \
        mariadb \
        phpmyadmin \
        git \
        curl \
        wget \
        unzip \
        tar \
        cloudflared
}

mkdir -p \
    ~/server/sites/default/public_html \
    ~/server/backups \
    ~/server/logs \
    ~/server/configs

# Configure phpMyAdmin if installed
if [ -d "$PREFIX/share/phpmyadmin" ]; then
    ln -sf "$PREFIX/share/phpmyadmin" ~/server/sites/default/public_html/phpmyadmin
    # Generate config that connects via TCP to avoid socket path issues
    if [ ! -f "$PREFIX/share/phpmyadmin/config.inc.php" ]; then
        BLOWFISH=$(php -r "echo bin2hex(random_bytes(16));" 2>/dev/null || echo "secretrandomkey123456")
        cat > "$PREFIX/share/phpmyadmin/config.inc.php" <<PMAEOF
<?php
\$cfg['blowfish_secret'] = '$BLOWFISH';
\$i = 0;
\$i++;
\$cfg['Servers'][\$i]['auth_type'] = 'cookie';
\$cfg['Servers'][\$i]['host'] = '127.0.0.1';
\$cfg['Servers'][\$i]['port'] = '3306';
\$cfg['Servers'][\$i]['compress'] = false;
\$cfg['Servers'][\$i]['AllowNoPassword'] = true;
\$cfg['Servers'][\$i]['hide_db'] = '(information_schema|performance_schema|mysql|sys)';
PMAEOF
        echo "[*] phpMyAdmin configured (TCP 127.0.0.1:3306)."
    fi
fi

echo "[*] Setting up File Manager..."

download_file() {
    local url="$1"
    local dest="$2"
    if ! curl -fsSL "$url" -o "$dest"; then
        echo "[!] Warning: Failed to download $url"
        return 1
    fi
}

mkdir -p ~/server/sites/default/public_html/filemanager
echo "[*] Installing Tiny File Manager..."
download_file "https://raw.githubusercontent.com/SayfullahSayeb/mobile-server/main/filemanager/panel.php" \
    ~/server/sites/default/public_html/filemanager/panel.php
download_file "https://raw.githubusercontent.com/prasathmani/tinyfilemanager/master/tinyfilemanager.php" \
    ~/server/sites/default/public_html/filemanager/tinyfilemanager.php
cat > ~/server/sites/default/public_html/filemanager/config.php <<'CFG'
<?php
$use_auth = false;
$root_path = (getenv('HOME') ?: '/data/data/com.termux/files/home') . '/server/sites';
$root_url = '';
$http_host = $_SERVER['HTTP_HOST'];
$default_timezone = 'Etc/UTC';
$datetime_format = 'Y-m-d H:i:s';
$app_title = 'File Manager';
$global_readonly = false;
$show_hidden = true;
$edit_files = true;
$CONFIG = '{"lang":"en","error_reporting":false,"show_hidden":true,"hide_Cols":false,"theme":"dark"}';
CFG
[ -f ~/server/sites/default/public_html/filemanager/tinyfilemanager.php ] && echo "[*] Tiny File Manager ready." || echo "[!] Warning: File Manager download failed."

# Create index.php that redirects to control panel login
cat > ~/server/sites/default/public_html/index.php <<'INDEXPHP'
<?php
header('Location: control.php');
exit;
INDEXPHP
chmod 644 ~/server/sites/default/public_html/index.php 2>/dev/null || true

download_file "https://sayfullahsayeb.github.io/mobile-server/control.php" \
    ~/server/sites/default/public_html/control.php

download_file "https://raw.githubusercontent.com/SayfullahSayeb/mobile-server/main/ms" \
    "$PREFIX/bin/ms"

if [ -f "$PREFIX/bin/ms" ]; then
    chmod +x "$PREFIX/bin/ms"
fi

echo "[*] Downloading library files..."

mkdir -p ~/server/sites/default/public_html/lib
download_file "https://raw.githubusercontent.com/SayfullahSayeb/mobile-server/main/lib/WordPressInstaller.php" \
    ~/server/sites/default/public_html/lib/WordPressInstaller.php

mkdir -p ~/server/sites/default/public_html/lib/templates
download_file "https://raw.githubusercontent.com/SayfullahSayeb/mobile-server/main/lib/templates/wp-config.php" \
    ~/server/sites/default/public_html/lib/templates/wp-config.php

echo "[*] Downloading panel files..."

mkdir -p ~/server/sites/default/public_html/panel
for panel_file in login.php header.php footer.php dashboard.php files.php sites.php update.php control.css terminal.php logs.php system.php update_stream.php; do
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
    CONTROL_PASS="changeme"
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

echo "[*] Initializing server configuration..."

if [ ! -f ~/server/configs/server.json ]; then
    cat > ~/server/configs/server.json <<'SRVEOF'
{
    "DB_HOST": "127.0.0.1",
    "DB_ROOT_USER": "root",
    "DB_ROOT_PASS": ""
}
SRVEOF
    echo "[*] Server configuration created (~/server/configs/server.json)."
else
    echo "[*] Server configuration already exists."
fi

echo "[*] Starting services..."

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

# Start ttyd (web terminal)
pkill ttyd 2>/dev/null || true
ttyd -W -p 7681 bash -l >/dev/null 2>&1 &
sleep 1
if pgrep ttyd >/dev/null; then
    echo "[*] ttyd started on port 7681."
else
    echo "[!] Warning: Failed to start ttyd."
fi

echo "[*] Detecting IP..."

USER=$(whoami)
IP=""

IP=$(ifconfig 2>/dev/null | awk '/^[a-z]/{i=$1} /inet /{if(i!="lo:" && $2!="127.0.0.1" && $2~/^192\.168\./){print $2;exit}}')
[ -z "$IP" ] && IP=$(ifconfig 2>/dev/null | awk '/^[a-z]/{i=$1} /inet /{if(i!="lo:" && $2!="127.0.0.1"){print $2;exit}}')
[ -z "$IP" ] && IP="127.0.0.1"

if [ -z "$IP" ]; then
    IP="127.0.0.1"
    echo "[!] Warning: Could not detect network IP. Using localhost."
fi

clear

echo "╔╦╗╔═╗╔╗ ╦╦ ╦  ╔═╗   ╔═╗╔═╗╦═╗╦  ╦╔═╗╦═╗"
echo "║║║║ ║╠╩╗║║ ║  ║╣    ╚═╗║╣ ╠╦╝╚╗╔╝║╣ ╠╦╝"
echo "╩ ╩╚═╝╚═╝╩╩ ╩═╝╚═╝   ╚═╝╚═╝╩╚═ ╚╝ ╚═╝╩╚═"
echo ""
echo "========================================"
echo "     Mobile Server Setup Complete!"
echo "========================================"
echo
echo "Terminal: http://${IP}:7681"
echo
echo "Website: http://${IP}:8080"
echo
echo "Panel Password: $CONTROL_PASS"
echo
echo "========================================"
