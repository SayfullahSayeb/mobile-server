#!/data/data/com.termux/files/usr/bin/bash

set -e

echo "========================================"
echo "   Mobile Server Installer v0.1"
echo "========================================"

echo
echo "[1/6] Updating packages..."
pkg update -y
pkg upgrade -y

echo
echo "[2/6] Installing required packages..."
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
echo "[3/6] Creating directories..."

mkdir -p ~/server
mkdir -p ~/server/sites
mkdir -p ~/server/backups
mkdir -p ~/server/logs
mkdir -p ~/server/configs

echo
echo "[4/6] Starting SSH..."

if ! pgrep sshd >/dev/null; then
    sshd
fi

echo
echo "[5/6] Checking installed software..."

echo
echo "Nginx:"
nginx -v

echo
echo "PHP:"
php -v | head -n1

echo
echo "PHP-FPM:"
php-fpm -v | head -n1

echo
echo "MariaDB:"
mariadb --version

echo
echo "[6/6] Installation completed."

USER_NAME=$(whoami)

if command -v ip >/dev/null 2>&1; then
    IP_ADDR=$(ip -4 -o addr show wlan0 2>/dev/null | awk '{print $4}' | cut -d/ -f1)
fi

if [ -z "$IP_ADDR" ]; then
    IP_ADDR=$(ifconfig wlan0 2>/dev/null | sed -n 's/.*inet \([0-9.]*\).*/\1/p')
fi

echo
echo "========================================"
echo "Installation Successful!"
echo "========================================"

echo "User : $USER_NAME"
echo "IP   : $IP_ADDR"

echo
echo "Connect from PC:"
echo "ssh -p 8022 ${USER_NAME}@${IP_ADDR}"

echo
echo "Next step:"
echo "Initialize MariaDB"
echo "Configure Nginx"
echo "Create first website"

echo
echo "========================================"
