<?php

namespace ArtPulse\Core;

use WP_Error;
use WP_Post;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function wp_kses_post;
use function wp_update_post;

/**
 * Storage helper for organization upgrade review requests.
 */
class UpgradeReviewRepository
{
    public const POST_TYPE = 'ap_review_request';

    public const META_TYPE = '_ap_review_type';
    public const META_STATUS = '_ap_review_status';
    public const META_USER = '_ap_review_user_id';
    public const META_POST = '_ap_review_post_id';
    public const META_REASON = '_ap_review_reason';
    public const TYPE_ORG = 'org';
    public const TYPE_ARTIST = 'artist';
    /**
     * @deprecated legacy alias – use TYPE_ORG instead.
     */
    public const TYPE_ORG_UPGRADE = 'org_upgrade';
    /**
     * @deprecated legacy alias – use TYPE_ARTIST instead.
     */
    public const TYPE_ARTIST_UPGRADE = 'artist_upgrade';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DENIED = 'denied';

    public static function find_pending(int $user_id, string $type): ?int
    {
        if ($user_id <= 0) {
            return null;
        }

        $normalized_type = UpgradeType::normalise($type);
        if (null === $normalized_type) {
            return null;
        }

        $type_values = UpgradeType::expand($normalized_type);

        $posts = get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => ['private', 'draft', 'publish'],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'     => self::META_USER,
                    'value'   => $user_id,
                    'compare' => '=',
                ],
                [
                    'key'     => self::META_TYPE,
                    'value'   => $type_values,
                    'compare' => 'IN',
                ],
                [
                    'key'   => self::META_STATUS,
                    'value' => self::STATUS_PENDING,
                ],
            ],
        ]);

        if (empty($posts) || !is_array($posts)) {
            return null;
        }

        $request_id = (int) $posts[0];

        return $request_id > 0 ? $request_id : null;
    }

    /**
     * @param array<string,mixed> $args
     */
    public static function create(int $user_id, string $type, array $args = []): int|WP_Error
    {
        if ($user_id <= 0) {
            return new WP_Error(
                'artpulse_upgrade_review_invalid_user',
                __('A valid user is required to create an upgrade review request.', 'artpulse-management')
            );
        }

        $normalized_type = UpgradeType::normalise($type);
        if (null === $normalized_type) {
            return new WP_Error(
                'artpulse_upgrade_review_invalid_type',
                __('The supplied upgrade review type is not supported.', 'artpulse-management')
            );
        }

        $existing_request = self::find_pending($user_id, $normalized_type);
        if (null !== $existing_request) {
            UpgradeAuditLog::log_duplicate_rejected($user_id, $normalized_type, (int) $existing_request, [
                'source' => 'create',
            ]);

            return new WP_Error(
                'ap_duplicate_pending',
                __('A pending upgrade review request already exists for this user and type.', 'artpulse-management'),
                [
                    'request_id' => $existing_request,
                    'status'     => 409,
                ]
            );
        }

        $post_id          = isset($args['post_id']) ? (int) $args['post_id'] : 0;
        $sanitized_post_id = $post_id > 0 ? $post_id : 0;

        $default_title = sprintf(
            /* translators: %1$s review type, %2$d user ID. */
            __('%1$s upgrade request for user %2$d', 'artpulse-management'),
            self::normalise_title_fragment($normalized_type),
            $user_id
        );

        $post_data = [
            'post_title'  => $default_title,
            'post_status' => 'private',
            'post_type'   => self::POST_TYPE,
        ];

        if (isset($args['post_title']) && '' !== trim((string) $args['post_title'])) {
            $post_data['post_title'] = sanitize_text_field((string) $args['post_title']);
        }

        if (isset($args['post_content'])) {
            $post_data['post_content'] = wp_kses_post((string) $args['post_content']);
        }

        if (isset($args['post_excerpt'])) {
            $post_data['post_excerpt'] = wp_kses_post((string) $args['post_excerpt']);
        }

        if (isset($args['note']) && is_string($args['note'])) {
            $sanitised_note = sanitize_textarea_field($args['note']);
            if ('' !== $sanitised_note) {
                $post_data['post_content'] = $sanitised_note;
            }
        }

        $request_id = wp_insert_post($post_data, true);

        if (is_wp_error($request_id)) {
            return $request_id;
        }

        update_post_meta($request_id, self::META_TYPE, $normalized_type);
        update_post_meta($request_id, self::META_STATUS, self::STATUS_PENDING);
        update_post_meta($request_id, self::META_USER, $user_id);
        update_post_meta($request_id, self::META_POST, $sanitized_post_id);
        delete_post_meta($request_id, self::META_REASON);

        UpgradeAuditLog::log_request_created($user_id, $normalized_type, (int) $request_id, [
            'post_id'     => $sanitized_post_id,
            'has_note'    => isset($args['note']) && '' !== trim((string) $args['note']),
            'created_via' => 'create',
        ]);

        return (int) $request_id;
    }

    /**
     * Create a pending review request linking a user to an organisation post.
     */
    public static function create_org_upgrade(int $user_id, int $post_id): ?int
    {
        if ($user_id <= 0 || $post_id <= 0) {
            return null;
        }

        $result = self::upsert_pending($user_id, self::TYPE_ORG, $post_id);

        return $result['request_id'] ?? null;
    }

    /**
     * Ensure that a pending review request exists for a user and type.
     *
     * @return array{request_id:int|null,created:bool}
     */
    public static function upsert_pending(int $user_id, string $type, int $post_id = 0): array
    {
        if ($user_id <= 0) {
            return ['request_id' => null, 'created' => false];
        }

        $normalized_type = UpgradeType::normalise($type);
        if (null === $normalized_type) {
            return ['request_id' => null, 'created' => false];
        }

        $existing = self::get_latest_for_user($user_id, $normalized_type);
        if ($existing instanceof WP_Post && self::STATUS_PENDING === self::get_status($existing)) {
            $request_id = (int) $existing->ID;

            if ($post_id > 0) {
                update_post_meta($request_id, self::META_POST, $post_id);
            }

            delete_post_meta($request_id, self::META_REASON);

            return ['request_id' => $request_id, 'created' => false];
        }

        $request_id = wp_insert_post([
            'post_type'   => self::POST_TYPE,
            'post_status' => 'private',
            'post_title'  => sprintf(
                /* translators: %1$s review type, %2$d user ID. */
                __('%1$s upgrade request for user %2$d', 'artpulse-management'),
                self::normalise_title_fragment($normalized_type),
                $user_id
            ),
        ]);

        if (!$request_id || is_wp_error($request_id)) {
            return ['request_id' => null, 'created' => false];
        }

        update_post_meta($request_id, self::META_TYPE, $normalized_type);
        update_post_meta($request_id, self::META_STATUS, self::STATUS_PENDING);
        update_post_meta($request_id, self::META_USER, $user_id);
        update_post_meta($request_id, self::META_POST, max(0, $post_id));
        delete_post_meta($request_id, self::META_REASON);

        UpgradeAuditLog::log_request_created($user_id, $normalized_type, (int) $request_id, [
            'post_id'     => max(0, $post_id),
            'created_via' => 'upsert',
        ]);

        return ['request_id' => (int) $request_id, 'created' => true];
    }

    /**
     * Retrieve the request type for a review post.
     */
    public static function get_type(WP_Post|int $post): string
    {
        $resolved_post = self::resolve_post($post);
        if (!$resolved_post) {
            return self::TYPE_ORG;
        }

        $type = get_post_meta($resolved_post->ID, self::META_TYPE, true);

        $normalised = is_string($type) && $type !== '' ? UpgradeType::normalise($type) : null;

        return $normalised ?? self::TYPE_ORG;
    }

    /**
     * Return the latest review request for a user and type.
     */
    public static function get_latest_for_user(int $user_id, string $type = self::TYPE_ORG): ?WP_Post
    {
        if ($user_id <= 0) {
            return null;
        }

        $normalized_type = UpgradeType::normalise($type);
        if (null === $normalized_type) {
            return null;
        }

        $query = get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => ['private', 'draft', 'publish'],
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'   => self::META_USER,
                    'value' => $user_id,
                    'compare' => '=',
                ],
                [
                    'key'     => self::META_TYPE,
                    'value'   => UpgradeType::expand($normalized_type),
                    'compare' => 'IN',
                ],
            ],
        ]);

        if (empty($query) || !($query[0] instanceof WP_Post)) {
            return null;
        }

        return $query[0];
    }

    /**
     * Retrieve the user identifier assigned to the review.
     */
    public static function get_user_id(WP_Post|int $post): int
    {
        $resolved_post = self::resolve_post($post);

        return $resolved_post ? (int) get_post_meta($resolved_post->ID, self::META_USER, true) : 0;
    }

    /**
     * Retrieve the target post identifier assigned to the review.
     */
    public static function get_post_id(WP_Post|int $post): int
    {
        $resolved_post = self::resolve_post($post);

        return $resolved_post ? (int) get_post_meta($resolved_post->ID, self::META_POST, true) : 0;
    }

    /**
     * Retrieve the status of the review.
     */
    public static function get_status(WP_Post|int $post): string
    {
        $resolved_post = self::resolve_post($post);
        if (!$resolved_post) {
            return self::STATUS_PENDING;
        }

        $status = get_post_meta($resolved_post->ID, self::META_STATUS, true);

        return is_string($status) && $status !== '' ? $status : self::STATUS_PENDING;
    }

    /**
     * Store a new status and optional reason for a review request.
     */
    public static function set_status(int $request_id, string $status, string $reason = ''): void
    {
        if ($request_id <= 0 || '' === $status) {
            return;
        }

        update_post_meta($request_id, self::META_STATUS, $status);

        if ($reason !== '') {
            $sanitised_reason = sanitize_textarea_field($reason);

            if ('' !== $sanitised_reason) {
                update_post_meta($request_id, self::META_REASON, $sanitised_reason);

                return;
            }
        }

        delete_post_meta($request_id, self::META_REASON);
    }

    public static function get_reason(WP_Post|int $post): string
    {
        $resolved_post = self::resolve_post($post);
        if (!$resolved_post) {
            return '';
        }

        $reason = get_post_meta($resolved_post->ID, self::META_REASON, true);
        return is_string($reason) ? $reason : '';
    }

    /**
     * Retrieve every review request for a given user.
     *
     * @return WP_Post[]
     */
    public static function get_all_for_user(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        $posts = get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => ['private', 'draft', 'publish'],
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'     => self::META_USER,
                    'value'   => $user_id,
                    'compare' => '=',
                ],
            ],
        ]);

        return array_filter(
            is_array($posts) ? $posts : [],
            static fn($post) => $post instanceof WP_Post
        );
    }

    public static function approve(int $request_id): bool
    {
        $post = self::get_request_post($request_id);
        if (!$post) {
            return false;
        }

        $current_status = self::get_status($post);
        if (self::STATUS_APPROVED === $current_status) {
            self::touch($post->ID);

            return true;
        }

        self::set_status($post->ID, self::STATUS_APPROVED);
        self::touch($post->ID);

        do_action(
            'artpulse/upgrade_review/approved',
            $post->ID,
            self::get_user_id($post),
            self::get_type($post)
        );

        return true;
    }

    public static function deny(int $request_id, string $reason = ''): bool
    {
        $post = self::get_request_post($request_id);
        if (!$post) {
            return false;
        }

        $current_status = self::get_status($post);
        $sanitised_reason = sanitize_textarea_field($reason);

        if (self::STATUS_DENIED !== $current_status || $sanitised_reason !== self::get_reason($post)) {
            self::set_status($post->ID, self::STATUS_DENIED, $sanitised_reason);
            self::touch($post->ID);

            do_action(
                'artpulse/upgrade_review/denied',
                $post->ID,
                self::get_user_id($post),
                self::get_type($post),
                $sanitised_reason
            );

            return true;
        }

        self::touch($post->ID);

        return true;
    }

    private static function touch(int $request_id): void
    {
        if ($request_id <= 0) {
            return;
        }

        wp_update_post([
            'ID' => $request_id,
        ]);
    }

    private static function resolve_post(WP_Post|int $post): ?WP_Post
    {
        if ($post instanceof WP_Post) {
            return $post;
        }

        if ($post <= 0) {
            return null;
        }

        $resolved_post = get_post($post);

        return $resolved_post instanceof WP_Post ? $resolved_post : null;
    }

    private static function get_request_post(int $request_id): ?WP_Post
    {
        if ($request_id <= 0) {
            return null;
        }

        $post = self::resolve_post($request_id);

        if (!$post instanceof WP_Post || self::POST_TYPE !== $post->post_type) {
            return null;
        }

        return $post;
    }

    private static function normalise_title_fragment(string $type): string
    {
        return match ($type) {
            self::TYPE_ARTIST => __('Artist', 'artpulse-management'),
            default => __('Organisation', 'artpulse-management'),
        };
    }
}
