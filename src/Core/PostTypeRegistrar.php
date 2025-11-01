<?php

namespace ArtPulse\Core;

use ArtPulse\Core\UpgradeReviewRepository;

class PostTypeRegistrar
{
    public const EVENT_POST_TYPE = 'artpulse_event';

    public const EVENT_TAXONOMY = 'artpulse_event_type';

    /**
     * @var string[]
     */
    public const EVENT_TAXONOMIES = [self::EVENT_TAXONOMY];

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

        // Register CPTs
        $post_types = [
            self::EVENT_POST_TYPE   => [
                'label'           => __('Events', 'artpulse-management'),
                'rewrite'         => ['slug' => 'events'],
                'taxonomies'      => self::EVENT_TAXONOMIES,
            ],
            'artpulse_artist'  => [
                'label'           => __('Artists', 'artpulse-management'),
                'rewrite'         => ['slug' => 'artists'],
                'supports'        => ['title', 'editor', 'thumbnail', 'custom-fields'], // Added custom-fields support
            ],
            'artpulse_artwork' => [
                'label'           => __('Artworks', 'artpulse-management'),
                'rewrite'         => ['slug' => 'artworks'],
                'supports'        => ['title', 'editor', 'thumbnail', 'custom-fields'], // Added custom-fields support
            ],
            'artpulse_org'     => [
                'label'           => __('Organizations', 'artpulse-management'),
                'rewrite'         => ['slug' => 'organizations'],
            ],
            'ap_link_request' => [
                'label'    => __('Link Requests', 'artpulse-management'),
                'rewrite'  => ['slug' => 'link-requests'],
                'supports' => ['title'],
                'public'   => false,
            ],
            UpgradeReviewRepository::POST_TYPE => [
                'label'       => __('Upgrade Reviews', 'artpulse-management'),
                'rewrite'     => false,
                'supports'    => ['title'],
                'public'      => false,
                'show_ui'     => false,
                'show_in_menu'=> false,
                'show_in_rest'=> false,
            ],
        ];

        foreach ($post_types as $post_type => $args) {
            $capabilities = self::generate_caps($post_type);
            register_post_type(
                $post_type,
                array_merge(
                    $common,
                    $args,
                    [
                        'capability_type' => $post_type,
                        'map_meta_cap'    => true,
                        'capabilities'    => $capabilities,
                    ]
                )
            );
        }

        // Register Meta Boxes
        self::register_meta_boxes();

        // Taxonomies
        register_taxonomy(
            self::EVENT_TAXONOMY,
            self::EVENT_POST_TYPE,
            [
                'label'        => __('Event Types', 'artpulse-management'),
                'public'       => true,
                'show_in_rest' => true,
                'hierarchical' => true,
                'rewrite'      => ['slug' => 'event-types'],
            ]
        );

        register_taxonomy(
            'artpulse_medium',
            'artpulse_artwork',
            [
                'label'        => __('Medium', 'artpulse-management'),
                'public'       => true,
                'show_in_rest' => true,
                'hierarchical' => true,
                'rewrite'      => ['slug' => 'medium'],
            ]
        );
    }

    private static function register_meta_boxes()
    {
        register_post_meta(
            self::EVENT_POST_TYPE,
            '_ap_event_date',
            [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
            ]
        );

        register_post_meta(
            self::EVENT_POST_TYPE,
            '_ap_event_location',
            [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
            ]
        );

        register_post_meta(
            self::EVENT_POST_TYPE,
            '_ap_event_start',
            [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
            ]
        );

        register_post_meta(
            self::EVENT_POST_TYPE,
            '_ap_event_end',
            [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
            ]
        );

        register_post_meta(
            self::EVENT_POST_TYPE,
            '_ap_event_all_day',
            [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'boolean',
                'default'      => false,
            ]
        );

        register_post_meta(
            self::EVENT_POST_TYPE,
            '_ap_event_timezone',
            [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
            ]
        );

        register_post_meta(
            self::EVENT_POST_TYPE,
            '_ap_event_cost',
            [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
            ]
        );

        register_post_meta(
            self::EVENT_POST_TYPE,
            '_ap_event_recurrence',
            [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
            ]
        );

        register_post_meta(
            self::EVENT_POST_TYPE,
            '_ap_event_organization',
            [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'integer',
            ]
        );

        register_post_meta(
            'artpulse_artist',
            '_ap_artist_bio',
            [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
            ]
        );

        register_post_meta(
            'artpulse_artist',
            '_ap_artist_org',
            [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'integer',
            ]
        );

        register_post_meta(
            'artpulse_artwork',
            '_ap_artwork_medium',
            [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
            ]
        );

        register_post_meta(
            'artpulse_artwork',
            '_ap_artwork_dimensions',
            [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
            ]
        );

        register_post_meta(
            'artpulse_artwork',
            '_ap_artwork_materials',
            [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
            ]
        );
    }

    public static function generate_caps(string $post_type): array
    {
        $plural = $post_type . 's'; // Simple pluralization

        return [
            'edit_post'             => "edit_{$post_type}",
            'read_post'             => "read_{$post_type}",
            'delete_post'           => "delete_{$post_type}",
            'edit_posts'            => "edit_{$plural}",
            'edit_others_posts'     => "edit_others_{$plural}",
            'publish_posts'         => "publish_{$plural}",
            'read_private_posts'    => "read_private_{$plural}",
            'delete_posts'          => "delete_{$plural}",
            'delete_private_posts'  => "delete_private_{$plural}",
            'delete_published_posts' => "delete_published_{$plural}",
            'delete_others_posts'   => "delete_others_{$plural}",
            'edit_private_posts'    => "edit_private_{$plural}",
            'edit_published_posts'  => "edit_published_{$plural}",
            'create_posts'          => "create_{$plural}",
        ];
    }
}