<?php

namespace ArtPulse\Frontend;

class OrganizationDashboardShortcode {
    public static function register() {
        add_shortcode('ap_org_dashboard', [self::class, 'render']);
        add_action('wp_ajax_ap_add_event', [self::class, 'handle_ajax_add_event']);
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

            <div id="ap-event-modal" style="display:none">
                <div class="ap-form-messages" role="status" aria-live="polite"></div>
                <form id="ap-event-form">
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

            <ul id="ap-event-list">
                <?php
                $events = get_posts([
                    'post_type' => 'artpulse_event',
                    'post_status' => 'any',
                    'meta_key' => '_ap_event_organization',
                    'meta_value' => $org_id
                ]);
                foreach ($events as $event) {
                    echo '<li>' . esc_html($event->post_title) . '</li>';
                }
                ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function handle_ajax_add_event() {
        check_ajax_referer('ap_org_dashboard_nonce', 'nonce');

        $title = sanitize_text_field($_POST['ap_event_title']);
        $date = sanitize_text_field($_POST['ap_event_date']);
        $location = sanitize_text_field($_POST['ap_event_location']);
        $event_type = intval($_POST['ap_event_type']);
        $org_id = intval($_POST['ap_event_organization']);

        $event_id = wp_insert_post([
            'post_title' => $title,
            'post_type' => 'artpulse_event',
            'post_status' => 'pending'
        ]);

        if (!$event_id) {
            wp_send_json_error(['message' => 'Failed to insert post']);
        }

        update_post_meta($event_id, '_ap_event_date', $date);
        update_post_meta($event_id, '_ap_event_location', $location);
        update_post_meta($event_id, '_ap_event_organization', $org_id);
        wp_set_post_terms($event_id, [$event_type], 'artpulse_event_type');

        // Reload the event list
        ob_start();
        $events = get_posts([
            'post_type' => 'artpulse_event',
            'post_status' => 'any',
            'meta_key' => '_ap_event_organization',
            'meta_value' => $org_id
        ]);
        foreach ($events as $event) {
            echo '<li>' . esc_html($event->post_title) . '</li>';
        }
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }
}
