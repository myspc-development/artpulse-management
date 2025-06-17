<?php
namespace ArtPulse\Admin;

class AdminColumnsEvent {

    public static function register() {
        add_filter('manage_ead_event_posts_columns', [self::class, 'add_columns']);
        add_action('manage_ead_event_posts_custom_column', [self::class, 'render_columns'], 10, 2);
        add_filter('manage_edit-ead_event_sortable_columns', [self::class, 'make_sortable']);
    }

    public static function add_columns($columns) {
        $new = [];
        foreach ($columns as $key => $label) {
            if ($key === 'title') {
                $new['event_banner'] = __('Banner', 'artpulse-management');
                $new[$key] = $label;
                $new['event_dates'] = __('Dates', 'artpulse-management');
                $new['event_venue'] = __('Venue', 'artpulse-management');
                $new['event_featured'] = __('⭐ Featured', 'artpulse-management');
            } else {
                $new[$key] = $label;
            }
        }
        return $new;
    }

    public static function render_columns($column, $post_id) {
        switch ($column) {
            case 'event_banner':
                $banner_id = get_post_meta($post_id, 'event_banner_id', true);
                if ($banner_id) {
                    echo wp_get_attachment_image($banner_id, [60, 60]);
                } else {
                    echo '&mdash;';
                }
                break;

            case 'event_dates':
                $start = get_post_meta($post_id, 'event_start_date', true);
                $end   = get_post_meta($post_id, 'event_end_date', true);
                echo esc_html($start);
                if ($end && $end !== $start) {
                    echo ' - ' . esc_html($end);
                }
                break;

            case 'event_venue':
                $venue = get_post_meta($post_id, 'venue_name', true);
                echo esc_html($venue ?: '—');
                break;

            case 'event_featured':
                $featured = get_post_meta($post_id, 'event_featured', true);
                echo $featured === '1' ? '⭐' : '—';
                break;
        }
    }

    public static function make_sortable($columns) {
        $columns['event_featured'] = 'event_featured';
        $columns['event_dates'] = 'event_start_date';
        return $columns;
    }
}
