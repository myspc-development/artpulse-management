<?php

namespace ArtPulse\Core;

use WP_Post;
use function sanitize_key;

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
    public const TYPE_ORG_UPGRADE = 'org_upgrade';
    public const TYPE_ARTIST_UPGRADE = 'artist_upgrade';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DENIED = 'denied';

    /**
     * Create a pending review request linking a user to an organisation post.
     */
    public static function create_org_upgrade(int $user_id, int $post_id): ?int
    {
        if ($user_id <= 0 || $post_id <= 0) {
            return null;
        }

        $result = self::upsert_pending($user_id, self::TYPE_ORG_UPGRADE, $post_id);

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

        $normalized_type = self::normalise_type($type);
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

        return ['request_id' => (int) $request_id, 'created' => true];
    }

    /**
     * Retrieve the request type for a review post.
     */
    public static function get_type(WP_Post $post): string
    {
        $type = get_post_meta($post->ID, self::META_TYPE, true);

        return is_string($type) && $type !== '' ? $type : self::TYPE_ORG_UPGRADE;
    }

    /**
     * Return the latest review request for a user and type.
     */
    public static function get_latest_for_user(int $user_id, string $type = self::TYPE_ORG_UPGRADE): ?WP_Post
    {
        if ($user_id <= 0) {
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
                    'key'   => self::META_TYPE,
                    'value' => $type,
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
    public static function get_user_id(WP_Post $post): int
    {
        return (int) get_post_meta($post->ID, self::META_USER, true);
    }

    /**
     * Retrieve the target post identifier assigned to the review.
     */
    public static function get_post_id(WP_Post $post): int
    {
        return (int) get_post_meta($post->ID, self::META_POST, true);
    }

    /**
     * Retrieve the status of the review.
     */
    public static function get_status(WP_Post $post): string
    {
        $status = get_post_meta($post->ID, self::META_STATUS, true);

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

    public static function get_reason(WP_Post $post): string
    {
        $reason = get_post_meta($post->ID, self::META_REASON, true);
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

    private static function normalise_type(string $type): ?string
    {
        $key = sanitize_key($type);

        return match ($key) {
            self::TYPE_ORG_UPGRADE => self::TYPE_ORG_UPGRADE,
            self::TYPE_ARTIST_UPGRADE => self::TYPE_ARTIST_UPGRADE,
            'organization', 'organisation', 'org', 'org_upgrade' => self::TYPE_ORG_UPGRADE,
            'artist', 'artist_upgrade' => self::TYPE_ARTIST_UPGRADE,
            default => null,
        };
    }

    private static function normalise_title_fragment(string $type): string
    {
        return match ($type) {
            self::TYPE_ARTIST_UPGRADE => __('Artist', 'artpulse-management'),
            default => __('Organisation', 'artpulse-management'),
        };
    }
}
