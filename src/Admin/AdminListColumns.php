<?php
namespace ArtPulse\Admin;

class AdminListColumns
{
    public static function register()
    {
        add_filter('manage_artpulse_artist_posts_columns', [self::class, 'artist_columns']);
        add_action('manage_artpulse_artist_posts_custom_column', [self::class, 'render_artist_columns'], 10, 2);

        add_filter('manage_artpulse_artwork_posts_columns', [self::class, 'artwork_columns']);
        add_action('manage_artpulse_artwork_posts_custom_column', [self::class, 'render_artwork_columns'], 10, 2);

        add_filter('manage_artpulse_event_posts_columns', [self::class, 'event_columns']);
        add_action('manage_artpulse_event_posts_custom_column', [self::class, 'render_event_columns'], 10, 2);
    }

    // --- Artists ---
    public static function artist_columns($columns)
    {
        $columns['artist_name'] = __('Artist Name', 'artpulse-management');
        $columns['artist_featured'] = __('Featured?', 'artpulse-management');
        return $columns;
    }

    public static function render_artist_columns($column, $post_id)
    {
        switch ($column) {
            case 'artist_name':
                echo esc_html(get_post_meta($post_id, 'artist_name', true));
                break;
            case 'artist_featured':
                echo get_post_meta($post_id, 'artist_featured', true) === '1' ? '✅' : '—';
                break;
        }
    }

    // --- Artworks ---
    public static function artwork_columns($columns)
    {
        $columns['artwork_title'] = __('Artwork Title', 'artpulse-management');
        $columns['artwork_artist'] = __('Artist', 'artpulse-management');
        $columns['artwork_featured'] = __('Featured?', 'artpulse-management');
        return $columns;
    }

    public static function render_artwork_columns($column, $post_id)
    {
        switch ($column) {
            case 'artwork_title':
                echo esc_html(get_post_meta($post_id, 'artwork_title', true));
                break;
            case 'artwork_artist':
                echo esc_html(get_post_meta($post_id, 'artwork_artist', true));
                break;
            case 'artwork_featured':
                echo get_post_meta($post_id, 'artwork_featured', true) === '1' ? '✅' : '—';
                break;
        }
    }

    // --- Events ---
    public static function event_columns($columns)
    {
        $columns['event_start_date'] = __('Start Date', 'artpulse-management');
        $columns['event_organizer_name'] = __('Organizer', 'artpulse-management');
        $columns['event_featured'] = __('Featured?', 'artpulse-management');
        return $columns;
    }

    public static function render_event_columns($column, $post_id)
    {
        switch ($column) {
            case 'event_start_date':
                echo esc_html(get_post_meta($post_id, 'event_start_date', true));
                break;
            case 'event_organizer_name':
                echo esc_html(get_post_meta($post_id, 'event_organizer_name', true));
                break;
            case 'event_featured':
                echo get_post_meta($post_id, 'event_featured', true) === '1' ? '✅' : '—';
                break;
        }
    }
}
