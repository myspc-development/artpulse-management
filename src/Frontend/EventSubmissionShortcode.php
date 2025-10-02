<?php

namespace ArtPulse\Frontend;

class EventSubmissionShortcode {

    /**
     * Stores fallback notices when WooCommerce helpers are unavailable.
     *
     * @var array<int, array{type: string, message: string}>
     */
    protected static $fallback_notices = [];

    public static function register() {
        add_shortcode('ap_submit_event', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_scripts']); // Enqueue scripts and styles
        add_action('init', [self::class, 'maybe_handle_form']); // Handle form submission
    }

    public static function enqueue_scripts() {
        // Enqueue your styles and scripts here
        wp_enqueue_style('ap-event-form-styles', get_template_directory_uri() . '/assets/css/event-form.css'); // Replace with your CSS file
    }

    public static function render() {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to submit an event.</p>';
        }

        $user_id = get_current_user_id();

        $orgs = get_posts([
            'post_type'   => 'artpulse_org',
            'author'      => $user_id,
            'numberposts' => -1,
        ]);

        ob_start();
        ?>
        <?php $notices = self::get_fallback_notices(); ?>
        <div class="ap-form-messages" role="status" aria-live="polite">
            <?php foreach ($notices as $notice): ?>
                <div class="ap-notice ap-notice-<?= esc_attr($notice['type']); ?>">
                    <?= esc_html($notice['message']); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <form method="post" enctype="multipart/form-data" class="ap-event-form">
            <?php wp_nonce_field('ap_submit_event', 'ap_event_nonce'); ?>

            <label for="ap_event_title">Event Title</label>
            <input id="ap_event_title" type="text" name="event_title" required>

            <label for="ap_event_description">Description</label>
            <textarea id="ap_event_description" name="event_description" rows="5" required></textarea>

            <label for="ap_event_date">Date</label>
            <input id="ap_event_date" type="date" name="event_date" required>

            <label for="ap_event_location">Location</label>
            <input id="ap_event_location" type="text" name="event_location">

            <label for="ap_event_org">Organization</label>
            <select id="ap_event_org" name="event_org" required>
                <option value="">Select Organization</option>
                <?php foreach ($orgs as $org): ?>
                    <option value="<?= esc_attr($org->ID) ?>"><?= esc_html($org->post_title) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="ap_event_image">Image</label>
            <input id="ap_event_image" type="file" name="event_image">

            <button type="submit" name="ap_submit_event">Submit Event</button>
        </form>
        <?php
        return ob_get_clean();
    }

    public static function maybe_handle_form() {
        if (!is_user_logged_in() || !isset($_POST['ap_submit_event'])) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['ap_event_nonce']) || !wp_verify_nonce($_POST['ap_event_nonce'], 'ap_submit_event')) {
            wp_die('Security check failed.'); // Or redirect with an error message
            return;
        }

        $user_id = get_current_user_id();

        // Validate event data
        $event_title = sanitize_text_field($_POST['event_title']);
        $event_description = wp_kses_post($_POST['event_description']);
        $event_date = sanitize_text_field($_POST['event_date']);
        $event_location = sanitize_text_field($_POST['event_location']);
        $event_org = intval($_POST['event_org']);

        if (empty($event_title)) {
            self::add_notice('Please enter an event title.', 'error'); // Or use your notification system
            return; // Stop processing
        }

        if (empty($event_description)) {
            self::add_notice('Please enter an event description.', 'error');
            return;
        }

        if (empty($event_date)) {
            self::add_notice('Please enter an event date.', 'error');
            return;
        }
          // Validate the date format
        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $event_date)) {
            self::add_notice('Please enter a valid date in YYYY-MM-DD format.', 'error');
            return;
        }

        if ($event_org <= 0) {
            self::add_notice('Please select an organization.', 'error');
            return;
        }

        $post_id = wp_insert_post([
            'post_type'   => 'artpulse_event',
            'post_status' => 'pending',
            'post_title'  => $event_title,
            'post_content'=> $event_description,
            'post_author' => $user_id,
        ]);

        if (is_wp_error($post_id)) {
            error_log('Error creating event post: ' . $post_id->get_error_message());
            self::add_notice('Error submitting event. Please try again later.', 'error');
            return;
        }

        update_post_meta($post_id, '_ap_event_date', $event_date);
        update_post_meta($post_id, '_ap_event_location', $event_location);
        update_post_meta($post_id, '_ap_event_organization', $event_org);

        // Handle image upload
        if (!empty($_FILES['event_image']['name'])) {
            if ( ! function_exists( 'media_handle_upload' ) ) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
            }

            $attachment_id = media_handle_upload('event_image', $post_id);

            if (is_wp_error($attachment_id)) {
                error_log('Error uploading image: ' . $attachment_id->get_error_message());
                self::add_notice('Error uploading image. Please try again.', 'error');
            } else {
                set_post_thumbnail($post_id, $attachment_id);
            }
        }

        // Success message and redirect
        self::add_notice('Event submitted successfully! It is awaiting review.', 'success');

        if (function_exists('wc_add_notice')) {
            wp_safe_redirect(home_url('/thank-you-page')); // Replace with your desired URL
            exit;
        }

        // Without WooCommerce, allow the request to continue so the fallback notices render.
        return;
    }

    /**
     * Adds a notice using WooCommerce if available, otherwise falls back to an internal system.
     *
     * @param string $message
     * @param string $type
     * @return void
     */
    protected static function add_notice($message, $type = 'error') {
        if (function_exists('wc_add_notice')) {
            wc_add_notice($message, $type);
            return;
        }

        self::$fallback_notices[] = [
            'type'    => function_exists('sanitize_key') ? sanitize_key($type) : $type,
            'message' => function_exists('wp_strip_all_tags') ? wp_strip_all_tags($message) : $message,
        ];
    }

    /**
     * Retrieves notices stored in the fallback system.
     *
     * @return array<int, array{type: string, message: string}>
     */
    protected static function get_fallback_notices() {
        return self::$fallback_notices;
    }
}