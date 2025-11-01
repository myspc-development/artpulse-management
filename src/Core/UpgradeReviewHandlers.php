<?php

namespace ArtPulse\Core;

use WP_Post;
use WP_User;

use function __;
use function add_query_args;
use function esc_url;
use function esc_url_raw;
use function get_missing_page_fallback;
use function get_page_url;
use function is_email;
use function sanitize_key;
use function sprintf;
use function wp_mail;
use function wp_strip_all_tags;

/**
 * Handles side effects triggered when upgrade reviews are approved or denied.
 */
class UpgradeReviewHandlers
{
    private const TYPE_ARTIST = 'artist';
    private const TYPE_ORGANIZATION = 'org';

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
            'label'        => 'Artist',
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
            'label'        => 'Organization',
        ],
    ];

    public static function register(): void
    {
        add_action('artpulse/upgrade_review/approved', [self::class, 'onApproved'], 10, 3);
        add_action('artpulse/upgrade_review/denied', [self::class, 'onDenied'], 10, 4);
    }

    public static function onApproved(int $review_id, int $user_id, string $type): void
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
        if ($profile_id > 0 && $review_id > 0) {
            update_post_meta($review_id, UpgradeReviewRepository::META_POST, $profile_id);
        }

        self::notify_decision($user, $normalised_type, 'approved', $review_id, $profile_id);
    }

    public static function onDenied(int $review_id, int $user_id, string $type, string $reason = ''): void
    {
        if ($user_id <= 0) {
            return;
        }

        $normalised_type = self::normalise_type($type);
        if (null === $normalised_type) {
            return;
        }

        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) {
            return;
        }

        if ('' === $reason && $review_id > 0) {
            $reason = UpgradeReviewRepository::get_reason($review_id);
        }

        self::notify_decision($user, $normalised_type, 'denied', $review_id, 0, $reason);
    }

    private static function notify_decision(
        WP_User $user,
        string $type,
        string $status,
        int $request_id,
        int $profile_id,
        string $reason = ''
    ): void {
        if (!array_key_exists($type, self::TYPE_CONFIG)) {
            return;
        }

        $label = (string) (self::TYPE_CONFIG[$type]['label'] ?? ucfirst($type));
        $reason = trim(wp_strip_all_tags($reason));

        $email_subject = 'approved' === $status
            ? __('Your ArtPulse upgrade was approved', 'artpulse-management')
            : __('Your ArtPulse upgrade was denied', 'artpulse-management');

        $greeting_name = trim((string) ($user->display_name ?: $user->user_login));
        $greeting = '' !== $greeting_name
            ? sprintf(__('Hi %s,', 'artpulse-management'), $greeting_name)
            : __('Hello,', 'artpulse-management');

        $dashboard_url = self::get_dashboard_url();
        $builder_url   = 'approved' === $status ? self::build_builder_url($type, $dashboard_url) : '';

        $email_lines = [$greeting];

        if ('approved' === $status) {
            $email_lines[] = sprintf(
                /* translators: %s is the upgrade label. */
                __('Great news! Your %s upgrade request was approved.', 'artpulse-management'),
                strtolower($label)
            );

            if ($builder_url !== '') {
                $email_lines[] = sprintf(
                    __('Start building your profile: %s', 'artpulse-management'),
                    $builder_url
                );
            }
        } else {
            $email_lines[] = sprintf(
                /* translators: %s is the upgrade label. */
                __('We’re sorry, but your %s upgrade request was not approved.', 'artpulse-management'),
                strtolower($label)
            );

            if ('' !== $reason) {
                $email_lines[] = sprintf(
                    /* translators: %s is the moderator supplied reason. */
                    __('Reason: %s', 'artpulse-management'),
                    $reason
                );
            }
        }

        if ($dashboard_url !== '') {
            $email_lines[] = sprintf(
                __('View your dashboard: %s', 'artpulse-management'),
                $dashboard_url
            );
        }

        $email_body = implode(PHP_EOL . PHP_EOL, $email_lines);

        $email_sent = false;
        if (is_email($user->user_email)) {
            try {
                $email_sent = (bool) wp_mail($user->user_email, $email_subject, $email_body);
            } catch (\Throwable $ignored) {
                $email_sent = false;
            }
        }

        $message = sprintf(
            /* translators: %s is the membership upgrade label. */
            __('Your %s upgrade request was %s.', 'artpulse-management'),
            $label,
            'approved' === $status ? __('approved', 'artpulse-management') : __('not approved', 'artpulse-management')
        );

        if ('approved' !== $status && '' !== $reason) {
            $message .= ' ' . sprintf(
                /* translators: %s is the moderator supplied reason. */
                __('Reason: %s', 'artpulse-management'),
                $reason
            );
        }

        $link_fragments = [];
        if ($builder_url !== '') {
            $link_fragments[] = sprintf(
                __('Builder: %s', 'artpulse-management'),
                esc_url($builder_url)
            );
        }
        if ($dashboard_url !== '') {
            $link_fragments[] = sprintf(
                __('Dashboard: %s', 'artpulse-management'),
                esc_url($dashboard_url)
            );
        }

        if (!empty($link_fragments)) {
            $message .= ' ' . implode(' ', $link_fragments);
        }

        $notification_type = 'approved' === $status
            ? 'upgrade_request_approved'
            : 'upgrade_request_denied';

        $object_id  = 'approved' === $status && $profile_id > 0 ? $profile_id : $request_id;
        $related_id = 'approved' === $status ? $request_id : 0;

        $notification_sent = false;
        if (class_exists('\\ArtPulse\\Community\\NotificationManager')) {
            try {
                \ArtPulse\Community\NotificationManager::add(
                    (int) $user->ID,
                    $notification_type,
                    $object_id > 0 ? $object_id : null,
                    $related_id > 0 ? $related_id : null,
                    $message
                );
                $notification_sent = true;
            } catch (\Throwable $ignored) {
                $notification_sent = false;
            }
        }

        UpgradeAuditLog::log_notifications_sent(
            (int) $user->ID,
            $status,
            [
                'request_id' => $request_id,
                'profile_id' => $profile_id,
                'type'       => $type,
                'channels'   => [
                    'email' => $email_sent,
                    'in_app'=> $notification_sent,
                ],
                'reason'     => $reason,
            ]
        );
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

        UpgradeAuditLog::log_profile_autocreated($user_id, (int) $post_id, [
            'type'      => $normalised_type,
            'post_type' => $post_type,
        ]);

        return (int) $post_id;
    }

    private static function get_dashboard_url(): string
    {
        $base = get_page_url('dashboard_page_id');
        if (!$base) {
            $base = get_missing_page_fallback('dashboard_page_id');
        }

        return is_string($base) && $base !== '' ? esc_url_raw($base) : '';
    }

    private static function build_builder_url(string $type, string $dashboard_url): string
    {
        $key      = self::TYPE_ARTIST === $type ? 'artist_builder_page_id' : 'org_builder_page_id';
        $base_url = get_page_url($key);

        if (!$base_url) {
            $base_url = get_missing_page_fallback($key);
        }

        if (!is_string($base_url) || '' === $base_url) {
            return '';
        }

        $query_type = self::TYPE_ARTIST === $type ? 'artist' : 'organization';

        $args = [
            'ap_builder' => $query_type,
            'autocreate' => '1',
        ];

        if ('' !== $dashboard_url) {
            $args['redirect'] = $dashboard_url;
        }

        $url = add_query_args($base_url, $args);

        return esc_url_raw($url);
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
            UpgradeReviewRepository::TYPE_ARTIST,
            UpgradeReviewRepository::TYPE_ARTIST_UPGRADE,
            'artist',
            'artists',
            'ap_artist',
            'artpulse_artist' => self::TYPE_ARTIST,
            UpgradeReviewRepository::TYPE_ORG,
            UpgradeReviewRepository::TYPE_ORG_UPGRADE,
            'organization',
            'organisation',
            'org',
            'orgs',
            'ap_org',
            'ap_org_manager',
            'artpulse_org',
            'artpulse_organization' => self::TYPE_ORGANIZATION,
            default => null,
        };
    }
}
