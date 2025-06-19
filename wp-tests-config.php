<?php
/**
 * WordPress test suite configuration file.
 */

// Load environment variables from a .env file if present
$env_file = __DIR__ . '/.env';
if (file_exists( $env_file ) ) {
    $env = parse_ini_file( $env_file, false, INI_SCANNER_RAW );
    if ( is_array( $env ) ) {
        foreach ( $env as $key => $value ) {
            if ( getenv( $key ) === false ) {
                putenv( "$key=$value" );
                $_ENV[ $key ] = $value;
            }
        }
    }
}

// DB settings for your test database
define( 'DB_NAME', getenv( 'DB_NAME' ) ?: 'wordpress_test' );
define( 'DB_USER', getenv( 'DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'DB_PASSWORD' ) ?: 'root' );
define( 'DB_HOST', getenv( 'DB_HOST' ) ?: '127.0.0.1' );
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
