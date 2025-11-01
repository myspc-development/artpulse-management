<?php

namespace ArtPulse\Frontend;

use function absint;
use function sanitize_text_field;
use function wp_strip_all_tags;
use function wp_unslash;

class OrganizationDashboardShortcode {
    public static function register() {
        add_shortcode('ap_org_dashboard', [self::class, 'render']);
        add_action('wp_ajax_ap_add_org_event', [self::class, 'handle_ajax_add_event']);
        add_action('wp_ajax_ap_delete_org_event', [self::class, 'handle_ajax_delete_event']);
    }

    public static function render($atts) {
        if (!is_user_logged_in()) {
            auth_redirect();
            exit;
        }

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
                <form id="ap-org-event-form" method="post">
                    <?php wp_nonce_field('ap_org_dashboard_nonce', 'nonce'); ?>
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

        if (!isset(
            $_POST['ap_event_title'],
            $_POST['ap_event_date'],
            $_POST['ap_event_location'],
            $_POST['ap_event_type'],
            $_POST['ap_event_organization']
        )) {
            wp_send_json_error(['message' => __('Invalid event data.', 'artpulse')]);
        }

        $title = sanitize_text_field(wp_unslash($_POST['ap_event_title']));
        $date = sanitize_text_field(wp_unslash($_POST['ap_event_date']));
        $location = sanitize_text_field(wp_unslash($_POST['ap_event_location']));
        $event_type = absint(wp_unslash($_POST['ap_event_type']));
        $org_id = absint(wp_unslash($_POST['ap_event_organization']));
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

        if (!$event_id || is_wp_error($event_id)) {
            wp_send_json_error(['message' => __('Failed to insert event.', 'artpulse')]);
        }

        update_post_meta($event_id, '_ap_event_date', $date);
        update_post_meta($event_id, '_ap_event_location', $location);
        update_post_meta($event_id, '_ap_event_organization', $org_id);

        if ($event_type > 0) {
            wp_set_post_terms($event_id, [$event_type], 'artpulse_event_type');
        }

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

        $event_id = isset($_POST['event_id']) ? absint(wp_unslash($_POST['event_id'])) : 0;
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
                $moderation_state = get_post_meta($event->ID, '_ap_moderation_state', true);
                $moderation_reason = get_post_meta($event->ID, '_ap_moderation_reason', true);
                $status_label = '';

                if ($moderation_state) {
                    switch ($moderation_state) {
                        case 'approved':
                            $status_label = __('Approved', 'artpulse-management');
                            break;
                        case 'rejected':
                            $status_label = __('Rejected', 'artpulse-management');
                            break;
                        default:
                            $status_label = __('Pending review', 'artpulse-management');
                            break;
                    }
                }

                echo sprintf(
                    '<li class="ap-org-event-item"><span class="ap-org-event-title">%1$s</span>%2$s%3$s <button type="button" class="ap-delete-event" data-id="%4$d" aria-label="%5$s">%6$s</button></li>',
                    esc_html($event->post_title),
                    $status_label ? sprintf(' <span class="ap-org-event-status" data-status="%2$s">%1$s</span>', esc_html($status_label), esc_attr($moderation_state)) : '',
                    $moderation_reason ? sprintf(' <span class="ap-org-event-reason">%1$s</span>', esc_html($moderation_reason)) : '',
                    absint($event->ID),
                    esc_attr(sprintf(__('Delete %s', 'artpulse-management'), wp_strip_all_tags($event->post_title))),
                    esc_html__('Delete', 'artpulse')
                );
            }
        }

        return ob_get_clean();
    }
}
