<?php
/**
 * Plugin Name:     ArtPulse Management
 * Description:     Management plugin for ArtPulse.
 * Version:         1.1.5
 * Author:          craig
 * Text Domain:     artpulse-management
 * License:         GPL2
 */

use ArtPulse\Core\Plugin;
use ArtPulse\Admin\EnqueueAssets;
use ArtPulse\Tools\CLI\BackfillLetters;
use ArtPulse\Tools\CLI\BackfillEventGeo;

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
} else {
    spl_autoload_register(static function ($class) {
        $prefix = 'ArtPulse\\';
        if (strpos($class, $prefix) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $relativePath = str_replace('\\', '/', $relative);
        $path = __DIR__ . '/src/' . $relativePath . '.php';

        if (file_exists($path)) {
            require $path;
        }
    });
}

require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/Core/Urls.php';
require_once __DIR__ . '/src/Core/RoleGate.php';
require_once __DIR__ . '/src/Core/RoleDashboards.php';

add_action( 'init', [ 'ArtPulse\\Core\\RoleDashboards', 'init' ], 20 );

add_action('plugins_loaded', static function () {
    load_plugin_textdomain(
        'artpulse-management',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
});

if (!class_exists(Plugin::class)) {
    $message = 'ArtPulse Management bootstrap aborted: Plugin class unavailable.';
    error_log($message);

    if (function_exists('add_action')) {
        add_action('admin_notices', static function () use ($message) {
            $formattedMessage = function_exists('esc_html') ? esc_html($message) : $message;
            echo '<div class="notice notice-error"><p>' . $formattedMessage . '</p></div>';
        });
    }

    return;
}

// 🔧 Boot the main plugin class (responsible for registering menus, settings, CPTs, etc.)
$main = new Plugin();

// WooCommerce hooks are registered from Plugin::register_core_modules() when enabled via settings.

// ✅ Hook for deactivation
//register_deactivation_hook(__FILE__, [$plugin, 'deactivate']);

// Register REST API routes
add_action('rest_api_init', function () {
    \ArtPulse\Rest\PortfolioRestController::register();
    \ArtPulse\Rest\MemberDashboardController::register();
    \ArtPulse\Rest\ArtistDashboardController::register();
});

if (defined('WP_CLI') && WP_CLI) {
    require_once __DIR__ . '/tools/cli/BackfillLetters.php';
    require_once __DIR__ . '/tools/cli/BackfillEventGeo.php';
    require_once __DIR__ . '/tools/cli/MetricsDump.php';
    require_once __DIR__ . '/tools/cli/Purge.php';
    \WP_CLI::add_command('artpulse backfill-letters', [BackfillLetters::class, 'handle']);
    \WP_CLI::add_command('artpulse backfill-event-geo', [BackfillEventGeo::class, 'handle']);
    \WP_CLI::add_command('artpulse metrics dump', [\ArtPulse\Tools\CLI\MetricsDump::class, 'handle']);
    \WP_CLI::add_command('artpulse mobile purge', [\ArtPulse\Tools\CLI\Purge::class, 'handle']);
    \WP_CLI::add_command('artpulse backfill-event-thumbnails', static function () {
        $query = new \WP_Query([
            'post_type'      => 'artpulse_event',
            'post_status'    => 'publish',
            'meta_key'       => '_ap_submission_images',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        foreach ($query->posts as $event_id) {
            $event_id = (int) $event_id;
            if (has_post_thumbnail($event_id)) {
                continue;
            }

            $submission_ids = (array) get_post_meta($event_id, '_ap_submission_images', true);
            $first_id       = (int) ($submission_ids[0] ?? 0);

            if ($first_id > 0) {
                set_post_thumbnail($event_id, $first_id);
            }
        }

        \WP_CLI::success('Backfill complete.');
    });
}

if ( ! function_exists( 'artpulse_create_custom_table' ) ) {
    function artpulse_create_custom_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'artpulse_data';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title text NOT NULL,
            artist_name varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
