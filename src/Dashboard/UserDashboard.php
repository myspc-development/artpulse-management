<?php
namespace EAD\Dashboard;

/**
 * Class UserDashboard
 *
 * Provides a simple dashboard for regular users to manage their profile and
 * discover local events.
 */
class UserDashboard {
    public static function init() {
        add_shortcode('ead_user_dashboard', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_action('admin_menu', [self::class, 'add_admin_menu']);
    }

    public static function enqueue_assets() {
        $plugin_url = EAD_PLUGIN_DIR_URL;
        wp_enqueue_style(
            'ead-user-dashboard',
            $plugin_url . 'assets/css/user-dashboard.css',
            [],
            EAD_MANAGEMENT_VERSION
        );
        wp_enqueue_script(
            'ead-user-dashboard',
            $plugin_url . 'assets/js/user-dashboard.js',
            ['jquery'],
            EAD_MANAGEMENT_VERSION,
            true
        );
        wp_localize_script(
            'ead-user-dashboard',
            'eadUserDashboard',
            [
                'restUrl' => esc_url_raw( rest_url( 'artpulse/v1/events' ) ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
            ]
        );
    }

    /**
     * Add the User Dashboard admin page.
     */
    public static function add_admin_menu() {
        add_menu_page(
            __( 'User Dashboard', 'artpulse-management' ),
            __( 'User Dashboard', 'artpulse-management' ),
            'read',
            'ead-user-dashboard',
            [ self::class, 'render_admin_page' ],
            'dashicons-admin-users',
            28
        );
    }

    /**
     * Render the admin page contents.
     */
    public static function render_admin_page() {
        echo '<div class="wrap">';
        echo '<h1>User Dashboard</h1>';
        echo do_shortcode( '[ead_user_dashboard]' );
        echo '</div>';
    }

    public static function render() {
        if ( ! is_user_logged_in() ) {
            return '<p>' . esc_html__( 'Please log in to view your dashboard.', 'artpulse-management' ) . '</p>';
        }

        ob_start();
        ?>
        <div class="ead-user-dashboard">
            <h2><?php esc_html_e( 'User Dashboard', 'artpulse-management' ); ?></h2>
            <div class="ead-user-filters">
                <label>
                    <?php esc_html_e( 'City', 'artpulse-management' ); ?>
                    <input type="text" id="ead-filter-city" />
                </label>
                <label>
                    <?php esc_html_e( 'State/Region', 'artpulse-management' ); ?>
                    <input type="text" id="ead-filter-state" />
                </label>
                <label>
                    <?php esc_html_e( 'Country', 'artpulse-management' ); ?>
                    <input type="text" id="ead-filter-country" />
                </label>
                <label>
                    <?php esc_html_e( 'Event Type', 'artpulse-management' ); ?>
                    <input type="text" id="ead-filter-type" />
                </label>
                <button id="ead-filter-submit" class="button">
                    <?php esc_html_e( 'Apply Filters', 'artpulse-management' ); ?>
                </button>
            </div>
            <div id="ead-user-events"></div>
            <h3><?php esc_html_e( 'Your Favorites', 'artpulse-management' ); ?></h3>
            <?php echo do_shortcode('[ead_favorites]'); ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

