<?php

namespace ArtPulse\Core;

class PostTypeRegistrar
{
    public static function register()
    {
        // Base config shared by all CPTs
        $common = [
            'public'        => true,
            'show_ui'       => true,
            'show_in_menu'  => true,
            'show_in_rest'  => true,
            'has_archive'   => true,
            'rewrite'       => true,
            'supports'      => ['title', 'editor', 'thumbnail'],
        ];

        // Events
        register_post_type('artpulse_event', array_merge($common, [
            'label'      => __('Events', 'artpulse'),
            'rewrite'    => ['slug' => 'events'],
            'taxonomies' => ['artpulse_event_type'],
        ]));

        register_post_meta('artpulse_event', '_ap_event_date', [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
        ]);
        register_post_meta('artpulse_event', '_ap_event_location', [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
        ]);

        // Artists
        register_post_type('artpulse_artist', array_merge($common, [
            'label'   => __('Artists', 'artpulse'),
            'rewrite' => ['slug' => 'artists'],
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
        ]));

        register_post_meta('artpulse_artist', '_ap_artist_bio', [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
        ]);
        register_post_meta('artpulse_artist', '_ap_artist_org', [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'integer',
        ]);

        // Artworks
        register_post_type('artpulse_artwork', array_merge($common, [
            'label'   => __('Artworks', 'artpulse'),
            'rewrite' => ['slug' => 'artworks'],
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
        ]));

        register_post_meta('artpulse_artwork', '_ap_artwork_medium', [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
        ]);
        register_post_meta('artpulse_artwork', '_ap_artwork_dimensions', [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
        ]);
        register_post_meta('artpulse_artwork', '_ap_artwork_materials', [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
        ]);

        // Organizations
        register_post_type('artpulse_org', array_merge($common, [
            'label'   => __('Organizations', 'artpulse'),
            'rewrite' => ['slug' => 'organizations'],
        ]));

        register_post_meta('artpulse_org', '_ap_org_address', [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
        ]);
        register_post_meta('artpulse_org', '_ap_org_website', [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
        ]);

        // Taxonomies
        register_taxonomy('artpulse_event_type', 'artpulse_event', [
            'label'        => __('Event Types', 'artpulse'),
            'public'       => true,
            'show_in_rest' => true,
            'hierarchical' => true,
            'rewrite'      => ['slug' => 'event-types'],
        ]);

        register_taxonomy('artpulse_medium', 'artpulse_artwork', [
            'label'        => __('Medium', 'artpulse'),
            'public'       => true,
            'show_in_rest' => true,
            'hierarchical' => true,
            'rewrite'      => ['slug' => 'medium'],
        ]);
    }
}
