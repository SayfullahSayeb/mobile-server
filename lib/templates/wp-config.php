<?php
/**
 * WordPress Configuration File
 * Generated automatically by Mobile Server
 *
 * Template placeholders are replaced during site creation:
 *   {{DB_NAME}}        - Database name
 *   {{DB_USER}}        - Database username
 *   {{DB_PASSWORD}}    - Database password
 *   {{DB_HOST}}        - Database host
 *   {{DB_CHARSET}}     - Database character set
 *   {{DB_COLLATE}}     - Database collation
 *   {{TABLE_PREFIX}}   - WordPress table prefix
 *   {{AUTH_KEY}}       - Authentication key
 *   {{SECURE_AUTH_KEY}} - Secure authentication key
 *   {{LOGGED_IN_KEY}}   - Logged in key
 *   {{NONCE_KEY}}       - Nonce key
 *   {{AUTH_SALT}}       - Authentication salt
 *   {{SECURE_AUTH_SALT}} - Secure authentication salt
 *   {{LOGGED_IN_SALT}}  - Logged in salt
 *   {{NONCE_SALT}}      - Nonce salt
 */

// ** Database settings ** //
define('DB_NAME', '{{DB_NAME}}');
define('DB_USER', '{{DB_USER}}');
define('DB_PASSWORD', '{{DB_PASSWORD}}');
define('DB_HOST', '{{DB_HOST}}');
define('DB_CHARSET', '{{DB_CHARSET}}');
define('DB_COLLATE', '{{DB_COLLATE}}');

// ** Authentication keys and salts ** //
define('AUTH_KEY',         '{{AUTH_KEY}}');
define('SECURE_AUTH_KEY',  '{{SECURE_AUTH_KEY}}');
define('LOGGED_IN_KEY',    '{{LOGGED_IN_KEY}}');
define('NONCE_KEY',        '{{NONCE_KEY}}');
define('AUTH_SALT',        '{{AUTH_SALT}}');
define('SECURE_AUTH_SALT', '{{SECURE_AUTH_SALT}}');
define('LOGGED_IN_SALT',   '{{LOGGED_IN_SALT}}');
define('NONCE_SALT',       '{{NONCE_SALT}}');

// ** WordPress database table prefix ** //
$table_prefix = '{{TABLE_PREFIX}}';

// ** Debug settings ** //
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);

// ** Filesystem method ** //
define('FS_METHOD', 'direct');

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
