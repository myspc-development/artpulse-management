<?php

namespace ArtPulse\Frontend;

use InvalidArgumentException;
use function __;

/**
 * Provide configuration for profile builders.
 */
final class ProfileBuilderConfig
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private const TYPES = [
        'artist' => [
            'post_type'       => 'artpulse_artist',
            'required_fields' => ['title', 'bio', 'featured_media'],
        ],
        'org' => [
            'post_type'       => 'artpulse_org',
            'required_fields' => ['title', 'bio', 'featured_media'],
        ],
    ];

    /**
     * Retrieve configuration for the requested builder type.
     *
     * @return array<string, mixed>
     */
    public static function for(string $type): array
    {
        if (!isset(self::TYPES[$type])) {
            throw new InvalidArgumentException('Unsupported profile builder type.');
        }

        $config = self::TYPES[$type];

        $steps = [
            [
                'slug'   => 'basics',
                'label'  => __('Basics', 'artpulse-management'),
                'fields' => ['title', 'tagline', 'bio'],
            ],
            [
                'slug'   => 'media',
                'label'  => __('Media', 'artpulse-management'),
                'fields' => ['featured_media', 'gallery'],
            ],
            [
                'slug'   => 'links',
                'label'  => __('Links', 'artpulse-management'),
                'fields' => ['website_url', 'socials'],
            ],
            [
                'slug'   => 'publish',
                'label'  => __('Publish', 'artpulse-management'),
                'fields' => ['visibility', 'status'],
            ],
        ];

        return [
            'type'            => $type,
            'post_type'       => $config['post_type'],
            'required_fields' => $config['required_fields'],
            'steps'           => $steps,
        ];
    }
}
