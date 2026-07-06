#!/data/data/com.termux/files/usr/bin/bash

case "${1:-}" in
    start)
        echo "Starting Mobile Server..."
        if pgrep nginx >/dev/null 2>&1; then echo "  Nginx      Already running"; elif nginx -t >/dev/null 2>&1; then nginx && echo "  Nginx      Running" || echo "  Nginx      Failed"; else echo "  Nginx      Config error — run 'nginx -t'"; fi
        if pgrep php-fpm >/dev/null 2>&1; then echo "  PHP-FPM    Already running"; else php-fpm 2>/dev/null && echo "  PHP-FPM    Running" || echo "  PHP-FPM    Failed"; fi
        if pgrep mariadbd >/dev/null 2>&1 || pgrep mysqld >/dev/null 2>&1; then echo "  MariaDB    Already running"; else mariadbd-safe >/dev/null 2>&1 & sleep 2; if pgrep mariadbd >/dev/null 2>&1 || pgrep mysqld >/dev/null 2>&1; then echo "  MariaDB    Running"; else echo "  MariaDB    Failed"; fi; fi
        if pgrep ttyd >/dev/null 2>&1; then echo "  ttyd       Already running"; else ttyd -W -p 7681 bash -l >/dev/null 2>&1 & sleep 1; if pgrep ttyd >/dev/null 2>&1; then echo "  ttyd       Running"; else echo "  ttyd       Failed"; fi; fi
        ;;
    stop)
        echo "Stopping Mobile Server..."
        for svc in nginx php-fpm mariadbd ttyd; do
            if pgrep "$svc" >/dev/null 2>&1; then
                pkill -9 "$svc" 2>/dev/null || true
                sleep 1
                if pgrep "$svc" >/dev/null 2>&1; then
                    printf "  %-10s  Force killed\n" "$svc"
                else
                    printf "  %-10s  Stopped\n" "$svc"
                fi
            else
                printf "  %-10s  Not running\n" "$svc"
            fi
        done
        # mariadbd-safe spawns mysqld as the actual server process
        if pgrep mysqld >/dev/null 2>&1; then
            pkill -9 mysqld 2>/dev/null || true
            printf "  %-10s  Stopped\n" "mysqld"
        fi
        echo "Mobile Server stopped"
        ;;
    restart)
        "$0" stop
        sleep 1
        "$0" start
        ;;
    status)
        IP=$(ip -4 -o addr show wlan0 2>/dev/null | awk '{print $4}' | cut -d/ -f1)
        if [ -z "$IP" ] || [ "$IP" = "127.0.0.1" ]; then
            IP=$(ifconfig wlan0 2>/dev/null | grep 'inet ' | awk '{print $2}')
        fi
        [ -z "$IP" ] && IP="127.0.0.1"

        echo "╔╦╗╔═╗╔╗ ╦╦ ╦  ╔═╗   ╔═╗╔═╗╦═╗╦  ╦╔═╗╦═╗"
        echo "║║║║ ║╠╩╗║║ ║  ║╣    ╚═╗║╣ ╠╦╝╚╗╔╝║╣ ╠╦╝"
        echo "╩ ╩╚═╝╚═╝╩╩ ╩═╝╚═╝   ╚═╝╚═╝╩╚═ ╚╝ ╚═╝╩╚═"
        echo ""
        echo "========================================"
        echo "     Mobile Server Status"
        echo "========================================"
        echo ""
        echo "Terminal: http://${IP}:7681"
        echo ""
        echo "Website: http://${IP}:8080"
        echo ""
        PASS_FILE="$HOME/server/.panel_password"
        if [ -f "$PASS_FILE" ]; then
            echo "Panel Password: $(cat "$PASS_FILE")"
            echo ""
        fi
        echo "Services:"
        for svc in nginx php-fpm ttyd; do
            if pgrep "$svc" >/dev/null 2>&1; then
                printf "  %-10s  Running\n" "$svc"
            else
                printf "  %-10s  Stopped\n" "$svc"
            fi
        done
        if pgrep mariadbd >/dev/null 2>&1 || pgrep mysqld >/dev/null 2>&1; then
            printf "  %-10s  Running\n" "MariaDB"
        else
            printf "  %-10s  Stopped\n" "MariaDB"
        fi
        echo ""
        echo "========================================"
        ;;
    update)
        HOME_DIR="$HOME"
        TARGET_DIR="$HOME_DIR/server/sites/default/public_html"
        TMP_DIR="$(mktemp -d 2>/dev/null || echo "/tmp/ms-update")"
        REPO_URL="https://github.com/SayfullahSayeb/mobile-server.git"
        HASH_FILE="$HOME_DIR/server/configs/.update_hash"

        echo "Updating Mobile Server from GitHub..."
        echo

        LATEST_HASH=$(git ls-remote "$REPO_URL" HEAD 2>/dev/null | awk '{print $1}')
        if [ -z "$LATEST_HASH" ]; then
            echo "[!] Failed to check for updates — no internet connection?"
            exit 1
        fi

        if [ -f "$HASH_FILE" ] && [ "$(cat "$HASH_FILE")" = "$LATEST_HASH" ]; then
            echo "  Already up to date (commit ${LATEST_HASH:0:8})."
            echo
            echo "No update needed."
            exit 0
        fi

        if git clone --depth 1 "$REPO_URL" "$TMP_DIR" 2>/dev/null; then
            echo "  Cloned repository."
            echo "$LATEST_HASH" > "$HASH_FILE"

            # Copy all files from repo (skip .git)
            for item in "$TMP_DIR"/* "$TMP_DIR"/.[!.]*; do
                base="$(basename "$item")"
                [ "$base" = ".git" ] || [ "$base" = "." ] || [ "$base" = ".." ] && continue
                cp -r "$item" "$TARGET_DIR/" 2>/dev/null
                printf "  \xE2\x9C\x93 Synced %s\n" "$base"
            done

            # Update CLI
            CLI_DEST="$PREFIX/bin/ms"
            if [ -f "$TMP_DIR/ms" ]; then
                cp "$TMP_DIR/ms" "$CLI_DEST" 2>/dev/null
                chmod +x "$CLI_DEST" 2>/dev/null
                printf "  \xE2\x9C\x93 Updated CLI\n"
            fi

            rm -rf "$TMP_DIR" 2>/dev/null
            echo
            echo "Update completed successfully."
        else
            echo "[!] Failed to clone repository. Check internet connection."
            rm -rf "$TMP_DIR" 2>/dev/null
            exit 1
        fi
        ;;
    *)
        echo "Usage: ms {start|stop|restart|status|update}"
        exit 1
        ;;
esac
