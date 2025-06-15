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
        $rsvps = get_post_meta($event->ID, 'event_rsvps', true) ?: [];
        $title = get_the_title($event);
        $start = get_post_meta($event->ID, 'event_start_datetime', true);

        foreach (array_keys($rsvps) as $user_id) {
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

// 1b. Register custom taxonomy for event tags
function artpulse_register_event_taxonomy() {
    register_taxonomy('event_tag', 'event', [
        'label'        => 'Tags',
        'public'       => true,
        'hierarchical' => false,
        'show_in_rest' => true,
    ]);
}
add_action('init', 'artpulse_register_event_taxonomy');

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
        $rsvps = get_post_meta($post_id, 'event_rsvps', true) ?: [];

        if (!array_key_exists($user_id, $rsvps)) {
            $guest_name       = sanitize_text_field($_POST['guest_name'] ?? '');
            $rsvps[$user_id] = $guest_name;
            update_post_meta($post_id, 'event_rsvps', $rsvps);

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
    $rsvps = get_post_meta($event_id, 'event_rsvps', true) ?: [];
    $guest = $rsvps[$user_id] ?? '';

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

// 4. Enqueue FullCalendar assets for [event_calendar]
function artpulse_enqueue_event_calendar_assets() {
    if (!is_singular()) {
        return;
    }

    global $post;
    if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'event_calendar')) {
        return;
    }

    wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css');
    wp_enqueue_script('fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js', [], null, true);
    wp_enqueue_script('artpulse-calendar-init', plugin_dir_url(__FILE__) . 'js/event-calendar-init.js', ['fullcalendar-js'], null, true);

    // Pass events to JavaScript
    $events = [];
    $query  = new WP_Query([
        'post_type'      => 'event',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ]);

    foreach ($query->posts as $post) {
        $start        = get_post_meta($post->ID, 'event_start_datetime', true);
        $end          = get_post_meta($post->ID, 'event_end_datetime', true);
        $organizer_id = get_post_meta($post->ID, 'event_organizer', true);
        $tags         = wp_get_post_terms($post->ID, 'event_tag', ['fields' => 'slugs']);
        $events[]     = [
            'title'     => get_the_title($post),
            'start'     => $start,
            'end'       => $end,
            'url'       => get_permalink($post),
            'organizer' => $organizer_id,
            'tags'      => $tags,
        ];
    }

    wp_localize_script('artpulse-calendar-init', 'artpulseCalendarData', [
        'events' => $events,
    ]);
}
add_action('wp_enqueue_scripts', 'artpulse_enqueue_event_calendar_assets');

// 5. Shortcode output container + organizer filter for event calendar
function artpulse_event_calendar_shortcode() {
    $organizers = [];
    $query      = new WP_Query([
        'post_type'      => 'event',
        'posts_per_page' => -1,
    ]);

    foreach ($query->posts as $post) {
        $organizer_id = get_post_meta($post->ID, 'event_organizer', true);
        if ($organizer_id && !in_array($organizer_id, $organizers, true)) {
            $organizers[] = $organizer_id;
        }
    }

    $options = '<option value="">All Organizers</option>';
    foreach ($organizers as $id) {
        $name    = (is_numeric($id) && $user = get_user_by('ID', $id))
            ? $user->display_name
            : 'Org #' . esc_html($id);
        $options .= '<option value="' . esc_attr($id) . '">' . esc_html($name) . '</option>';
    }

    $terms       = get_terms([ 'taxonomy' => 'event_tag', 'hide_empty' => false ]);
    $tag_options = '<option value="">All Tags</option>';
    foreach ($terms as $tag) {
        $tag_options .= '<option value="' . esc_attr($tag->slug) . '">' . esc_html($tag->name) . '</option>';
    }

    return '<div class="flex flex-wrap gap-4 items-center mb-4">'
        . '<label class="text-sm font-medium">Organizer:'
        . '<select id="calendar-organizer-filter" class="ml-2 border px-2 py-1 rounded">' . $options . '</select>'
        . '</label>'
        . '<label class="text-sm font-medium">Tag:'
        . '<select id="calendar-tag-filter" class="ml-2 border px-2 py-1 rounded">' . $tag_options . '</select>'
        . '</label>'
        . '<label class="text-sm font-medium">From:'
        . '<input type="date" id="calendar-start-filter" class="ml-2 border px-2 py-1 rounded" />'
        . '</label>'
        . '<label class="text-sm font-medium">To:'
        . '<input type="date" id="calendar-end-filter" class="ml-2 border px-2 py-1 rounded" />'
        . '</label>'
        . '</div>'
        . '<div id="event-calendar" class="my-6"></div>';
}
add_shortcode('event_calendar', 'artpulse_event_calendar_shortcode');
function artpulse_event_card_shortcode($atts) {
    $atts = shortcode_atts(['id' => null], $atts);
    $post = get_post($atts['id']);

    if (!$post || $post->post_type != 'event') {
        return '<p>Event not found.</p>';
    }

    $start        = get_post_meta($post->ID, 'event_start_datetime', true);
    $end          = get_post_meta($post->ID, 'event_end_datetime', true);
    $location     = get_post_meta($post->ID, 'event_location', true);
    $organizer    = get_post_meta($post->ID, 'event_organizer', true);
    $recurring    = get_post_meta($post->ID, 'event_is_recurring', true);
    $rsvp_enabled = get_post_meta($post->ID, 'event_rsvp_enabled', true);
    $rsvps        = get_post_meta($post->ID, 'event_rsvps', true) ?: [];
    $total_rsvps  = count($rsvps);
    ob_start();
    ?>
    <div class="event-card border p-4 rounded shadow space-y-4">
        <?php if (has_post_thumbnail($post->ID)) {
            echo get_the_post_thumbnail($post->ID, 'large', ['class' => 'rounded']);
        } ?>

        <h2 class="text-xl font-bold"><?php echo esc_html($post->post_title); ?></h2>


        <?php if ($start): ?><p><strong>Start:</strong> <?php echo esc_html(date('F j, Y, g:i a', strtotime($start))); ?></p><?php endif; ?>
        <?php if ($end): ?><p><strong>End:</strong> <?php echo esc_html(date('F j, Y, g:i a', strtotime($end))); ?></p><?php endif; ?>
        <?php if ($location): ?><p><strong>Location:</strong> <?php echo esc_html($location); ?></p><?php endif; ?>
        <?php if ($organizer): ?><p><strong>Organizer ID:</strong> <?php echo esc_html($organizer); ?></p><?php endif; ?>
        <?php if ($recurring === '1'): ?><p><em>This event recurs.</em></p><?php endif; ?>

        <div class="mt-3"><?php echo wpautop( (string) $post->post_content ); ?></div>

        <?php
        $current_user = wp_get_current_user();
        if ($rsvp_enabled && is_user_logged_in()) {
            echo '<form method="post">';
            echo '<input type="hidden" name="event_rsvp_id" value="' . esc_attr($post->ID) . '">';
            echo '<p><label>Your Guest Name (optional):<br><input type="text" name="guest_name" class="input"></label></p>';
            echo '<button type="submit" class="button">RSVP</button>';
            echo '</form>';
        }
        ?>

        <div class="mt-4">
            <p><strong>Total RSVPs:</strong> <?php echo $total_rsvps; ?></p>
            <?php if (!empty($rsvps)): ?>
                <ul class="list-disc pl-5 text-sm space-y-1 text-gray-800">
                    <?php foreach ($rsvps as $uid => $guest):
                        $user = get_user_by('ID', $uid);
                        if ($user): ?>
                        <li class="flex justify-between">
                            <span class="font-medium"><?php echo esc_html($user->display_name); ?></span>
                            <?php if ($guest): ?><span class="italic"><?php echo esc_html($guest); ?></span><?php endif; ?>
                        </li>
                    <?php endif; endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="mt-6 flex flex-wrap gap-4 text-sm">
            <?php if ($start):
                $title        = urlencode($post->post_title);
                $location_str = urlencode($location);
                $start_gcal   = urlencode(gmdate('Ymd\THis\Z', strtotime($start)));
                $end_gcal     = $end ? urlencode(gmdate('Ymd\THis\Z', strtotime($end))) : '';
                $gcal_url     = "https://calendar.google.com/calendar/render?action=TEMPLATE&text={$title}&dates={$start_gcal}/{$end_gcal}&location={$location_str}";
                $ics_url      = add_query_arg('generate_ics', $post->ID);
            ?>
            <a href="<?php echo esc_url($gcal_url); ?>" class="inline-block px-3 py-1 bg-blue-100 text-blue-800 rounded hover:bg-blue-200 transition" target="_blank">Add to Google Calendar</a>
            <a href="<?php echo esc_url($ics_url); ?>" class="inline-block px-3 py-1 bg-green-100 text-green-800 rounded hover:bg-green-200 transition">Download ICS</a>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('event_card', 'artpulse_event_card_shortcode');

// Output a downloadable ICS file when ?generate_ics=ID is present
add_action('template_redirect', function () {
    if (!isset($_GET['generate_ics'])) {
        return;
    }

    $event_id = absint($_GET['generate_ics']);
    $post     = get_post($event_id);
    if (!$post || $post->post_type !== 'event') {
        return;
    }

    $start    = get_post_meta($event_id, 'event_start_datetime', true);
    $end      = get_post_meta($event_id, 'event_end_datetime', true);
    $location = get_post_meta($event_id, 'event_location', true);

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename=event-' . $event_id . '.ics');

    echo "BEGIN:VCALENDAR\r\n";
    echo "VERSION:2.0\r\n";
    echo "BEGIN:VEVENT\r\n";
    echo "UID:event-{$event_id}@artpulse\r\n";
    echo 'DTSTAMP:' . gmdate('Ymd\THis\Z') . "\r\n";
    echo 'DTSTART:' . gmdate('Ymd\THis\Z', strtotime($start)) . "\r\n";
    if ($end) {
        echo 'DTEND:' . gmdate('Ymd\THis\Z', strtotime($end)) . "\r\n";
    }
    echo 'SUMMARY:' . esc_html($post->post_title) . "\r\n";
    echo 'LOCATION:' . esc_html($location) . "\r\n";
    echo 'DESCRIPTION:' . esc_html( strip_tags( (string) $post->post_content ) ) . "\r\n";
    echo "END:VEVENT\r\n";
    echo "END:VCALENDAR\r\n";

    exit;
});

// Handle RSVP form submissions
add_action('init', function () {
    if (!empty($_POST['event_rsvp_id']) && is_user_logged_in()) {
        $event_id  = absint($_POST['event_rsvp_id']);
        $user_id   = get_current_user_id();
        $guest_name = sanitize_text_field($_POST['guest_name'] ?? '');

        $rsvps = get_post_meta($event_id, 'event_rsvps', true) ?: [];
        $rsvps[$user_id] = $guest_name;
        update_post_meta($event_id, 'event_rsvps', $rsvps);
    }
});
