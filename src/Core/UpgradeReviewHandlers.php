<?php

namespace ArtPulse\Core;

use WP_Post;
use WP_User;

use function sanitize_key;

/**
 * Handles side effects triggered when upgrade reviews are approved or denied.
 */
class UpgradeReviewHandlers
{
    private const TYPE_ARTIST = 'artist';
    private const TYPE_ORGANIZATION = 'organization';

    /**
     * Map normalised upgrade types to their configuration.
     *
     * @var array<string, array<string, mixed>>
     */
    private const TYPE_CONFIG = [
        self::TYPE_ARTIST => [
            'role'         => 'ap_artist',
            'fallbackRole' => 'artist',
            'post_type'    => 'artpulse_artist',
            'meta_key'     => '_ap_artist_post_id',
            'capabilities' => [
                Capabilities::CAP_MANAGE_OWN_ARTIST,
                Capabilities::CAP_MANAGE_PORTFOLIO,
                Capabilities::CAP_SUBMIT_EVENTS,
            ],
        ],
        self::TYPE_ORGANIZATION => [
            'role'         => 'ap_org_manager',
            'fallbackRole' => 'organization',
            'post_type'    => 'artpulse_org',
            'meta_key'     => '_ap_org_post_id',
            'capabilities' => [
                Capabilities::CAP_MANAGE_OWN_ORG,
                Capabilities::CAP_MANAGE_PORTFOLIO,
                Capabilities::CAP_SUBMIT_EVENTS,
            ],
        ],
    ];

    public static function register(): void
    {
        add_action('artpulse/upgrade_review/approved', [self::class, 'handle_approved'], 10, 3);
        add_action('artpulse/upgrade_review/denied', [self::class, 'handle_denied'], 10, 3);
    }

    public static function handle_approved(int $request_id, int $user_id, string $type): void
    {
        if ($user_id <= 0) {
            return;
        }

        $normalised_type = self::normalise_type($type);
        if (null === $normalised_type) {
            return;
        }

        $config = self::TYPE_CONFIG[$normalised_type];
        $user   = get_user_by('id', $user_id);

        if (!$user instanceof WP_User) {
            return;
        }

        self::maybe_assign_role($user, (string) $config['role'], (string) $config['fallbackRole']);
        self::maybe_assign_capabilities($user, (array) $config['capabilities']);

        $profile_id = self::get_or_create_profile_post($user_id, $normalised_type);
        if ($profile_id > 0 && $request_id > 0) {
            update_post_meta($request_id, UpgradeReviewRepository::META_POST, $profile_id);
        }
    }

    public static function handle_denied(int $request_id, int $user_id, string $type): void
    {
        // Stage 6 will add notifications. Nothing to do for now.
    }

    public static function get_or_create_profile_post(int $user_id, string $type): int
    {
        if ($user_id <= 0) {
            return 0;
        }

        $normalised_type = self::normalise_type($type);
        if (null === $normalised_type) {
            return 0;
        }

        $config   = self::TYPE_CONFIG[$normalised_type];
        $meta_key = (string) $config['meta_key'];
        $post_type = (string) $config['post_type'];

        $existing_id = (int) get_user_meta($user_id, $meta_key, true);
        if ($existing_id > 0) {
            $existing_post = get_post($existing_id);
            if ($existing_post instanceof WP_Post && $existing_post->post_type === $post_type) {
                self::ensure_post_ownership($existing_post->ID, $user_id);

                return $existing_post->ID;
            }

            delete_user_meta($user_id, $meta_key);
        }

        $owned_posts = get_posts([
            'post_type'      => $post_type,
            'post_status'    => ['draft', 'pending', 'publish', 'private', 'future'],
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'   => '_ap_owner_user',
                    'value' => $user_id,
                ],
            ],
        ]);

        if (!empty($owned_posts)) {
            $post_id = (int) $owned_posts[0];
            if ($post_id > 0) {
                update_user_meta($user_id, $meta_key, $post_id);
                self::ensure_post_ownership($post_id, $user_id);

                return $post_id;
            }
        }

        $authored_posts = get_posts([
            'post_type'      => $post_type,
            'post_status'    => ['draft', 'pending', 'publish', 'private', 'future'],
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'author'         => $user_id,
        ]);

        if (!empty($authored_posts)) {
            $post_id = (int) $authored_posts[0];
            if ($post_id > 0) {
                update_user_meta($user_id, $meta_key, $post_id);
                self::ensure_post_ownership($post_id, $user_id);

                return $post_id;
            }
        }

        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) {
            return 0;
        }

        $display_name = trim($user->display_name ?: $user->user_login);
        $title = '';
        if ('' === $display_name) {
            $title = self::TYPE_ARTIST === $normalised_type
                ? __('New artist profile', 'artpulse-management')
                : __('New organization profile', 'artpulse-management');
        } elseif (self::TYPE_ARTIST === $normalised_type) {
            $title = sprintf(
                /* translators: %s is the display name of the artist. */
                __('Artist profile for %s', 'artpulse-management'),
                $display_name
            );
        } else {
            $title = sprintf(
                /* translators: %s is the display name of the organization manager. */
                __('Organization profile for %s', 'artpulse-management'),
                $display_name
            );
        }

        $post_id = wp_insert_post(
            [
                'post_type'   => $post_type,
                'post_status' => 'draft',
                'post_title'  => $title,
                'post_author' => $user_id,
                'meta_input'  => [
                    '_ap_owner_user' => $user_id,
                ],
            ],
            true
        );

        if (!$post_id || is_wp_error($post_id)) {
            return 0;
        }

        update_user_meta($user_id, $meta_key, $post_id);

        return (int) $post_id;
    }

    private static function maybe_assign_role(WP_User $user, string $role, string $fallback_role): void
    {
        $target_role = $role;

        if ('' !== $role && !get_role($role) && '' !== $fallback_role && get_role($fallback_role)) {
            $target_role = $fallback_role;
        }

        if ('' === $target_role || in_array($target_role, $user->roles, true) || !get_role($target_role)) {
            return;
        }

        $user->add_role($target_role);
    }

    /**
     * @param string[] $caps
     */
    private static function maybe_assign_capabilities(WP_User $user, array $caps): void
    {
        foreach ($caps as $cap) {
            if (!is_string($cap) || '' === $cap) {
                continue;
            }

            if ($user->has_cap($cap)) {
                continue;
            }

            $user->add_cap($cap);
        }
    }

    private static function ensure_post_ownership(int $post_id, int $user_id): void
    {
        $current_owner = (int) get_post_meta($post_id, '_ap_owner_user', true);
        if ($current_owner !== $user_id) {
            update_post_meta($post_id, '_ap_owner_user', $user_id);
        }

        $post = get_post($post_id);
        if ($post instanceof WP_Post && (int) $post->post_author !== $user_id) {
            wp_update_post([
                'ID'          => $post_id,
                'post_author' => $user_id,
            ]);
        }
    }

    private static function normalise_type(string $type): ?string
    {
        $key = sanitize_key($type);

        return match ($key) {
            UpgradeReviewRepository::TYPE_ARTIST_UPGRADE, 'artist', 'artists', 'ap_artist', 'artpulse_artist' => self::TYPE_ARTIST,
            UpgradeReviewRepository::TYPE_ORG_UPGRADE, 'organization', 'organisation', 'org', 'orgs', 'ap_org', 'artpulse_org', 'artpulse_organization' => self::TYPE_ORGANIZATION,
            default => null,
        };
    }
}
