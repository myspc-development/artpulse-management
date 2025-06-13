<?php
namespace EAD\Shortcodes;

class OrganizerDashboard {
    public static function register() {
        add_shortcode('ead_organizer_dashboard', [self::class, 'render']);
        add_action('wp_loaded', [self::class, 'handle_featured_request_submission']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_styles_and_scripts']); // Consolidated enqueue
    }

    public static function enqueue_styles_and_scripts() {
        $main_file = WP_PLUGIN_DIR . '/artpulse-management/artpulse-management.php';

        // Enqueue badge styles
        wp_enqueue_style(
            'ead-badges',
            plugins_url('assets/css/ead-badges.css', $main_file)
        );

        // Enqueue organization dashboard styles and scripts
        wp_enqueue_style(
            'ead-organization-dashboard',
            plugins_url('assets/css/organization-dashboard.css', $main_file),
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'ead-organization-dashboard',
            plugins_url('assets/js/organization-dashboard.js', $main_file),
            ['jquery'],
            '1.0.0',
            true
        );
    }

    // Handle featured request submissions from dashboard
    public static function handle_featured_request_submission() {
        if (
            isset($_POST['ead_request_featured_submit']) &&
            isset($_POST['ead_request_featured_listing_id']) &&
            wp_verify_nonce($_POST['_wpnonce'], 'ead_request_featured_' . intval($_POST['ead_request_featured_listing_id']))
        ) {
            $listing_id = intval($_POST['ead_request_featured_listing_id']);
            $user_id = get_current_user_id();
            $post = get_post($listing_id);
            if (
                $post &&
                $post->post_author == $user_id &&
                $post->post_type === 'ead_event'
            ) {
                // Flag the event as having requested featured status
                update_post_meta($listing_id, '_ead_featured_request', '1');
                update_post_meta($listing_id, '_ead_featured_payment_status', 'pending');

                // Store the request timestamp separately for reference
                update_post_meta($listing_id, '_ead_featured_request_time', current_time('mysql'));

                $checkout = \EAD\Integration\WooCommercePayments::generate_checkout_url( $listing_id );
                if ( $checkout ) {
                    update_post_meta( $listing_id, '_ead_featured_payment_url', esc_url_raw( $checkout ) );
                }
                // Notify admin
                wp_mail(
                    get_option('admin_email'),
                    __('Featured Listing Request', 'artpulse-management'),
                    sprintf(
                        "User %d (%s) has requested their event \"%s\" (ID %d) be featured.",
                        $user_id,
                        wp_get_current_user()->user_email,
                        get_the_title($listing_id),
                        $listing_id
                    )
                );
                // Show a confirmation message
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

    public static function render($atts) {
        if (!is_user_logged_in()) {
            return '<div class="ead-dashboard-card"><p>Please <a href="' . esc_url(wp_login_url(get_permalink())) . '">log in</a> to view your dashboard.</p></div>';
        }

        $args = [
            'post_type' => 'ead_event',
            'post_status' => ['publish', 'pending', 'draft'],
            'author' => get_current_user_id(),
            'posts_per_page' => 100,
        ];

        $events = get_posts($args);
        $dashboard_url = get_permalink();

        ob_start();
        ?>
        <div class="ead-dashboard-card">
            <h2>Your Events</h2>
            <a href="<?php echo esc_url(add_query_arg('create', '1', $dashboard_url)); ?>" class="button" style="margin-bottom: 16px;">+ Submit New Event</a>

            <?php if ($events): ?>
                <table class="ead-dashboard-table" style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Start Date</th>
                            <th>Featured</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><?php echo esc_html($event->post_title); ?></td>
                            <td>
                                <?php
                                if ($event->post_status === 'pending') {
                                    echo '<span style="color:#c90;">Pending Review</span>';
                                } elseif ($event->post_status === 'draft') {
                                    echo '<span style="color:#888;">Draft</span>';
                                } else {
                                    echo '<span style="color:#090;">Published</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html(get_post_meta($event->ID, 'event_start_date', true)); ?></td>
                            <td>
                                <?php
                                $payment_status = get_post_meta($event->ID, '_ead_featured_payment_status', true);
                                $payment_url    = get_post_meta($event->ID, '_ead_featured_payment_url', true);
                                if (get_post_meta($event->ID, '_ead_featured', true)) {
                                    echo '<span class="ead-badge-featured" style="color:#fff;background:#fd7e14;padding:2px 9px;border-radius:10px;font-size:12px;">Featured</span>';
                                } elseif ($payment_status === 'pending' && $payment_url) {
                                    echo '<a href="' . esc_url($payment_url) . '" class="button button-small" style="font-size:12px;">' . esc_html__('Pay Now', 'artpulse-management') . '</a>';
                                } elseif (get_post_meta($event->ID, '_ead_featured_request', true)) {
                                    echo '<span class="ead-badge-requested" style="color:#fff;background:#fbc02d;padding:2px 9px;border-radius:10px;font-size:12px;">Requested</span>';
                                } else {
                                    ?>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field('ead_request_featured_' . $event->ID); ?>
                                        <input type="hidden" name="ead_request_featured_listing_id" value="<?php echo esc_attr($event->ID); ?>">
                                        <button type="submit" name="ead_request_featured_submit" class="button button-small" style="font-size:12px;">Request</button>
                                    </form>
                                    <?php
                                }
                                ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg('edit', $event->ID, $dashboard_url)); ?>" class="button button-small">Edit</a>
                                <a href="<?php echo get_permalink($event->ID); ?>" class="button button-small" target="_blank">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>You have not created any events yet.</p>
            <?php endif; ?>
        </div>

        <style>
            .ead-dashboard-card {background:#fff;border-radius:12px;box-shadow:0 4px 14px rgba(0,0,0,0.09);padding:2rem;max-width:900px;margin:2rem auto;}
            .ead-dashboard-table th, .ead-dashboard-table td {padding:10px 6px;border-bottom:1px solid #eee;}
            .ead-dashboard-table th {background:#fafafa;}
            .button.button-small {font-size:12px;padding:3px 10px;margin-right:4px;}
        </style>
        <?php
        return ob_get_clean();
    }
}