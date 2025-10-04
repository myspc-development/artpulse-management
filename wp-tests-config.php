<?php
/**
 * WordPress test suite configuration file.
 */

define( 'DB_NAME', 'wp_phpunit_tests' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', '127.0.0.1' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

define( 'WP_TESTS_MULTISITE', false );
define( 'WP_DEBUG', true );
define( 'DISABLE_WP_CRON', true );

