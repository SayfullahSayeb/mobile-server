<?php

class WordPressInstaller {
    private string $siteName;
    private string $sitePath;
    private string $publicHtml;
    private string $mdbCli = '';

    private string $dbName  = '';
    private string $dbUser  = '';
    private string $dbPass  = '';
    private string $tablePrefix = '';

    private array $serverConfig;

    private bool $dirCreated    = false;
    private bool $dbCreated     = false;
    private bool $userCreated   = false;
    private bool $configWritten = false;

    public function __construct(string $siteName, array $serverConfig = []) {
        $this->siteName     = $siteName;
        $this->sitePath     = SITES_DIR . '/' . $siteName;
        $this->publicHtml   = $this->sitePath . '/public_html';
        $this->serverConfig = array_merge([
            'DB_HOST'      => 'localhost',
            'DB_ROOT_USER' => 'root',
            'DB_ROOT_PASS' => '',
        ], $serverConfig);
    }

    public function createWebsite(string $domain, int $port): array {
        try {
            $this->generateCredentials();
            $this->findMariaDB();
            $this->createDirectory();
            $this->downloadWordPress();
            $this->extractWordPress();
            $this->createDatabase();
            $this->createDatabaseUser();
            $this->generateWpConfig();
            $this->setPermissions();
            $this->createWebServerConfig($domain, $port);
            $this->reloadWebServer();

            return [
                'success' => true,
                'message' => "WordPress site '{$this->siteName}' created successfully",
                'credentials' => [
                    'db_name'   => $this->dbName,
                    'db_user'   => $this->dbUser,
                    'db_pass'   => $this->dbPass,
                    'prefix'    => $this->tablePrefix,
                ],
            ];
        } catch (\RuntimeException $e) {
            $this->rollback();
            panelLog("[WordPress] {$this->siteName}: installation failed — " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function deleteWebsite(string $siteName, string $dbName = '', string $dbUser = ''): void {
        if ($dbName && $dbUser) {
            exec("mariadb -e " . escapeshellarg("DROP DATABASE IF EXISTS `$dbName`") . " 2>/dev/null");
            exec("mariadb -e " . escapeshellarg("DROP USER IF EXISTS '$dbUser'@'localhost'") . " 2>/dev/null");
        } else {
            $dbBase = str_replace('-', '_', $siteName);
            exec("mariadb -e " . escapeshellarg("DROP DATABASE IF EXISTS `$dbBase`") . " 2>/dev/null");
            exec("mariadb -e " . escapeshellarg("DROP USER IF EXISTS '$dbBase'@'localhost'") . " 2>/dev/null");
        }
        $siteDir = SITES_DIR . '/' . $siteName;
        if (is_dir($siteDir)) {
            exec("rm -rf " . escapeshellarg($siteDir) . " 2>/dev/null");
        }
    }

    // ── Credential generation ──────────────────────────────────────

    private function generateCredentials(): void {
        $dbBase = str_replace('-', '_', $this->siteName);
        $this->dbName = $dbBase;
        $this->dbUser = $dbBase;
        $this->dbPass = $this->randomPassword(32);
        $this->tablePrefix = 'wp_' . bin2hex(random_bytes(2)) . '_';
    }

    private function randomPassword(int $length): string {
        $upper   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower   = 'abcdefghijklmnopqrstuvwxyz';
        $digits  = '0123456789';
        $symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        $all     = $upper . $lower . $digits . $symbols;

        $pw  = $upper[random_int(0, 25)];
        $pw .= $lower[random_int(0, 25)];
        $pw .= $digits[random_int(0, 9)];
        $pw .= $symbols[random_int(0, strlen($symbols) - 1)];

        for ($i = strlen($pw); $i < $length; $i++) {
            $pw .= $all[random_int(0, strlen($all) - 1)];
        }
        return str_shuffle($pw);
    }

    // ── MariaDB connectivity ───────────────────────────────────────

    private function findMariaDB(): void {
        $rootUser = $this->serverConfig['DB_ROOT_USER'] ?? 'root';
        $rootPass = $this->serverConfig['DB_ROOT_PASS'] ?? '';

        // Try configured root credentials first
        foreach (['mariadb', 'mysql'] as $bin) {
            exec("command -v $bin 2>/dev/null", $null, $rc);
            if ($rc !== 0) continue;

            $passArg = $rootPass !== '' ? ' --password=' . escapeshellarg($rootPass) : '';
            exec("$bin -u " . escapeshellarg($rootUser) . "$passArg -e 'SELECT 1' 2>&1", $out, $rc2);
            if ($rc2 === 0) {
                $this->mdbCli = "$bin -u " . escapeshellarg($rootUser) . $passArg;
                return;
            }
        }

        // Fallback: try passwordless root access (original behavior)
        foreach (['mariadb', 'mysql'] as $bin) {
            exec("command -v $bin 2>/dev/null", $null, $rc);
            if ($rc !== 0) continue;

            exec("$bin -u root -e 'SELECT 1' 2>&1", $out, $rc2);
            if ($rc2 === 0) {
                $this->mdbCli = "$bin -u root";
                return;
            }
        }

        // If we got here no binary worked as root — check if server is running
        $serverRunning = true;
        exec("pgrep mariadbd 2>/dev/null || pgrep mysqld 2>/dev/null", $pout, $prc);
        if ($prc !== 0) {
            $serverRunning = false;
        }

        $detail = !empty($out) ? implode('; ', $out) : 'not found';

        if (!$serverRunning) {
            // Auto-start MariaDB
            panelLog("[WordPress] MariaDB not running — attempting to start...");
            
            // First check if data directory needs initialization
            $datadir = '/data/data/com.termux/files/usr/var/lib/mysql';
            if (!is_dir($datadir . '/mysql')) {
                panelLog("[WordPress] MariaDB data dir not initialized — running mariadb-install-db");
                exec('mariadb-install-db --user=' . get_current_user() . ' 2>&1', $initOut, $initRc);
                if ($initRc !== 0) {
                    panelLog("[WordPress] mariadb-install-db failed: " . implode('; ', $initOut));
                }
            }
            
            exec('mariadbd-safe >/dev/null 2>&1 &', $null, $startRc);
            sleep(4);
            // Re-check connectivity
            foreach (['mariadb', 'mysql'] as $bin) {
                exec("command -v $bin 2>/dev/null", $null, $rc);
                if ($rc !== 0) continue;
                exec("$bin -u root -e 'SELECT 1' 2>&1", $out, $rc2);
                if ($rc2 === 0) {
                    $this->mdbCli = "$bin -u root";
                    panelLog("[WordPress] MariaDB started automatically");
                    return;
                }
            }
            throw new \RuntimeException("MariaDB server is not running and could not be started automatically. Start it from the Dashboard first.");
        }

        // Binary exists, server is running, but -u root failed — try without user (no password)
        foreach (['mariadb', 'mysql'] as $bin) {
            exec("command -v $bin 2>/dev/null", $null, $rc);
            if ($rc !== 0) continue;
            exec("$bin -e 'SELECT 1' 2>&1", $out2, $rc2);
            if ($rc2 === 0) {
                $this->mdbCli = $bin;
                panelLog("[WordPress] WARNING: connected without -u root — anonymous user may lack privileges");
                return;
            }
        }

        // Last resort: try root without password explicitly
        foreach (['mariadb', 'mysql'] as $bin) {
            exec("command -v $bin 2>/dev/null", $null, $rc);
            if ($rc !== 0) continue;
            exec("$bin -u root --password='' -e 'SELECT 1' 2>&1", $out3, $rc3);
            if ($rc3 === 0) {
                $this->mdbCli = "$bin -u root --password=''";
                panelLog("[WordPress] Connected as root without password");
                return;
            }
        }

        throw new \RuntimeException("MariaDB/MySQL CLI unreachable ($detail). Make sure MariaDB package is installed and running. Try: mariadbd-safe &");
    }

    private function mariadb(string $sql): array {
        exec($this->mdbCli . ' -e ' . escapeshellarg($sql) . ' 2>&1', $out, $rc);
        return ['rc' => $rc, 'out' => implode("\n", $out)];
    }

    // ── Directory ──────────────────────────────────────────────────

    private function createDirectory(): void {
        if (!is_dir($this->publicHtml)) {
            if (!@mkdir($this->publicHtml, 0755, true)) {
                throw new \RuntimeException("Failed to create directory: {$this->publicHtml}");
            }
            $this->dirCreated = true;
        }
    }

    // ── WordPress download / extract ───────────────────────────────

    private function downloadWordPress(): void {
        $zip = $this->getWpZip();
        if ($zip === '') {
            throw new \RuntimeException('Failed to download WordPress. Check internet connection.');
        }
        if (!is_file($zip)) {
            throw new \RuntimeException('WordPress zip not found after download.');
        }
    }

    private function getWpZip(): string {
        $cacheDir = HOME_DIR . '/server/wp-cache';
        $zipPath  = $cacheDir . '/latest.zip';
        $tmpPath  = $cacheDir . '/latest.zip.tmp';
        $metaFile = $cacheDir . '/.meta';
        $ttl      = 86400 * 7;
        $minSize  = 1024 * 1024;

        @mkdir($cacheDir, 0755, true);

        if (is_file($zipPath) && filesize($zipPath) < $minSize) {
            @unlink($zipPath);
            @unlink($metaFile);
        }

        $meta = [];
        if (is_file($metaFile)) {
            $meta = @json_decode(@file_get_contents($metaFile), true);
        }
        $fresh = is_file($zipPath) && filesize($zipPath) >= $minSize
              && is_array($meta) && isset($meta['time'])
              && (time() - (int)$meta['time']) < $ttl;

        if ($fresh) return $zipPath;

        exec("command -v unzip 2>/dev/null || which unzip 2>/dev/null", $null, $unzipRc);
        if ($unzipRc !== 0) {
            return is_file($zipPath) ? $zipPath : '';
        }

        for ($attempt = 0; $attempt < 2; $attempt++) {
            @unlink($tmpPath);
            $flags = $attempt === 0 ? '-sL' : '-sLk';
            exec("curl $flags --connect-timeout 30 --max-time 120 https://wordpress.org/latest.zip -o "
                . escapeshellarg($tmpPath) . ' 2>&1', $raw, $rc);
            if ($rc === 0 && is_file($tmpPath) && filesize($tmpPath) >= $minSize) {
                @unlink($zipPath);
                rename($tmpPath, $zipPath);
                file_put_contents($metaFile, json_encode(['time' => time()]));
                return $zipPath;
            }
        }

        @unlink($tmpPath);
        if (is_file($zipPath) && filesize($zipPath) >= $minSize) return $zipPath;
        return '';
    }

    private function extractWordPress(): void {
        $cacheDir = HOME_DIR . '/server/wp-cache';
        $zipPath  = $cacheDir . '/latest.zip';

        exec("unzip -qo " . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($this->sitePath) . ' 2>&1', $raw, $rc);
        if ($rc !== 0) {
            throw new \RuntimeException('Failed to extract WordPress. Is unzip installed?');
        }

        $wpTemp = $this->sitePath . '/wordpress';
        if (is_dir($wpTemp)) {
            exec("cp -r " . escapeshellarg($wpTemp . '/.') . ' ' . escapeshellarg($this->publicHtml . '/') . ' 2>/dev/null');
            exec("rm -rf " . escapeshellarg($wpTemp));
        }

        if (!is_file($this->publicHtml . '/wp-includes/version.php')) {
            throw new \RuntimeException('WordPress core files missing after extraction.');
        }
    }

    // ── Database ───────────────────────────────────────────────────

    private function createDatabase(): void {
        $r = $this->mariadb("CREATE DATABASE IF NOT EXISTS `{$this->dbName}`");
        if ($r['rc'] !== 0) {
            throw new \RuntimeException("Failed to create database: " . $r['out']);
        }
        $this->dbCreated = true;
    }

    private function createDatabaseUser(): void {
        $r = $this->mariadb("CREATE USER IF NOT EXISTS '{$this->dbUser}'@'localhost' IDENTIFIED BY '{$this->dbPass}'");
        if ($r['rc'] !== 0) {
            throw new \RuntimeException("Failed to create database user: " . $r['out']);
        }

        $r = $this->mariadb("GRANT ALL PRIVILEGES ON `{$this->dbName}`.* TO '{$this->dbUser}'@'localhost'; FLUSH PRIVILEGES");
        if ($r['rc'] !== 0) {
            throw new \RuntimeException("Failed to grant privileges: " . $r['out']);
        }
        $this->userCreated = true;
    }

    // ── wp-config.php ──────────────────────────────────────────────

    private function generateWpConfig(): void {
        $templatePath = defined('WP_CONFIG_TEMPLATE') ? WP_CONFIG_TEMPLATE : __DIR__ . '/templates/wp-config.php';
        if (!is_file($templatePath)) {
            throw new \RuntimeException('wp-config.php template not found at: ' . $templatePath);
        }

        $template = file_get_contents($templatePath);

        // Generate authentication keys and salts
        $keys = [];
        foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
                   'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'] as $key) {
            $keys[$key] = bin2hex(random_bytes(16));
        }

        // Build replacement map
        $replacements = [
            '{{DB_NAME}}'         => $this->dbName,
            '{{DB_USER}}'         => $this->serverConfig['DB_ROOT_USER'] ?? 'root',
            '{{DB_PASSWORD}}'     => $this->serverConfig['DB_ROOT_PASS'] ?? '',
            '{{DB_HOST}}'         => $this->serverConfig['DB_HOST'] ?? 'localhost',
            '{{DB_CHARSET}}'      => 'utf8mb4',
            '{{DB_COLLATE}}'      => '',
            '{{TABLE_PREFIX}}'    => $this->tablePrefix,
            '{{AUTH_KEY}}'        => $keys['AUTH_KEY'],
            '{{SECURE_AUTH_KEY}}' => $keys['SECURE_AUTH_KEY'],
            '{{LOGGED_IN_KEY}}'   => $keys['LOGGED_IN_KEY'],
            '{{NONCE_KEY}}'       => $keys['NONCE_KEY'],
            '{{AUTH_SALT}}'       => $keys['AUTH_SALT'],
            '{{SECURE_AUTH_SALT}}'=> $keys['SECURE_AUTH_SALT'],
            '{{LOGGED_IN_SALT}}'  => $keys['LOGGED_IN_SALT'],
            '{{NONCE_SALT}}'      => $keys['NONCE_SALT'],
        ];

        // Replace all placeholders
        $config = str_replace(array_keys($replacements), array_values($replacements), $template);

        if (file_put_contents($this->publicHtml . '/wp-config.php', $config) === false) {
            throw new \RuntimeException('Failed to write wp-config.php.');
        }
        $this->configWritten = true;
    }

    // ── Permissions ────────────────────────────────────────────────

    private function setPermissions(): void {
        @chmod($this->publicHtml, 0755);
        $wpContent = $this->publicHtml . '/wp-content';
        if (is_dir($wpContent)) @chmod($wpContent, 0755);
    }

    // ── Web server config ──────────────────────────────────────────

    private function createWebServerConfig(string $domain, int $port): void {
        $block = generateNginxBlock($domain, $port, $this->publicHtml);
        if ($block === '') {
            throw new \RuntimeException('Failed to generate nginx configuration.');
        }
        $target = NGINX_SITES_DIR . '/' . $this->siteName . '.conf';
        if (!is_dir(NGINX_SITES_DIR)) {
            @mkdir(NGINX_SITES_DIR, 0755, true);
        }
        if (file_put_contents($target, $block) === false) {
            throw new \RuntimeException('Failed to write nginx configuration.');
        }
        $this->configWritten = true;
        rewriteNginxMainConfig();
    }

    private function reloadWebServer(): void {
        if (!reloadNginx()) {
            $diag = trim(@shell_exec('nginx -t 2>&1') ?: 'unknown error');
            panelLog("[WordPress] nginx reload failed — diagnostics: $diag");
            // Don't throw — site is created successfully, nginx just needs manual restart
            // The control panel will also attempt reload and show appropriate message
        }
    }

    // ── Rollback ───────────────────────────────────────────────────

    private function rollback(): void {
        if ($this->userCreated) {
            $this->mariadb("DROP USER IF EXISTS '{$this->dbUser}'@'localhost'");
        }
        if ($this->dbCreated) {
            $this->mariadb("DROP DATABASE IF EXISTS `{$this->dbName}`");
        }
        if ($this->dirCreated && is_dir($this->sitePath)) {
            exec("rm -rf " . escapeshellarg($this->sitePath) . ' 2>/dev/null');
        }
        @unlink(NGINX_SITES_DIR . '/' . $this->siteName . '.conf');
        clearProgress($this->siteName);
    }
}
