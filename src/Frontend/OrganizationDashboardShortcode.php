<?php

namespace ArtPulse\Frontend;

class OrganizationDashboardShortcode {
    public static function register() {
        add_shortcode('ap_org_dashboard', [self::class, 'render']);
        add_action('wp_ajax_ap_add_org_event', [self::class, 'handle_ajax_add_event']);
        add_action('wp_ajax_ap_delete_org_event', [self::class, 'handle_ajax_delete_event']);
    }

    public static function render($atts) {
        if (!is_user_logged_in()) return '<p>You must be logged in to view this dashboard.</p>';

        $user_id = get_current_user_id();
        $org_id = get_user_meta($user_id, 'ap_organization_id', true);
        if (!$org_id) return '<p>No organization assigned.</p>';

        ob_start();
        ?>
        <div class="ap-org-dashboard">
            <h2>Organization Events</h2>
            <button id="ap-add-event-btn">Add New Event</button>

            <div
                id="ap-org-modal"
                class="ap-org-modal"
                aria-hidden="true"
                hidden
            >
                <button type="button" id="ap-modal-close" class="ap-modal-close" aria-label="Close">&times;</button>
                <div
                    id="ap-status-message"
                    class="ap-form-messages"
                    role="status"
                    aria-live="polite"
                ></div>
                <form id="ap-org-event-form">
                    <label for="ap_event_title">Event Title</label>
                    <input id="ap_event_title" type="text" name="ap_event_title" required>

                    <label for="ap_event_date">Event Date</label>
                    <input id="ap_event_date" type="date" name="ap_event_date" required>

                    <label for="ap_event_location">Location</label>
                    <input id="ap_event_location" type="text" name="ap_event_location" required>

                    <label for="ap_event_type">Event Type</label>
                    <select id="ap_event_type" name="ap_event_type">
                        <?php
                        $terms = get_terms('artpulse_event_type', ['hide_empty' => false]);
                        foreach ($terms as $term) {
                            echo '<option value="' . esc_attr($term->term_id) . '">' . esc_html($term->name) . '</option>';
                        }
                        ?>
                    </select>

                    <input type="hidden" name="ap_event_organization" value="<?php echo esc_attr($org_id); ?>">
                    <button type="submit">Submit</button>
                </form>
            </div>

            <ul id="ap-org-events" class="ap-org-events">
                <?php echo self::get_events_list_html((int) $org_id); ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function handle_ajax_add_event() {
        check_ajax_referer('ap_org_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in to add events.', 'artpulse')]);
        }

        $title = sanitize_text_field($_POST['ap_event_title']);
        $date = sanitize_text_field($_POST['ap_event_date']);
        $location = sanitize_text_field($_POST['ap_event_location']);
        $event_type = intval($_POST['ap_event_type']);
        $org_id = intval($_POST['ap_event_organization']);
        $user_id = get_current_user_id();
        $user_org_id = get_user_meta($user_id, 'ap_organization_id', true);

        if (!$org_id || (int) $user_org_id !== $org_id) {
            wp_send_json_error(['message' => __('Invalid organization.', 'artpulse')]);
        }

        if (empty($title) || empty($date) || empty($location) || empty($event_type)) {
            wp_send_json_error(['message' => __('All fields are required.', 'artpulse')]);
        }

        $event_id = wp_insert_post([
            'post_title' => $title,
            'post_type' => 'artpulse_event',
            'post_status' => 'pending'
        ]);

        if (!$event_id) {
            wp_send_json_error(['message' => __('Failed to insert event.', 'artpulse')]);
        }

        update_post_meta($event_id, '_ap_event_date', $date);
        update_post_meta($event_id, '_ap_event_location', $location);
        update_post_meta($event_id, '_ap_event_organization', $org_id);
        wp_set_post_terms($event_id, [$event_type], 'artpulse_event_type');

        $html = self::get_events_list_html($org_id);

        wp_send_json_success([
            'message' => __('Event submitted successfully.', 'artpulse'),
            'updated_list_html' => $html,
        ]);
    }

    public static function handle_ajax_delete_event() {
        check_ajax_referer('ap_org_dashboard_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in to delete events.', 'artpulse')]);
        }

        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        if (!$event_id) {
            wp_send_json_error(['message' => __('Invalid event.', 'artpulse')]);
        }

        $user_id = get_current_user_id();
        $org_id = (int) get_user_meta($user_id, 'ap_organization_id', true);
        if (!$org_id) {
            wp_send_json_error(['message' => __('No organization assigned.', 'artpulse')]);
        }

        $event_org = (int) get_post_meta($event_id, '_ap_event_organization', true);
        if ($event_org !== $org_id) {
            wp_send_json_error(['message' => __('You cannot delete this event.', 'artpulse')]);
        }

        $deleted = wp_trash_post($event_id);
        if (!$deleted) {
            wp_send_json_error(['message' => __('Failed to delete event.', 'artpulse')]);
        }

        $html = self::get_events_list_html($org_id);

        wp_send_json_success([
            'message' => __('Event deleted.', 'artpulse'),
            'updated_list_html' => $html,
        ]);
    }

    private static function get_events_list_html($org_id) {
        $org_id = intval($org_id);
        ob_start();

        $events = get_posts([
            'post_type' => 'artpulse_event',
            'post_status' => 'any',
            'meta_key' => '_ap_event_organization',
            'meta_value' => $org_id,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if (empty($events)) {
            echo '<li class="ap-org-event-empty">' . esc_html__('No events found.', 'artpulse') . '</li>';
        } else {
            foreach ($events as $event) {
                echo sprintf(
                    '<li class="ap-org-event-item"><span class="ap-org-event-title">%1$s</span> <button type="button" class="ap-delete-event" data-id="%2$d">%3$s</button></li>',
                    esc_html($event->post_title),
                    absint($event->ID),
                    esc_html__('Delete', 'artpulse')
                );
            }
        }

        return ob_get_clean();
    }
}
