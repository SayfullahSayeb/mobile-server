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

    private bool $dirCreated    = false;
    private bool $dbCreated     = false;
    private bool $userCreated   = false;
    private bool $configWritten = false;

    public function __construct(string $siteName) {
        $this->siteName   = $siteName;
        $this->sitePath   = SITES_DIR . '/' . $siteName;
        $this->publicHtml = $this->sitePath . '/public_html';
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
            $dbBase = 'ms_' . str_replace('-', '_', $siteName);
            exec("mariadb -e " . escapeshellarg("DROP DATABASE IF EXISTS `$dbBase`") . " 2>/dev/null");
            exec("mariadb -e " . escapeshellarg("DROP USER IF EXISTS 'msu_$dbBase'@'localhost'") . " 2>/dev/null");
        }
        $siteDir = SITES_DIR . '/' . $siteName;
        if (is_dir($siteDir)) {
            exec("rm -rf " . escapeshellarg($siteDir) . " 2>/dev/null");
        }
    }

    // ── Credential generation ──────────────────────────────────────

    private function generateCredentials(): void {
        $dbBase = str_replace('-', '_', $this->siteName);
        $this->dbName = 'ms_' . $dbBase;
        $this->dbUser = 'msu_' . $dbBase;
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
        foreach (['mariadb', 'mysql'] as $bin) {
            exec("command -v $bin 2>/dev/null", $null, $rc);
            if ($rc === 0) {
                exec("$bin -e 'SELECT 1' 2>&1", $out, $rc2);
                if ($rc2 === 0) {
                    $this->mdbCli = $bin;
                    return;
                }
            }
        }
        $detail = !empty($out) ? implode('; ', $out) : 'not found';
        throw new \RuntimeException("MariaDB/MySQL CLI unreachable ($detail). Start MariaDB from the Dashboard.");
    }

    private function mariadb(string $sql): array {
        exec(escapeshellarg($this->mdbCli) . ' -e ' . escapeshellarg($sql) . ' 2>&1', $out, $rc);
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
        $sample = $this->publicHtml . '/wp-config-sample.php';
        if (!is_file($sample)) {
            throw new \RuntimeException('wp-config-sample.php not found.');
        }

        $config = file_get_contents($sample);

        // Database settings
        $config = str_replace(
            ["'database_name_here'", "'username_here'", "'password_here'"],
            ["'{$this->dbName}'", "'{$this->dbUser}'", "'{$this->dbPass}'"],
            $config
        );
        $config = preg_replace(
            "/define\s*\(\s*'DB_HOST'.*\);/",
            "define('DB_HOST', 'localhost');",
            $config
        );
        $config = preg_replace(
            "/define\s*\(\s*'DB_CHARSET'.*\);/",
            "define('DB_CHARSET', 'utf8mb4');",
            $config
        );
        $config = preg_replace(
            "/define\s*\(\s*'DB_COLLATE'.*\);/",
            "define('DB_COLLATE', '');",
            $config
        );

        // Table prefix
        $config = preg_replace(
            "/\\\$table_prefix\s*=\s*'[^']*';/",
            "\$table_prefix = '{$this->tablePrefix}';",
            $config
        );

        // Authentication keys and salts
        foreach (['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT'] as $key) {
            $val = bin2hex(random_bytes(16));
            $config = preg_replace(
                "/define\s*\(\s*'{$key}'\s*,\s*'[^']*'\s*\);/",
                "define('{$key}', '{$val}');",
                $config
            );
        }

        // Add debug and filesystem settings before the final "That's all" comment
        $stopMarker = "/* That's all, stop editing!";
        $debugBlock = "\n" . 'define(\'WP_DEBUG\', false);'
                    . "\n" . 'define(\'WP_DEBUG_LOG\', false);'
                    . "\n" . 'define(\'WP_DEBUG_DISPLAY\', false);'
                    . "\n" . 'define(\'FS_METHOD\', \'direct\');'
                    . "\n\n";
        $pos = strpos($config, $stopMarker);
        if ($pos !== false) {
            $config = substr_replace($config, $debugBlock, $pos, 0);
        } else {
            $config .= $debugBlock;
        }

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
            $err = trim(@shell_exec('nginx -t 2>&1') ?: 'unknown error');
            throw new \RuntimeException("Nginx reload failed: $err");
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
