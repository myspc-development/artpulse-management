<?php
namespace EAD\Shortcodes;

use EAD\Shortcodes\HoneypotTrait;

class EditEventForm {
    use HoneypotTrait;
    public static function register() {
        add_shortcode('ead_edit_event_form', [self::class, 'render']);
        add_action('wp_loaded', [self::class, 'handle_edit']);
    }

    public static function handle_edit() {
        if (!isset($_POST['ead_edit_event_nonce'], $_POST['event_id'])) return;
        if (!is_user_logged_in()) return;

        $event_id = intval($_POST['event_id']);
        if (!wp_verify_nonce($_POST['ead_edit_event_nonce'], 'ead_edit_event_' . $event_id)) return;

        if ( self::honeypot_triggered() ) {
            return;
        }

        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'ead_event' || $event->post_author != get_current_user_id()) return;

        // Sanitize fields
        $title = sanitize_text_field($_POST['event_title'] ?? '');
        $type = sanitize_text_field($_POST['event_type'] ?? '');
        $desc = wp_kses_post($_POST['event_description'] ?? '');
        $start = sanitize_text_field($_POST['event_start_date'] ?? '');
        $end = sanitize_text_field($_POST['event_end_date'] ?? '');
        $organizer_name = sanitize_text_field($_POST['organizer_name'] ?? '');
        $organizer_email = sanitize_email($_POST['organizer_email'] ?? '');

        // Update post
        wp_update_post([
            'ID' => $event_id,
            'post_title' => $title,
            'post_content' => $desc,
            'post_status' => 'pending',
        ]);
        wp_set_object_terms($event_id, [$type], 'ead_event_type');
        update_post_meta($event_id, 'event_start_date', $start);
        update_post_meta($event_id, 'event_end_date', $end);
        update_post_meta($event_id, 'event_organizer_name', $organizer_name);
        update_post_meta($event_id, 'event_organizer_email', $organizer_email);

        // Gallery and other meta as needed...

        // Redirect with success message
        wp_redirect(add_query_arg(['edit' => $event_id, 'ead_msg' => 'updated'], get_permalink()));
        exit;
    }

    public static function render($atts) {
        if (!is_user_logged_in()) {
            return '<div class="ead-dashboard-card"><p>Please <a href="' . esc_url(wp_login_url(get_permalink())) . '">log in</a> to edit your event.</p></div>';
        }

        $event_id = intval($_GET['edit'] ?? 0);
        if (!$event_id) return '<div class="ead-dashboard-card"><p>No event selected.</p></div>';

        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'ead_event' || $event->post_author != get_current_user_id()) {
            return '<div class="ead-dashboard-card"><p>Invalid or unauthorized event.</p></div>';
        }

        $msg = '';
        if (!empty($_GET['ead_msg']) && $_GET['ead_msg'] === 'updated') {
            $msg = '<div style="background:#eaffea;color:#308000;border-radius:8px;padding:10px 14px;margin-bottom:18px;">Event updated and pending review!</div>';
        }

        $types = get_terms(['taxonomy' => 'ead_event_type', 'hide_empty' => false]);
        $current_types = wp_get_post_terms($event_id, 'ead_event_type', ['fields' => 'slugs']);

        ob_start();
        ?>
        <div class="ead-dashboard-card">
            <?php echo $msg; ?>
            <h2>Edit Event: <?php echo esc_html($event->post_title); ?></h2>
            <form method="post">
                <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">
                <?php wp_nonce_field('ead_edit_event_' . $event_id, 'ead_edit_event_nonce'); ?>

                <label>Title <input type="text" name="event_title" value="<?php echo esc_attr($event->post_title); ?>" required></label><br>
                <label>Type
                    <select name="event_type" required>
                        <option value="">Select Type</option>
                        <?php
                        foreach ($types as $type) {
                            echo '<option value="' . esc_attr($type->slug) . '"' . (in_array($type->slug, $current_types) ? ' selected' : '') . '>' . esc_html($type->name) . '</option>';
                        }
                        ?>
                    </select>
                </label><br>
                <label>Start Date <input type="date" name="event_start_date" value="<?php echo esc_attr(get_post_meta($event_id, 'event_start_date', true)); ?>" required></label><br>
                <label>End Date <input type="date" name="event_end_date" value="<?php echo esc_attr(get_post_meta($event_id, 'event_end_date', true)); ?>" required></label><br>
                <label>Description <textarea name="event_description" rows="3"><?php echo esc_textarea($event->post_content); ?></textarea></label><br>
                <label>Organizer Name <input type="text" name="organizer_name" value="<?php echo esc_attr(get_post_meta($event_id, 'event_organizer_name', true)); ?>" required></label><br>
                <label>Organizer Email <input type="email" name="organizer_email" value="<?php echo esc_attr(get_post_meta($event_id, 'event_organizer_email', true)); ?>" required></label><br>

                <?php echo self::render_honeypot( $atts ); ?>
                <button type="submit" class="ead-btn-primary">Update Event</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
