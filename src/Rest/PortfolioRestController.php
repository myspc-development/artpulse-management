<?php

namespace ArtPulse\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class PortfolioRestController
{
    public static function register(): void
    {
        register_rest_route('artpulse/v1', '/portfolio/(?P<user_id>\\d+)', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_portfolio'],
            'permission_callback' => [self::class, 'can_view_portfolio'],
            'args'                => [
                'user_id' => [
                    'validate_callback' => 'is_numeric',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    public static function can_view_portfolio(): bool
    {
        /**
         * Filters whether unauthenticated users can view public portfolios.
         *
         * @param bool $allowed Default true.
         */
        return (bool) apply_filters('artpulse_rest_can_view_portfolio', true);
    }

    public static function get_portfolio(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id = absint($request->get_param('user_id'));
        if (!$user_id || !get_user_by('id', $user_id)) {
            return new WP_Error('invalid_user', __('Invalid user ID.', 'artpulse'), ['status' => 400]);
        }

        $items = get_posts([
            'post_type'        => ['artpulse_portfolio', 'portfolio'],
            'author'           => $user_id,
            'post_status'      => 'publish',
            'numberposts'      => -1,
            'meta_query'       => [
                'relation' => 'OR',
                [
                    'key'   => '_ap_visibility',
                    'value' => 'public',
                ],
                [
                    'key'   => 'portfolio_visibility',
                    'value' => 'public',
                ],
            ],
            'fields'           => 'ids',
            'no_found_rows'    => true,
            'suppress_filters' => false,
        ]);

        $response = [];
        foreach ($items as $post_id) {
            $content     = (string) get_post_field('post_content', $post_id);
            $legacy      = (string) get_post_meta($post_id, 'portfolio_description', true);
            if ('' === trim($content) && '' !== $legacy) {
                $content = $legacy;
            }

            $link        = (string) get_post_meta($post_id, '_ap_portfolio_link', true);
            if ($link === '') {
                $link = (string) get_post_meta($post_id, 'portfolio_link', true);
            }
            $categories  = wp_get_post_terms($post_id, 'portfolio_category', ['fields' => 'slugs']);

            $response[] = [
                'id'          => $post_id,
                'title'       => get_post_field('post_title', $post_id),
                'description' => wp_kses_post($content),
                'link'        => esc_url_raw($link),
                'category'    => isset($categories[0]) ? sanitize_key((string) $categories[0]) : '',
            ];
        }

        return rest_ensure_response($response);
    }
}
