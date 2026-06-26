#!/data/data/com.termux/files/usr/bin/bash
set -e

# ── helpers ────────────────────────────────────────────────────────────────────
PREFIX="${PREFIX:-/data/data/com.termux/files/usr}"
HOME_DIR="${HOME:-/data/data/com.termux/files/home}"
SERVER_ROOT="$HOME_DIR/server"
WEB_ROOT="$SERVER_ROOT/sites/default/public_html"

die()  { echo "❌ $*" >&2; exit 1; }
info() { echo "➤  $*"; }

get_ip() {
  ip -4 -o addr show wlan0 2>/dev/null | awk '{print $4}' | cut -d/ -f1 \
    || hostname -I 2>/dev/null | awk '{print $1}'
}

svc_status() { pgrep "$1" >/dev/null 2>&1 && echo "Running" || echo "Stopped"; }

# ── install packages ────────────────────────────────────────────────────────────
info "Installing Mobile Server..."
pkg update -y && pkg upgrade -y
pkg install -y openssh iproute2 nginx php php-fpm mariadb git curl wget unzip tar

# ── create mobile-server control script ────────────────────────────────────────
cat > "$PREFIX/bin/mobile-server" <<'CTRL'
#!/data/data/com.termux/files/usr/bin/bash

get_ip() {
  ip -4 -o addr show wlan0 2>/dev/null | awk '{print $4}' | cut -d/ -f1 \
    || hostname -I 2>/dev/null | awk '{print $1}'
}
svc_status() { pgrep "$1" >/dev/null 2>&1 && echo "Running" || echo "Stopped"; }

start_server() {
  pgrep sshd   >/dev/null 2>&1 || sshd
  pkill -q php-fpm  2>/dev/null; php-fpm
  pgrep mariadbd >/dev/null 2>&1 || mariadbd-safe >/dev/null 2>&1 &
  pkill -q nginx 2>/dev/null;    nginx
  echo "✅ Mobile Server Started"
  echo "SSH : ssh -p 8022 $(whoami)@$(get_ip)"
  echo "Web : http://$(get_ip):8080"
}

stop_server() {
  for svc in nginx php-fpm mariadbd sshd; do pkill "$svc" 2>/dev/null || true; done
  echo "🛑 Mobile Server Stopped"
}

status_server() {
  printf "%-8s : %s\n" \
    SSH     "$(svc_status sshd)"   \
    Nginx   "$(svc_status nginx)"  \
    PHP-FPM "$(svc_status php-fpm)"\
    MariaDB "$(svc_status mariadbd)"
}

case "${1:-}" in
  start)   start_server ;;
  stop)    stop_server  ;;
  restart) stop_server; sleep 1; start_server ;;
  status)  status_server ;;
  *)
    echo "Mobile Server"
    echo
    echo "Usage:"
    printf "  mobile-server %s\n" start stop restart status
    exit 1
    ;;
esac
CTRL
chmod +x "$PREFIX/bin/mobile-server"

# ── directory structure ─────────────────────────────────────────────────────────
mkdir -p "$WEB_ROOT" "$SERVER_ROOT"/{backups,logs,configs}

# ── default index.php ──────────────────────────────────────────────────────────
cat > "$WEB_ROOT/index.php" <<'PHP'
<?php
echo "<h1>🎉 Mobile Server</h1>";
echo "<p>Installation successful.</p>";
echo "<strong>PHP " . phpversion() . "</strong>";
PHP

# ── nginx config ───────────────────────────────────────────────────────────────
PHP_SOCKET=$(awk '/^listen[[:space:]]*=/{print $3; exit}' \
  "$PREFIX/etc/php-fpm.d/www.conf")

cat > "$PREFIX/etc/nginx/nginx.conf" <<NGINX
worker_processes 1;
events { worker_connections 1024; }
http {
    include      mime.types;
    default_type application/octet-stream;
    sendfile     on;
    server {
        listen 8080;
        root  $WEB_ROOT;
        index index.php index.html;
        location / { try_files \$uri \$uri/ /index.php?\$query_string; }
        location ~ \.php$ {
            include            fastcgi.conf;
            fastcgi_pass       unix:${PHP_SOCKET};
            fastcgi_index      index.php;
            fastcgi_param      SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        }
    }
}
NGINX

# ── init MariaDB (first run only) ──────────────────────────────────────────────
[ -d "$PREFIX/var/lib/mysql/mysql" ] || \
  mariadb-install-db --user="$(whoami)"

# ── generate SSH password & start services ─────────────────────────────────────
SSH_PASS=$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 12)
printf '%s\n%s\n' "$SSH_PASS" "$SSH_PASS" | passwd >/dev/null 2>&1

pgrep sshd    >/dev/null 2>&1 || sshd
pkill -q php-fpm  2>/dev/null; php-fpm
pkill -q mariadbd 2>/dev/null; mariadbd-safe >/dev/null 2>&1 &
sleep 2
pkill -q nginx 2>/dev/null; nginx

# ── final output ───────────────────────────────────────────────────────────────
USER=$(whoami)
IP=$(get_ip)
SEP="========================================"
clear
cat <<OUT
$SEP
     Mobile Server Ready 🚀
$SEP

SSH Access:
  ssh -p 8022 ${USER}@${IP}

Password:
  $SSH_PASS

Website:
  http://${IP}:8080
$SEP
OUT
