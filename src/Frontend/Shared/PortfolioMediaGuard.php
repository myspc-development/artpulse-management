<?php

namespace ArtPulse\Frontend\Shared;

use WP_Post;
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
        $cap = $args[0] ?? '';
        if (!in_array($cap, ['delete_post', 'delete_attachment'], true)) {
            return $allcaps;
        }

        $post_id = 0;

        if (isset($args[2])) {
            $context = $args[2];

            if (is_numeric($context)) {
                $post_id = (int) $context;
            } elseif (is_object($context) && property_exists($context, 'post') && $context->post instanceof WP_Post) {
                $post_id = (int) $context->post->ID;
            }
        }

        if ($post_id <= 0) {
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

        $allcaps['delete_post']        = false;
        $allcaps['delete_attachment'] = false;

        return $allcaps;
    }
}

