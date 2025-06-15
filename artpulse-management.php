<?php
/**
 * Plugin Name: ArtPulse Management
 * Description: A feature-rich directory plugin for managing events, organizations, artists, artworks, dashboards, reviews, mobile API, roles, and data exports.
 * Version: 3.7.7
 * Author: ArtPulse
 * Text Domain: artpulse-management
 */

namespace EAD;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define the plugin directory path and URL
if ( ! defined( 'EAD_PLUGIN_DIR_PATH' ) ) {
    define( 'EAD_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'EAD_PLUGIN_DIR_URL' ) ) {
    define( 'EAD_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'EAD_PLUGIN_VERSION' ) ) {
    define( 'EAD_PLUGIN_VERSION', '3.7.7' );
}

if ( ! defined( 'EAD_MANAGEMENT_VERSION' ) ) {
    define( 'EAD_MANAGEMENT_VERSION', EAD_PLUGIN_VERSION );
}

/**
 * Ensure the RolesManager class is loaded and the legacy alias exists.
 */
function ead_load_roles_manager() {
    $roles_manager_path = EAD_PLUGIN_DIR_PATH . 'src/RolesManager.php';
    if ( file_exists( $roles_manager_path ) ) {
        require_once $roles_manager_path;
    }

    if ( ! class_exists( '\\EAD\\RolesManager' ) && class_exists( '\\EAD\\Roles\\RolesManager' ) ) {
        class_alias( '\\EAD\\Roles\\RolesManager', '\\EAD\\RolesManager' );
    }
}

// Register custom table alias on $wpdb for easier access
global $wpdb;
$wpdb->ead_rsvps = $wpdb->prefix . 'ead_rsvps';

// Include Composer autoloader if available
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Include and register the autoloader
if ( file_exists( EAD_PLUGIN_DIR_PATH . 'src/Autoloader.php' ) ) {
    require_once EAD_PLUGIN_DIR_PATH . 'src/Autoloader.php';
    Autoloader::register();

    // Load the roles manager and alias for backward compatibility
    ead_load_roles_manager();

    // Also load the roles manager early in the plugins_loaded phase
    add_action( 'plugins_loaded', __NAMESPACE__ . '\\ead_load_roles_manager', 0 );
} else {
    // Fallback or error handling if autoloader is missing
    add_action(
        'admin_notices',
        function () {
            echo '<div class="notice notice-error"><p>';
            esc_html_e( 'ArtPulse Management: Autoloader.php is missing. The plugin may not function correctly.', 'artpulse-management' );
            echo '</p></div>';
        }
    );
    return; // Stop further execution if autoloader is critical
}


// Load role-based helpers
$redirect_by_role_file = EAD_PLUGIN_DIR_PATH . 'redirect_user_by_role.php';
if ( file_exists( $redirect_by_role_file ) ) {
    require_once $redirect_by_role_file;
}

$user_profile_tab_file = EAD_PLUGIN_DIR_PATH . 'user_profile_tab.php';
if ( file_exists( $user_profile_tab_file ) ) {
    require_once $user_profile_tab_file;
}

// Membership overview functionality removed.
// $membership_overview_file = EAD_PLUGIN_DIR_PATH . 'admin_membership_overview.php';
// if ( file_exists( $membership_overview_file ) ) {
//     require_once $membership_overview_file;
// }


$membership_core_file = EAD_PLUGIN_DIR_PATH . 'membership-core.php';
// Legacy membership functionality has been replaced by classes under src/.

$functions_file = EAD_PLUGIN_DIR_PATH . 'functions.php';
if ( file_exists( $functions_file ) ) {
    require_once $functions_file;
}


// Load user profile enhancements
require_once plugin_dir_path(__FILE__) . 'users-profile.php';

// Load organization management functionality
require_once plugin_dir_path(__FILE__) . 'organizations.php';

// Load basic events functionality
require_once plugin_dir_path(__FILE__) . 'events.php';

// Load artist management functionality
require_once plugin_dir_path(__FILE__) . 'artists.php';

// Load artwork management functionality
require_once plugin_dir_path(__FILE__) . 'artworks.php';


/**
 * Copies plugin templates to the child theme directory on activation,
 * only if they don't already exist in the child theme.
 */
function artpulse_copy_templates_to_child_theme() {
    $child_theme_dir    = get_stylesheet_directory(); // child theme directory
    $plugin_templates_dir = plugin_dir_path( __FILE__ ) . 'templates/';
    $templates          = [
        'archive-ead_artwork.php',
        'archive-ead_event.php',
        'archive-ead_artist.php',
        'archive-ead_organization.php',
        'single-ead_artwork.php',
        'single-ead_event.php',
        'single-ead_artist.php',
        'single-ead_organization.php',
    ];

    if ( ! is_dir( $plugin_templates_dir ) ) {
        return;
    }

    foreach ( $templates as $template_filename ) {
        $source_file      = $plugin_templates_dir . $template_filename;
        $destination_file = $child_theme_dir . '/' . $template_filename;

        if ( file_exists( $source_file ) && ! file_exists( $destination_file ) ) {
            if ( is_writable( $child_theme_dir ) ) {
                copy( $source_file, $destination_file );
            }
        }
    }
}

/**
 * Registers default event types on plugin activation.
 */
function ead_register_default_event_types() {
    $event_types = [
        'Exhibition',
        'Workshop',
        'Art Talk',
        'Walkabout',
        'Performance',
        'Art Auction',
    ];

    foreach ( $event_types as $type ) {
        $slug = sanitize_title( $type );

        if ( ! term_exists( $slug, 'ead_event_type' ) ) {
            wp_insert_term( $type, 'ead_event_type', [ 'slug' => $slug ] );
        }
    }
}

/**
 * Creates the RSVP table on plugin activation.
 */
function ead_create_rsvp_table() {
    global $wpdb;

    $table_name      = $wpdb->prefix . 'ead_rsvps';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        event_id BIGINT(20) UNSIGNED NOT NULL,
        rsvp_email VARCHAR(255) NOT NULL,
        rsvp_date DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY event_id (event_id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

/**
 * Migrates legacy organization logo meta keys on plugin activation.
 */
function ead_migrate_org_logo_meta() {
    $query = new \WP_Query(
        [
            'post_type'      => 'ead_organization',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'OR',
                [ 'key' => 'ead_organisation_logo_id', 'compare' => 'EXISTS' ],
                [ 'key' => 'organisation_logo', 'compare' => 'EXISTS' ],
            ],
        ]
    );

    foreach ( $query->posts as $post_id ) {
        $logo_id = get_post_meta( $post_id, 'ead_org_logo_id', true );

        if ( ! $logo_id ) {
            $old_id = get_post_meta( $post_id, 'ead_organisation_logo_id', true );
            if ( $old_id ) {
                update_post_meta( $post_id, 'ead_org_logo_id', $old_id );
                delete_post_meta( $post_id, 'ead_organisation_logo_id' );
                $logo_id = $old_id;
            }
        }

        if ( ! $logo_id ) {
            $old_id = get_post_meta( $post_id, 'organisation_logo', true );
            if ( $old_id ) {
                update_post_meta( $post_id, 'ead_org_logo_id', $old_id );
                delete_post_meta( $post_id, 'organisation_logo' );
            }
        }
    }
}

register_activation_hook( __FILE__, 'EAD\artpulse_copy_templates_to_child_theme' );
register_activation_hook( __FILE__, 'EAD\ead_register_default_event_types' ); // ADDED: Hook for event type registration
register_activation_hook( __FILE__, 'EAD\ead_create_rsvp_table' );
register_activation_hook( __FILE__, 'EAD\ead_migrate_org_logo_meta' );
register_activation_hook( __FILE__, [ \EAD\Roles\RolesManager::class, 'register_membership_roles' ] );
register_deactivation_hook( __FILE__, [ \EAD\Roles\RolesManager::class, 'remove_membership_roles' ] );

/**
 * Daily cron task to remove expired memberships.
 */
function ead_check_membership_expiry() {
    $users = get_users([
        'meta_query' => [
            [
                'key'     => 'membership_expires',
                'value'   => current_time('mysql'),
                'compare' => '<=',
                'type'    => 'DATETIME',
            ],
        ],
    ]);

    foreach ( $users as $user ) {
        delete_user_meta( $user->ID, 'is_member' );
        delete_user_meta( $user->ID, 'membership_level' );
        delete_user_meta( $user->ID, 'membership_expires' );
        $user->set_role( 'member_registered' );
    }
}

register_activation_hook( __FILE__, function() {
    if ( ! wp_next_scheduled( 'ead_membership_expiry_check' ) ) {
        wp_schedule_event( time(), 'daily', 'ead_membership_expiry_check' );
    }
} );
add_action( 'ead_membership_expiry_check', __NAMESPACE__ . '\\ead_check_membership_expiry' );

register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'ead_membership_expiry_check' );
} );

/**
 * Registers CPTs/endpoints and flushes rewrites on activation.
 */
function ead_flush_rewrites() {
    // Ensure post types and taxonomies are registered.
    if ( class_exists( Plugin::class ) ) {
        Plugin::register_post_types_and_taxonomies();
    }

    // Register custom endpoints needed for the plugin.
    add_rewrite_endpoint( 'organization-confirmation', EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'membership-confirmation', EP_ROOT | EP_PAGES );

    flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'EAD\ead_flush_rewrites' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

// --- Consolidated Use Statements (Grouped for better readability) ---
// Admin
use EAD\Admin\Menu;
use EAD\Admin\SettingsPage;
use EAD\Admin\CSVImportExport;
use EAD\Admin\CSVImportEnqueue;
use EAD\Admin\MetaBoxesEvent;
use EAD\Admin\MetaBoxesOrganisation;
use EAD\Admin\MetaBoxesAddress;
use EAD\Admin\MetaBoxesArtist;
use EAD\Admin\MetaBoxesArtwork;
use EAD\Admin\PendingEvents;
use EAD\Admin\PendingOrganizations;
use EAD\Admin\PendingArtists;
use EAD\Admin\PendingArtworks;
use EAD\Admin\ReviewsModerator;
use EAD\Admin\NotificationSettingsAdmin;
use EAD\Admin\ManageMembers;
use EAD\Admin\AdminEventForm;
use EAD\Admin\Geocoder;
use EAD\Admin\AdminRedirects;

// Shortcodes
use EAD\Shortcodes\EventsListShortcode;
use EAD\Shortcodes\SubmitEventForm;
use EAD\Shortcodes\OrganizerDashboard;
use EAD\Shortcodes\EditEventForm;
use EAD\Shortcodes\OrganizationForm;
use EAD\Shortcodes\OrganizationList;
use EAD\Shortcodes\OrgReviewForm;
use EAD\Shortcodes\OrganizationRegistrationForm;
use EAD\Shortcodes\ArtistRegistrationForm;
use EAD\Shortcodes\FavoritesList;
use EAD\Shortcodes\MembershipSignupForm;
use EAD\Shortcodes\EventCalendar;
add_action( 'init', function() {
    \EAD\Shortcodes\ArtworkSubmissionForm::register();
});

// REST API
use EAD\Rest\EventsEndpoint;
use EAD\Rest\ArtistDashboardEndpoint;
use EAD\Rest\OrganizationDashboardEndpoint;
use EAD\Rest\ModerationEndpoint;
use EAD\Rest\Like_Endpoint;
use EAD\Rest\JwtAuthEndpoint;
use EAD\Rest\MembershipEndpoint;

// Dashboards
use EAD\Dashboard\ArtistDashboard;
use EAD\Dashboard\OrganizationDashboard;
use EAD\Dashboard\UserDashboard;

// Other Core Features
use EAD\Reviews\Reviews;
use EAD\Export\DataExport;
use EAD\Analytics\ListingAnalytics;
use EAD\Integration\WPBakery;
use EAD\Integration\WooCommercePayments;
use EAD\Notifications\PushNotificationService;
use EAD\Roles\RolesManager;


class Plugin {

    const VERSION     = EAD_PLUGIN_VERSION;
    const TEXT_DOMAIN = 'artpulse-management';

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', [ self::class, 'load_textdomain_static' ] );
        add_action( 'init', [ self::class, 'register_post_types_and_taxonomies' ] );
        add_action( 'init', [ $this, 'register_core_modules' ] );
        add_action( 'init', [ $this, 'register_shortcodes' ] );
        add_action( 'rest_api_init', [ self::class, 'register_rest_endpoints' ] ); // **FIXED: Changed `$this` to `self::class`**
        $this->register_phase3_features();
        $this->register_integrations();
        $this->enqueue_assets();
        $this->setup_admin_features();
        $this->register_ajax_handlers();

        // Check for registration pages during admin init
        add_action( 'admin_init', [ self::class, 'check_registration_pages' ] );

        // Hook for organization approval notification
        add_action( 'transition_post_status', [ self::class, 'notify_user_on_organization_approval' ], 10, 3 );

        // Hook for event approval notification
        add_action( 'transition_post_status', [ self::class, 'notify_organizer_on_event_approval' ], 10, 3 );

        // Hook for notifying admin of new event submissions
        add_action( 'transition_post_status', [ self::class, 'notify_admin_on_event_pending' ], 10, 3 );

        // Hooks for artwork submission notifications
        add_action( 'transition_post_status', [ self::class, 'notify_artist_on_artwork_approval' ], 10, 3 );
        add_action( 'transition_post_status', [ self::class, 'notify_admin_on_artwork_pending' ], 10, 3 );

        // Push notifications on updates
        add_action( 'save_post_ead_event', [ self::class, 'notify_event_updated' ], 10, 3 );
        add_action( 'save_post_ead_organization', [ self::class, 'notify_organization_updated' ], 10, 3 );

        // CSV Export Hook
        add_action( 'admin_post_ead_export_pending_events_csv', [ self::class, 'process_export_pending_csv' ] );
    }

    public static function init() {
        // Load AJAX handlers so actions are registered when the plugin starts.
        $ajax_file = EAD_PLUGIN_DIR_PATH . 'includes/ajax-handlers.php';
        if ( file_exists( $ajax_file ) ) {
            require_once $ajax_file;
        }

        self::get_instance();
    }

    public static function load_textdomain_static() {
        load_plugin_textdomain(
            self::TEXT_DOMAIN,
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages'
        );
    }

    public static function register_post_types_and_taxonomies() {
        $cpt_support     = [ 'title', 'editor', 'thumbnail', 'custom-fields', 'author' ];
        $default_labels_fn = function ( $singular, $plural ) {
            return [
                'name'               => $plural,
                'singular_name'      => $singular,
                'add_new'            => sprintf( esc_html__( 'Add New %s', self::TEXT_DOMAIN ), $singular ),
                'add_new_item'       => sprintf( esc_html__( 'Add New %s', self::TEXT_DOMAIN ), $singular ),
                'edit_item'          => sprintf( esc_html__( 'Edit %s', self::TEXT_DOMAIN ), $singular ),
                'new_item'           => sprintf( esc_html__( 'New %s', self::TEXT_DOMAIN ), $singular ),
                'view_item'          => sprintf( esc_html__( 'View %s', self::TEXT_DOMAIN ), $singular ),
                'search_items'       => sprintf( esc_html__( 'Search %s', self::TEXT_DOMAIN ), $plural ),
                'not_found'          => sprintf( esc_html__( 'No %s found', self::TEXT_DOMAIN ), strtolower( $plural ) ),
                'not_found_in_trash' => sprintf( esc_html__( 'No %s found in Trash', self::TEXT_DOMAIN ), strtolower( $plural ) ),
                'parent_item_colon'  => sprintf( esc_html__( 'Parent %s:', self::TEXT_DOMAIN ), $singular ),
                'all_items'          => sprintf( esc_html__( 'All %s', self::TEXT_DOMAIN ), $plural ),
                'archives'           => sprintf( esc_html__( '%s Archives', self::TEXT_DOMAIN ), $singular ),
                'attributes'         => sprintf( esc_html__( '%s Attributes', self::TEXT_DOMAIN ), $singular ),
                'insert_into_item'   => sprintf( esc_html__( 'Insert into %s', self::TEXT_DOMAIN ), strtolower( $singular ) ),
                'uploaded_to_this_item' => sprintf( esc_html__( 'Uploaded to this %s', self::TEXT_DOMAIN ), strtolower( $singular ) ),
                'filter_items_list'  => sprintf( esc_html__( 'Filter %s list', self::TEXT_DOMAIN ), strtolower( $plural ) ),
                'items_list_navigation' => sprintf( esc_html__( '%s list navigation', self::TEXT_DOMAIN ), $plural ),
                'items_list'         => sprintf( esc_html__( '%s list', self::TEXT_DOMAIN ), $plural ),
                'menu_name'          => $plural,
                'name_admin_bar'     => $singular,
            ];
        };

        register_post_type(
            'ead_event',
            [
                'labels'          => $default_labels_fn( __( 'Event', self::TEXT_DOMAIN ), __( 'Events', self::TEXT_DOMAIN ) ),
                'public'          => true,
                'has_archive'     => true,
                'menu_icon'       => 'dashicons-calendar-alt',
                'show_in_menu'    => true,
                'menu_position'   => 30,
                'supports'        => $cpt_support,
                'rewrite'         => [ 'slug' => 'events' ],
                'show_in_rest'    => true,
                'capability_type' => 'post',
            ]
        );

        register_post_type(
            'ead_organization',
            [
                'labels'          => $default_labels_fn( __( 'Organization', self::TEXT_DOMAIN ), __( 'Organizations', self::TEXT_DOMAIN ) ),
                'public'          => true,
                'has_archive'     => true,
                'menu_icon'       => 'dashicons-building',
                'show_in_menu'    => true,
                'menu_position'   => 29,
                'supports'        => $cpt_support,
                'rewrite'         => [ 'slug' => 'organizations' ],
                'show_in_rest'    => true,
                'capability_type' => 'post',
            ]
        );

        register_post_type(
            'ead_artist',
            [
                'labels'          => $default_labels_fn( __( 'Artist', self::TEXT_DOMAIN ), __( 'Artists', self::TEXT_DOMAIN ) ),
                'public'          => true,
                'has_archive'     => true,
                'menu_icon'       => 'dashicons-art',
                'show_in_menu'    => true,
                'menu_position'   => 31,
                'supports'        => $cpt_support,
                'rewrite'         => [ 'slug' => 'artists' ],
                'show_in_rest'    => true,
                'capability_type' => 'post',
            ]
        );

        $artwork_labels = $default_labels_fn( __( 'Artwork', self::TEXT_DOMAIN ), __( 'Artworks', self::TEXT_DOMAIN ) );
        $artwork_labels['add_new_item'] = __( 'Add Artwork', self::TEXT_DOMAIN );
        $artwork_labels['edit_item']    = __( 'Edit Artwork', self::TEXT_DOMAIN );
        $artwork_labels['all_items']    = __( 'All Submissions', self::TEXT_DOMAIN );

        register_post_type(
            'ead_artwork',
            [
                'label'          => __( 'User Artwork', self::TEXT_DOMAIN ),
                'labels'         => $artwork_labels,
                'public'         => false,
                'show_ui'        => true,
                'has_archive'    => true,
                'menu_icon'      => 'dashicons-format-image',
                'show_in_menu'   => true,
                'menu_position'  => 32,
                'supports'       => [ 'title', 'thumbnail', 'author' ],
                'rewrite'        => [ 'slug' => 'artworks' ],
                'show_in_rest'   => true,
                'capability_type'=> 'post',
            ]
        );

        register_post_type(
            'ead_org_review',
            [
                'labels'          => $default_labels_fn( __( 'Organization Review', self::TEXT_DOMAIN ), __( 'Organization Reviews', self::TEXT_DOMAIN ) ),
                'public'          => false,
                'show_ui'         => true,
                'menu_icon'       => 'dashicons-star-half',
                'supports'        => [ 'title', 'editor', 'author', 'custom-fields' ],
                'show_in_menu'    => 'edit.php?post_type=ead_organization',
                'capability_type' => 'post',
            ]
        );

        register_post_type(
            'event_rsvp',
            [
                'labels'          => $default_labels_fn( __( 'RSVP', self::TEXT_DOMAIN ), __( 'RSVPs', self::TEXT_DOMAIN ) ),
                'public'          => false,
                'show_ui'         => true,
                'show_in_menu'    => 'edit.php?post_type=ead_event',
                'supports'        => [ 'title', 'custom-fields' ],
                'capability_type' => 'event_rsvp',
                'map_meta_cap'    => true,
                'capabilities'    => [
                    'edit_posts'         => 'ead_manage_rsvps',
                    'edit_others_posts'  => 'ead_manage_rsvps',
                    'publish_posts'      => 'ead_manage_rsvps',
                    'read_private_posts' => 'ead_manage_rsvps',
                    'delete_posts'       => 'ead_manage_rsvps',
                ],
            ]
        );

        register_post_type(
            'ead_booking',
            [
                'labels'       => $default_labels_fn( __( 'Booking', self::TEXT_DOMAIN ), __( 'Bookings', self::TEXT_DOMAIN ) ),
                'public'       => false,
                'show_ui'      => true,
                'show_in_menu' => true,
                'menu_position' => 33,
                'supports'     => [ 'title', 'editor', 'custom-fields', 'author' ],
                'show_in_rest' => true,
                'capability_type' => 'post',
            ]
        );

        register_post_type(
            'ead_notification',
            [
                'labels'       => $default_labels_fn( __( 'Notification', self::TEXT_DOMAIN ), __( 'Notifications', self::TEXT_DOMAIN ) ),
                'public'       => false,
                'show_ui'      => true,
                'supports'     => [ 'title', 'editor' ],
                'capability_type' => 'post',
                'has_archive'  => false,
            ]
        );

        register_taxonomy(
            'ead_event_type',
            'ead_event',
            [
                'labels'            => $default_labels_fn( __( 'Event Type', self::TEXT_DOMAIN ), __( 'Event Types', self::TEXT_DOMAIN ) ),
                'hierarchical'      => true,
                'public'            => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'query_var'         => true,
                'rewrite'           => [ 'slug' => 'event-type' ],
                'show_in_rest'      => true,
            ]
        );

        register_taxonomy(
            'ead_event_category',
            'ead_event',
            [
                'labels'           => $default_labels_fn( __( 'Event Category', self::TEXT_DOMAIN ), __( 'Event Categories', self::TEXT_DOMAIN ) ),
                'public'           => true,
                'hierarchical'     => false,
                'show_admin_column'=> true,
                'show_in_rest'     => true,
                'rewrite'          => [ 'slug' => 'event-category' ],
            ]
        );
    }

    public function register_core_modules() {
        if ( is_admin() ) {
            Menu::register_menus();
            SettingsPage::register();
            CSVImportExport::register();
            CSVImportEnqueue::register();
            ReviewsModerator::register();
            NotificationSettingsAdmin::register();
            PendingEvents::register();
            PendingOrganizations::register();
            PendingArtists::register();
            PendingArtworks::register();

            // Ensure the ManageMembers class is loaded before registering
            // hooks in case autoloading fails or the file is outdated.
            $manage_members_path = EAD_PLUGIN_DIR_PATH . 'src/Admin/ManageMembers.php';
            if ( file_exists( $manage_members_path ) ) {
                require_once $manage_members_path;
            }

            ManageMembers::register();
            MetaBoxesOrganisation::register();
            MetaBoxesArtist::register();
            MetaBoxesEvent::register();
            MetaBoxesArtwork::register();
            MetaBoxesAddress::register( [ 'ead_organization', 'ead_event', 'ead_artist' ] );
            AdminEventForm::register(); // Corrected placement
            Geocoder::register();
            \EAD\Admin\HelpTabs::register();
            AdminRedirects::init();
        }
    }

    public function register_shortcodes() {
        EventsListShortcode::register();
        SubmitEventForm::register(); // Correct usage of SubmitEventForm
        OrganizationList::register();
        OrganizationForm::register();
        OrganizationRegistrationForm::register();
        ArtistRegistrationForm::register();
        EditEventForm::register();
        OrganizerDashboard::register();
        OrgReviewForm::register();
        FavoritesList::register();
        MembershipSignupForm::register();
        EventCalendar::register();
    }

    public static function register_rest_endpoints() { // **FIXED: Added `static` keyword**
        // Register all REST endpoints for Artpulse Management plugin
        $endpoints = [
            new \EAD\Rest\DashboardEndpoint(),
            new \EAD\Rest\ArtistDashboardEndpoint(),
            new \EAD\Rest\OrganizationDashboardEndpoint(),
            new \EAD\Rest\ReviewsEndpoint(),
            new \EAD\Rest\OrganizationsEndpoint(),
            new \EAD\Rest\ArtistsEndpoint(),
            new \EAD\Rest\BookingsEndpoint(),
            new \EAD\Rest\CommentEndpoint(),
            new \EAD\Rest\EventsEndpoint(),
            new \EAD\Rest\Like_Endpoint(),
            new \EAD\Rest\ModerationEndpoint(),
            new \EAD\Rest\JwtAuthEndpoint(),
            new \EAD\Rest\NotificationSettingsEndpoint(),
            new \EAD\Rest\SubmitEventEndpoint(),
            new \EAD\Rest\TaxonomyEndpoint(),
            new \EAD\Rest\UserProfileEndpoint(),
            new \EAD\Rest\ProfileEndpoint(),
            new \EAD\Rest\ChangePasswordEndpoint(),
            new \EAD\Rest\ArtworkEndpoint(),
            new \EAD\Rest\UploadEndpoint(),
            new \EAD\Rest\SubmissionEndpoint(),
            new \EAD\Rest\SyncEndpoint(),
            new \EAD\Rest\SettingsEndpoint(),
            new \EAD\Rest\CalendarEndpoint(),
            new \EAD\Rest\ManageMembersEndpoint(),
            new \EAD\Rest\MembershipEndpoint(),
            new \Artpulse\UserController(),
        ];

        foreach ( $endpoints as $endpoint ) {
            if ( method_exists( $endpoint, 'register_routes' ) ) {
                $endpoint->register_routes();
            }
        }

    }

    private function register_phase3_features() {
        if ( class_exists( ArtistDashboard::class ) ) {
            ArtistDashboard::init();
        }

        if ( class_exists( OrganizationDashboard::class ) ) {
            OrganizationDashboard::init();
        }

        if ( class_exists( UserDashboard::class ) ) {
            UserDashboard::init();
        }

        if ( class_exists( Reviews::class ) ) {
            Reviews::init();
        }

        if ( class_exists( DataExport::class ) ) {
            DataExport::init();
        }

        if ( class_exists( \EAD\Analytics\ListingAnalytics::class ) ) {
            \EAD\Analytics\ListingAnalytics::init();
        }
    }

    private function register_integrations() {
        if ( class_exists( 'Vc_Manager' ) && class_exists( WPBakery::class ) ) {
            WPBakery::register();
        }
        if ( class_exists( 'WooCommerce' ) && class_exists( WooCommercePayments::class ) ) {
            WooCommercePayments::register();
        }
    }

    private function enqueue_assets() {
        add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_frontend_assets' ] );
        add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_map_assets_conditionally' ] );
        add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_single_map_assets' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin_assets' ] );
    }

    public static function enqueue_frontend_assets() {
        $plugin_url = EAD_PLUGIN_DIR_URL;
        $version    = self::VERSION;

        wp_enqueue_style( 'ead-main-style', $plugin_url . 'assets/css/ead-main.css', [], $version );
        wp_enqueue_style( 'ead-badges-style', $plugin_url . 'assets/css/ead-badges.css', [], $version );
        wp_enqueue_style( 'ead-artist-gallery', $plugin_url . 'assets/css/artist-gallery.css', [], $version );

        wp_enqueue_script( 'ead-main-js', $plugin_url . 'assets/js/ead-main.js', [ 'jquery' ], $version, true );

        // Membership profile UI script
        wp_enqueue_script(
            'ead-membership-ui',
            $plugin_url . 'assets/js/membership-profile.js',
            [ 'wp-api-fetch' ],
            '1.0',
            true
        );
        wp_localize_script(
            'ead-membership-ui',
            'artpulse_vars',
            [
                'rest_nonce' => wp_create_nonce( 'wp_rest' ),
            ]
        );

        wp_localize_script(
            'ead-main-js',
            'eadFrontend',
            [
                'ajaxUrl'                 => admin_url( 'admin-ajax.php' ),
                'restUrl'                 => esc_url_raw( rest_url() ),
                'nonce_wp_rest'           => wp_create_nonce( 'wp_rest' ),
                'nonce_frontend_submit'   => wp_create_nonce( 'ead_frontend_submit_nonce' ),
                'nonce_upload_image'      => wp_create_nonce( 'ead_upload_image_nonce' ),
                'nonce_submit_org_review' => wp_create_nonce( 'ead_submit_org_review_nonce' ),
            ]
        );

        // Add the organization registration script
        wp_enqueue_script(
            'ead-organization-registration',
            $plugin_url . 'assets/js/organization-registration.js',
            [ 'jquery' ],
            $version,
            true
        );

        wp_localize_script(
            'ead-organization-registration',
            'EAD_VARS',
            [
                'ajaxUrl'                    => admin_url( 'admin-ajax.php' ),
                'restUrl'                    => esc_url_raw( rest_url( 'artpulse/v1/organizations' ) ),
                'registrationNonce'          => wp_create_nonce( 'wp_rest' ),
                'organizationConfirmationUrl' => home_url( '/organization-registration-success/' ),
            ]
        );

        wp_localize_script(
            'ead-organization-registration',
            'EAD_OrgReg',
            [
                'text_file_too_large'    => __( 'File exceeds %s limit.', 'artpulse-management' ),
                'text_invalid_file_type' => __( 'Invalid file type (JPG, PNG, GIF only).', 'artpulse-management' ),
                'text_required_field'    => __( 'This field is required.', 'artpulse-management' ),
                'text_invalid_email'     => __( 'Invalid email format.', 'artpulse-management' ),
                'text_invalid_url'       => __( 'Invalid URL format.', 'artpulse-management' ),
                'text_fill_required_fields' => __( 'Please correct the errors below.', 'artpulse-management' ),
                'text_submitting'        => __( 'Submitting...', 'artpulse-management' ),
                'text_registration_success' => __( 'Organization registered successfully!', 'artpulse-management' ),
                'text_registration_error'   => __( 'Registration failed.', 'artpulse-management' ),
                'text_submit_button'     => __( 'Register Organization', 'artpulse-management' ),
            ]
        );
    }

    public static function enqueue_admin_assets( $hook_suffix ) {
        $plugin_url = EAD_PLUGIN_DIR_URL;
        $version    = self::VERSION;
        $screen = get_current_screen();

        $allowed_post_types_for_featured_js = [ 'ead_event', 'ead_organization', 'ead_artist', 'ead_artwork', 'artwork' ];

        if ( $screen && in_array( $screen->post_type, $allowed_post_types_for_featured_js, true ) && in_array( $hook_suffix, [ 'post.php', 'post-new.php', 'edit.php' ] ) ) {
            wp_enqueue_script( 'ead-admin-featured-js', $plugin_url . 'assets/js/ead-featured.js', [ 'jquery' ], $version, true );

            wp_localize_script(
                'ead-admin-featured-js',
                'eadFeaturedAdmin',
                [
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'restUrl' => esc_url_raw( rest_url( 'artpulse/v1' ) ),
                    'nonce'   => wp_create_nonce( 'wp_rest' ),
                ]
            );

            // Enqueue the media uploader and image uploader script
            wp_enqueue_media();
            wp_enqueue_script( 'ead-image-uploader', $plugin_url . 'assets/js/ead-image-uploader.js', array( 'jquery', 'media-upload' ), $version, true );
        }
    }

    public static function enqueue_map_assets_conditionally() {
        global $post;

        if (
            is_a( $post, 'WP_Post' ) &&
            class_exists( OrganizationList::class ) &&
            defined( OrganizationList::class . '::SHORTCODE_TAG_MAP' ) &&
            has_shortcode( $post->post_content, OrganizationList::SHORTCODE_TAG_MAP )
        ) {
            $version = self::VERSION;

            $settings      = get_option( 'artpulse_plugin_settings', [] );
            $gmaps_api_key = isset( $settings['google_maps_api_key'] ) ? $settings['google_maps_api_key'] : '';

            wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4' );
            wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true );

            wp_enqueue_style( 'leaflet-cluster', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css', [ 'leaflet' ], '1.5.3' );
            wp_enqueue_script( 'leaflet-cluster', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js', [ 'leaflet' ], '1.5.3', true );

            wp_enqueue_script( 'ead-org-map-ajax', EAD_PLUGIN_DIR_URL . 'assets/js/ead-org-map-ajax.js', [ 'jquery', 'leaflet', 'leaflet-cluster' ], $version, true );

            if ( $gmaps_api_key ) {
                wp_enqueue_script( 'google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . $gmaps_api_key, [], null, true );
            }

            wp_localize_script(
                'ead-org-map-ajax',
                'EAD_ORG_MAP_AJAX',
                [
                    'ajaxurl'     => admin_url( 'admin-ajax.php' ),
                    'nonce'       => wp_create_nonce( 'ead_get_orgs_in_bounds_nonce' ),
                    'defaultLat'  => get_option( 'ead_map_default_lat', 40.7128 ),
                    'defaultLng'  => get_option( 'ead_map_default_lng', - 74.0060 ),
                    'defaultZoom' => get_option( 'ead_map_default_zoom', 10 ),
                    'text_no_orgs_found' => __( 'No organizations found in this area.', self::TEXT_DOMAIN ),
                    'gmapsApiKey' => $gmaps_api_key,
                ]
            );
        }
    }

    public static function enqueue_single_map_assets() {
        $settings      = get_option( 'artpulse_plugin_settings', [] );
        $gmaps_api_key = isset( $settings['google_maps_api_key'] ) ? $settings['google_maps_api_key'] : '';

        if ( is_singular( 'ead_event' ) ) {
            $post_id = get_the_ID();
            $lat = get_post_meta( $post_id, 'event_latitude', true );
            if ( ! $lat ) {
                $lat = get_post_meta( $post_id, 'event_lat', true );
            }
            $lng = get_post_meta( $post_id, 'event_longitude', true );
            if ( ! $lng ) {
                $lng = get_post_meta( $post_id, 'event_lng', true );
            }
            if ( $lat && $lng ) {
                $version = self::VERSION;
                wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4' );
                wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true );
                wp_enqueue_script( 'ead-frontend-map', EAD_PLUGIN_DIR_URL . 'assets/js/ead-frontend-map.js', [ 'jquery', 'leaflet' ], $version, true );
                if ( $gmaps_api_key ) {
                    wp_enqueue_script( 'google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . $gmaps_api_key, [], null, true );
                }
                wp_localize_script( 'ead-frontend-map', 'EAD_SINGLE_MAP', [
                    'lat'         => $lat,
                    'lng'         => $lng,
                    'gmapsApiKey' => $gmaps_api_key,
                ] );
            }
        }

        if ( is_singular( 'ead_organization' ) ) {
            $post_id = get_the_ID();
            $lat = get_post_meta( $post_id, 'ead_latitude', true );
            if ( ! $lat ) {
                $lat = get_post_meta( $post_id, 'org_lat', true );
            }
            $lng = get_post_meta( $post_id, 'ead_longitude', true );
            if ( ! $lng ) {
                $lng = get_post_meta( $post_id, 'org_lng', true );
            }
            if ( $lat && $lng ) {
                $version = self::VERSION;
                wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4' );
                wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true );
                wp_enqueue_script( 'ead-org-map-ajax', EAD_PLUGIN_DIR_URL . 'assets/js/ead-org-map-ajax.js', [ 'jquery', 'leaflet' ], $version, true );
                if ( $gmaps_api_key ) {
                    wp_enqueue_script( 'google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . $gmaps_api_key, [], null, true );
                }
                wp_localize_script( 'ead-org-map-ajax', 'EAD_SINGLE_MAP', [
                    'lat'         => $lat,
                    'lng'         => $lng,
                    'gmapsApiKey' => $gmaps_api_key,
                ] );
            }
        }
    }

    private function setup_admin_features() {
        self::setup_event_admin_features();
        add_action( 'restrict_manage_posts', [ self::class, 'add_featured_request_filter_for_cpts' ] );
        add_filter( 'parse_query', [ self::class, 'parse_featured_request_query_for_cpts' ] );
    }

    private function register_ajax_handlers() {
        $ajax_actions = [
            'ead_upload_image'       => 'handle_image_upload_ajax',
            'ead_submit_org_review'  => 'handle_org_review_submit_ajax',
            'ead_get_orgs_in_bounds' => 'ajax_get_orgs_in_bounds',
        ];

        foreach ( $ajax_actions as $action => $handler_method_name ) {
            if ( method_exists( self::class, $handler_method_name ) ) {
                add_action( 'wp_ajax_' . $action, [ self::class, $handler_method_name ] );
                if ( $action !== 'ead_upload_image' ) {
                    add_action( 'wp_ajax_nopriv_' . $action, [ self::class, $handler_method_name ] );
                }
            }
        }
    }

    public static function setup_event_admin_features() {
        add_filter( 'manage_ead_event_posts_columns', [ self::class, 'modify_event_admin_columns' ] );
        add_action( 'manage_ead_event_posts_custom_column', [ self::class, 'render_event_admin_custom_columns' ], 10, 2 );
        add_filter( 'manage_edit-ead_event_sortable_columns', [ self::class, 'make_event_admin_columns_sortable' ] );
        add_action( 'pre_get_posts', [ self::class, 'handle_event_admin_column_orderby' ] );
        add_action( 'restrict_manage_posts', [ self::class, 'add_event_status_filter_and_export_button' ] );
        add_filter( 'bulk_actions-edit-ead_event', [ self::class, 'add_event_bulk_actions' ] );
        add_filter( 'handle_bulk_actions-edit-ead_event', [ self::class, 'handle_event_bulk_actions' ], 10, 3 );
        add_action( 'admin_notices', [ self::class, 'display_event_bulk_action_notices' ] );
        add_action( 'admin_head', [ self::class, 'add_event_admin_list_styles' ] );
    }

    public static function modify_event_admin_columns( $columns ) {
        $new_columns = [];

        foreach ( $columns as $key => $title ) {
            $new_columns[ $key ] = $title;
            if ( $key === 'title' ) {
                $new_columns['event_organisation'] = __( 'Linked Organization', self::TEXT_DOMAIN );
                $new_columns['organizer_name']   = __( 'Submitter Name', self::TEXT_DOMAIN );
                $new_columns['organizer_email']  = __( 'Submitter Email', self::TEXT_DOMAIN );
                $new_columns['event_start']      = __( 'Start Date', self::TEXT_DOMAIN );
                $new_columns['event_end']        = __( 'End Date', self::TEXT_DOMAIN );
                $new_columns['gallery']          = __( 'Gallery', self::TEXT_DOMAIN );
            }
        }

        if ( ! isset( $new_columns['ead_featured_request'] ) ) {
            $new_columns['ead_featured_request'] = __( 'Featured Requested', self::TEXT_DOMAIN );
        }

        return $new_columns;
    }

    public static function render_event_admin_custom_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'event_organisation':
                $org_id = get_post_meta( $post_id, '_ead_event_organisation_id', true );
                if ( $org_id && get_post( $org_id ) && 'trash' !== get_post_status( $org_id ) ) {
                    echo '<a href="' . esc_url( get_edit_post_link( $org_id ) ) . '">' . esc_html( get_the_title( $org_id ) ) . '</a>';
                } else {
                    echo '<em>' . esc_html__( 'N/A', self::TEXT_DOMAIN ) . '</em>';
                }
                break;

            case 'organizer_name':
                echo esc_html( get_post_meta( $post_id, 'event_organizer_name', true ) );
                break;

            case 'organizer_email':
                $email = get_post_meta( $post_id, 'event_organizer_email', true );
                if ( $email && is_email( $email ) ) {
                    echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
                }
                break;

            case 'event_start':
                echo esc_html( get_post_meta( $post_id, 'event_start_date', true ) );
                break;

            case 'event_end':
                echo esc_html( get_post_meta( $post_id, 'event_end_date', true ) );
                break;

            case 'gallery':
                $gallery_ids = get_post_meta( $post_id, 'event_gallery', true );
                if ( ! empty( $gallery_ids ) && is_array( $gallery_ids ) ) {
                    echo esc_html( count( $gallery_ids ) ) . ' ' . esc_html( _n( 'image', 'images', count( $gallery_ids ), self::TEXT_DOMAIN ) );
                } else {
                    echo esc_html__( '0 images', self::TEXT_DOMAIN );
                }
                break;

            case 'ead_featured_request':
                $payment_status = get_post_meta( $post_id, '_ead_featured_payment_status', true );
                if ( get_post_meta( $post_id, '_ead_featured', true ) ) {
                    echo esc_html__( 'Featured', self::TEXT_DOMAIN );
                } elseif ( $payment_status === 'pending' ) {
                    echo esc_html__( 'Payment Pending', self::TEXT_DOMAIN );
                } elseif ( get_post_meta( $post_id, '_ead_featured_request', true ) === '1' ) {
                    echo '<span style="color:orange;font-weight:bold;">✔</span> ' . esc_html__( 'Requested', self::TEXT_DOMAIN );
                }
                break;
        }
    }

    public static function make_event_admin_columns_sortable( $columns ) {
        $columns['event_organisation'] = 'event_organisation_meta';
        $columns['organizer_name']   = 'organizer_name_meta';
        $columns['organizer_email']  = 'organizer_email_meta';
        $columns['event_start']      = 'event_start_date_meta';
        $columns['event_end']        = 'event_end_date_meta';

        return $columns;
    }

    public static function handle_event_admin_column_orderby( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'ead_event' ) {
            return;
        }

        $orderby = $query->get( 'orderby' );

        $meta_key_map = [
            'event_organisation_meta' => '_ead_event_organisation_id',
            'organizer_name_meta'   => 'event_organizer_name',
            'organizer_email_meta'  => 'event_organizer_email',
            'event_start_date_meta' => 'event_start_date',
            'event_end_date_meta'   => 'event_end_date',
        ];

        if ( isset( $meta_key_map[ $orderby ] ) ) {
            $query->set( 'meta_key', $meta_key_map[ $orderby ] );
            $query->set( 'orderby', ( $orderby === 'event_organisation_meta' ) ? 'meta_value_num' : 'meta_value' );

            if ( $orderby === 'event_start_date_meta' || $orderby === 'event_end_date_meta' ) {
                $query->set( 'meta_type', 'DATE' );
            }
        }
    }

    public static function add_event_status_filter_and_export_button( $post_type ) {
        if ( $post_type === 'ead_event' ) {
            $current_status = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : '';
            ?>
            <select name="post_status" id="post_status_filter">
                <option value=""><?php esc_html_e( 'All Statuses', self::TEXT_DOMAIN ); ?></option>
                <option value="publish" <?php selected( $current_status, 'publish' ); ?>><?php esc_html_e( 'Published', self::TEXT_DOMAIN ); ?></option>
                <option value="pending" <?php selected( $current_status, 'pending' ); ?>><?php esc_html_e( 'Pending Review', self::TEXT_DOMAIN ); ?></option>
                <option value="draft" <?php selected( $current_status, 'draft' ); ?>><?php esc_html_e( 'Draft', self::TEXT_DOMAIN ); ?></option>
                <option value="trash" <?php selected( $current_status, 'trash' ); ?>><?php esc_html_e( 'Trash', self::TEXT_DOMAIN ); ?></option>
            </select>
            <?php

            if ( current_user_can( 'edit_others_posts' ) && ( empty( $current_status ) || $current_status === 'pending' ) ) {
                $export_url = add_query_arg(
                    [
                        'action'  => 'ead_export_pending_events_csv',
                        '_wpnonce' => wp_create_nonce( 'ead_export_pending_csv_nonce' ),
                    ],
                    admin_url( 'admin-post.php' )
                );

                echo '<a href="' . esc_url( $export_url ) . '" class="button button-secondary" style="margin-left:10px;">' . esc_html__( 'Export Pending to CSV', self::TEXT_DOMAIN ) . '</a>';
            }
        }
    }

    public static function process_export_pending_csv() {
        // Nonce and capability checks are crucial here.
        if (
            ! isset( $_GET['_wpnonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ead_export_pending_csv_nonce' )
        ) {
            wp_die(
                esc_html__( 'Security check failed.', self::TEXT_DOMAIN ),
                esc_html__( 'Error', self::TEXT_DOMAIN ),
                [ 'response' => 403 ]
            );
        }

        if ( ! current_user_can( 'edit_others_posts' ) ) {
            wp_die(
                esc_html__( 'You do not have permission to export this data.', self::TEXT_DOMAIN ),
                esc_html__( 'Error', self::TEXT_DOMAIN ),
                [ 'response' => 403 ]
            );
        }

        $args = [
            'post_type'      => 'ead_event',
            'post_status'    => 'pending',
            'posts_per_page' => - 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $events = get_posts( $args );

        if ( empty( $events ) ) {
            wp_safe_redirect( admin_url( 'edit.php?post_type=ead_event&exported_status=empty' ) );
            exit;
        }

        $filename = 'pending-events-' . date( 'Y-m-d' ) . '.csv';

        // Send HTTP headers to force download
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        $headers = [
            __( 'ID', self::TEXT_DOMAIN ),
            __( 'Title', self::TEXT_DOMAIN ),
            __( 'Organizer Name', self::TEXT_DOMAIN ),
            __( 'Organizer Email', self::TEXT_DOMAIN ),
            __( 'Start Date', self::TEXT_DOMAIN ),
            __( 'End Date', self::TEXT_DOMAIN ),
            __( 'Description', self::TEXT_DOMAIN ),
            __( 'Submitted Date', self::TEXT_DOMAIN ),
            __( 'Linked Organization ID', self::TEXT_DOMAIN ),
            __( 'Linked Organization Name', self::TEXT_DOMAIN ),
        ];

        fputcsv( $output, $headers );

        foreach ( $events as $event ) {
            $org_id   = get_post_meta( $event->ID, '_ead_event_organisation_id', true );
            $org_name = '';

            if ( $org_id && get_post( $org_id ) ) {
                $org_name = get_the_title( $org_id );
            }

            fputcsv(
                $output,
                [
                    $event->ID,
                    $event->post_title,
                    get_post_meta( $event->ID, 'event_organizer_name', true ),
                    get_post_meta( $event->ID, 'event_organizer_email', true ),
                    get_post_meta( $event->ID, 'event_start_date', true ),
                    get_post_meta( $event->ID, 'event_end_date', true ),
                    wp_strip_all_tags( $event->post_content ),
                    get_the_date( 'Y-m-d H:i:s', $event->ID ),
                    $org_id,
                    $org_name,
                ]
            );
        }

        fclose( $output );
        exit;
    }

    public static function add_event_bulk_actions( $bulk_actions ) {
        $bulk_actions['bulk_approve'] = __( 'Approve Events', self::TEXT_DOMAIN );
        $bulk_actions['bulk_reject']  = __( 'Reject Events (Move to Trash)', self::TEXT_DOMAIN );

        return $bulk_actions;
    }

    public static function handle_event_bulk_actions( $redirect_to, $doaction, $post_ids ) {
        if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
            return $redirect_to;
        }

        $processed_count = 0;

        if ( $doaction === 'bulk_approve' ) {
            foreach ( $post_ids as $post_id_val ) {
                $post_id = intval( $post_id_val );

                if ( get_post_status( $post_id ) === 'pending' && current_user_can( 'publish_post', $post_id ) ) {
                    wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
                    $processed_count ++;
                }
            }

            if ( $processed_count > 0 ) {
                $redirect_to = add_query_arg( 'bulk_approved', $processed_count, $redirect_to );
            }
        } elseif ( $doaction === 'bulk_reject' ) {
            foreach ( $post_ids as $post_id_val ) {
                $post_id = intval( $post_id_val );

                if ( current_user_can( 'delete_post', $post_id ) ) {
                    wp_trash_post( $post_id );
                    $processed_count ++;
                }
            }

            if ( $processed_count > 0 ) {
                $redirect_to = add_query_arg( 'bulk_rejected', $processed_count, $redirect_to );
            }
        }

        return $redirect_to;
    }

    public static function display_event_bulk_action_notices() {
        global $pagenow, $typenow;

        if ( $pagenow === 'edit.php' && $typenow === 'ead_event' ) {
            if ( ! empty( $_REQUEST['bulk_approved'] ) ) {
                $count = intval( $_REQUEST['bulk_approved'] );
                printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( sprintf( _n( '%s event approved.', '%s events approved.', $count, self::TEXT_DOMAIN ), number_format_i18n( $count ) ) ) );
            }

            if ( ! empty( $_REQUEST['bulk_rejected'] ) ) {
                $count = intval( $_REQUEST['bulk_rejected'] );
                printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html( sprintf( _n( '%s event rejected and moved to trash.', '%s events rejected and moved to trash.', $count, self::TEXT_DOMAIN ), number_format_i18n( $count ) ) ) );
            }

            if ( isset( $_GET['exported_status'] ) && $_GET['exported_status'] === 'empty' ) {
                echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'No pending events found to export.', self::TEXT_DOMAIN ) . '</p></div>';
            }
        }
    }

    public static function add_event_admin_list_styles() {
        global $pagenow, $typenow;

        if ( $pagenow === 'edit.php' && $typenow === 'ead_event' ) {
            echo '<style>
                .post-type-ead_event tr.status-pending { background-color:#fff5e0 !important; }
                .post-type-ead_event tr.status-publish { background-color:#eaffea !important; }
                .post-type-ead_event .column-title .post-state {
                    background:#ffd080; color:#835b00; border-radius:3px;
                    padding:0 4px; margin-left:6px; font-size:11px;
                }
            </style>';
        }
    }

    public static function notify_user_on_organization_approval( $new_status, $old_status, $post ) {
        if ( $post instanceof \WP_Post && $post->post_type === 'ead_organization' && $old_status === 'pending' && $new_status === 'publish' ) {
            $user_id   = $post->post_author;
            $user_data = get_userdata( $user_id );

            if ( $user_data && ! empty( $user_data->user_email ) ) {
                $org_name          = $post->post_title;
                $user_display_name = $user_data->display_name;

                $subject = sprintf( esc_html__( 'Your Organization "%s" has been Approved!', self::TEXT_DOMAIN ), $org_name );
                $body    = sprintf( esc_html__( "Hello %s,\n\n", self::TEXT_DOMAIN ), esc_html( $user_display_name ) );
                $body   .= sprintf( esc_html__( "Great news! Your organization submission:\n\n\"%s\"\n\nhas been approved and is now live on our website.\n\n", self::TEXT_DOMAIN ), esc_html( $org_name ) );
                $body   .= sprintf( esc_html__( "You can view it here: %s\n\n", self::TEXT_DOMAIN ), esc_url( get_permalink( $post->ID ) ) );
                $body   .= esc_html__( "You can now proceed to submit events associated with your organization.\n\n", self::TEXT_DOMAIN );
                $body   .= esc_html__( "Thank you!\n", self::TEXT_DOMAIN ) . get_bloginfo( 'name' );

                $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

                wp_mail( $user_data->user_email, $subject, $body, $headers );

                $settings = get_option( 'artpulse_notification_settings', [] );
                if ( ! empty( $settings['push_organization_approved'] ) ) {
                    PushNotificationService::send(
                        __( 'Organization Approved', self::TEXT_DOMAIN ),
                        sprintf( __( 'Organization "%s" has been approved.', self::TEXT_DOMAIN ), $org_name ),
                        'organizations'
                    );
                }
            }
        }
    }

    public static function notify_organizer_on_event_approval( $new_status, $old_status, $post ) {
        if ( $post instanceof \WP_Post && $post->post_type === 'ead_event' && $old_status === 'pending' && $new_status === 'publish' ) {
            $organizer_email = get_post_meta( $post->ID, 'event_organizer_email', true );
            $organizer_name  = get_post_meta( $post->ID, 'event_organizer_name', true );
            $event_title     = get_the_title( $post->ID );

            if ( $organizer_email && is_email( $organizer_email ) ) {
                $subject = sprintf( esc_html__( 'Your event "%s" has been approved!', self::TEXT_DOMAIN ), $event_title );
                $body    = sprintf( esc_html__( "Hello %s,\n\n", self::TEXT_DOMAIN ), esc_html( $organizer_name ) );
                $body   .= esc_html__( "Great news! Your event submission:\n\n", self::TEXT_DOMAIN ) . '"' . esc_html( $event_title ) . "\"\n\n" . esc_html__( "has been approved and published on our website.\n\n", self::TEXT_DOMAIN );
                $body   .= sprintf( esc_html__( "You can view it here: %s\n\n", self::TEXT_DOMAIN ), esc_url( get_permalink( $post->ID ) ) );
                $body   .= esc_html__( "Thank you for sharing your event with us!\n", self::TEXT_DOMAIN ) . get_bloginfo( 'name' );

                $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

                wp_mail( $organizer_email, $subject, $body, $headers );

                $settings = get_option( 'artpulse_notification_settings', [] );
                if ( ! empty( $settings['push_event_approved'] ) ) {
                    PushNotificationService::send(
                        __( 'Event Approved', self::TEXT_DOMAIN ),
                        sprintf( __( 'Event "%s" has been approved.', self::TEXT_DOMAIN ), $event_title ),
                        'events'
                    );
                }
            }
        }
    }

    public static function notify_admin_on_event_pending( $new_status, $old_status, $post ) {
        if ( $post instanceof \WP_Post && $post->post_type === 'ead_event' && $new_status === 'pending' && $old_status !== 'pending' ) {
            $settings = get_option( 'artpulse_notification_settings', [] );
            if ( isset( $settings['new_event_submission_notification'] ) && ! $settings['new_event_submission_notification'] ) {
                return;
            }

            $admin_email = get_option( 'admin_email' );
            $event_title = get_the_title( $post->ID );
            $subject     = sprintf( esc_html__( 'New event "%s" awaiting approval', self::TEXT_DOMAIN ), $event_title );
            $body        = sprintf( esc_html__( 'A new event submission titled "%s" is awaiting approval.', self::TEXT_DOMAIN ), $event_title ) . "\n\n";
            $body       .= esc_html__( 'Review it here:', self::TEXT_DOMAIN ) . ' ' . esc_url( admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) );

            $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

            wp_mail( $admin_email, $subject, $body, $headers );
        }
    }

    public static function notify_artist_on_artwork_approval( $new_status, $old_status, $post ) {
        if ( $post instanceof \WP_Post && $post->post_type === 'ead_artwork' && $old_status === 'pending' && $new_status === 'publish' ) {
            $user_data = get_userdata( $post->post_author );

            if ( $user_data && ! empty( $user_data->user_email ) ) {
                $artwork_title = $post->post_title;
                $subject       = sprintf( esc_html__( 'Your artwork "%s" has been approved!', self::TEXT_DOMAIN ), $artwork_title );
                $body          = sprintf( esc_html__( "Hello %s,\n\n", self::TEXT_DOMAIN ), esc_html( $user_data->display_name ) );
                $body         .= esc_html__( "Great news! Your artwork submission:\n\n", self::TEXT_DOMAIN ) . '"' . esc_html( $artwork_title ) . "\"\n\n" . esc_html__( "has been approved and published on our website.\n\n", self::TEXT_DOMAIN );
                $body         .= sprintf( esc_html__( "You can view it here: %s\n\n", self::TEXT_DOMAIN ), esc_url( get_permalink( $post->ID ) ) );
                $body         .= esc_html__( "Thank you for sharing your work!\n", self::TEXT_DOMAIN ) . get_bloginfo( 'name' );

                $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

                wp_mail( $user_data->user_email, $subject, $body, $headers );
            }
        }
    }

    public static function notify_admin_on_artwork_pending( $new_status, $old_status, $post ) {
        if ( $post instanceof \WP_Post && $post->post_type === 'ead_artwork' && $new_status === 'pending' && $old_status !== 'pending' ) {
            $admin_email   = get_option( 'admin_email' );
            $artwork_title = $post->post_title;
            $subject       = sprintf( esc_html__( 'New artwork "%s" awaiting approval', self::TEXT_DOMAIN ), $artwork_title );
            $body          = sprintf( esc_html__( 'A new artwork submission titled "%s" is awaiting approval.', self::TEXT_DOMAIN ), $artwork_title ) . "\n\n";
            $body         .= esc_html__( 'Review it here:', self::TEXT_DOMAIN ) . ' ' . esc_url( admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) );

            $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

            wp_mail( $admin_email, $subject, $body, $headers );
        }
    }

    /**
     * Send a push notification when a published event is updated.
     */
    public static function notify_event_updated( $post_id, $post, $update ) {
        if ( ! $update || $post->post_status !== 'publish' ) {
            return;
        }

        $settings = get_option( 'artpulse_notification_settings', [] );
        if ( empty( $settings['push_event_updated'] ) ) {
            return;
        }

        PushNotificationService::send(
            __( 'Event Updated', self::TEXT_DOMAIN ),
            sprintf( __( 'The event "%s" has been updated.', self::TEXT_DOMAIN ), $post->post_title ),
            'events'
        );
    }

    /**
     * Send a push notification when a published organization is updated.
     */
    public static function notify_organization_updated( $post_id, $post, $update ) {
        if ( ! $update || $post->post_status !== 'publish' ) {
            return;
        }

        $settings = get_option( 'artpulse_notification_settings', [] );
        if ( empty( $settings['push_organization_updated'] ) ) {
            return;
        }

        PushNotificationService::send(
            __( 'Organization Updated', self::TEXT_DOMAIN ),
            sprintf( __( 'The organization "%s" has been updated.', self::TEXT_DOMAIN ), $post->post_title ),
            'organizations'
        );
    }

    public static function add_featured_request_filter_for_cpts( $post_type ) {
        $supported_post_types = [ 'ead_event', 'ead_organization', 'ead_artist' ];

        if ( in_array( $post_type, $supported_post_types, true ) ) {
            $current_filter_value = isset( $_GET['ead_featured_request_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['ead_featured_request_filter'] ) ) : '';
            ?>
            <select name="ead_featured_request_filter">
                <option value=""><?php esc_html_e( 'All Featured Status', self::TEXT_DOMAIN ); ?></option>
                <option value="requested" <?php selected( $current_filter_value, 'requested' ); ?>><?php esc_html_e( 'Has Featured Request', self::TEXT_DOMAIN ); ?></option>
                <option value="not_requested" <?php selected( $current_filter_value, 'not_requested' ); ?>><?php esc_html_e( 'No Featured Request', self::TEXT_DOMAIN ); ?></option>
                <option value="is_featured" <?php selected( $current_filter_value, 'is_featured' ); ?>><?php esc_html_e( 'Is Currently Featured', self::TEXT_DOMAIN ); ?></option>
            </select>
            <?php
        }
    }

    public static function parse_featured_request_query_for_cpts( $query ) {
        global $pagenow;

        $supported_post_types = [ 'ead_event', 'ead_organization', 'ead_artist' ];

        if (
            is_admin() &&
            $pagenow === 'edit.php' &&
            $query->is_main_query() &&
            isset( $query->query_vars['post_type'] ) && in_array( $query->query_vars['post_type'], $supported_post_types, true ) &&
            ! empty( $_GET['ead_featured_request_filter'] )
        ) {
            $filter_value = sanitize_text_field( wp_unslash( $_GET['ead_featured_request_filter'] ) );
            $meta_query   = $query->get( 'meta_query', [] );

            if ( ! is_array( $meta_query ) ) {
                $meta_query = [];
            }

            if ( $filter_value === 'requested' ) {
                $meta_query[] = [ 'key' => '_ead_featured_request', 'value' => '1', 'compare' => '=' ];
            } elseif ( $filter_value === 'not_requested' ) {
                $meta_query[] = [
                    'relation' => 'OR',
                    [ 'key' => '_ead_featured_request', 'compare' => 'NOT EXISTS' ],
                    [ 'key' => '_ead_featured_request', 'value' => '1', 'compare' => '!=' ],
                ];
            } elseif ( $filter_value === 'is_featured' ) {
                $meta_query[] = [ 'key' => '_ead_featured', 'value' => '1', 'compare' => '=' ];
            }

            $query->set( 'meta_query', $meta_query );
        }
    }

    public static function handle_image_upload_ajax() {
        if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ead_upload_image_nonce' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Security check failed.', self::TEXT_DOMAIN ) ], 403 );
            return;
        }

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to upload files.', self::TEXT_DOMAIN ) ], 403 );
            return;
        }

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.VIP.SuperGlobalInputUsage.AccessDetected
        if ( empty( $_FILES['file'] ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'No file was uploaded.', self::TEXT_DOMAIN ) ] );
            return;
        }

        $file = $_FILES['file'];
        // phpcs:enable

        $allowed_types = apply_filters( 'ead_allowed_image_upload_types', [ 'image/jpeg', 'image/png', 'image/gif' ] );

        if ( ! in_array( $file['type'], $allowed_types, true ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Invalid file type. Only JPG, PNG, or GIF are allowed.', self::TEXT_DOMAIN ) ] );
            return;
        }

        $max_size = apply_filters( 'ead_max_image_upload_size', 5 * 1024 * 1024 ); // 5MB

        if ( $file['size'] > $max_size ) {
            wp_send_json_error( [ 'message' => sprintf( esc_html__( 'File is too large. Maximum size is %s.', self::TEXT_DOMAIN ), size_format( $max_size ) ) ] );
            return;
        }

        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( [ 'message' => esc_html__( 'File upload error code: ', self::TEXT_DOMAIN ) . $file['error'] ] );
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload( 'file', 0 ); // 0 means no parent post

        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Upload failed: ', self::TEXT_DOMAIN ) . $attachment_id->get_error_message() ] );
            return;
        }

        $url            = wp_get_attachment_url( $attachment_id );
        $thumbnail_html = wp_get_attachment_image( $attachment_id, 'thumbnail', false, [ 'style' => 'height:48px; width:auto; border-radius:6px;' ] );

        wp_send_json_success(
            [
                'id'      => $attachment_id,
                'url'     => esc_url( $url ),
                'html'    => $thumbnail_html,
                'message' => esc_html__( 'Image uploaded successfully.', self::TEXT_DOMAIN ),
            ]
        );
    }

    public static function handle_org_review_submit_ajax() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        if ( ! empty( $_POST['website_url_hp'] ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Spam detected.', self::TEXT_DOMAIN ) ], 403 );
            return;
        }
        // phpcs:enable

        if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ead_submit_org_review_nonce' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Security check failed. Please refresh and try again.', self::TEXT_DOMAIN ) ], 403 );
            return;
        }

        $recaptcha_secret   = SettingsPage::get_setting( 'recaptcha_secret_key' );
        $recaptcha_site_key = SettingsPage::get_setting( 'recaptcha_site_key' );

        if ( ! empty( $recaptcha_secret ) && ! empty( $recaptcha_site_key ) ) {
            $recaptcha_response = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';

            if ( empty( $recaptcha_response ) ) {
                wp_send_json_error( [ 'message' => esc_html__( 'Please complete the reCAPTCHA.', self::TEXT_DOMAIN ) ] );
                return;
            }

            $remote_ip = ! empty( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : null;

            if ( ! $remote_ip ) {
                wp_send_json_error( [ 'message' => esc_html__( 'Could not verify reCAPTCHA. IP address missing.', self::TEXT_DOMAIN ) ] );
                return;
            }

            $verify_response = wp_remote_post(
                'https://www.google.com/recaptcha/api/siteverify',
                [
                    'body' => [
                        'secret'   => $recaptcha_secret,
                        'response' => $recaptcha_response,
                        'remoteip' => $remote_ip,
                    ],
                ]
            );

            if ( is_wp_error( $verify_response ) ) {
                wp_send_json_error( [ 'message' => esc_html__( 'Could not verify reCAPTCHA. Please try again later.', self::TEXT_DOMAIN ) ] );
                return;
            }

            $result = json_decode( wp_remote_retrieve_body( $verify_response ), true );

            if ( empty( $result['success'] ) ) {
                $error_codes = isset( $result['error-codes'] ) ? implode( ', ', $result['error-codes'] ) : 'unknown';
                wp_send_json_error( [ 'message' => sprintf( esc_html__( 'reCAPTCHA verification failed. Please try again. (Error: %s)', self::TEXT_DOMAIN ), esc_html( $error_codes ) ) ] );
                return;
            }
        }

        $errors          = [];
        $organization_id = isset( $_POST['organization_id'] ) ? intval( $_POST['organization_id'] ) : 0;
        $review_content  = isset( $_POST['review_comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['review_comment'] ) ) : '';
        $rating          = isset( $_POST['review_rating'] ) ? intval( $_POST['review_rating'] ) : 0;
        $reviewer_name   = isset( $_POST['reviewer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['reviewer_name'] ) ) : '';
        $reviewer_email  = isset( $_POST['reviewer_email'] ) ? sanitize_email( wp_unslash( $_POST['reviewer_email'] ) ) : '';

        if ( empty( $organization_id ) || get_post_type( $organization_id ) !== 'ead_organization' || get_post_status( $organization_id ) !== 'publish' ) {
            $errors[] = esc_html__( 'Invalid organization selected.', self::TEXT_DOMAIN );
        }

        if ( empty( $review_content ) ) {
            $errors[] = esc_html__( 'Review comment is required.', self::TEXT_DOMAIN );
        }

        if ( mb_strlen( $review_content ) < 10 ) {
            $errors[] = esc_html__( 'Review comment must be at least 10 characters long.', self::TEXT_DOMAIN );
        }

        if ( $rating < 1 || $rating > 5 ) {
            $errors[] = esc_html__( 'Please provide a valid rating between 1 and 5.', self::TEXT_DOMAIN );
        }

        if ( ! is_user_logged_in() ) {
            if ( empty( $reviewer_name ) ) {
                $errors[] = esc_html__( 'Your name is required.', self::TEXT_DOMAIN );
            }

            if ( empty( $reviewer_email ) ) {
                $errors[] = esc_html__( 'A valid email is required.', self::TEXT_DOMAIN );
            }
        } else {
            $current_user  = wp_get_current_user();
            $reviewer_name = $current_user->display_name;
            $reviewer_email = $current_user->user_email;
        }

        if ( ! empty( $errors ) ) {
            wp_send_json_error( [ 'message' => implode( '<br>', $errors ) ] );
            return;
        }

        $existing_args = [
            'post_type'      => 'ead_org_review',
            'post_status'    => [ 'publish', 'pending' ],
            'posts_per_page' => 1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_ead_organization_id',
                    'value'   => $organization_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
            ],
        ];

        if ( is_user_logged_in() ) {
            $existing_args['author'] = get_current_user_id();
        } else {
            $existing_args['meta_query'][] = [ 'key' => '_ead_reviewer_email', 'value' => $reviewer_email, 'compare' => '=' ];
        }

        $existing_reviews_query = new \WP_Query( $existing_args );

        if ( $existing_reviews_query->have_posts() ) {
            wp_send_json_error( [ 'message' => esc_html__( 'You have already submitted a review for this organization.', self::TEXT_DOMAIN ) ] );
            return;
        }

        $post_author_id     = is_user_logged_in() ? get_current_user_id() : 0;
        $author_display_name = $reviewer_name;
        $org_title           = get_the_title( $organization_id );
        $review_title_generated = sprintf( esc_html__( 'Review for %s by %s', self::TEXT_DOMAIN ), $org_title, $author_display_name );

        $review_post_data = [
            'post_title'   => $review_title_generated,
            'post_content' => $review_content,
            'post_status'  => 'pending',
            'post_type'    => 'ead_org_review',
            'post_author'  => $post_author_id,
        ];

        $review_post_id = wp_insert_post( $review_post_data, true );

        if ( is_wp_error( $review_post_id ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Failed to submit review: ', self::TEXT_DOMAIN ) . $review_post_id->get_error_message() ] );
            return;
        }

        update_post_meta( $review_post_id, '_ead_organization_id', $organization_id );
        update_post_meta( $review_post_id, '_ead_rating', $rating );

        if ( ! is_user_logged_in() ) {
            update_post_meta( $review_post_id, '_ead_reviewer_email', $reviewer_email );
            update_post_meta( $review_post_id, '_ead_reviewer_name', $reviewer_name );
        }

        $admin_email_address = get_option( 'admin_email' );
        $subject_admin = sprintf( esc_html__( '[%s] New Organization Review Submitted: %s', self::TEXT_DOMAIN ), get_bloginfo( 'name' ), $org_title );
        $body_admin    = sprintf( esc_html__( "A new review has been submitted for the organization: %s\n\n", self::TEXT_DOMAIN ), esc_html( $org_title ) );
        $body_admin   .= sprintf( esc_html__( "Reviewer: %s %s\n", self::TEXT_DOMAIN ), esc_html( $author_display_name ), ( $reviewer_email ? "<" . esc_html( $reviewer_email ) . ">" : "" ) );
        $body_admin   .= sprintf( esc_html__( "Rating: %d/5\n", self::TEXT_DOMAIN ), $rating );
        $body_admin   .= esc_html__( "Comment:\n", self::TEXT_DOMAIN ) . esc_html( $review_content ) . "\n\n";
        $body_admin   .= sprintf( esc_html__( "You can moderate this review here: %s\n", self::TEXT_DOMAIN ), esc_url( admin_url( 'post.php?post=' . $review_post_id . '&action=edit' ) ) );

        wp_mail( $admin_email_address, $subject_admin, $body_admin, [ 'Content-Type: text/plain; charset=UTF-8' ] );

        wp_send_json_success( [ 'message' => esc_html__( 'Thank you for your review! It has been submitted for moderation.', self::TEXT_DOMAIN ) ] );
    }

    public static function ajax_get_orgs_in_bounds() {
        if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ead_get_orgs_in_bounds_nonce' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Security check failed.', self::TEXT_DOMAIN ) ], 403 );
            return;
        }

        $ne_lat_raw = isset( $_POST['ne_lat'] ) ? wp_unslash( $_POST['ne_lat'] ) : null;
        $ne_lng_raw = isset( $_POST['ne_lng'] ) ? wp_unslash( $_POST['ne_lng'] ) : null;
        $sw_lat_raw = isset( $_POST['sw_lat'] ) ? wp_unslash( $_POST['sw_lat'] ) : null;
        $sw_lng_raw = isset( $_POST['sw_lng'] ) ? wp_unslash( $_POST['sw_lng'] ) : null;

        $ne_lat = filter_var( $ne_lat_raw, FILTER_VALIDATE_FLOAT, [ 'options' => [ 'min_range' => - 90, 'max_range' => 90 ] ] );
        $ne_lng = filter_var( $ne_lng_raw, FILTER_VALIDATE_FLOAT, [ 'options' => [ 'min_range' => - 180, 'max_range' => 180 ] ] );
        $sw_lat = filter_var( $sw_lat_raw, FILTER_VALIDATE_FLOAT, [ 'options' => [ 'min_range' => - 90, 'max_range' => 90 ] ] );
        $sw_lng = filter_var( $sw_lng_raw, FILTER_VALIDATE_FLOAT, [ 'options' => [ 'min_range' => - 180, 'max_range' => 180 ] ] );

        if ( false === $ne_lat || false === $ne_lng || false === $sw_lat || false === $sw_lng ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Invalid map bounds provided.', self::TEXT_DOMAIN ) ] );
            return;
        }

        if ( $sw_lat >= $ne_lat ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Invalid latitude range.', self::TEXT_DOMAIN ) ] );
            return;
        }

        $meta_query_conditions = [
            'relation' => 'AND',
            [
                'key'     => 'ead_organisation_lat',
                'value'   => [ $sw_lat, $ne_lat ],
                'type'    => 'DECIMAL(10,6)', // Adjust precision if needed
                'compare' => 'BETWEEN',
            ],
        ];

        if ( $sw_lng > $ne_lng ) { // Dateline crossed
            $meta_query_conditions[] = [
                'relation' => 'OR',
                [
                    'key'     => 'ead_organisation_lng',
                    'value'   => [ $sw_lng, 180 ],
                    'type'    => 'DECIMAL(10,6)',
                    'compare' => 'BETWEEN',
                ],
                [
                    'key'     => 'ead_organisation_lng',
                    'value'   => [ - 180, $ne_lng ],
                    'type'    => 'DECIMAL(10,6)',
                    'compare' => 'BETWEEN',
                ],
            ];
        } else {
            $meta_query_conditions[] = [
                'key'     => 'ead_organisation_lng',
                'value'   => [ $sw_lng, $ne_lng ],
                'type'    => 'DECIMAL(10,6)',
                'compare' => 'BETWEEN',
            ];
        }

        $args = [
            'post_type'           => 'ead_organization',
            'post_status'         => 'publish',
            'posts_per_page'      => - 1,
            'meta_query'          => $meta_query_conditions,
            'no_found_rows'       => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];

        $query = new \WP_Query( $args );

        $orgs_data = [];

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id = get_the_ID();

                $lat_val = get_post_meta( $post_id, 'ead_organisation_lat', true );
                $lng_val = get_post_meta( $post_id, 'ead_organisation_lng', true );

                if ( empty( $lat_val ) || empty( $lng_val ) ) {
                    continue;
                }

                $logo_id           = get_post_meta( $post_id, 'ead_org_logo_id', true );
                $default_logo_url = EAD_PLUGIN_DIR_URL . 'assets/images/default-org-logo.png';
                $logo_url          = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : $default_logo_url;

                if ( ! $logo_url ) {
                    $logo_url = $default_logo_url;
                }

                $orgs_data[] = [
                    'id'       => $post_id,
                    'title'    => get_the_title(),
                    'link'     => esc_url( get_permalink() ),
                    'logo_url' => esc_url( $logo_url ),
                    'desc'     => wp_trim_words( get_post_meta( $post_id, 'ead_org_description_content', true ), 16, '...' ),
                    'website'  => esc_url( get_post_meta( $post_id, 'ead_org_website', true ) ),
                    'lat'      => floatval( $lat_val ),
                    'lng'      => floatval( $lng_val ),
                    'featured' => (bool) get_post_meta( $post_id, '_ead_featured', true ),
                ];
            }
        }

        wp_reset_postdata();
        wp_send_json_success( [ 'orgs' => $orgs_data ] );
    }

    /**
     * Check that required registration pages exist and optionally create them.
     */
    public static function check_registration_pages() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_GET['ead_create_registration_pages'] ) ) {
            check_admin_referer( 'ead_create_registration_pages' );
            self::create_registration_page_if_missing( 'ap_artist_registration_form', __( 'Artist Registration', self::TEXT_DOMAIN ) );
            self::create_registration_page_if_missing( 'ead_organization_registration_form', __( 'Organization Registration', self::TEXT_DOMAIN ) );

            wp_safe_redirect( remove_query_arg( [ 'ead_create_registration_pages', '_wpnonce' ] ) . '&ead_pages_created=1' );
            exit;
        }

        $missing = [];
        if ( ! self::find_page_with_shortcode( 'ap_artist_registration_form' ) ) {
            $missing[] = '[ap_artist_registration_form]';
        }
        if ( ! self::find_page_with_shortcode( 'ead_organization_registration_form' ) ) {
            $missing[] = '[ead_organization_registration_form]';
        }

        if ( $missing ) {
            add_action( 'admin_notices', function () use ( $missing ) {
                $create_url = wp_nonce_url( add_query_arg( 'ead_create_registration_pages', 1 ), 'ead_create_registration_pages' );
                echo '<div class="notice notice-warning"><p>';
                printf(
                    esc_html__( 'Pages containing the following shortcodes are missing: %s', self::TEXT_DOMAIN ),
                    esc_html( implode( ', ', $missing ) )
                );
                echo ' '; // space before button
                echo '<a href="' . esc_url( $create_url ) . '" class="button button-primary">' . esc_html__( 'Create Pages', self::TEXT_DOMAIN ) . '</a>';
                echo '</p></div>';
            } );
        } elseif ( isset( $_GET['ead_pages_created'] ) ) {
            add_action( 'admin_notices', function () {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Registration pages created.', self::TEXT_DOMAIN ) . '</p></div>';
            } );
        }
    }

    private static function find_page_with_shortcode( $shortcode ) {
        $pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        foreach ( $pages as $page_id ) {
            $content = get_post_field( 'post_content', $page_id );
            if ( has_shortcode( $content, $shortcode ) ) {
                return $page_id;
            }
        }

        return 0;
    }

    private static function create_registration_page_if_missing( $shortcode, $title ) {
        if ( self::find_page_with_shortcode( $shortcode ) ) {
            return;
        }

        wp_insert_post([
            'post_title'   => $title,
            'post_content' => '[' . $shortcode . ']',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);
    }

    public static function add_roles() {
        // Add the roles here
        add_role(
            'ead_artist',
            __( 'Artist', 'artpulse-management' ),
            [
                'read'         => true,  // Allows a user to read
                'edit_posts'   => false, // Allows user to edit their own posts
                'delete_posts' => false, // Allows user to delete their own posts
            ]
        );

        add_role(
            'ead_organization',
            __( 'Organization', 'artpulse-management' ),
            [
                'read'         => true,  // Allows a user to read
                'edit_posts'   => false, // Allows user to edit their own posts
                'delete_posts' => false, // Allows user to delete their own posts
            ]
        );

        // Membership roles for the directory
        add_role(
            'member_basic',
            'Basic Member',
            [
                'read'         => true,
                'edit_posts'   => false,
                'delete_posts' => false,
            ]
        );

        add_role(
            'member_pro',
            'Pro Member',
            [
                'read'         => true,
                'upload_files' => true,
                'edit_posts'   => true,
                'delete_posts' => false,
            ]
        );

        add_role(
            'member_org',
            'Organization Member',
            [
                'read'          => true,
                'edit_posts'    => true,
                'upload_files'  => true,
                'publish_posts' => false,
            ]
        );
    }

    public static function remove_roles() {
        remove_role( 'ead_artist' );
        remove_role( 'ead_organization' );
    }
} // End class Plugin

// Initialize the main plugin class
add_action( 'plugins_loaded', [ Plugin::class, 'init' ] );

// Activation and Deactivation Hooks for custom roles
if ( class_exists( Plugin::class ) ) {
    register_activation_hook( __FILE__, [ Plugin::class, 'add_roles' ] );
    register_deactivation_hook( __FILE__, [ Plugin::class, 'remove_roles' ] );
}

// Ensure role capabilities are registered on init
if ( class_exists( RolesManager::class ) ) {
    add_action( 'init', [ RolesManager::class, 'init' ] );
}


add_action( 'template_redirect', function () {
    if ( get_query_var( 'submission-confirmation' ) !== '' ) {
        status_header( 200 );
        // Use your own plugin template here!
        include plugin_dir_path( __FILE__ ) . 'templates/confirmation-template.php';
        exit;
    }
} );

add_action( 'init', function () {
    add_rewrite_endpoint( 'organization-confirmation', EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'membership-confirmation', EP_ROOT | EP_PAGES );
} );

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'organization-confirmation';
    $vars[] = 'membership-confirmation';
    return $vars;
} );

add_action( 'template_redirect', function () {
    if ( get_query_var( 'organization-confirmation' ) !== '' ) {
        status_header( 200 );
        include plugin_dir_path( __FILE__ ) . 'templates/organization-confirmation-template.php';
        exit;
    }

    if ( get_query_var( 'membership-confirmation' ) !== '' ) {
        status_header( 200 );
        include plugin_dir_path( __FILE__ ) . 'templates/confirmation-template.php';
        exit;
    }
} );

// Hook into WordPress to enqueue our dashboard assets
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_dashboard_assets' );

function enqueue_dashboard_assets() {
    // Adjust this as needed depending on plugin structure
    $plugin_url = plugin_dir_url( __FILE__ ); // __FILE__ points to the current file

    // Enqueue CSS
    wp_enqueue_style(
        'ead-organization-dashboard',
        $plugin_url . 'assets/css/organization-dashboard.css',
        [],
        '1.0.0'
    );

    // Enqueue JS
    wp_enqueue_script(
        'ead-organization-dashboard',
        $plugin_url . 'assets/js/organization-dashboard.js',
        [ 'jquery' ],
        '1.0.0',
        true
    );
}

/*
 * The artpulse_save_cpt_meta_boxes() function was previously defined in
 * includes/meta-boxes.php, but that file has been removed. The original hook
 * and implementation below remain deprecated.
 *
 * add_action( 'save_post', 'artpulse_save_cpt_meta_boxes' );
 * function artpulse_save_cpt_meta_boxes( $post_id ) {
 *     ...
 * }
 *
 * The example dropdown function has also been removed.
 */
