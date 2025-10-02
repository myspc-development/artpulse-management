<?php

namespace ArtPulse\Frontend;

class EditEventShortcode {

    public static function register() {
        add_shortcode('ap_edit_event', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_scripts']);
        add_action('wp_ajax_ap_save_event', [self::class, 'handle_ajax']);
        add_action('wp_ajax_ap_delete_event', [self::class, 'handle_ajax_delete']);
    }

    public static function render($atts) {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);

        $post_id = intval($atts['id']);
        if (!$post_id || get_post_type($post_id) !== 'artpulse_event') {
            return '<p>Invalid event ID.</p>';
        }

        $event = get_post($post_id);
        $user_id = get_current_user_id();

        if (!current_user_can('edit_post', $post_id)) {
            return '<p>You do not have permission to edit this event.</p>';
        }

        $title = esc_attr($event->post_title);
        $content = esc_textarea($event->post_content);
        $date = esc_attr(get_post_meta($post_id, '_ap_event_date', true));
        $location = esc_attr(get_post_meta($post_id, '_ap_event_location', true));
        $event_type = wp_get_post_terms($post_id, 'artpulse_event_type', ['fields' => 'ids']);
        $event_type_id = !empty($event_type) ? $event_type[0] : '';

        ob_start();
        ?>
        <form id="ap-edit-event-form" data-post-id="<?php echo $post_id; ?>">
            <p>
                <label>Title<br>
                    <input type="text" name="title" value="<?php echo $title; ?>" required>
                </label>
            </p>
            <p>
                <label>Description<br>
                    <textarea name="content" required><?php echo $content; ?></textarea>
                </label>
            </p>
            <p>
                <label>Date<br>
                    <input type="date" name="date" value="<?php echo $date; ?>">
                </label>
            </p>
            <p>
                <label>Location<br>
                    <input type="text" name="location" value="<?php echo $location; ?>">
                </label>
            </p>
            <p>
                <label>Event Type<br>
                    <?php
                    wp_dropdown_categories([
                        'taxonomy' => 'artpulse_event_type',
                        'name' => 'event_type',
                        'selected' => $event_type_id,
                        'show_option_none' => 'Select type',
                        'hide_empty' => false,
                    ]);
                    ?>
                </label>
            </p>
            <p class="ap-edit-event-error" style="color:red;"></p>
            <p>
                <button type="submit">Save Changes</button>
            </p>
        </form>
        <?php
        return ob_get_clean();
    }

    public static function enqueue_scripts() {
        wp_enqueue_script(
            'ap-edit-event-js',
            plugins_url('assets/js/ap-edit-event.js', ARTPULSE_PLUGIN_FILE),
            ['jquery'],
            '1.0.0',
            true
        );
        wp_localize_script('ap-edit-event-js', 'APEditEvent', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ap_edit_event_nonce')
        ]);
    }

    public static function handle_ajax() {
        check_ajax_referer('ap_edit_event_nonce', 'nonce');

        $post_id = intval($_POST['post_id']);
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $title = sanitize_text_field($_POST['title']);
        $content = sanitize_textarea_field($_POST['content']);
        $date = sanitize_text_field($_POST['date']);
        $location = sanitize_text_field($_POST['location']);
        $event_type = intval($_POST['event_type']);

        if (!$title || !$content) {
            wp_send_json_error(['message' => 'Title and content are required.']);
        }

        wp_update_post([
            'ID' => $post_id,
            'post_title' => $title,
            'post_content' => $content,
        ]);

        update_post_meta($post_id, '_ap_event_date', $date);
        update_post_meta($post_id, '_ap_event_location', $location);

        if ($event_type) {
            wp_set_post_terms($post_id, [$event_type], 'artpulse_event_type');
        }

        wp_send_json_success(['message' => 'Event updated.']);
    }
    
    public static function handle_ajax_delete() {
        if (!current_user_can('delete_post', $_POST['post_id'])) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        check_ajax_referer('ap_edit_event_nonce', 'nonce');

        $post_id = intval($_POST['post_id']);
        if (get_post_type($post_id) !== 'artpulse_event') {
            wp_send_json_error(['message' => 'Invalid event.']);
        }

        wp_delete_post($post_id, true);
        wp_send_json_success(['message' => 'Deleted']);
    }
}
