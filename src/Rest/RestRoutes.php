<?php

namespace ArtPulse\Rest;

class RestRoutes
{
    public static function register()
    {
        add_action('rest_api_init', function () {
            // Register listing endpoints
            register_rest_route('artpulse/v1', '/events', [
                'methods'             => 'GET',
                'callback'            => [self::class, 'get_events'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('artpulse/v1', '/artists', [
                'methods'             => 'GET',
                'callback'            => [self::class, 'get_artists'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('artpulse/v1', '/artworks', [
                'methods'             => 'GET',
                'callback'            => [self::class, 'get_artworks'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('artpulse/v1', '/orgs', [
                'methods'             => 'GET',
                'callback'            => [self::class, 'get_orgs'],
                'permission_callback' => '__return_true',
            ]);

            // ✅ Register the new SubmissionRestController endpoint
            \ArtPulse\Rest\SubmissionRestController::register();
        });
    }

    public static function get_events()
    {
        return self::get_posts_with_meta('artpulse_event', [
            'event_date'     => '_ap_event_date',
            'event_location' => '_ap_event_location',
        ]);
    }

    public static function get_artists()
    {
        return self::get_posts_with_meta('artpulse_artist', [
            'artist_bio' => '_ap_artist_bio',
            'artist_org' => '_ap_artist_org',
        ]);
    }

    public static function get_artworks()
    {
        return self::get_posts_with_meta('artpulse_artwork', [
            'medium'     => '_ap_artwork_medium',
            'dimensions' => '_ap_artwork_dimensions',
            'materials'  => '_ap_artwork_materials',
        ]);
    }

    public static function get_orgs()
    {
        return self::get_posts_with_meta('artpulse_org', [
            'address' => '_ap_org_address',
            'website' => '_ap_org_website',
        ]);
    }

    private static function get_posts_with_meta($post_type, $meta_keys = [])
    {
        $posts = get_posts([
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]);

        $output = [];

        foreach ($posts as $post) {
            $item = [
                'id'      => $post->ID,
                'title'   => get_the_title($post),
                'content' => apply_filters('the_content', $post->post_content),
                'link'    => get_permalink($post),
            ];

            foreach ($meta_keys as $field => $meta_key) {
                $item[$field] = get_post_meta($post->ID, $meta_key, true);
            }

            $output[] = $item;
        }

        return $output;
    }
}
