<?php

namespace ArtPulse\Frontend;

class OrganizationEventForm {

    public static function register() {
        add_shortcode('ap_submit_event', [self::class, 'render']);
    }

    public static function render() {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to submit an event.</p>';
        }

        // Show success message if redirected after submission
        if (!empty($_GET['event_submitted'])) {
            echo '<div class="ap-success-message">✅ Event submitted successfully!</div>';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ap_event_nonce']) && wp_verify_nonce($_POST['ap_event_nonce'], 'submit_event')) {
            self::handle_submission();
        }

        ob_start();
        ?>
        <form method="post" enctype="multipart/form-data" class="ap-event-form">
            <?php wp_nonce_field('submit_event', 'ap_event_nonce'); ?>

            <label>Event Title*</label>
            <input type="text" name="title" required>

            <label>Description*</label>
            <textarea name="description" required></textarea>

            <label>Event Date*</label>
            <input type="date" name="event_date" required>

            <label>Location*</label>
            <input type="text" name="event_location" required>

            <label>Event Type</label>
            <select name="event_type">
                <option value="">Select Type</option>
                <?php
                $terms = get_terms(['taxonomy' => 'artpulse_event_type', 'hide_empty' => false]);
                foreach ($terms as $term) {
                    echo '<option value="' . esc_attr($term->term_id) . '">' . esc_html($term->name) . '</option>';
                }
                ?>
            </select>

            <label>Event Flyer</label>
            <input type="file" name="event_flyer">

            <button type="submit">Submit Event</button>
        </form>
        <?php
        return ob_get_clean();
    }

    public static function handle_submission() {
        $title = sanitize_text_field($_POST['title']);
        $description = wp_kses_post($_POST['description']);
        $date = sanitize_text_field($_POST['event_date']);
        $location = sanitize_text_field($_POST['event_location']);
        $type = intval($_POST['event_type']);

        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $description,
            'post_type'    => 'artpulse_event',
            'post_status'  => 'pending',
            'post_author'  => get_current_user_id(),
        ]);

        if (is_wp_error($post_id)) return;

        update_post_meta($post_id, '_ap_event_date', $date);
        update_post_meta($post_id, '_ap_event_location', $location);

        if ($type) {
            wp_set_post_terms($post_id, [$type], 'artpulse_event_type');
        }

        if (!empty($_FILES['event_flyer']['tmp_name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            
            $attachment_id = media_handle_upload('event_flyer', $post_id);
            if (!is_wp_error($attachment_id)) {
                set_post_thumbnail($post_id, $attachment_id);
            }
        }

        // Admin notification
        $admin_email = get_option('admin_email');
        $subject = 'New Event Submission on ArtPulse';
        $message = sprintf(
            "A new event was submitted:\n\nTitle: %s\n\nBy User ID: %d\n\nEdit: %s",
            $title,
            get_current_user_id(),
            admin_url("post.php?post={$post_id}&action=edit")
        );
        wp_mail($admin_email, $subject, $message);

        // User confirmation
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        $user_subject = 'Thanks for submitting your event';
        $user_message = "Hi {$current_user->display_name},\n\nThanks for submitting your event \"{$title}\". It is now pending review.";
        wp_mail($user_email, $user_subject, $user_message);

        wp_redirect(add_query_arg('event_submitted', '1', wp_get_referer()));
        exit;
    }
}
