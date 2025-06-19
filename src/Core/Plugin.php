<?php

namespace ArtPulse\Core;

/**
 * Main plugin class for the ArtPulse Management Plugin.
 */
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
        if ( ! defined( 'ARTPULSE_VERSION' ) ) {
            define( 'ARTPULSE_VERSION', self::VERSION );
        }
        if ( ! defined( 'ARTPULSE_PLUGIN_DIR' ) ) {
            define( 'ARTPULSE_PLUGIN_DIR', plugin_dir_path( dirname( dirname( __FILE__ ) ) ) );
        }
        if ( ! defined( 'ARTPULSE_PLUGIN_FILE' ) ) {
            define( 'ARTPULSE_PLUGIN_FILE', ARTPULSE_PLUGIN_DIR . 'artpulse-management.php' );
        }
    }

    private function register_hooks()
    {
        register_activation_hook( ARTPULSE_PLUGIN_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( ARTPULSE_PLUGIN_FILE, [ $this, 'deactivate' ] );

        // Register core modules and front-end submission forms
        add_action( 'init',               [ $this, 'register_core_modules' ] );
        add_action( 'init',               [ \ArtPulse\Frontend\SubmissionForms::class, 'register' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );

        // REST API endpoints
        add_action( 'rest_api_init', [ \ArtPulse\Community\NotificationRestController::class, 'register' ] );
        add_action( 'rest_api_init', [ \ArtPulse\Rest\SubmissionRestController::class, 'register' ] );
    }

    public function activate()
    {
        $db_version_option = 'artpulse_db_version';

        // Initialize settings
        if ( false === get_option( 'artpulse_settings' ) ) {
            add_option( 'artpulse_settings', [ 'version' => self::VERSION ] );
        } else {
            $settings            = get_option( 'artpulse_settings' );
            $settings['version'] = self::VERSION;
            update_option( 'artpulse_settings', $settings );
        }

        // Install/update DB tables
        $stored_db_version = get_option( $db_version_option );
        if ( $stored_db_version !== self::VERSION ) {
            \ArtPulse\Community\FavoritesManager::install_favorites_table();
         \ArtPulse\Community\ProfileLinkRequestManager::install_link_request_table();
            \ArtPulse\Community\FollowManager::install_follows_table();
            \ArtPulse\Community\NotificationManager::install_notifications_table();
            update_option( $db_version_option, self::VERSION );
        }

        // Register CPTs and flush rewrite rules
        \ArtPulse\Core\PostTypeRegistrar::register();
        flush_rewrite_rules();

        // Setup roles and capabilities
        require_once ARTPULSE_PLUGIN_DIR . 'src/Core/RoleSetup.php';
        \ArtPulse\Core\RoleSetup::install();

        // Schedule daily expiration check
        if ( ! wp_next_scheduled( 'ap_daily_expiry_check' ) ) {
            wp_schedule_event( time(), 'daily', 'ap_daily_expiry_check' );
        }
    }

    public function deactivate()
    {
        flush_rewrite_rules();
        wp_clear_scheduled_hook( 'ap_daily_expiry_check' );
    }

    public function register_core_modules()
    {
        \ArtPulse\Core\PostTypeRegistrar::register();
        \ArtPulse\Core\MetaBoxRegistrar::register();
        \ArtPulse\Core\AdminDashboard::register();
        \ArtPulse\Core\ShortcodeManager::register();
        \ArtPulse\Admin\SettingsPage::register();

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
        \ArtPulse\Admin\EnqueueAssets::register();
        \ArtPulse\Frontend\Shortcodes::register();
        \ArtPulse\Frontend\MyEventsShortcode::register();
        \ArtPulse\Frontend\EventSubmissionShortcode::register();
        \ArtPulse\Frontend\EditEventShortcode::register();
        \ArtPulse\Frontend\OrganizationDashboardShortcode::register();
        \ArtPulse\Frontend\OrganizationEventForm::register();
        \ArtPulse\Frontend\UserProfileShortcode::register();
        \ArtPulse\Frontend\ProfileEditShortcode::register();
        \ArtPulse\Frontend\PortfolioBuilder::register();
        \ArtPulse\Admin\MetaBoxesRelationship::register();
        \ArtPulse\Blocks\RelatedItemsSelectorBlock::register();
        \ArtPulse\Admin\ApprovalManager::register();
        \ArtPulse\Rest\RestRoutes::register();

        // Admin meta box registrations
        \ArtPulse\Admin\MetaBoxesArtist::register();
        \ArtPulse\Admin\MetaBoxesArtwork::register();
        \ArtPulse\Admin\MetaBoxesEvent::register();
        \ArtPulse\Admin\MetaBoxesOrganisation::register();

        \ArtPulse\Admin\AdminColumnsArtist::register();
        \ArtPulse\Admin\AdminColumnsArtwork::register();
        \ArtPulse\Admin\AdminColumnsEvent::register();
        \ArtPulse\Admin\AdminColumnsOrganisation::register();
        \ArtPulse\Admin\QuickStartGuide::register();

        \ArtPulse\Taxonomies\TaxonomiesRegistrar::register();

        if ( class_exists( '\\ArtPulse\\Ajax\\FrontendFilterHandler' ) ) {
            \ArtPulse\Ajax\FrontendFilterHandler::register();
        }

        $opts = get_option( 'artpulse_settings', [] );
        if ( ! empty( $opts['woo_enabled'] ) ) {
            \ArtPulse\Core\WooCommerceIntegration::register();
            \ArtPulse\Core\PurchaseShortcode::register();
        }
    }

    public function enqueue_frontend_scripts()
    {
        wp_enqueue_script(
            'ap-membership-account-js',
            plugins_url( 'assets/js/ap-membership-account.js', ARTPULSE_PLUGIN_FILE ),
            [ 'wp-api-fetch' ],
            '1.0.0',
            true
        );
        wp_enqueue_script(
            'ap-favorites-js',
            plugins_url( 'assets/js/ap-favorites.js', ARTPULSE_PLUGIN_FILE ),
            [],
            '1.0.0',
            true
        );
        wp_enqueue_script(
            'ap-notifications-js',
            plugins_url( 'assets/js/ap-notifications.js', ARTPULSE_PLUGIN_FILE ),
            [ 'wp-api-fetch' ],
            '1.0.0',
            true
        );
        wp_localize_script(
            'ap-notifications-js',
            'APNotifications',
            [
                'apiRoot' => esc_url_raw( rest_url() ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
            ]
        );

        wp_enqueue_script(
            'ap-submission-form-js',
            plugins_url( 'assets/js/ap-submission-form.js', ARTPULSE_PLUGIN_FILE ),
            [ 'wp-api-fetch' ],
            '1.0.0',
            true
        );
        wp_localize_script(
    'ap-submission-form-js',
    'APSubmission',
    [
        'endpoint'      => esc_url_raw( rest_url( 'artpulse/v1/submissions' ) ),
        'mediaEndpoint' => esc_url_raw( rest_url( 'wp/v2/media' ) ),
        'nonce'         => wp_create_nonce( 'wp_rest' ),
    ]
);


        wp_enqueue_style(
            'ap-forms-css',
            plugins_url( 'assets/css/ap-forms.css', ARTPULSE_PLUGIN_FILE ),
            [],
            '1.0.0'
        );

        $opts = get_option( 'artpulse_settings', [] );
        if ( ! empty( $opts['service_worker_enabled'] ) ) {
            wp_enqueue_script(
                'ap-sw-loader',
                plugins_url( 'assets/js/sw-loader.js', ARTPULSE_PLUGIN_FILE ),
                [],
                '1.0.0',
                true
            );
            wp_localize_script( 'ap-sw-loader', 'APServiceWorker', [
                'url'     => plugins_url( 'assets/js/service-worker.js', ARTPULSE_PLUGIN_FILE ),
                'enabled' => true,
            ] );
        }
    }
}
