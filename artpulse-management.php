<?php
/**
 * Plugin Name:     ArtPulse Management
 * Description:     Management plugin for ArtPulse.
 * Version:         1.1.5
 * Author:          craig
 * Text Domain:     artpulse
 * License:         GPL2
 */

// Suppress deprecated notices if WP_DEBUG enabled
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    @ini_set( 'display_errors', '0' );
    @error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );
}

// Composer autoloader — must be loaded BEFORE using any classes
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// OPTIONAL: Check if the class loaded successfully
if (class_exists(\ArtPulse\Core\Plugin::class)) {
    error_log('Plugin class loaded successfully');
} else {
    error_log('Failed to load Plugin class');
}

use ArtPulse\Core\Plugin;

// Instantiate the plugin class
$plugin = new Plugin();

// Register activation and deactivation hooks
register_activation_hook( __FILE__, [ $plugin, 'activate' ] );
register_deactivation_hook( __FILE__, [ $plugin, 'deactivate' ] );

// No additional hooks needed here — plugin hooks are registered inside the Plugin class constructor
