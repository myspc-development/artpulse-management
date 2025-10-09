<?php

namespace ArtPulse\Frontend\Shared;

/**
 * Registry for portfolio widgets used in builders and public rendering.
 */
final class PortfolioWidgetRegistry
{
    public static function defaults(): array
    {
        return [
            'hero'     => ['enabled' => true,  'label' => __('Hero', 'artpulse-management')],
            'about'    => ['enabled' => true,  'label' => __('About', 'artpulse-management')],
            'gallery'  => ['enabled' => true,  'label' => __('Gallery', 'artpulse-management')],
            'events'   => ['enabled' => true,  'label' => __('Upcoming Events', 'artpulse-management')],
            'map'      => ['enabled' => false, 'label' => __('Map & Location', 'artpulse-management')],
            'contact'  => ['enabled' => true,  'label' => __('Contact & Links', 'artpulse-management')],
            'press'    => ['enabled' => false, 'label' => __('Press/Highlights', 'artpulse-management')],
            'sponsors' => ['enabled' => false, 'label' => __('Sponsors', 'artpulse-management')],
        ];
    }

    public static function for_post(int $post_id): array
    {
        $stored   = (array) get_post_meta($post_id, '_ap_widgets', true);
        $defaults = self::defaults();

        $merged = array_replace_recursive($defaults, $stored);

        return apply_filters('artpulse/portfolio_widgets', $merged, $post_id);
    }

    public static function save(int $post_id, array $widgets): void
    {
        update_post_meta($post_id, '_ap_widgets', $widgets);
    }
}
