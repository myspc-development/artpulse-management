<?php
namespace ArtPulse\Rest;

class RestRelationships
{
    public static function register() {
        add_action('rest_api_init', [self::class, 'register_rest_fields']);
    }

    public static function register_rest_fields() {
        $relationship_meta = [
            '_ap_artist_artworks'     => 'artpulse_artist',
            '_ap_event_artworks'      => 'artpulse_event',
            '_ap_event_organizations' => 'artpulse_event',
            '_ap_artwork_artist'      => 'artpulse_artwork',
            '_ap_org_artists'         => 'artpulse_org',
        ];

        foreach ($relationship_meta as $meta_key => $post_type) {
            register_rest_field($post_type, ltrim($meta_key, '_'), [
                'get_callback'    => function($post) use ($meta_key) {
                    $value = get_post_meta($post['id'], $meta_key, true);
                    return $value ? $value : ($meta_key === '_ap_artwork_artist' ? 0 : []);
                },
                'update_callback' => function($value, $post) use ($meta_key) {
                    if (is_array($value)) {
                        $value = array_map('intval', $value);
                    } else {
                        $value = intval($value);
                    }
                    update_post_meta($post->ID, $meta_key, $value);
                },
                'schema' => [
                    'type' => 'array',
                    'items' => [ 'type' => 'integer' ],
                    'context' => ['view', 'edit'],
                ],
            ]);
        }
    }
}
