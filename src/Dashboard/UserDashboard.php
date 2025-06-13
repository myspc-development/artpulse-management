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
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js',
            [],
            null,
            true
        );
        wp_enqueue_style(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css'
        );
        wp_enqueue_script(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js',
            [],
            null,
            true
        );
        wp_enqueue_script(
            'ead-user-dashboard',
            $plugin_url . 'assets/js/user-dashboard.js',
            ['jquery', 'chart-js', 'fullcalendar'],
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

    /**
     * Render the dashboard home panel based on user roles.
     *
     * @param array $roles Current user roles.
     * @return string HTML output for the dashboard landing panel.
     */
    public static function render_dashboard_home( $roles ) {
        ob_start();

        if ( in_array( 'administrator', $roles, true ) ) {
            ?>
            <h3>Admin Overview</h3>
            <ul>
                <li>Pending Artwork: <?php echo wp_count_posts( 'ead_artwork' )->pending; ?></li>
                <li>Total Users: <?php echo count_users()['total_users']; ?></li>
                <li>Recent Messages: <a href="#">View Inbox</a></li>
            </ul>
            <?php
        } elseif ( in_array( 'artist', $roles, true ) ) {
            ?>
            <h3>Welcome, Artist</h3>
            <ul>
                <li>Artworks Uploaded: <?php echo count_user_posts( get_current_user_id(), 'ead_artwork' ); ?></li>
                <li>Badges Earned: [Load via JS]</li>
                <li><a href="#" class="button">Upload New Artwork</a></li>
            </ul>
            <?php
        } elseif ( in_array( 'organization', $roles, true ) ) {
            ?>
            <h3>Organization Dashboard</h3>
            <ul>
                <li>Upcoming Events: <?php echo wp_count_posts( 'ead_event' )->publish; ?></li>
                <li>Total RSVPs: [Load via JS]</li>
                <li><a href="#" class="button">Create New Event</a></li>
            </ul>
            <?php
        } else {
            ?>
            <h3>Welcome to Your Dashboard</h3>
            <ul>
                <li>Favorites: [Load via JS]</li>
                <li>RSVPs This Month: [Load via JS]</li>
                <li>Recommended Events: <a href="#">Explore</a></li>
            </ul>
            <?php
        }

        return ob_get_clean();
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

            <?php $current_user = wp_get_current_user(); $roles = $current_user->roles; $is_admin = in_array( 'administrator', $roles, true ); $is_org = in_array( 'organization', $roles, true ); ?>
            <div class="ead-tabs">
                <button class="ead-tab-button active" data-tab="dashboard">Dashboard</button>
                <button class="ead-tab-button" data-tab="events"><?php esc_html_e( 'Events', 'artpulse-management' ); ?></button>
                <button class="ead-tab-button" data-tab="favorites"><?php esc_html_e( 'Favorites', 'artpulse-management' ); ?></button>
                <button class="ead-tab-button" data-tab="calendar">Calendar</button>
                <button class="ead-tab-button" data-tab="notifications"><?php esc_html_e('Notifications', 'artpulse-management'); ?></button>
                <button class="ead-tab-button" data-tab="profile"><?php esc_html_e( 'Profile', 'artpulse-management' ); ?></button>
                <button class="ead-tab-button" data-tab="uploads"><?php esc_html_e('My Uploads', 'artpulse-management'); ?></button>
                <?php if ( $is_admin || $is_org ) : ?>
                    <button class="ead-tab-button" data-tab="submissions"><?php esc_html_e( 'Submissions', 'artpulse-management' ); ?></button>
                <?php endif; ?>
            </div>
            <div class="ead-tab-content active" id="ead-tab-dashboard">
                <?php echo self::render_dashboard_home( $roles ); ?>
            </div>
            <div class="ead-tab-content" id="ead-tab-events">
                <?php self::render_events_section(); ?>
            </div>
            <div class="ead-tab-content" id="ead-tab-favorites">
                <p><?php esc_html_e( 'Your saved favorite items will appear here.', 'artpulse-management' ); ?></p>
            </div>
            <div class="ead-tab-content" id="ead-tab-calendar">
                <h3>Event Calendar</h3>
                <div id="ead-calendar-filters">
                    <label>
                        Show:
                        <select id="ead-filter-rsvp">
                            <option value="all">All Events</option>
                            <option value="rsvped">Only RSVPed</option>
                            <option value="not-rsvped">Not RSVPed</option>
                        </select>
                    </label>
                </div>
                <div id="ead-event-calendar"></div>
            </div>
            <div class="ead-tab-content" id="ead-tab-notifications">
                <p><?php esc_html_e( 'Loading notifications...', 'artpulse-management' ); ?></p>
            </div>
            <div class="ead-tab-content" id="ead-tab-uploads">
                <h3><?php esc_html_e('Upload Your Artwork', 'artpulse-management'); ?></h3>
                <form id="ead-upload-form" enctype="multipart/form-data">
                    <label>
                        <?php esc_html_e('Title', 'artpulse-management'); ?>
                        <input type="text" name="title" required />
                    </label>
                    <label>
                        <?php esc_html_e('Upload Image or File', 'artpulse-management'); ?>
                        <input type="file" name="file" accept="image/*,.pdf" required />
                    </label>
                    <button type="submit" class="button"><?php esc_html_e('Submit Artwork', 'artpulse-management'); ?></button>
                </form>
                <div id="ead-upload-feedback"></div>
            </div>
            <?php if ( $is_admin || $is_org ) : ?>
            <div class="ead-tab-content" id="ead-tab-submissions">
                <div class="ead-dashboard-widgets">
                    <div class="ead-widget">
                        <h4>🕓 Pending</h4>
                        <p id="widget-pending">&ndash;</p>
                    </div>
                    <div class="ead-widget">
                        <h4>✅ Approved (30d)</h4>
                        <p id="widget-approved">&ndash;</p>
                    </div>
                    <div class="ead-widget">
                        <h4>❌ Rejected (30d)</h4>
                        <p id="widget-rejected">&ndash;</p>
                    </div>
                </div>
                <h3><?php esc_html_e( 'Submissions This Month', 'artpulse-management' ); ?></h3>
                <canvas id="ead-submission-chart" height="150"></canvas>
                <h3><?php esc_html_e( 'Pending Submissions', 'artpulse-management' ); ?></h3>
                <div id="ead-submission-list"><?php esc_html_e( 'Loading...', 'artpulse-management' ); ?></div>
            </div>
            <?php endif; ?>
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
                <h3><?php esc_html_e('Your Activity', 'artpulse-management'); ?></h3>
                <canvas id="ead-activity-chart" width="400" height="200"></canvas>
                <h3><?php esc_html_e('Your Badges', 'artpulse-management'); ?></h3>
                <div id="ead-profile-badges"></div>
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

