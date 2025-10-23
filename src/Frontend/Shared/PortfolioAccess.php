<?php

namespace ArtPulse\Frontend\Shared;

use ArtPulse\Core\Capabilities;

/**
 * Utility helpers for verifying portfolio ownership.
 */
final class PortfolioAccess
{
    private const OWNERSHIP_POST_STATUSES = ['publish', 'draft', 'pending', 'future'];

    public static function is_owner(int $user_id, int $post_id): bool
    {
        if ($user_id <= 0 || $post_id <= 0) {
            return false;
        }

        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        if (user_can($user_id, 'administrator')) {
            return true;
        }

        if ((int) $post->post_author === $user_id) {
            return true;
        }

        $primary_owner = (int) get_post_meta($post_id, '_ap_owner_user', true);
        if ($primary_owner === $user_id) {
            return true;
        }

        $additional = get_post_meta($post_id, '_ap_owner_users', true);
        if (is_array($additional) && in_array($user_id, array_map('intval', $additional), true)) {
            return true;
        }

        return false;
    }

    /**
     * Retrieve all portfolio post identifiers owned by a user for a given post type.
     */
    public static function get_owned_portfolio_ids(int $user_id, string $post_type): array
    {
        if ($user_id <= 0) {
            return [];
        }

        $args = [
            'post_type'      => $post_type,
            'post_status'    => self::OWNERSHIP_POST_STATUSES,
            'fields'         => 'ids',
            'posts_per_page' => -1,
        ];

        $by_primary_owner = get_posts(
            array_merge(
                $args,
                [
                    'meta_key'   => '_ap_owner_user',
                    'meta_value' => $user_id,
                ]
            )
        );

        $by_author = get_posts(
            array_merge(
                $args,
                [
                    'author' => $user_id,
                ]
            )
        );

        $by_team = get_posts(
            array_merge(
                $args,
                [
                    'meta_query' => [
                        [
                            'key'     => '_ap_owner_users',
                            'value'   => sprintf(':%d;', $user_id),
                            'compare' => 'LIKE',
                        ],
                    ],
                ]
            )
        );

        $ids = array_unique(array_map('intval', array_merge($by_primary_owner, $by_author, $by_team)));

        return array_values($ids);
    }

    public static function can_manage_portfolio(int $user_id, int $post_id): bool
    {
        if ($user_id <= 0 || $post_id <= 0) {
            return false;
        }

        $post_type = get_post_type($post_id);
        if (in_array($post_type, ['artpulse_org', 'artpulse_artist'], true)) {
            return user_can($user_id, Capabilities::CAP_MANAGE_PORTFOLIO) && self::is_owner($user_id, $post_id);
        }

        return false;
    }
}
