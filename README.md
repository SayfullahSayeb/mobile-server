# Mobile Server

Turn your Android phone into a web server. Runs **Nginx + PHP-FPM + MariaDB** inside Termux — no root or PC required.

## What you can do

- Host PHP sites directly from your phone
- Create virtual hosts with custom domains and ports (like Laragon)
- One-click WordPress installation with auto database setup
- Browse and edit files using the built-in **Tiny File Manager**
- Web terminal via ttyd on port 7681
- Full control panel accessible from any browser on the same network

## Install

```bash
bash <(curl -fsSL https://sayfullahsayeb.github.io/mobile-server/install.sh)
```

## Usage

| Command | Action |
|---------|--------|
| `ms start` | Start all services |
| `ms stop` | Stop all services |
| `ms restart` | Restart all services |
| `ms status` | Check running status |
| `ms update` | Update system |

Open `http://<device-ip>:8080` for the Control Panel.
Open `http://<device-ip>:7681` for the Web Terminal (ttyd).
