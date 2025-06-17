<?php
namespace ArtPulse\Core;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


class Plugin
{
    private const VERSION = '1.1.5';

    public function __construct()
    {
        $this->define_constants();
        $this->load_dependencies();
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

    private function load_dependencies()
    {
        // If not using Composer autoload, require files here
        // require_once ARTPULSE_PLUGIN_DIR . 'src/Ajax/FrontendFilterHandler.php';
        // etc.
    }

    private function register_hooks()
    {
        register_activation_hook( ARTPULSE_PLUGIN_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( ARTPULSE_PLUGIN_FILE, [ $this, 'deactivate' ] );

        add_action( 'init', [ $this, 'register_core_modules' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );
        add_action( 'rest_api_init', [ \ArtPulse\Community\NotificationRestController::class, 'register' ] );
    }

    public function activate()
    {
        $db_version_option = 'artpulse_db_version';

        if ( false === get_option( 'artpulse_settings' ) ) {
            add_option( 'artpulse_settings', [ 'version' => self::VERSION ] );
        } else {
            $settings = get_option( 'artpulse_settings' );
            $settings['version'] = self::VERSION;
            update_option( 'artpulse_settings', $settings );
        }

        $stored_db_version = get_option( $db_version_option );
        if ( $stored_db_version !== self::VERSION ) {
            \ArtPulse\Community\FavoritesManager::install_favorites_table();
            \ArtPulse\Community\ProfileLinkRequestManager::install_link_request_table();
            \ArtPulse\Community\FollowManager::install_follows_table();
            \ArtPulse\Community\NotificationManager::install_notifications_table();
            update_option( $db_version_option, self::VERSION );
        }

        \ArtPulse\Core\PostTypeRegistrar::register();
        flush_rewrite_rules();

        $roles = [ 'administrator', 'editor' ];
        $caps  = [
            // Your custom capabilities here...
        ];
        foreach ( $roles as $role_name ) {
            if ( $role = get_role( $role_name ) ) {
                foreach ( $caps as $cap ) {
                    $role->add_cap( $cap );
                }
            }
        }

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
        \ArtPulse\Core\SettingsPage::register();
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
        \ArtPulse\Admin\MetaBoxesRelationship::register();
        \ArtPulse\Blocks\RelatedItemsSelectorBlock::register();

        \ArtPulse\Admin\AdminColumnsArtist::register();
        \ArtPulse\Admin\AdminColumnsArtwork::register();
        \ArtPulse\Admin\AdminColumnsEvent::register();
        \ArtPulse\Admin\AdminColumnsOrganisation::register();

        \ArtPulse\Taxonomies\TaxonomiesRegistrar::register();

        if ( class_exists( '\ArtPulse\Ajax\FrontendFilterHandler' ) ) {
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
        wp_localize_script( 'ap-notifications-js', 'APNotifications', [
            'apiRoot' => esc_url_raw( rest_url() ),
            'nonce'   => wp_create_nonce( 'wp_rest' )
        ] );

        wp_localize_script(
            'ap-membership-account-js',
            'ArtPulseApi',
            [
                'root'        => esc_url_raw( rest_url() ),
                'nonce'       => wp_create_nonce( 'wp_rest' ),
                'i18n'        => [
                    'levelLabel'    => __( 'Membership Level', 'artpulse' ),
                    'expiresLabel'  => __( 'Expires', 'artpulse' ),
                    'errorFetching' => __( 'Unable to fetch account data. Please try again later.', 'artpulse' ),
                ],
            ]
        );
    }
}
