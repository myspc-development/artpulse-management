<?php
/**
 * WordPress test suite configuration file.
 */

// DB settings for your test database
define( 'DB_NAME', 'sql_192_168_88_3' );
define( 'DB_USER', 'sql_192_168_88_3' );
define( 'DB_PASSWORD', 'beca2446d15b1' );
define( 'DB_HOST', '127.0.0.1' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

// Site constants required by WP test suite
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

// PHP binary for running tests
define( 'WP_PHP_BINARY', 'php' );

// Path to WordPress source; adjust if you use a different setup
define( 'ABSPATH', dirname( __FILE__ ) . '/wordpress' );

// Bootstrap the WordPress environment for testing
require_once ABSPATH . '/wp-settings.php';
