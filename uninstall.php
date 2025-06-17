<?php
// In uninstall.php at plugin root:
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options.
delete_option( 'artpulse_settings' );

// Drop custom DB tables if any.
// global $wpdb;
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}artpulse_events" );
