<?php
namespace EAD\Dashboard;

/**
 * Class OrganizationDashboard
 *
 * Handles the organization dashboard functionality.
 *
 * @package EAD\Dashboard
 */
class OrganizationDashboard {

    /**
     * Initialize the Organization Dashboard.
     */
    public static function init() {
        if (method_exists(self::class, 'render_dashboard')) {
            add_shortcode('ead_organization_dashboard', [self::class, 'render_dashboard']);
        }
        if (method_exists(self::class, 'enqueue_assets')) {
            add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
        }
        if (method_exists(self::class, 'add_admin_menu')) {
            add_action('admin_menu', [self::class, 'add_admin_menu']);
        }
        if (method_exists(self::class, 'handle_profile_submission')) {
            add_action('template_redirect', [self::class, 'handle_profile_submission']);
        }
        if (method_exists(self::class, 'handle_events_bulk_action')) {
            add_action('template_redirect', [self::class, 'handle_events_bulk_action']);
        }
        if (method_exists(self::class, 'export_rsvps_csv')) {
            add_action('admin_post_ead_export_rsvps_csv', [self::class, 'export_rsvps_csv']);
        }
        if (method_exists(self::class, 'export_event_analytics_csv')) {
            add_action('admin_post_ead_export_event_analytics', [self::class, 'export_event_analytics_csv']);
        }
        if (method_exists(self::class, 'handle_featured_request_submission')) {
            add_action('wp_loaded', [self::class, 'handle_featured_request_submission']);
        }
    }

    /**
     * Enqueue dashboard CSS and JS assets.
     */
    public static function enqueue_assets() {
        $plugin_url = EAD_PLUGIN_DIR_URL;

        wp_enqueue_style(
            'ead-organization-dashboard',
            $plugin_url . 'assets/css/organization-dashboard.css',
            [],
            defined('EAD_MANAGEMENT_VERSION') ? EAD_MANAGEMENT_VERSION : '1.0.0'
        );

        wp_enqueue_style(
            'ead-organization-gallery',
            $plugin_url . 'assets/css/organization-gallery.css',
            [],
            defined('EAD_MANAGEMENT_VERSION') ? EAD_MANAGEMENT_VERSION : '1.0.0'
        );

        wp_enqueue_script(
            'ead-organization-gallery',
            $plugin_url . 'assets/js/organization-gallery.js',
            ['jquery'],
            defined('EAD_MANAGEMENT_VERSION') ? EAD_MANAGEMENT_VERSION : '1.0.0',
            true
        );

        wp_enqueue_script(
            'ead-organization-dashboard',
            $plugin_url . 'assets/js/organization-dashboard.js',
            ['jquery', 'ead-organization-gallery'],
            defined('EAD_MANAGEMENT_VERSION') ? EAD_MANAGEMENT_VERSION : '1.0.0',
            true
        );

        wp_localize_script('ead-organization-dashboard', 'eadOrganizationDashboardApi', [
            'restUrl' => esc_url_raw(rest_url('artpulse/v1/')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }

    /**
     * Add the Organization Dashboard to the WordPress admin menu.
     *
     * The page is only visible to users with the `view_dashboard` capability.
     */
    public static function add_admin_menu() {
        add_menu_page(
            __('Org Dashboard Admin', 'artpulse-management'),
            __('Org Dashboard Admin', 'artpulse-management'),
            'view_dashboard',
            'ead-organization-dashboard',
            [self::class, 'render_admin_page'],
            'dashicons-groups',
            26
        );
    }

    /**
     * Render the Organization Dashboard admin page.
     */
    public static function render_admin_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Org Dashboard Admin', 'artpulse-management') . '</h1>';
        echo do_shortcode('[ead_organization_dashboard]');
        echo '</div>';
    }

    /**
     * Render the organization dashboard shortcode.
     *
     * Requires the `view_dashboard` capability.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function render_dashboard($atts) {
        if (!current_user_can('view_dashboard')) {
            return '<p>' . esc_html__('You do not have permission to view this dashboard.', 'artpulse-management') . '</p>';
        }

        // Show success message if transient is set
        if ($message = get_transient('ead_profile_success_' . get_current_user_id())) {
            echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
            delete_transient('ead_profile_success_' . get_current_user_id());
        }

        // Show error message from bulk actions if set
        if ($error = get_transient('ead_events_error_' . get_current_user_id())) {
            echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
            delete_transient('ead_events_error_' . get_current_user_id());
        }

        ob_start();
        ?>
        <div id="ead-organization-dashboard" class="ead-organization-dashboard">
            <h2><?php esc_html_e('Organization Dashboard', 'artpulse-management'); ?></h2>
            <p><?php esc_html_e('This is where organizations can manage their profile, events, and metrics.', 'artpulse-management'); ?></p>

            <section class="ead-dashboard-section">
                <h3><?php esc_html_e('Profile Management', 'artpulse-management'); ?></h3>
                <?php
                // Guard prevents fatal errors if a subclass overrides or omits the method.
                if (method_exists(self::class, 'render_profile_form')) {
                    self::render_profile_form();
                }
                ?>
            </section>

            <section class="ead-dashboard-section">
                <h3><?php esc_html_e('Events Management', 'artpulse-management'); ?></h3>
                <?php
                // Guard prevents fatal errors if a subclass overrides or omits the method.
                if (method_exists(self::class, 'render_events_table')) {
                    self::render_events_table();
                }
                ?>
            </section>

            <section class="ead-dashboard-section">
                <h3><?php esc_html_e('Performance Metrics', 'artpulse-management'); ?></h3>
                <?php
                // Guard prevents fatal errors if a subclass overrides or omits the method.
                if (method_exists(self::class, 'render_metrics')) {
                    self::render_metrics();
                }
                ?>
            </section>

            <section class="ead-dashboard-section">
                <h3><?php esc_html_e('Event RSVPs', 'artpulse-management'); ?></h3>
                <?php
                // Guard prevents fatal errors if a subclass overrides or omits the method.
                if (method_exists(self::class, 'render_rsvps')) {
                    self::render_rsvps();
                }
                ?>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the profile management form for the current organization.
     */
    public static function render_profile_form() {
        $user_id = get_current_user_id();
        $org_id  = get_user_meta($user_id, 'ead_organisation_id', true);

        if (!$org_id) {
            echo '<p>' . esc_html__('No organization is linked to your profile.', 'artpulse-management') . '</p>';
            return;
        }

        $fields = apply_filters('ead_organization_profile_fields', [
            'ead_org_name'                      => __('Organization Name', 'artpulse-management'),
            'ead_org_email'                     => __('Organization Email', 'artpulse-management'),
            'ead_org_description'               => __('Short Description', 'artpulse-management'),
            'ead_org_phone'                     => __('Organization Phone', 'artpulse-management'),
            'ead_org_website_url'               => __('Website', 'artpulse-management'),
            'ead_org_primary_contact_email'     => __('Primary Contact Email', 'artpulse-management'),
            'ead_org_facebook_url'              => __('Facebook URL', 'artpulse-management'),
            // Add more fields as needed...
        ]);

        $values = [];
        foreach ($fields as $key => $label) {
            $values[$key] = get_post_meta($org_id, $key, true);
        }
        ?>
        <form method="post" class="ead-organization-profile-form">
            <?php wp_nonce_field('ead_organization_profile_update', 'ead_organization_profile_nonce'); ?>
            <?php foreach ($fields as $key => $label): ?>
                <div class="form-group">
                    <label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                    <?php if ($key === 'ead_org_description'): ?>
                        <textarea id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" rows="4"><?php echo esc_textarea($values[$key]); ?></textarea>
                    <?php else: ?>
                        <input type="text" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($values[$key]); ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit" name="ead_organization_profile_submit" class="button button-primary">
                <?php esc_html_e('Save Profile', 'artpulse-management'); ?>
            </button>
        </form>
        <?php
        $payment_status = get_post_meta($org_id, '_ead_featured_payment_status', true);
        $payment_url    = get_post_meta($org_id, '_ead_featured_payment_url', true);
        if (get_post_meta($org_id, '_ead_featured', true)) {
            echo '<span class="ead-badge-featured"><span class="dashicons dashicons-star-filled"></span>' . esc_html__('Featured', 'artpulse-management') . '</span>';
        } elseif ($payment_status === 'pending' && $payment_url) {
            echo '<a href="' . esc_url($payment_url) . '" class="button button-small">' . esc_html__('Complete Payment', 'artpulse-management') . '</a>';
        } elseif (get_post_meta($org_id, '_ead_featured_request', true)) {
            echo '<span class="ead-badge-requested"><span class="dashicons dashicons-star-filled"></span>' . esc_html__('Requested', 'artpulse-management') . '</span>';
        } else {
            ?>
            <form method="post" style="margin-top:10px;">
                <?php wp_nonce_field('ead_request_featured_' . $org_id); ?>
                <input type="hidden" name="ead_request_featured_listing_id" value="<?php echo esc_attr($org_id); ?>">
                <button type="submit" name="ead_request_featured_submit" class="button"><?php esc_html_e('Request Featured', 'artpulse-management'); ?></button>
            </form>
            <?php
        }
        ?>
    }

    /**
     * Handle the profile update form submission.
     */
    public static function handle_profile_submission() {
        if (
            !is_user_logged_in() ||
            $_SERVER['REQUEST_METHOD'] !== 'POST' ||
            empty($_POST['ead_organization_profile_nonce']) ||
            !wp_verify_nonce($_POST['ead_organization_profile_nonce'], 'ead_organization_profile_update') ||
            empty($_POST['ead_organization_profile_submit'])
        ) {
            return;
        }

        $user_id = get_current_user_id();
        $org_id  = get_user_meta($user_id, 'ead_organisation_id', true);

        if (!$org_id) {
            return;
        }

        $allowed_fields = apply_filters('ead_organization_profile_save_fields', [
            'ead_org_name', 'ead_org_email', 'ead_org_phone', 'ead_org_description',
            'ead_org_website_url', 'ead_org_primary_contact_email',
            'ead_org_facebook_url'
            // Add the rest here...
        ]);

        foreach ($allowed_fields as $key) {
            if (isset($_POST[$key])) {
                $value = sanitize_text_field($_POST[$key]);
                update_post_meta($org_id, $key, $value);
                if ($key === 'ead_org_name') {
                    wp_update_post([
                        'ID' => $org_id,
                        'post_title' => $value,
                    ]);
                }
            }
        }

        // Set a transient for success message
        set_transient('ead_profile_success_' . $user_id, __('Organization profile updated successfully.', 'artpulse-management'), 30);

        // Redirect to avoid resubmission
        wp_redirect(wp_get_referer());
        exit;
    }

    /**
     * Render the events management table (placeholder).
     */
    public static function render_events_table() {
        $user_id = get_current_user_id();

        $filter = isset($_GET['event_filter']) ? sanitize_text_field($_GET['event_filter']) : 'all';

        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
        $order   = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';

        if (! in_array($orderby, ['date', 'title', 'event_end_date'], true)) {
            $orderby = 'date';
        }
        if (! in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        $args = [
            'post_type'      => 'ead_event',
            'post_status'    => ['publish', 'pending', 'draft'],
            'posts_per_page' => -1,
            'author'         => $user_id,
            'orderby'        => $orderby === 'event_end_date' ? 'meta_value' : $orderby,
            'order'          => $order,
        ];
        if ($orderby === 'event_end_date') {
            $args['meta_key'] = 'event_end_date';
        }

        $today = date('Y-m-d');
        if ($filter === 'upcoming') {
            $args['meta_key']     = 'event_end_date';
            $args['meta_value']   = $today;
            $args['meta_compare'] = '>=';
        } elseif ($filter === 'expired') {
            $args['meta_key']     = 'event_end_date';
            $args['meta_value']   = $today;
            $args['meta_compare'] = '<';
        }

        $events = get_posts($args);

        echo '<form method="get" class="ead-events-filter" style="margin-bottom:15px;">';
        echo '<label for="event_filter" class="screen-reader-text">' . esc_html__('Filter Events', 'artpulse-management') . '</label>';
        echo '<select name="event_filter" id="event_filter" onchange="this.form.submit()">';
        $options = [
            'all'      => __('All Events', 'artpulse-management'),
            'upcoming' => __('Upcoming', 'artpulse-management'),
            'expired'  => __('Expired', 'artpulse-management'),
        ];
        foreach ($options as $val => $label) {
            $selected = $filter === $val ? 'selected' : '';
            echo '<option value="' . esc_attr($val) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</form>';

        if (empty($events)) {
            echo '<p>' . esc_html__('No events found.', 'artpulse-management') . '</p>';
            return;
        }

        echo '<form method="post">';
        wp_nonce_field('ead_events_bulk_action', 'ead_events_bulk_nonce');
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th class="check-column"><input type="checkbox" id="ead-select-all"></th>';

        $base_url = remove_query_arg(["orderby", "order"]);
        $title_order = ($orderby === 'title' && $order === 'ASC') ? 'DESC' : 'ASC';
        $title_link  = add_query_arg(['orderby' => 'title', 'order' => $title_order], $base_url);
        echo '<th><a href="' . esc_url($title_link) . '">' . esc_html__('Event', 'artpulse-management') . '</a></th>';

        echo '<th>' . esc_html__('Status', 'artpulse-management') . '</th>';

        $date_order = ($orderby === 'event_end_date' && $order === 'ASC') ? 'DESC' : 'ASC';
        $date_link  = add_query_arg(['orderby' => 'event_end_date', 'order' => $date_order], $base_url);
        echo '<th><a href="' . esc_url($date_link) . '">' . esc_html__('End Date', 'artpulse-management') . '</a></th>';

        echo '<th>' . esc_html__('Actions', 'artpulse-management') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($events as $event) {
            echo '<tr>';
            echo '<th class="check-column"><input type="checkbox" name="event_ids[]" value="' . esc_attr($event->ID) . '"></th>';
            echo '<td>' . esc_html($event->post_title);
            if (get_post_meta($event->ID, '_ead_featured', true)) {
                echo ' <span class="ead-badge-featured"><span class="dashicons dashicons-star-filled"></span>' . esc_html__('Featured', 'artpulse-management') . '</span>';
            }
            echo '</td>';
            $status_badge = '<span class="ead-badge-status ead-badge-status-' . esc_attr($event->post_status) . '"><span class="dashicons dashicons-info"></span> ' . esc_html(ucfirst($event->post_status)) . '</span>';
            echo '<td>' . $status_badge . '</td>';
            echo '<td>' . esc_html(get_post_meta($event->ID, 'event_end_date', true)) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url(get_edit_post_link($event->ID)) . '" class="button button-small">' . esc_html__('Edit', 'artpulse-management') . '</a> ';
            echo '<a href="' . esc_url(get_permalink($event->ID)) . '" class="button button-small" target="_blank">' . esc_html__('View', 'artpulse-management') . '</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<div style="margin-top:10px;">';
        echo '<select name="bulk_action">';
        echo '<option value="">' . esc_html__('Bulk Actions', 'artpulse-management') . '</option>';
        echo '<option value="feature">' . esc_html__('Feature', 'artpulse-management') . '</option>';
        echo '<option value="delete">' . esc_html__('Delete', 'artpulse-management') . '</option>';
        echo '</select> ';
        echo '<input type="submit" name="ead_events_bulk_submit" class="button" value="' . esc_attr__('Apply', 'artpulse-management') . '">';
        echo '</div>';
        echo '</form>';
        ?>
        <script>
        document.getElementById('ead-select-all')?.addEventListener('change', function(e){
            document.querySelectorAll('input[name="event_ids[]"]').forEach(cb=>cb.checked=e.target.checked);
        });
        </script>
        <?php
    }

    /**
     * Render the performance metrics widgets (placeholder).
     */
    public static function render_metrics() {
        echo '<div id="ead-organization-metrics-widgets"></div>';
        echo '<div id="ead-event-analytics"></div>';
        $export_url = add_query_arg(
            [
                'action'  => 'ead_export_event_analytics',
                '_wpnonce' => wp_create_nonce('ead_export_event_analytics'),
            ],
            admin_url('admin-post.php')
        );
        echo '<p>';
        echo '<button id="ead-refresh-metrics" class="button">' . esc_html__('Refresh Metrics', 'artpulse-management') . '</button> ';
        echo '<a href="' . esc_url($export_url) . '" class="button">' . esc_html__('Export CSV', 'artpulse-management') . '</a>';
        echo '</p>';
    }

    /**
     * Render container for the RSVP list.
     */
    public static function render_rsvps() {
        $export_url = add_query_arg(
            [
                'action'  => 'ead_export_rsvps_csv',
                '_wpnonce' => wp_create_nonce('ead_export_rsvps'),
            ],
            admin_url('admin-post.php')
        );
        $events = get_posts([
            'post_type'      => 'ead_event',
            'post_status'    => ['publish', 'pending', 'draft'],
            'posts_per_page' => -1,
            'author'         => get_current_user_id(),
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ]);
        ?>
        <form id="ead-dashboard-rsvp-form" style="margin-bottom:15px;">
            <?php wp_nonce_field('ead_event_rsvp', 'ead_event_rsvp_nonce'); ?>
            <label for="ead-dashboard-rsvp-email" class="screen-reader-text"><?php esc_html_e('Email Address', 'artpulse-management'); ?></label>
            <input type="email" id="ead-dashboard-rsvp-email" name="email" placeholder="<?php esc_attr_e('Email Address', 'artpulse-management'); ?>" required>
            <label for="ead-dashboard-rsvp-event" class="screen-reader-text"><?php esc_html_e('Select Event', 'artpulse-management'); ?></label>
            <select id="ead-dashboard-rsvp-event" name="event_id" required>
                <option value=""><?php esc_html_e('Select Event', 'artpulse-management'); ?></option>
                <?php foreach ($events as $ev_id): ?>
                    <option value="<?php echo esc_attr($ev_id); ?>"><?php echo esc_html(get_the_title($ev_id)); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button"><?php esc_html_e('Add RSVP', 'artpulse-management'); ?></button>
            <span id="ead-dashboard-rsvp-message" style="margin-left:10px;"></span>
        </form>
        <div id="ead-my-event-rsvps" class="my-event-rsvps">
            <p><?php esc_html_e('Loading RSVPs...', 'artpulse-management'); ?></p>
        </div>
        <p><a href="<?php echo esc_url($export_url); ?>" class="button"><?php esc_html_e('Export All RSVPs', 'artpulse-management'); ?></a></p>
        <?php
    }

    /**
     * Handle bulk actions on events.
     */
    public static function handle_events_bulk_action() {
        if (
            ! is_user_logged_in() ||
            $_SERVER['REQUEST_METHOD'] !== 'POST' ||
            empty($_POST['ead_events_bulk_nonce']) ||
            ! wp_verify_nonce($_POST['ead_events_bulk_nonce'], 'ead_events_bulk_action') ||
            empty($_POST['bulk_action']) ||
            empty($_POST['event_ids']) ||
            ! is_array($_POST['event_ids']) ||
            ! current_user_can('manage_events')
        ) {
            return;
        }

        $user_id = get_current_user_id();
        $action   = sanitize_text_field($_POST['bulk_action']);
        $skipped  = false;

        foreach ((array) $_POST['event_ids'] as $event_id) {
            $event_id = intval($event_id);
            if (get_post_type($event_id) !== 'ead_event') {
                continue;
            }

            if ((int) get_post_field('post_author', $event_id) !== $user_id) {
                $skipped = true;
                continue;
            }

            if ($action === 'feature') {
                update_post_meta($event_id, '_ead_featured', '1');
            } elseif ($action === 'delete') {
                wp_delete_post($event_id, true);
            }
        }

        if ($skipped) {
            set_transient(
                'ead_events_error_' . $user_id,
                __('Some selected events could not be processed due to permission issues.', 'artpulse-management'),
                30
            );
        }

        wp_redirect(wp_get_referer());
        exit;
    }

    public static function handle_featured_request_submission() {
        if (
            isset($_POST['ead_request_featured_submit']) &&
            isset($_POST['ead_request_featured_listing_id']) &&
            wp_verify_nonce($_POST['_wpnonce'], 'ead_request_featured_' . intval($_POST['ead_request_featured_listing_id']))
        ) {
            $listing_id = intval($_POST['ead_request_featured_listing_id']);
            $user_id    = get_current_user_id();
            $post       = get_post($listing_id);

            if (
                $post &&
                $post->post_author == $user_id &&
                $post->post_type === 'ead_organization'
            ) {
                update_post_meta($listing_id, '_ead_featured_request', '1');
                update_post_meta($listing_id, '_ead_featured_payment_status', 'pending');
                update_post_meta($listing_id, '_ead_featured_request_time', current_time('mysql'));

                $checkout = \EAD\Integration\WooCommercePayments::generate_checkout_url( $listing_id );
                if ( $checkout ) {
                    update_post_meta( $listing_id, '_ead_featured_payment_url', esc_url_raw( $checkout ) );
                }

                wp_mail(
                    get_option('admin_email'),
                    __('Featured Listing Request', 'artpulse-management'),
                    sprintf(
                        'User %d (%s) has requested their organization "%s" (ID %d) be featured.',
                        $user_id,
                        wp_get_current_user()->user_email,
                        get_the_title($listing_id),
                        $listing_id
                    )
                );

                add_action('wp_footer', function() use ( $checkout ) {
                    $msg = esc_html__('Your featured request was sent for review.', 'artpulse-management');
                    if ( $checkout ) {
                        $msg = esc_html__('Proceed to payment to complete your request.', 'artpulse-management') . ' <a href="' . esc_url( $checkout ) . '" class="button">' . esc_html__('Pay Now', 'artpulse-management') . '</a>';
                    }
                    echo '<div class="ead-featured-confirm" style="background:#eaffea;color:#308000;border-radius:8px;padding:10px 14px;position:fixed;bottom:32px;right:32px;z-index:99;">' . $msg . '</div>';
                });
            }
        }
    }

    /**
     * Export RSVPs to CSV for the current user's events.
     */
    public static function export_rsvps_csv() {
        if (
            ! current_user_can('ead_manage_rsvps') ||
            ! isset($_GET['_wpnonce']) ||
            ! wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'ead_export_rsvps')
        ) {
            wp_die(__('Permission denied.', 'artpulse-management'));
        }

        $user_id  = get_current_user_id();
        $event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;

        $events = get_posts([
            'post_type'      => 'ead_event',
            'author'         => $user_id,
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'post_status'    => ['publish','pending','draft'],
        ]);

        if ($event_id && ! in_array($event_id, $events, true)) {
            wp_die(__('Invalid event.', 'artpulse-management'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ead_rsvps';

        if ($event_id) {
            $query = $wpdb->prepare("SELECT * FROM {$table} WHERE event_id = %d ORDER BY rsvp_date DESC", $event_id);
        } else {
            if (empty($events)) {
                wp_die(__('No events found.', 'artpulse-management'));
            }
            $placeholders = implode(',', array_fill(0, count($events), '%d'));
            $query        = $wpdb->prepare("SELECT * FROM {$table} WHERE event_id IN ($placeholders) ORDER BY rsvp_date DESC", $events);
        }

        $rows = $wpdb->get_results($query);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="rsvps-export.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Event ID', 'Event Title', 'Email', 'Date']);

        foreach ($rows as $row) {
            $title = get_the_title((int) $row->event_id);
            fputcsv($out, [$row->event_id, $title, $row->rsvp_email, $row->rsvp_date]);
        }

        fclose($out);
        exit;
    }

    /**
     * Export analytics for the current user's events as CSV.
     */
    public static function export_event_analytics_csv() {
        if (
            ! current_user_can('view_dashboard') ||
            ! isset($_GET['_wpnonce']) ||
            ! wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'ead_export_event_analytics')
        ) {
            wp_die(__('Permission denied.', 'artpulse-management'));
        }

        $user_id  = get_current_user_id();
        $event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;

        $events = get_posts([
            'post_type'      => 'ead_event',
            'author'         => $user_id,
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'post_status'    => ['publish','pending','draft'],
        ]);

        if ($event_id && ! in_array($event_id, $events, true)) {
            wp_die(__('Invalid event.', 'artpulse-management'));
        }

        if ($event_id) {
            $events = [$event_id];
        }

        if (empty($events)) {
            wp_die(__('No events found.', 'artpulse-management'));
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="event-analytics.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Event ID', 'Event Title', 'Views', 'Clicks']);

        foreach ($events as $ev_id) {
            fputcsv($out, [
                $ev_id,
                get_the_title($ev_id),
                (int) get_post_meta($ev_id, '_ead_view_count', true),
                (int) get_post_meta($ev_id, '_ead_click_count', true),
            ]);
        }

        fclose($out);
        exit;
    }
}
