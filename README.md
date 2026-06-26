# Mobile Server

Turn your Android phone into a web server. Runs **Nginx + PHP-FPM + MariaDB** inside Termux — no root or PC required.

## What you can do

- Host PHP sites directly from your phone
- Create virtual hosts with custom domains and ports (like Laragon)
- One-click WordPress installation with auto database setup
- Expose sites to the internet via **Cloudflare Tunnel** (no port forwarding)
- Browse and edit files using the built-in **elFinder** file manager
- SSH into your phone on port 8022
- Full control panel accessible from any browser on the same network

## Install

```bash
bash <(curl -fsSL https://sayfullahsayeb.github.io/mobile-server/install.sh)
```

## Usage

| Command | Action |
|---------|--------|
| `mobile-server start` | Start all services |
| `mobile-server stop` | Stop all services |
| `mobile-server restart` | Restart all services |
| `mobile-server status` | Check running status |
| `mobile-server update` | Update system from GitHub |

Open `http://<device-ip>:8080` for the status dashboard, or `http://<device-ip>:8080/control.php` (password: `admin`) for the full control panel.
