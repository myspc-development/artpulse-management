<?php
// Module: ArtPulse Events

// 9. Frontend event list: [event_list limit=5]
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
