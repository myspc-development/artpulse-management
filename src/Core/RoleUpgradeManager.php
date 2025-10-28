<?php

namespace ArtPulse\Core;

use WP_Post;
use WP_User;

/**
 * Centralises membership and role upgrades triggered by submissions or approvals.
 */
class RoleUpgradeManager
{
    /**
     * Roles mapped to membership level labels.
     */
    private const MEMBERSHIP_LABELS = [
        'artist'       => 'Artist',
        'organization' => 'Organization',
    ];

    private const UPGRADE_NOTIFICATION_META_PREFIX = '_ap_upgrade_notified_';

    /**
     * Register WordPress hooks.
     */
    public static function register(): void
    {
        add_action('transition_post_status', [self::class, 'maybe_grant_role_on_publish'], 10, 3);
    }

    /**
     * Ensure ownership metadata is set for a submission.
     */
    public static function attach_owner(int $post_id, int $user_id): void
    {
        if ($post_id <= 0 || $user_id <= 0) {
            return;
        }

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

    /**
     * Grant a role to the given user and mark the membership level.
     *
     * @param array<string, mixed> $context Additional audit context.
     */
    public static function grant_role(int $user_id, string $role, array $context = []): ?int
    {
        if ($user_id <= 0 || '' === $role) {
            return null;
        }

        $user = get_user_by('id', $user_id);

        if (!$user instanceof WP_User) {
            return null;
        }

        if (!in_array($role, $user->roles, true)) {
            $user->add_role($role);
        }

        if (isset(self::MEMBERSHIP_LABELS[$role])) {
            update_user_meta($user_id, 'ap_membership_level', self::MEMBERSHIP_LABELS[$role]);
        }

        $profile_post_id = null;

        if ('artist' === $role) {
            $profile_post_id = self::ensure_artist_profile_exists($user);

            if ($profile_post_id) {
                $context['artist_post_id'] = $context['artist_post_id'] ?? $profile_post_id;
                $context['post_id']        = $context['post_id'] ?? $profile_post_id;
                $context['builder_url']    = $context['builder_url'] ?? home_url(add_query_arg([
                    'ap_builder' => 'artist',
                    'post_id'    => $profile_post_id,
                ], '/'));
            }
        }

        AuditLogger::log('role_granted', array_merge($context, [
            'user_id' => $user_id,
            'role'    => $role,
        ]));

        self::send_upgrade_email($user, $role, $context);

        return isset($context['post_id']) && $context['post_id']
            ? (int) $context['post_id']
            : $profile_post_id;
    }

    public static function grant_role_if_missing(int $user_id, string $role, array $context = []): ?int
    {
        $user = get_user_by('id', $user_id);

        if (!$user instanceof WP_User) {
            return null;
        }

        if (in_array($role, $user->roles, true)) {
            if (isset(self::MEMBERSHIP_LABELS[$role])) {
                update_user_meta($user_id, 'ap_membership_level', self::MEMBERSHIP_LABELS[$role]);
            }

            return null;
        }

        return self::grant_role($user_id, $role, $context);
    }

    public static function revoke_role_if_present(int $user_id, string $role, array $context = []): void
    {
        if ($user_id <= 0 || '' === $role) {
            return;
        }

        $user = get_user_by('id', $user_id);

        if (!$user instanceof WP_User || !in_array($role, $user->roles, true)) {
            return;
        }

        $user->remove_role($role);

        AuditLogger::log('role_revoked', array_merge($context, [
            'user_id' => $user_id,
            'role'    => $role,
        ]));
    }

    /**
     * Automatically grant roles when a submission is approved/published.
     */
    public static function maybe_grant_role_on_publish(string $new_status, string $old_status, WP_Post $post): void
    {
        if ('publish' !== $new_status || 'publish' === $old_status) {
            return;
        }

        if (!in_array($post->post_type, ['artpulse_artist', 'artpulse_org'], true)) {
            return;
        }

        $owner_id = (int) get_post_meta($post->ID, '_ap_owner_user', true);
        if (!$owner_id && $post->post_author) {
            $owner_id = (int) $post->post_author;
        }

        if ($owner_id <= 0) {
            return;
        }

        $role = 'artpulse_artist' === $post->post_type ? 'artist' : 'organization';
        self::grant_role($owner_id, $role, [
            'source'   => 'post_publish',
            'post_id'  => $post->ID,
            'postType' => $post->post_type,
        ]);
    }

    /**
     * Notify members when they gain a new role.
     */
    protected static function send_upgrade_email(WP_User $user, string $role, array $context = []): void
    {
        if (empty($user->user_email)) {
            return;
        }

        $meta_key = self::UPGRADE_NOTIFICATION_META_PREFIX . sanitize_key($role);
        $already_notified = get_user_meta($user->ID, $meta_key, true);

        if ($already_notified) {
            return;
        }

        $role_label = self::MEMBERSHIP_LABELS[$role] ?? ucfirst($role);
        $subject    = sprintf(
            /* translators: %s is the new role label. */
            __('Your ArtPulse account is now a %s', 'artpulse-management'),
            $role_label
        );

        $dashboard_url = home_url('dashboard');
        $builder_url   = '';

        if ('artist' === $role) {
            $builder_url = isset($context['builder_url']) ? (string) $context['builder_url'] : '';

            if ('' === $builder_url) {
                $post_id = isset($context['post_id']) ? (int) $context['post_id'] : 0;

                if ($post_id <= 0 && isset($context['artist_post_id'])) {
                    $post_id = (int) $context['artist_post_id'];
                }

                if ($post_id > 0) {
                    $builder_url = home_url(add_query_arg([
                        'ap_builder' => 'artist',
                        'post_id'    => $post_id,
                    ], '/'));
                }
            }
        }

        $message = sprintf(
            "%s\n\n%s\n%s",
            sprintf(
                /* translators: 1: member display name, 2: role label. */
                __('Hi %1$s, your account has been upgraded to the %2$s role.', 'artpulse-management'),
                $user->display_name ?: $user->user_login,
                $role_label
            ),
            __('You can access your new tools from the dashboard:', 'artpulse-management'),
            esc_url($dashboard_url)
        );

        if ('' !== $builder_url) {
            $message .= "\n\n" . sprintf(
                /* translators: %s is the artist builder URL. */
                __('Start building your artist profile here: %s', 'artpulse-management'),
                esc_url($builder_url)
            );
        }

        wp_mail($user->user_email, $subject, $message);

        update_user_meta($user->ID, $meta_key, current_time('mysql')); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
    }

    private static function ensure_artist_profile_exists(WP_User $user): int
    {
        $user_id = (int) $user->ID;

        if ($user_id <= 0) {
            return 0;
        }

        $existing = get_posts([
            'post_type'      => 'artist',
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'   => '_ap_owner_user_id',
                    'value' => $user_id,
                ],
            ],
        ]);

        if (!empty($existing)) {
            $post_id = (int) $existing[0];

            if ($post_id > 0) {
                add_post_meta($post_id, '_ap_owner_user_id', $user_id, true);
                update_user_meta($user_id, '_ap_artist_post_id', $post_id);

                return $post_id;
            }
        }

        $post_id = wp_insert_post([
            'post_type'   => 'artist',
            'post_status' => 'draft',
            'post_title'  => $user->display_name ?: $user->user_login,
            'post_author' => $user_id,
        ]);

        if (!$post_id || is_wp_error($post_id)) {
            return 0;
        }

        add_post_meta($post_id, '_ap_owner_user_id', $user_id, true);
        update_user_meta($user_id, '_ap_artist_post_id', $post_id);

        return (int) $post_id;
    }
}
