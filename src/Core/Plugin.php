<?php

namespace ArtPulse\Core;

use ArtPulse\Admin\SettingsPage;
use ArtPulse\Core\PortfolioManager;
use ArtPulse\Rest\PortfolioRestController;

class Plugin
{
    private const VERSION = '1.1.5';

    public function __construct()
    {
        $this->define_constants();
        $this->register_hooks();
    }

    private function define_constants()
    {
        if (!defined('ARTPULSE_VERSION')) {
            define('ARTPULSE_VERSION', self::VERSION);
        }
        if (!defined('ARTPULSE_PLUGIN_DIR')) {
            define('ARTPULSE_PLUGIN_DIR', plugin_dir_path(dirname(dirname(__FILE__))));
        }
        if (!defined('ARTPULSE_PLUGIN_FILE')) {
            define('ARTPULSE_PLUGIN_FILE', ARTPULSE_PLUGIN_DIR . '/artpulse-management.php');
        }
    }

    private function register_hooks()
    {
        register_activation_hook(ARTPULSE_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(ARTPULSE_PLUGIN_FILE, [$this, 'deactivate']);
        add_action('init', [$this, 'register_core_modules']);
        add_action('init', [\ArtPulse\Frontend\SubmissionForms::class, 'register']);
        //remove the enqueue scripts from here since the main file will do the enqueuing
       // add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
       //  add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('rest_api_init', [\ArtPulse\Community\NotificationRestController::class, 'register']);
        add_action('rest_api_init', [\ArtPulse\Rest\SubmissionRestController::class, 'register']);
        add_action('wp_login', [self::class, 'updateLastLogin'], 10, 2);
    }

    public function activate()
    {
        $db_version_option = 'artpulse_db_version';

        if (false === get_option('artpulse_settings')) {
            add_option('artpulse_settings', ['version' => self::VERSION]);
        } else {
            $settings = get_option('artpulse_settings');
            $settings['version'] = self::VERSION;
            update_option('artpulse_settings', $settings);
        }

        $stored_db_version = get_option($db_version_option);

        if ($stored_db_version !== self::VERSION) {
            \ArtPulse\Community\FavoritesManager::install_favorites_table();
            \ArtPulse\Community\ProfileLinkRequestManager::install_link_request_table();
            \ArtPulse\Community\FollowManager::install_follows_table();
            \ArtPulse\Community\NotificationManager::install_notifications_table();
            update_option($db_version_option, self::VERSION);
        }

        \ArtPulse\Core\PostTypeRegistrar::register();
        flush_rewrite_rules();

        require_once ARTPULSE_PLUGIN_DIR . '/src/Core/RoleSetup.php';
        \ArtPulse\Core\RoleSetup::install();

        if (!wp_next_scheduled('ap_daily_expiry_check')) {
            wp_schedule_event(time(), 'daily', 'ap_daily_expiry_check');
        }
    }

    public function deactivate()
    {
        flush_rewrite_rules();
        wp_clear_scheduled_hook('ap_daily_expiry_check');
    }

    public function register_core_modules()
    {
        \ArtPulse\Core\PostTypeRegistrar::register();
        \ArtPulse\Core\MetaBoxRegistrar::register();
        \ArtPulse\Core\AdminDashboard::register();
        \ArtPulse\Core\ShortcodeManager::register();
        \ArtPulse\Core\MembershipManager::register();
        \ArtPulse\Core\AccessControlManager::register();
        \ArtPulse\Core\DirectoryManager::register();
        \ArtPulse\Core\UserDashboardManager::register();
        \ArtPulse\Core\AnalyticsManager::register();
        \ArtPulse\Core\AnalyticsDashboard::register();
        \ArtPulse\Core\FrontendMembershipPage::register();
        \ArtPulse\Community\ProfileLinkRequestManager::register();
        \ArtPulse\Core\MyFollowsShortcode::register();
        \ArtPulse\Core\NotificationShortcode::register();
        \ArtPulse\Admin\AdminListSorting::register();
        \ArtPulse\Rest\RestSortingSupport::register();
        \ArtPulse\Admin\AdminListColumns::register();
        \ArtPulse\Frontend\Shortcodes::register();
        \ArtPulse\Admin\MetaBoxesRelationship::register();
        \ArtPulse\Blocks\RelatedItemsSelectorBlock::register();
        \ArtPulse\Admin\ApprovalManager::register();
        \ArtPulse\Rest\RestRoutes::register();
        \ArtPulse\Core\CapabilitiesManager::register();
        \ArtPulse\Rest\ArtistRestController::register();
        \ArtPulse\Admin\MemberEnhancements::register();
        \ArtPulse\Admin\EngagementDashboard::register();
        PortfolioManager::register();

        require_once ARTPULSE_PLUGIN_DIR . '/src/Core/RoleSetup.php';
        \ArtPulse\Core\RoleSetup::install();

        \ArtPulse\Admin\MetaBoxesArtist::register();
        \ArtPulse\Admin\MetaBoxesArtwork::register();
        \ArtPulse\Admin\MetaBoxesEvent::register();
        \ArtPulse\Admin\MetaBoxesOrganisation::register();
        \ArtPulse\Admin\AdminColumnsArtist::register();
        \ArtPulse\Admin\AdminColumnsArtwork::register();
        \ArtPulse\Admin\AdminColumnsEvent::register();
        \ArtPulse\Admin\AdminColumnsOrganisation::register();
        \ArtPulse\Core\MembershipNotifier::register();
        \ArtPulse\Core\MembershipCron::register();
        \ArtPulse\Taxonomies\TaxonomiesRegistrar::register();
        \ArtPulse\Frontend\PortfolioBuilder::register();

        if (class_exists('\\ArtPulse\\Ajax\\FrontendFilterHandler')) {
            \ArtPulse\Ajax\FrontendFilterHandler::register();
        }

        $opts = get_option('artpulse_settings', []);
        if (!empty($opts['woo_enabled'])) {
            \ArtPulse\Core\WooCommerceIntegration::register();
            \ArtPulse\Core\PurchaseShortcode::register();
        }

        SettingsPage::register();
    }

    //remove the frontend enqueuing since the main file will be doing it
   // public function enqueue_frontend_assets()
   // {
   //     $plugin_url = plugin_dir_url(ARTPULSE_PLUGIN_FILE);
   //     wp_enqueue_script(
   //         'ap-membership-account-js',
   //         $plugin_url . '/assets/js/ap-membership-account.js',
   //         ['wp-api-fetch'],
   //         '1.0.0',
   //         true
   //     );
   //     wp_enqueue_script(
   //         'ap-favorites-js',
   //         $plugin_url . '/assets/js/ap-favorites.js',
   //         [],
   //         '1.0.0',
   //         true
   //     );
   //     wp_enqueue_script(
   //         'ap-notifications-js',
   //         $plugin_url . '/assets/js/ap-notifications.js',
   //         ['wp-api-fetch'],
   //         '1.0.0',
   //         true
   //     );
   //     wp_localize_script('ap-notifications-js', 'APNotifications', [
   //         'apiRoot' => esc_url_raw(rest_url()),
   //         'nonce'   => wp_create_nonce('wp_rest'),
   //     ]);
   //     wp_enqueue_script(
   //         'ap-submission-form-js',
   //         $plugin_url . '/assets/js/ap-submission-form.js',
   //         ['wp-api-fetch'],
   //         '1.0.0',
   //         true
   //     );
   //     wp_localize_script('ap-submission-form-js', 'APSubmission', [
   //         'endpoint'      => esc_url_raw(rest_url('artpulse/v1/submissions')),
   //         'mediaEndpoint' => esc_url_raw(rest_url('wp/v2/media')),
   //         'nonce'         => wp_create_nonce('wp_rest'),
   //     ]);
   //     wp_enqueue_style(
   //         'ap-forms-css',
   //         $plugin_url . '/assets/css/ap-forms.css',
   //         [],
   //         '1.0.0'
   //     );
   //     // Add other frontend assets here
   //     wp_enqueue_style(
   //         'ap-directory-css',
   //         $plugin_url . '/assets/css/ap-directory.css',
   //         [],
   //         '1.0.0'
   //     );
   // }
   // public function enqueue_admin_assets()
   // {
   //     $plugin_url = plugin_dir_url(ARTPULSE_PLUGIN_FILE);
   //     // Enqueue assets from the Admin\EnqueueAssets class
   //     \ArtPulse\Admin\EnqueueAssets::enqueue_block_editor_assets();
   //     \ArtPulse\Admin\EnqueueAssets::enqueue_block_editor_styles();
   //     \ArtPulse\Admin\EnqueueAssets::enqueue();
   //     // Enqueue your Core-specific admin assets here
   //     wp_enqueue_style(
   //         'ap-user-dashboard-css',
   //         $plugin_url . '/assets/css/ap-user-dashboard.css', // Corrected path
   //         [],
   //         '1.0.0'
   //     );
   //     wp_enqueue_script(
   //         'ap-user-dashboard-js',
   //         $plugin_url . '/assets/js/ap-user-dashboard.js', // Corrected path
   //         [],
   //         '1.0.0',
   //         true
   //     );
   //     wp_enqueue_script(
   //         'ap-analytics-js',
   //         $plugin_url . '/assets/js/ap-analytics.js',
   //         [],
   //         '1.0.0',
   //         true
   //     );
   //     wp_enqueue_script(
   //         'ap-my-follows-js',
   //         $plugin_url . '/assets/js/ap-my-follows.js',
   //         [],
   //         '1.0.0',
   //         true
   //     );
   // }
    public static function updateLastLogin($user_login, $user)
    {
        update_user_meta($user->ID, 'last_login', current_time('mysql'));
    }
}