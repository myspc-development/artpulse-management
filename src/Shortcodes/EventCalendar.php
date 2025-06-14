<?php
namespace EAD\Shortcodes;

class EventCalendar {
    const SHORTCODE_TAG = 'event_calendar';

    public static function register() {
        add_shortcode(self::SHORTCODE_TAG, [self::class, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets_if_needed']);
    }

    public static function enqueue_assets_if_needed() {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, self::SHORTCODE_TAG)) {
            self::enqueue_assets();
        }
    }

    private static function enqueue_assets() {
        wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css');
        wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js', [], null, true);
        wp_enqueue_script(
            'ead-event-calendar',
            EAD_PLUGIN_DIR_URL . 'assets/js/ead-event-calendar.js',
            ['fullcalendar'],
            defined('EAD_MANAGEMENT_VERSION') ? EAD_MANAGEMENT_VERSION : '1.0.0',
            true
        );
    }

    public static function render_shortcode($atts = []) {
        self::enqueue_assets();

        $query = new \WP_Query([
            'post_type'      => 'ead_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => 'event_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        ]);

        $events = [];
        foreach ($query->posts as $event) {
            $events[] = [
                'title' => get_the_title($event),
                'start' => get_post_meta($event->ID, 'event_date', true),
                'url'   => get_permalink($event->ID),
            ];
        }
        wp_reset_postdata();

        wp_localize_script('ead-event-calendar', 'eventCalendarData', $events);

        return '<div id="event-calendar"></div>';
    }
}
