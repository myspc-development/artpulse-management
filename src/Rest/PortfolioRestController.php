<?php

namespace ArtPulse\Rest;

class PortfolioRestController
{
    public static function register()
    {
        register_rest_route('artpulse/v1', '/portfolio/(?P<user_id>\d+)', [
            'methods'  => 'GET',
            'callback' => [self::class, 'get_portfolio'],
            'permission_callback' => '__return_true',
            'args'     => [
                'user_id' => [
                    'validate_callback' => 'is_numeric',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    public static function get_portfolio($request)
    {
        $user_id = intval($request['user_id']);
        if (!$user_id) return new \WP_Error('invalid_user', 'Invalid user ID', ['status' => 400]);

        $items = get_posts([
            'post_type'   => 'portfolio',
            'author'      => $user_id,
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query'  => [[
                'key'   => 'portfolio_visibility',
                'value' => 'public',
            ]]
        ]);

        $response = [];
        foreach ($items as $item) {
            $response[] = [
                'id'          => $item->ID,
                'title'       => $item->post_title,
                'description' => get_post_meta($item->ID, 'portfolio_description', true),
                'link'        => get_post_meta($item->ID, 'portfolio_link', true),
                'image'       => get_post_meta($item->ID, 'portfolio_image', true),
                'category'    => wp_get_post_terms($item->ID, 'portfolio_category', ['fields' => 'slugs'])[0] ?? '',
            ];
        }

        return rest_ensure_response($response);
    }
}