<?php

namespace ArtPulse\Core;

use ArtPulse\Frontend\ProfileBuilderConfig;
use ArtPulse\Frontend\Shared\PortfolioAccess;
use WP_Post;
use function absint;
use function add_action;
use function array_filter;
use function array_map;
use function delete_transient;
use function get_post;
use function get_post_meta;
use function get_posts;
use function get_the_title;
use function get_transient;
use function get_permalink;
use function in_array;
use function is_array;
use function preg_split;
use function sanitize_key;
use function set_transient;
use function sprintf;

/**
 * Provide a normalized snapshot of a user's profile state.
 */
final class ProfileState
{
    /**
     * Cache user state for a short period to avoid repeated queries.
     */
    private const CACHE_TTL = 90;

    /**
     * Register hooks for cache invalidation.
     */
    public static function register(): void
    {
        add_action('save_post_artpulse_artist', [self::class, 'purge_by_post'], 10, 3);
        add_action('save_post_artpulse_org', [self::class, 'purge_by_post'], 10, 3);

        $meta_hooks = ['added_post_meta', 'updated_post_meta', 'deleted_post_meta'];
        foreach ($meta_hooks as $hook) {
            add_action($hook, [self::class, 'maybe_purge_on_meta'], 10, 4);
        }
    }

    /**
     * Retrieve a cached snapshot for the given user.
     *
     * @return array<string, mixed>
     */
    public static function for_user(string $type, int $user_id): array
    {
        $type = sanitize_key($type);
        if (!in_array($type, ['artist', 'org'], true) || $user_id <= 0) {
            return self::empty_state();
        }

        $cache_key = self::cache_key($type, $user_id);
        $cached    = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $config    = ProfileBuilderConfig::for($type);
        $post_type = $config['post_type'];

        $post_id = self::resolve_latest_post_id($user_id, $post_type);
        if (!$post_id) {
            $state = array_merge(
                self::empty_state(),
                [
                    'builder_url' => self::builder_url_for_type($type),
                ]
            );
            set_transient($cache_key, $state, self::CACHE_TTL);

            return $state;
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            $state = array_merge(
                self::empty_state(),
                [
                    'builder_url' => self::builder_url_for_type($type),
                ]
            );
            set_transient($cache_key, $state, self::CACHE_TTL);

            return $state;
        }

        $payload  = self::build_payload($post_id);
        $progress = ProfileProgress::compute($payload, $config['required_fields'], $config['steps']);

        $status = self::normalize_status($post->post_status);
        $visibility = self::normalize_visibility((string) get_post_meta($post_id, '_ap_visibility', true));

        $state = [
            'exists'      => true,
            'post_id'     => $post_id,
            'status'      => $status,
            'visibility'  => $visibility,
            'complete'    => (int) ($progress['percent'] ?? 0),
            'public_url'  => self::public_url($post_id, $status, $visibility),
            'builder_url' => self::builder_url_for_type($type),
        ];

        set_transient($cache_key, $state, self::CACHE_TTL);

        return $state;
    }

    public static function can_submit_events(array $state): bool
    {
        return ($state['exists'] ?? false)
            && (($state['status'] ?? '') === 'publish')
            && (($state['visibility'] ?? '') === 'public')
            && (int) ($state['complete'] ?? 0) >= 80;
    }

    /**
     * Purge cache entries when the post is saved.
     */
    public static function purge_by_post(int $post_id, WP_Post $post = null, bool $update = false): void
    {
        $post = $post instanceof WP_Post ? $post : get_post($post_id);
        if (!$post instanceof WP_Post) {
            return;
        }

        $type = self::type_from_post_type($post->post_type);
        if (!$type) {
            return;
        }

        foreach (self::collect_related_users($post_id, $post) as $user_id) {
            delete_transient(self::cache_key($type, $user_id));
        }
    }

    /**
     * Invalidate caches when relevant meta changes.
     */
    public static function maybe_purge_on_meta($meta_id, int $object_id, string $meta_key, $meta_value): void
    {
        $post = get_post($object_id);
        if (!$post instanceof WP_Post) {
            return;
        }

        if (!self::is_relevant_meta($meta_key)) {
            return;
        }

        self::purge_by_post($object_id, $post, true);
    }

    /**
     * Determine whether a meta key should trigger invalidation.
     */
    private static function is_relevant_meta(string $meta_key): bool
    {
        $keys = [
            '_ap_tagline',
            '_ap_about',
            '_ap_website',
            '_ap_socials',
            '_ap_gallery_ids',
            '_thumbnail_id',
            '_ap_visibility',
            '_ap_owner_user',
            '_ap_owner_users',
        ];

        return in_array($meta_key, $keys, true);
    }

    /**
     * Build a consistent cache key.
     */
    private static function cache_key(string $type, int $user_id): string
    {
        return sprintf('ap_profile_state_%s_%d', $type, $user_id);
    }

    /**
     * Resolve the most recently modified owned post.
     */
    private static function resolve_latest_post_id(int $user_id, string $post_type): ?int
    {
        $owned = PortfolioAccess::get_owned_portfolio_ids($user_id, $post_type);
        if (empty($owned)) {
            return null;
        }

        $posts = get_posts([
            'post_type'      => $post_type,
            'post_status'    => ['draft', 'pending', 'publish', 'future'],
            'post__in'       => $owned,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'numberposts'    => 1,
            'fields'         => 'ids',
        ]);

        if (empty($posts)) {
            return null;
        }

        return (int) $posts[0];
    }

    /**
     * Assemble payload fields used for progress calculations.
     *
     * @return array<string, mixed>
     */
    private static function build_payload(int $post_id): array
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return [];
        }

        $social_meta = get_post_meta($post_id, '_ap_socials', true);
        if (is_array($social_meta)) {
            $socials = array_filter(array_map('trim', array_map('strval', $social_meta)));
        } else {
            $socials = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $social_meta) ?: []));
        }

        $gallery = get_post_meta($post_id, '_ap_gallery_ids', true);
        if (!is_array($gallery)) {
            $gallery = [];
        }

        return [
            'title'          => get_the_title($post_id),
            'tagline'        => (string) get_post_meta($post_id, '_ap_tagline', true),
            'bio'            => (string) get_post_meta($post_id, '_ap_about', true),
            'website_url'    => (string) get_post_meta($post_id, '_ap_website', true),
            'socials'        => array_values($socials),
            'featured_media' => (int) get_post_thumbnail_id($post_id),
            'gallery'        => array_values(array_map('absint', $gallery)),
            'visibility'     => self::normalize_visibility((string) get_post_meta($post_id, '_ap_visibility', true)),
            'status'         => self::normalize_status($post->post_status),
        ];
    }

    /**
     * Convert WordPress post status to normalized value.
     */
    private static function normalize_status(string $status): string
    {
        if (in_array($status, ['draft', 'pending', 'publish'], true)) {
            return $status;
        }

        if ('future' === $status) {
            return 'pending';
        }

        return 'draft';
    }

    /**
     * Normalize stored visibility meta.
     */
    private static function normalize_visibility(string $visibility): ?string
    {
        $visibility = strtolower(trim($visibility));
        if (in_array($visibility, ['public', 'private'], true)) {
            return $visibility;
        }

        return null;
    }

    /**
     * Determine public URL when published and visible.
     */
    private static function public_url(int $post_id, string $status, ?string $visibility): ?string
    {
        if ('publish' === $status && 'public' === $visibility) {
            $url = get_permalink($post_id);
            return $url ? $url : null;
        }

        return null;
    }

    /**
     * Map post type to profile type key.
     */
    private static function type_from_post_type(string $post_type): ?string
    {
        if ('artpulse_artist' === $post_type) {
            return 'artist';
        }

        if ('artpulse_org' === $post_type) {
            return 'org';
        }

        return null;
    }

    /**
     * Determine builder URL for a type.
     */
    private static function builder_url_for_type(string $type): ?string
    {
        if ('artist' === $type) {
            return get_page_url('artist_builder_page_id');
        }

        if ('org' === $type) {
            return get_page_url('org_builder_page_id');
        }

        return null;
    }

    /**
     * Collect all relevant user identifiers for a post.
     *
     * @return int[]
     */
    private static function collect_related_users(int $post_id, WP_Post $post): array
    {
        $users = [absint($post->post_author)];

        $primary = absint(get_post_meta($post_id, '_ap_owner_user', true));
        if ($primary) {
            $users[] = $primary;
        }

        $additional = get_post_meta($post_id, '_ap_owner_users', true);
        if (is_array($additional)) {
            foreach ($additional as $user_id) {
                $users[] = absint($user_id);
            }
        }

        $users = array_filter($users, static fn($id) => $id > 0);

        return array_values(array_unique($users));
    }

    /**
     * Empty default state.
     *
     * @return array<string, mixed>
     */
    private static function empty_state(): array
    {
        return [
            'exists'      => false,
            'post_id'     => null,
            'status'      => 'none',
            'visibility'  => null,
            'complete'    => 0,
            'public_url'  => null,
            'builder_url' => null,
        ];
    }
}
