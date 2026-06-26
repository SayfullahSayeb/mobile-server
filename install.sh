#!/data/data/com.termux/files/usr/bin/bash

set -e

# --- SERVICE MANAGEMENT FUNCTIONS ---

start_services() {
    echo "Starting Mobile Server services..."
    
    # Start SSH
    if pgrep sshd >/dev/null; then
        echo "  - SSH is already running."
    else
        sshd && echo "  - SSH started."
    fi

    # Start PHP-FPM
    if pgrep php-fpm >/dev/null; then
        echo "  - PHP-FPM is already running."
    else
        php-fpm && echo "  - PHP-FPM started."
    fi

    # Start MariaDB
    if pgrep mariadbd >/dev/null; then
        echo "  - MariaDB is already running."
    else
        mariadbd-safe >/dev/null 2>&1 &
        echo "  - MariaDB started."
    fi

    # Start Nginx
    if pgrep nginx >/dev/null; then
        echo "  - Nginx is already running."
    else
        sleep 1 # Give MariaDB a second to init
        nginx && echo "  - Nginx started."
    fi
    
    echo "All services processed."
}

stop_services() {
    echo "Stopping Mobile Server services..."
    pkill nginx 2>/dev/null && echo "  - Nginx stopped." || echo "  - Nginx wasn't running."
    pkill mariadbd 2>/dev/null && echo "  - MariaDB stopped." || echo "  - MariaDB wasn't running."
    pkill php-fpm 2>/dev/null && echo "  - PHP-FPM stopped." || echo "  - PHP-FPM wasn't running."
    pkill sshd 2>/dev/null && echo "  - SSH stopped." || echo "  - SSH wasn't running."
    echo "All services stopped."
}

check_status() {
    echo "Mobile Server Status:"
    pgrep sshd >/dev/null && echo "  [RUNNING] SSH" || echo "  [STOPPED] SSH"
    pgrep php-fpm >/dev/null && echo "  [RUNNING] PHP-FPM" || echo "  [STOPPED] PHP-FPM"
    pgrep mariadbd >/dev/null && echo "  [RUNNING] MariaDB" || echo "  [STOPPED] MariaDB"
    pgrep nginx >/dev/null && echo "  [RUNNING] Nginx" || echo "  [STOPPED] Nginx"
}

# --- ARGUMENT HANDLING ---

case "$1" in
    start)
        start_services
        exit 0
        ;;
    stop)
        stop_services
        exit 0
        ;;
    restart)
        stop_services
        sleep 1
        start_services
        exit 0
        ;;
    status)
        check_status
        exit 0
        ;;
    "")
        # No arguments passed? Proceed to run the installer below.
        ;;
    *)
        echo "Usage: mobile-server [start|stop|restart|status]"
        exit 1
        ;;
esac

# --- INSTALLER LOGIC ---

echo "Installing Mobile Server..."

pkg update -y
pkg upgrade -y

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

mkdir -p ~/server/sites/default/public_html ~/server/backups ~/server/logs ~/server/configs

cat > ~/server/sites/default/public_html/index.php <<'EOF'
<?php
echo "<h1>🎉 Mobile Server</h1>";
echo "<p>Installation successful.</p>";
echo "<strong>PHP ".phpversion()."</strong>";
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

# Setup root password for Termux user
SSH_PASS=$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 12)
printf "%s\n%s\n" "$SSH_PASS" "$SSH_PASS" | passwd >/dev/null

# Register this very script into Termux system binaries so it can be called anywhere
cp "$0" "$PREFIX/bin/mobile-server"
chmod +x "$PREFIX/bin/mobile-server"

# Trigger start
start_services

USER=$(whoami)
IP=$(ip -4 -o addr show wlan0 2>/dev/null | awk '{print $4}' | cut -d/ -f1)
[ -z "$IP" ] && IP=$(hostname -I | awk '{print $1}')

clear

echo "========================================"
echo "      Mobile Server Ready 🚀"
echo "========================================"
echo
echo "You can now manage your server globally using:"
echo "  mobile-server start"
echo "  mobile-server stop"
echo "  mobile-server restart"
echo "  mobile-server status"
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
