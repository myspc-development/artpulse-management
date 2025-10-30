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

        \ArtPulse\Core\Capabilities::register();
        \ArtPulse\Core\LoginRedirector::register();
        \ArtPulse\Core\TitleTools::register();
        \ArtPulse\Core\Rewrites::register();
        \ArtPulse\Core\ProfileState::register();

        add_action( 'save_post_artpulse_artist', [ \ArtPulse\Core\ProfileState::class, 'purge_by_post_id' ], 10, 1 );
        add_action( 'save_post_artpulse_org', [ \ArtPulse\Core\ProfileState::class, 'purge_by_post_id' ], 10, 1 );
        add_action( 'set_post_thumbnail', [ \ArtPulse\Core\ProfileState::class, 'purge_by_post_id' ], 10, 1 );
        add_action( 'delete_post_thumbnail', [ \ArtPulse\Core\ProfileState::class, 'purge_by_post_id' ], 10, 1 );
        add_action(
            'transition_post_status',
            function ( $new_status, $old_status, $post ) {
                if ( $post instanceof \WP_Post && in_array( $post->post_type, [ 'artpulse_artist', 'artpulse_org' ], true ) ) {
                    \ArtPulse\Core\ProfileState::purge_by_post_id( (int) $post->ID );
                }
            },
            10,
            3
        );
        add_action(
            'updated_post_meta',
            function ( $meta_id, $post_id, $meta_key ) {
                if ( in_array( $meta_key, [ 'ap_visibility', 'ap_tagline', 'ap_gallery', 'ap_socials', 'ap_website_url' ], true ) ) {
                    \ArtPulse\Core\ProfileState::purge_by_post_id( (int) $post_id );
                }
            },
            10,
            3
        );

        add_action( 'admin_init', [ $this, 'maybe_retry_letter_index' ] );
        add_action( 'admin_notices', [ $this, 'maybe_display_letter_index_notice' ] );

        // Register core modules and front-end submission forms
        add_action( 'init',               [ $this, 'register_core_modules' ] );
        add_action( 'init',               [ $this, 'load_textdomain' ] );
        add_action( 'init',               [ \ArtPulse\Frontend\SubmissionForms::class, 'register' ] );
        add_action( 'init',               [ \ArtPulse\Core\RoleDashboards::class, 'register' ] );
        add_action( 'init',               [ \ArtPulse\Frontend\MemberDashboard::class, 'register' ] );
        add_action( 'init',               [ \ArtPulse\Frontend\ArtistRequestStatusRoute::class, 'register' ] );
        if ( get_option( 'ap_enable_org_builder', true ) ) {
            add_action( 'init', [ \ArtPulse\Frontend\OrgBuilderShortcode::class, 'register' ] );
        }

        if ( get_option( 'ap_enable_artist_builder', true ) ) {
            add_action( 'init', [ \ArtPulse\Frontend\ArtistBuilderShortcode::class, 'register' ] );
        }
        \ArtPulse\Frontend\Shared\PortfolioMediaGuard::register();
        \ArtPulse\Core\RoleUpgradeManager::register();
        \ArtPulse\Core\RoleSetup::register();
        add_action( 'init',               [ \ArtPulse\Core\RoleSetup::class, 'maybe_upgrade' ] );
        add_action( 'init',               [ \ArtPulse\Admin\UpgradeReviewsController::class, 'register' ] );
        \ArtPulse\Admin\Settings::register();
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );
        add_action( 'after_setup_theme',  [ $this, 'register_image_sizes' ] );
        add_action( 'after_setup_theme',  [ \ArtPulse\Frontend\Salient\ImageFallback::class, 'register' ] );

        // REST API endpoints
        add_action( 'rest_api_init', [ \ArtPulse\Community\FavoritesRestController::class, 'register' ] );
        add_action( 'rest_api_init', [ \ArtPulse\Community\FollowRestController::class, 'register' ] );
        add_action( 'rest_api_init', [ \ArtPulse\Community\NotificationRestController::class, 'register' ] );
        add_action( 'rest_api_init', [ \ArtPulse\Rest\SubmissionRestController::class, 'register' ] );
        add_action( 'rest_api_init', [ \ArtPulse\Rest\UpgradeReviewsController::class, 'register' ] );
        add_action( 'rest_api_init', [ \ArtPulse\Rest\PortfolioController::class, 'register' ] );
        add_action( 'rest_api_init', [ \ArtPulse\Rest\NonceController::class, 'register' ] );
        add_action( 'rest_api_init', [ \ArtPulse\Mobile\MobileRestController::class, 'register' ] );

        \ArtPulse\Mobile\Cors::register();
        \ArtPulse\Mobile\RequestMetrics::register();
        \ArtPulse\Mobile\RefreshTokens::register_hooks();
        \ArtPulse\Mobile\JWT::boot();
        \ArtPulse\Mobile\EventGeo::boot();
        \ArtPulse\Mobile\NotificationPipeline::boot();
    }

    public function activate()
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        \artpulse_create_custom_table();
        Activator::activate();

        $index_result = DatabaseUtils::add_letter_meta_index();
        if ( DatabaseUtils::INDEX_RESULT_FAILED === $index_result ) {
            update_option( 'artpulse_letter_index_error', DatabaseUtils::get_last_error() );
        } elseif ( DatabaseUtils::INDEX_RESULT_SUCCESS === $index_result ) {
            delete_option( 'artpulse_letter_index_error' );
        }

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
         // \ArtPulse\Community\ProfileLinkRequestManager::install_link_request_table();
            \ArtPulse\Community\FollowManager::install_follows_table();
            \ArtPulse\Community\NotificationManager::install_notifications_table();
            \ArtPulse\Mobile\EventInteractions::install_tables();
            \ArtPulse\Mobile\EventGeo::install_table();
            update_option( $db_version_option, self::VERSION );
        }

        // Register CPTs and flush rewrite rules
        \ArtPulse\Core\PostTypeRegistrar::register();
        \ArtPulse\Core\Rewrites::add_rewrite_rules();
        \ArtPulse\Core\Rewrites::register_directory_sitemap_route();
        \ArtPulse\Frontend\ArtistRequestStatusRoute::register();
        flush_rewrite_rules();

        // Setup roles and capabilities
        require_once ARTPULSE_PLUGIN_DIR . 'src/Core/RoleSetup.php';
        \ArtPulse\Core\RoleSetup::install();

        // Schedule daily expiration check
        if ( ! wp_next_scheduled( 'ap_daily_expiry_check' ) ) {
            wp_schedule_event( time(), 'daily', 'ap_daily_expiry_check' );
        }

        if ( ! wp_next_scheduled( 'ap_mobile_purge_inactive_sessions' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ap_mobile_purge_inactive_sessions' );
        }

        if ( ! wp_next_scheduled( 'ap_mobile_purge_metrics' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ap_mobile_purge_metrics' );
        }
    }

    public function maybe_retry_letter_index()
    {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( empty( $_GET['ap_retry_letter_index'] ) ) {
            return;
        }

        check_admin_referer( 'ap-retry-letter-index' );

        $result = DatabaseUtils::add_letter_meta_index();

        if ( DatabaseUtils::INDEX_RESULT_FAILED === $result ) {
            update_option( 'artpulse_letter_index_error', DatabaseUtils::get_last_error() );
        } elseif ( DatabaseUtils::INDEX_RESULT_SUCCESS === $result ) {
            delete_option( 'artpulse_letter_index_error' );
        }

        $redirect_url = remove_query_arg(
            [ 'ap_retry_letter_index', '_wpnonce' ],
            add_query_arg( 'ap_letter_index_status', $result )
        );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    public function maybe_display_letter_index_notice()
    {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $status = isset( $_GET['ap_letter_index_status'] ) ? sanitize_key( wp_unslash( $_GET['ap_letter_index_status'] ) ) : '';

        if ( DatabaseUtils::INDEX_RESULT_SUCCESS === $status ) {
            printf(
                '<div class="notice notice-success"><p>%s</p></div>',
                esc_html__( 'ArtPulse letter index created successfully.', 'artpulse-management' )
            );
        } elseif ( DatabaseUtils::INDEX_RESULT_UNSUPPORTED === $status ) {
            printf(
                '<div class="notice notice-info"><p>%s</p></div>',
                esc_html__( 'The database server does not support the optional ArtPulse letter index.', 'artpulse-management' )
            );
        } elseif ( DatabaseUtils::INDEX_RESULT_FAILED === $status ) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__( 'Failed to create the ArtPulse letter index. Please review the database logs.', 'artpulse-management' )
            );
        }

        $error_message = get_option( 'artpulse_letter_index_error' );

        if ( empty( $error_message ) ) {
            return;
        }

        $message  = esc_html__( 'ArtPulse could not create the optional letter index used for directory filtering.', 'artpulse-management' );
        $message .= ' '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Added below.
        $message .= esc_html__( 'Database error:', 'artpulse-management' ) . ' ' . esc_html( $error_message );

        $retry_button = '';

        if ( DatabaseUtils::supports_letter_meta_index() ) {
            $retry_url = wp_nonce_url( add_query_arg( 'ap_retry_letter_index', 1 ), 'ap-retry-letter-index' );
            $retry_button = sprintf(
                ' <a href="%s" class="button button-secondary">%s</a>',
                esc_url( $retry_url ),
                esc_html__( 'Retry index creation', 'artpulse-management' )
            );
        }

        printf(
            '<div class="notice notice-warning"><p>%s%s</p></div>',
            $message,
            $retry_button
        );
    }

    public function deactivate()
    {
        flush_rewrite_rules();
        wp_clear_scheduled_hook( 'ap_daily_expiry_check' );
        wp_clear_scheduled_hook( 'ap_mobile_purge_inactive_sessions' );
        wp_clear_scheduled_hook( 'ap_mobile_purge_metrics' );
    }

    public function register_image_sizes(): void
    {
        add_image_size( 'ap-grid', 800, 600, true );
    }

    public function load_textdomain()
    {
        load_plugin_textdomain( 'artpulse-management', false, dirname( plugin_basename( ARTPULSE_PLUGIN_FILE ) ) . '/languages' );
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
        \ArtPulse\Frontend\OrganizationRegistrationShortcode::register();
        \ArtPulse\Frontend\EditEventShortcode::register();
        \ArtPulse\Frontend\OrganizationDashboardShortcode::register();
        \ArtPulse\Frontend\OrganizationEventForm::register();
        \ArtPulse\Frontend\UserProfileShortcode::register();
        \ArtPulse\Frontend\ProfileEditShortcode::register();
        \ArtPulse\Frontend\ArtistsDirectory::register();
        \ArtPulse\Frontend\OrgsDirectory::register();
        \ArtPulse\Frontend\EventsCalendar::register();
        \ArtPulse\Frontend\TemplateLoader::register();
        \ArtPulse\Admin\MetaBoxesRelationship::register();
        \ArtPulse\Blocks\RelatedItemsSelectorBlock::register();
        \ArtPulse\Admin\ApprovalManager::register();
        \ArtPulse\Admin\EventApprovals::register();
        \ArtPulse\Rest\RestRoutes::register();
        \ArtPulse\Rest\EventsController::boot();
        \ArtPulse\Rest\ArtistRestController::register();
        \ArtPulse\Rest\OrganizationRestController::register();

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
            'ap-social-js',
            plugins_url( 'assets/js/ap-social.js', ARTPULSE_PLUGIN_FILE ),
            [],
            '1.0.0',
            true
        );
        wp_localize_script(
            'ap-social-js',
            'APSocial',
            [
                'root'     => esc_url_raw( rest_url() ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'messages' => [
                    'favoriteError' => __( 'Unable to update favorite. Please try again.', 'artpulse' ),
                    'followError'   => __( 'Unable to update follow. Please try again.', 'artpulse' ),
                ],
            ]
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

        $this->maybe_enqueue_member_dashboard_assets();


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

    private function maybe_enqueue_member_dashboard_assets(): void
    {
        if ( ! $this->page_contains_member_dashboard_shortcode() ) {
            return;
        }

        $script_path = plugin_dir_path( ARTPULSE_PLUGIN_FILE ) . 'assets/js/ap-dashboards.js';
        $version     = file_exists( $script_path ) ? (string) filemtime( $script_path ) : self::VERSION;

        wp_register_script(
            'ap-dashboards-js',
            plugins_url( 'assets/js/ap-dashboards.js', ARTPULSE_PLUGIN_FILE ),
            [ 'wp-api-fetch', 'wp-dom-ready', 'ap-social-js' ],
            $version,
            true
        );

        \ArtPulse\Core\RoleDashboards::enqueueAssets();
    }

    private function page_contains_member_dashboard_shortcode(): bool
    {
        if ( ! is_singular() ) {
            return false;
        }

        $post = get_post();

        if ( ! $post instanceof \WP_Post ) {
            return false;
        }

        if ( has_shortcode( $post->post_content, 'ap_member_dashboard' ) ) {
            return true;
        }

        if ( function_exists( 'has_block' ) && has_block( 'core/shortcode', $post ) ) {
            $blocks = parse_blocks( $post->post_content );

            foreach ( $blocks as $block ) {
                if ( ( $block['blockName'] ?? '' ) !== 'core/shortcode' ) {
                    continue;
                }

                $inner_content = $block['innerContent'] ?? [];

                foreach ( $inner_content as $content ) {
                    if ( is_string( $content ) && false !== strpos( $content, '[ap_member_dashboard' ) ) {
                        return true;
                    }
                }

                $inner_html = $block['innerHTML'] ?? '';

                if ( is_string( $inner_html ) && false !== strpos( $inner_html, '[ap_member_dashboard' ) ) {
                    return true;
                }
            }
        }

        return false;
    }
}
