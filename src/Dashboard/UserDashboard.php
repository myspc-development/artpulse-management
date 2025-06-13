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
                'restUrl'  => esc_url_raw( rest_url( 'artpulse/v1' ) ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'favorites' => get_user_meta( get_current_user_id(), 'ead_favorites', true ) ?: [],
                'rsvps'    => get_user_meta( get_current_user_id(), 'ead_rsvps', true ) ?: [],
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
            <div id="ead-loader" class="ead-loader" style="display: none;">Loading...</div>

            <div class="ead-tabs">
                <button class="ead-tab-button active" data-tab="events"><?php esc_html_e( 'Events', 'artpulse-management' ); ?></button>
                <button class="ead-tab-button" data-tab="favorites"><?php esc_html_e( 'Favorites', 'artpulse-management' ); ?></button>
                <button class="ead-tab-button" data-tab="notifications"><?php esc_html_e('Notifications', 'artpulse-management'); ?></button>
                <button class="ead-tab-button" data-tab="profile"><?php esc_html_e( 'Profile', 'artpulse-management' ); ?></button>
            </div>

            <div class="ead-tab-content active" id="ead-tab-events">
                <?php self::render_events_section(); ?>
            </div>
            <div class="ead-tab-content" id="ead-tab-favorites">
                <p><?php esc_html_e( 'Your saved favorite items will appear here.', 'artpulse-management' ); ?></p>
            </div>
            <div class="ead-tab-content" id="ead-tab-notifications">
                <p><?php esc_html_e( 'Loading notifications...', 'artpulse-management' ); ?></p>
            </div>
            <div class="ead-tab-content" id="ead-tab-profile">
                <div class="ead-profile-summary">
                    <h3><?php esc_html_e('Your Profile', 'artpulse-management'); ?></h3>
                    <form id="ead-profile-form">
                        <label>
                            <?php esc_html_e('Display Name', 'artpulse-management'); ?>
                            <input type="text" id="ead-profile-name" name="display_name" value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>" />
                        </label>
                        <label>
                            <?php esc_html_e('City', 'artpulse-management'); ?>
                            <input type="text" id="ead-profile-city" name="city" value="<?php echo esc_attr(get_user_meta(get_current_user_id(), 'ead_city', true)); ?>" />
                        </label>
                        <label>
                            <?php esc_html_e('Country', 'artpulse-management'); ?>
                            <input type="text" id="ead-profile-country" name="country" value="<?php echo esc_attr(get_user_meta(get_current_user_id(), 'ead_country', true)); ?>" />
                        </label>
                        <label>
                            <input type="checkbox" id="ead-profile-newsletter" name="newsletter" <?php checked(get_user_meta(get_current_user_id(), 'ead_newsletter', true), 'yes'); ?> />
                            <?php esc_html_e('Subscribe to email updates', 'artpulse-management'); ?>
                        </label>
                        <button type="submit" class="button"><?php esc_html_e('Save Changes', 'artpulse-management'); ?></button>
                    </form>
                </div>
                <div class="ead-profile-password">
                    <h3><?php esc_html_e('Change Password', 'artpulse-management'); ?></h3>
                    <form id="ead-password-form">
                        <label>
                            <?php esc_html_e('Current Password', 'artpulse-management'); ?>
                            <input type="password" id="ead-password-current" name="current_password" required />
                        </label>
                        <label>
                            <?php esc_html_e('New Password', 'artpulse-management'); ?>
                            <input type="password" id="ead-password-new" name="new_password" required minlength="6" />
                        </label>
                        <label>
                            <?php esc_html_e('Confirm New Password', 'artpulse-management'); ?>
                            <input type="password" id="ead-password-confirm" name="confirm_password" required />
                        </label>
                        <button type="submit" class="button"><?php esc_html_e('Update Password', 'artpulse-management'); ?></button>
                    </form>
                </div>
            </div>
            <div id="ead-toast" class="ead-toast" style="display: none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the events section of the dashboard.
     */
    public static function render_events_section() {
        ?>
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
            <label>
                <?php esc_html_e( 'Start Date', 'artpulse-management' ); ?>
                <input type="date" id="ead-filter-start-date" />
            </label>
            <label>
                <?php esc_html_e( 'End Date', 'artpulse-management' ); ?>
                <input type="date" id="ead-filter-end-date" />
            </label>
            <label>
                <?php esc_html_e( 'Sort By', 'artpulse-management' ); ?>
                <select id="ead-filter-sort">
                    <option value="date"><?php esc_html_e( 'Date', 'artpulse-management' ); ?></option>
                    <option value="popularity"><?php esc_html_e( 'Popularity', 'artpulse-management' ); ?></option>
                </select>
            </label>
            <button id="ead-filter-submit" class="button">
                <?php esc_html_e( 'Apply Filters', 'artpulse-management' ); ?>
            </button>
        </div>
        <div id="ead-user-events"></div>
        <h3><?php esc_html_e( 'Recommended For You', 'artpulse-management' ); ?></h3>
        <div id="ead-user-recommendations"></div>
        <h3><?php esc_html_e( 'Your Favorites', 'artpulse-management' ); ?></h3>
        <?php echo do_shortcode('[ead_favorites]'); ?>
        <?php
    }
}

