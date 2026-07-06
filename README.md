# Mobile Server

Turn your Android phone into a web server. Runs **Nginx + PHP-FPM + MariaDB** inside Termux — no root or PC required.

## What you can do

- Host PHP sites directly from your phone
- One-click WordPress installation with auto database setup
- Share your local development site with the world using built-in Cloudflared tunnels
- Access your Termux terminal from any web browser
- Full control panel accessible from any browser on the same network

## Prerequisites

Install **Termux** first (Google Play version is outdated):

- **F-Droid:** https://f-droid.org/en/packages/com.termux/
- **GitHub Releases:** https://github.com/termux/termux-app/releases

After installing Termux, open it and run:

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

- Open `http://<device-ip>:8080` for the Control Panel.
- Open `http://<device-ip>:7681` for the Web Terminal (ttyd).
