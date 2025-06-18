<?php

namespace ArtPulse\Core;

use ArtPulse\Admin\SettingsPage;
use ArtPulse\Core\PortfolioManager;
use ArtPulse\Rest\PortfolioRestController;

class Plugin {

    private const VERSION = '1.1.5';

    public function __construct() {
        // $this->define_constants(); // REMOVE THIS LINE
        $this->register_hooks();
    }

    // private function define_constants() { // REMOVE THIS ENTIRE METHOD
    //     if (!defined('ARTPULSE_VERSION')) {
    //         define('ARTPULSE_VERSION', self::VERSION);
    //     }
    //     if (!defined('ARTPULSE_PLUGIN_DIR')) {
    //         define('ARTPULSE_PLUGIN_DIR', plugin_dir_path(dirname(dirname(__FILE__))));
    //     }
    //     // REMOVE this definition. ARTPULSE_PLUGIN_FILE is defined in artpulse-management.php
    //     // if (!defined('ARTPULSE_PLUGIN_FILE')) {
    //     // define('ARTPULSE_PLUGIN_FILE', ARTPULSE_PLUGIN_DIR . '/artpulse-management.php');
    //     // }
    // }

    private function register_hooks() {
        // Make sure ARTPULSE_PLUGIN_FILE is defined BEFORE using it.
        if (!defined('ARTPULSE_PLUGIN_FILE')) {
            error_log('ARTPULSE_PLUGIN_FILE is not defined!'); // Log an error
            return; // Exit if the constant is not defined
        }

        register_activation_hook(ARTPULSE_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(ARTPULSE_PLUGIN_FILE, [$this, 'deactivate']);
        add_action('init', [$this, 'register_core_modules']);
        add_action('init', [\ArtPulse\Frontend\SubmissionForms::class, 'register']);
        add_action('rest_api_init', [\ArtPulse\Community\NotificationRestController::class, 'register']);
        add_action('rest_api_init', [\ArtPulse\Rest\SubmissionRestController::class, 'register']);
        add_action('wp_login', [self::class, 'updateLastLogin'], 10, 2);
    }

    public function activate() {
        if (!defined('ARTPULSE_PLUGIN_FILE')) {
            error_log('ARTPULSE_PLUGIN_FILE is not defined during activation!');
            return;
        }

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
        require_once plugin_dir_path(ARTPULSE_PLUGIN_FILE) . '/src/Core/RoleSetup.php';
        \ArtPulse\Core\RoleSetup::install();

        if (!wp_next_scheduled('ap_daily_expiry_check')) {
            wp_schedule_event(time(), 'daily', 'ap_daily_expiry_check');
        }
    }

    public function deactivate() {
        flush_rewrite_rules();
        wp_clear_scheduled_hook('ap_daily_expiry_check');
    }

    public function register_core_modules() {
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
        require_once plugin_dir_path(ARTPULSE_PLUGIN_FILE) . '/src/Core/RoleSetup.php';
        \ArtPulse\Core\RoleSetup::install();

        // Load the UserProfileShortcode class
        add_action('init', function () {
            \ArtPulse\Frontend\UserProfileShortcode::register();
        });

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

    public static function updateLastLogin($user_login, $user) {
        update_user_meta($user->ID, 'last_login', current_time('mysql'));
    }
}