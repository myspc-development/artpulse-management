<?php
/**
 * Plugin Name:     ArtPulse Management
 * Description:     Management plugin for ArtPulse.
 * Version:         0.1.0
 * Author:          Your Name
 * Text Domain:     artpulse
 * License:         GPL2
 */

// Autoload dependencies via Composer
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Import core classes
use ArtPulse\Core\PostTypeRegistrar;
use ArtPulse\Core\MetaBoxRegistrar;
use ArtPulse\Core\AdminDashboard;
use ArtPulse\Core\ShortcodeManager;
use ArtPulse\Core\SettingsPage;
use ArtPulse\Core\MembershipManager;
use ArtPulse\Core\AccessControlManager;
use ArtPulse\Core\CapabilitiesManager;

/**
 * Runs on plugin activation:
 *  - Creates default options
 *  - Registers CPTs and flushes rewrite rules
 *  - Schedules daily expiry cron
 */
function artpulse_activate() {
    // Default settings
    if ( false === get_option( 'artpulse_settings' ) ) {
        add_option( 'artpulse_settings', [
            'version' => '0.1.0',
        ] );
    }

    // Register CPTs so permalinks work immediately
    PostTypeRegistrar::register();
    flush_rewrite_rules();

    // Schedule daily expiry check
    if ( ! wp_next_scheduled( 'ap_daily_expiry_check' ) ) {
        wp_schedule_event( time(), 'daily', 'ap_daily_expiry_check' );
    }
}
register_activation_hook( __FILE__, 'artpulse_activate' );

/**
 * Runs on plugin deactivation:
 *  - Flushes rewrite rules
 *  - Clears scheduled cron
 */
function artpulse_deactivate() {
    flush_rewrite_rules();
    wp_clear_scheduled_hook( 'ap_daily_expiry_check' );
}
register_deactivation_hook( __FILE__, 'artpulse_deactivate' );

/**
 * Enqueue frontend assets
 */
function artpulse_enqueue_assets() {
    wp_enqueue_style(
        'artpulse-portfolio',
        plugins_url( 'assets/css/ap-portfolio.css', __FILE__ ),
        [],
        '1.0.0'
    );
}
add_action( 'wp_enqueue_scripts', 'artpulse_enqueue_assets' );

/**
 * Initialize all modules
 */
add_action( 'init', function() {
    PostTypeRegistrar::register();
    MetaBoxRegistrar::register();
    AdminDashboard::register();
    ShortcodeManager::register();
    SettingsPage::register();
    MembershipManager::register();
    AccessControlManager::register();
    CapabilitiesManager::register();
} );
