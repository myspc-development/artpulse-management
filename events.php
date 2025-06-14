<?php
// Module: ArtPulse Events

// 1. Schedule reminder emails for upcoming events
if (!wp_next_scheduled('artpulse_event_reminder_cron')) {
    wp_schedule_event(time(), 'hourly', 'artpulse_event_reminder_cron');
}

register_deactivation_hook(__FILE__, function () {
    $timestamp = wp_next_scheduled('artpulse_event_reminder_cron');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'artpulse_event_reminder_cron');
    }
});

add_action('artpulse_event_reminder_cron', function () {
    $now      = current_time('timestamp');
    $tomorrow = date('Y-m-d', strtotime('+1 day', $now));

    $query = new WP_Query([
        'post_type'   => 'event',
        'post_status' => 'publish',
        'meta_query'  => [
            [
                'key'     => 'event_start_datetime',
                'value'   => $tomorrow,
                'compare' => 'LIKE',
            ],
        ],
    ]);

    foreach ($query->posts as $event) {
        $attendees = get_post_meta($event->ID, 'event_rsvp_users', true) ?: [];
        $title     = get_the_title($event);
        $start     = get_post_meta($event->ID, 'event_start_datetime', true);

        foreach ($attendees as $user_id) {
            $user = get_user_by('ID', $user_id);
            if (!$user) {
                continue;
            }

            $subject = 'Reminder: Upcoming Event Tomorrow – ' . $title;
            $message = "Hi {$user->display_name},\n\n" .
                "This is a reminder that the event \"{$title}\" is happening tomorrow." .
                "\n\nDate/Time: " . date('F j, Y, g:i a', strtotime($start)) .
                "\n\nThanks for RSVP’ing!\n– ArtPulse";

            wp_mail($user->user_email, $subject, $message);
        }
    }
});

// 2. Frontend event list: [event_list limit=5]
function artpulse_event_list_shortcode($atts) {
    $atts = shortcode_atts(['limit' => 5], $atts);
    $now  = current_time('mysql');

    $query = new WP_Query([
        'post_type'      => 'event',
        'posts_per_page' => intval($atts['limit']),
        'post_status'    => 'publish',
        'meta_key'       => 'event_start_datetime',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [
            [
                'key'     => 'event_start_datetime',
                'value'   => $now,
                'compare' => '>=',
                'type'    => 'DATETIME',
            ],
        ],
    ]);

    if (!$query->have_posts()) {
        return '<p>No upcoming events found.</p>';
    }

    ob_start();
    echo '<div class="event-list space-y-4">';
    while ($query->have_posts()) {
        $query->the_post();
        $start    = get_post_meta(get_the_ID(), 'event_start_datetime', true);
        $location = get_post_meta(get_the_ID(), 'event_location', true);
        echo '<div class="border p-4 rounded shadow">'
            . '<h3 class="text-lg font-semibold">' . esc_html(get_the_title()) . '</h3>'
            . '<p class="text-sm text-gray-600">' . esc_html(date('F j, Y, g:i a', strtotime($start))) . '</p>'
            . '<p class="text-sm">' . esc_html($location) . '</p>'
            . '<a href="' . get_permalink() . '" class="inline-block mt-2 text-blue-600 underline">View Event</a>'
            . '</div>';
    }
    echo '</div>';
    wp_reset_postdata();

    return ob_get_clean();
}
add_shortcode('event_list', 'artpulse_event_list_shortcode');

// 3. RSVP form with optional guest name
function artpulse_event_rsvp_shortcode($atts) {
    $atts    = shortcode_atts(['id' => null], $atts);
    $post_id = intval($atts['id']);

    if (!is_user_logged_in()) {
        return '<p>Please <a href="' . esc_url(wp_login_url()) . '">log in</a> to RSVP.</p>';
    }

    $user_id = get_current_user_id();

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['artpulse_rsvp_nonce']) &&
        wp_verify_nonce($_POST['artpulse_rsvp_nonce'], 'rsvp_event_' . $post_id)
    ) {
        $attendees = get_post_meta($post_id, 'event_rsvp_users', true) ?: [];
        $guests    = get_post_meta($post_id, 'event_rsvp_guests', true) ?: [];

        if (!in_array($user_id, $attendees, true)) {
            $attendees[] = $user_id;
            update_post_meta($post_id, 'event_rsvp_users', $attendees);

            $guest_name = sanitize_text_field($_POST['guest_name'] ?? '');
            if ($guest_name !== '') {
                $guests[$user_id] = $guest_name;
                update_post_meta($post_id, 'event_rsvp_guests', $guests);
            }

            artpulse_send_rsvp_email($post_id, $user_id);
            echo '<div class="p-2 bg-green-100 text-green-700 border border-green-300 rounded">RSVP confirmed!</div>';
        } else {
            echo '<div class="p-2 bg-yellow-100 text-yellow-700 border border-yellow-300 rounded">You have already RSVP’d.</div>';
        }
    }

    ob_start();
    ?>
    <form method="post" class="mt-4">
        <?php wp_nonce_field('rsvp_event_' . $post_id, 'artpulse_rsvp_nonce'); ?>
        <p><label>Guest Name (optional):<br>
        <input type="text" name="guest_name" class="w-full border rounded px-2 py-1" placeholder="Full name of your guest" /></label></p>
        <p><button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Confirm RSVP</button></p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('event_rsvp', 'artpulse_event_rsvp_shortcode');

function artpulse_send_rsvp_email($event_id, $user_id) {
    $user   = get_user_by('ID', $user_id);
    $event  = get_post($event_id);
    $start  = get_post_meta($event_id, 'event_start_datetime', true);
    $guests = get_post_meta($event_id, 'event_rsvp_guests', true) ?: [];
    $guest  = $guests[$user_id] ?? '';

    if (!$user || !$event) {
        return;
    }

    $to      = $user->user_email;
    $subject = 'RSVP Confirmation – ' . $event->post_title;
    $message = "Hi {$user->display_name},\n\n" .
        "You’ve successfully RSVP’d to the event: {$event->post_title}.\n\n" .
        'Date: ' . date('F j, Y, g:i a', strtotime($start)) . "\n";

    if ($guest) {
        $message .= 'Guest: ' . $guest . "\n";
    }

    $message .= "\nThank you!\nArtPulse";

    wp_mail($to, $subject, $message);
}
