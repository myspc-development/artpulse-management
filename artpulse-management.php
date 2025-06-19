<?php
/**
 * Plugin Name:     ArtPulse Management
 * Description:     Management plugin for ArtPulse.
 * Version:         1.1.5
 * Author:          craig
 * Text Domain:     artpulse
 * License:         GPL2
 */

use ArtPulse\Core\Plugin;
use ArtPulse\Core\WooCommerceIntegration;
use ArtPulse\Core\Activator;
use ArtPulse\Admin\EnqueueAssets;

// Suppress deprecated notices if WP_DEBUG enabled
if (defined('WP_DEBUG') && WP_DEBUG) {
    @ini_set('display_errors', '0');
    @error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}

// Define ARTPULSE_PLUGIN_FILE constant (THIS IS CRUCIAL - MUST BE DEFINED CORRECTLY)
if (!defined('ARTPULSE_PLUGIN_FILE')) {
    define('ARTPULSE_PLUGIN_FILE', __FILE__);
}

// Load Composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// 🔧 Boot the main plugin class (responsible for registering menus, settings, CPTs, etc.)
$main = new Plugin();

// Optional debug log for class check
if (class_exists(WooCommerceIntegration::class)) {
    error_log('Plugin class loaded successfully');
} else {
    error_log('Failed to load Plugin class');
}

// Instantiate WooCommerce integration (if needed for runtime)
$plugin = new WooCommerceIntegration();

// ✅ Hook for activation
register_activation_hook(__FILE__, function () {
    Activator::activate(); // WooCommerceIntegration has no activate() method
});

// ✅ Hook for deactivation
//register_deactivation_hook(__FILE__, [$plugin, 'deactivate']);

// Register REST API routes
add_action('rest_api_init', function () {
    \ArtPulse\Rest\PortfolioRestController::register();
});

// Register Enqueue Assets
add_action('init', function () {
    EnqueueAssets::register();
});
