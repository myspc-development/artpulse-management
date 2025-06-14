<?php

// Define minimal WordPress constant so the plugin loads.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/Stubs.php';

// Load the plugin which registers the autoloader itself.
require_once dirname( __DIR__ ) . '/artpulse-management.php';

// Ensure the wpdb prefix property exists for tests.
global $wpdb;
if ( ! isset( $wpdb->prefix ) ) {
    $wpdb->prefix = '';
}
