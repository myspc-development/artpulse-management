<?php

namespace ArtPulse\Frontend\Shared;

use WP_User;

/**
 * Prevent non-owners from deleting portfolio media attachments.
 */
final class PortfolioMediaGuard
{
    public static function register(): void
    {
        add_filter('user_has_cap', [self::class, 'filter_media_delete_cap'], 10, 4);
    }

    /**
     * @param array<string, bool> $allcaps
     * @param string[]            $caps
     * @param array<int, mixed>   $args
     * @return array<string, bool>
     */
    public static function filter_media_delete_cap(array $allcaps, array $caps, array $args, ?WP_User $user = null): array
    {
        $cap     = $args[0] ?? '';
        $post_id = isset($args[2]) ? (int) $args[2] : 0;

        if ('delete_post' !== $cap || $post_id <= 0) {
            return $allcaps;
        }

        if ('attachment' !== get_post_type($post_id)) {
            return $allcaps;
        }

        $parent_id = (int) get_post_field('post_parent', $post_id);
        if ($parent_id <= 0) {
            return $allcaps;
        }

        $parent_type = get_post_type($parent_id);
        if (!in_array($parent_type, ['artpulse_org', 'artpulse_artist'], true)) {
            return $allcaps;
        }

        $user_id = $user instanceof WP_User ? (int) $user->ID : 0;

        if ($user instanceof WP_User && in_array('administrator', (array) $user->roles, true)) {
            return $allcaps;
        }

        if ($user_id && PortfolioAccess::is_owner($user_id, $parent_id)) {
            return $allcaps;
        }

        $allcaps['delete_post'] = false;

        return $allcaps;
    }
}

