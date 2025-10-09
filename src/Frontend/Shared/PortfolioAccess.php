<?php

namespace ArtPulse\Frontend\Shared;

use ArtPulse\Core\Capabilities;

/**
 * Utility helpers for verifying portfolio ownership.
 */
final class PortfolioAccess
{
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
