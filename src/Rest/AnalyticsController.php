<?php

namespace ArtPulse\Rest;

use ArtPulse\Core\Metrics;
use ArtPulse\Core\RoleGate;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_User_Query;
use function absint;
use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function delete_transient;
use function get_current_user_id;
use function get_post;
use function get_post_meta;
use function get_posts;
use function get_user_meta;
use function get_transient;
use function rest_ensure_response;
use function set_transient;
use function __;

final class AnalyticsController
{
    private const CACHE_TTL = 10 * MINUTE_IN_SECONDS;

    public static function register(): void
    {
        register_rest_route('artpulse/v1', '/analytics/member', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_member_analytics'],
            'permission_callback' => [self::class, 'ensure_member_access'],
            'args'                => self::range_args(),
        ]);

        register_rest_route('artpulse/v1', '/analytics/artist', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_artist_analytics'],
            'permission_callback' => [self::class, 'ensure_artist_access'],
            'args'                => self::range_args(),
        ]);

        register_rest_route('artpulse/v1', '/analytics/org', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_org_analytics'],
            'permission_callback' => [self::class, 'ensure_org_access'],
            'args'                => self::range_args(),
        ]);
    }

    public static function ensure_member_access(): bool|WP_Error
    {
        if (RoleGate::user_can_access('member')) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            __('You must be logged in to view analytics.', 'artpulse-management'),
            ['status' => 403]
        );
    }

    public static function ensure_artist_access(): bool|WP_Error
    {
        if (RoleGate::user_can_access('artist')) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            __('Artist access required to view analytics.', 'artpulse-management'),
            ['status' => 403]
        );
    }

    public static function ensure_org_access(): bool|WP_Error
    {
        if (RoleGate::user_can_access('org')) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            __('Organization access required to view analytics.', 'artpulse-management'),
            ['status' => 403]
        );
    }

    public static function get_member_analytics(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = get_current_user_id();
        $range   = Metrics::normalize_range((int) $request->get_param('range'));
        $cache   = get_transient(self::member_cache_key($user_id, $range));
        if (false !== $cache) {
            return rest_ensure_response($cache);
        }

        $period   = Metrics::build_period($range);
        $dates    = $period['dates'];
        $start    = $period['start'];
        $end      = $period['end'];
        $favorites_table = self::table('ap_favorites');
        $follows_table   = self::table('ap_follows');
        $saves_table     = self::table('ap_event_saves');

        $metrics  = [
            'views'     => self::collect_member_notifications($user_id, $start, $end),
            'favorites' => self::collect_table_counts($favorites_table, 'favorited_on', ['user_id = %d'], [$user_id], $start, $end),
            'follows'   => self::collect_table_counts($follows_table, 'followed_on', ['user_id = %d'], [$user_id], $start, $end),
            'rsvps'     => self::collect_table_counts($saves_table, 'saved_on', ['user_id = %d'], [$user_id], $start, $end),
        ];

        $payload = self::build_payload($range, $dates, $metrics);
        set_transient(self::member_cache_key($user_id, $range), $payload, self::CACHE_TTL);

        return rest_ensure_response($payload);
    }

    public static function get_artist_analytics(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = get_current_user_id();
        $range   = Metrics::normalize_range((int) $request->get_param('range'));
        $cache   = get_transient(self::artist_cache_key($user_id, $range));
        if (false !== $cache) {
            return rest_ensure_response($cache);
        }

        $artist_ids = self::get_artist_ids_for_user($user_id);
        $period     = Metrics::build_period($range);
        $dates      = $period['dates'];
        $start      = $period['start'];
        $end        = $period['end'];

        $artwork_ids = self::get_content_ids_for_artists('artpulse_artwork', $artist_ids);
        $event_ids   = self::get_content_ids_for_artists('artpulse_event', $artist_ids);

        $follows_table = self::table('ap_follows');

        $metrics = [
            'views'     => self::collect_event_likes($event_ids, $start, $end),
            'favorites' => self::collect_artist_favorites($artwork_ids, $event_ids, $start, $end),
            'follows'   => !empty($artist_ids)
                ? self::collect_table_counts(
                    $follows_table,
                    'followed_on',
                    ['object_type = %s', 'object_id IN (' . self::placeholders(count($artist_ids)) . ')'],
                    array_merge(['artpulse_artist'], $artist_ids),
                    $start,
                    $end
                )
                : [],
            'rsvps'     => self::collect_event_saves($event_ids, $start, $end),
        ];

        $payload = self::build_payload($range, $dates, $metrics);
        set_transient(self::artist_cache_key($user_id, $range), $payload, self::CACHE_TTL);

        return rest_ensure_response($payload);
    }

    public static function get_org_analytics(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = get_current_user_id();
        $range   = Metrics::normalize_range((int) $request->get_param('range'));
        $cache   = get_transient(self::org_cache_key($user_id, $range));
        if (false !== $cache) {
            return rest_ensure_response($cache);
        }

        $org_ids = self::get_org_ids_for_user($user_id);
        $period  = Metrics::build_period($range);
        $dates   = $period['dates'];
        $start   = $period['start'];
        $end     = $period['end'];

        $event_ids = self::get_content_ids_for_orgs('artpulse_event', $org_ids);
        $follows_table = self::table('ap_follows');

        $metrics = [
            'views'     => self::collect_event_likes($event_ids, $start, $end),
            'favorites' => self::collect_org_favorites($event_ids, $start, $end),
            'follows'   => !empty($org_ids)
                ? self::collect_table_counts(
                    $follows_table,
                    'followed_on',
                    ['object_type = %s', 'object_id IN (' . self::placeholders(count($org_ids)) . ')'],
                    array_merge(['artpulse_org'], $org_ids),
                    $start,
                    $end
                )
                : [],
            'rsvps'     => self::collect_event_saves($event_ids, $start, $end),
        ];

        $payload = self::build_payload($range, $dates, $metrics);
        set_transient(self::org_cache_key($user_id, $range), $payload, self::CACHE_TTL);

        return rest_ensure_response($payload);
    }

    public static function invalidate_member_cache(int $user_id): void
    {
        if ($user_id <= 0) {
            return;
        }
        self::purge_cache_set(self::member_cache_key($user_id, 7), self::member_cache_key($user_id, 30), self::member_cache_key($user_id, 90));
    }

    public static function invalidate_artist_cache(int $user_id): void
    {
        if ($user_id <= 0) {
            return;
        }
        self::purge_cache_set(self::artist_cache_key($user_id, 7), self::artist_cache_key($user_id, 30), self::artist_cache_key($user_id, 90));
    }

    public static function invalidate_org_cache(int $user_id): void
    {
        if ($user_id <= 0) {
            return;
        }
        self::purge_cache_set(self::org_cache_key($user_id, 7), self::org_cache_key($user_id, 30), self::org_cache_key($user_id, 90));
    }

    public static function invalidate_event_caches(int $event_id): void
    {
        $post = get_post($event_id);
        if (!$post instanceof WP_Post) {
            return;
        }

        $author_id = (int) $post->post_author;
        if ($author_id > 0) {
            self::invalidate_member_cache($author_id);
            self::invalidate_artist_cache($author_id);
            self::invalidate_org_cache($author_id);
        }

        $artist_id = (int) get_post_meta($event_id, '_ap_artist_id', true);
        if ($artist_id > 0) {
            $artist_users = self::get_artist_manager_user_ids($artist_id);
            foreach ($artist_users as $user_id) {
                self::invalidate_artist_cache($user_id);
            }
        }

        $org_id = (int) get_post_meta($event_id, '_ap_org_id', true);
        if ($org_id > 0) {
            $org_users = self::get_org_manager_user_ids($org_id);
            foreach ($org_users as $user_id) {
                self::invalidate_org_cache($user_id);
            }
        }
    }

    private static function build_payload(int $range, array $dates, array $metrics): array
    {
        $series_data = Metrics::build_series($dates, $metrics);

        return [
            'range'  => $range,
            'series' => $series_data['series'],
            'totals' => $series_data['totals'],
        ];
    }

    /**
     * @param array<int> $event_ids
     * @return array<string, int>
     */
    private static function collect_event_likes(array $event_ids, string $start, string $end): array
    {
        if (empty($event_ids)) {
            return [];
        }

        return self::collect_table_counts(
            self::table('ap_event_likes'),
            'liked_on',
            ['event_id IN (' . self::placeholders(count($event_ids)) . ')'],
            $event_ids,
            $start,
            $end
        );
    }

    /**
     * @param array<int> $event_ids
     * @return array<string, int>
     */
    private static function collect_event_saves(array $event_ids, string $start, string $end): array
    {
        if (empty($event_ids)) {
            return [];
        }

        return self::collect_table_counts(
            self::table('ap_event_saves'),
            'saved_on',
            ['event_id IN (' . self::placeholders(count($event_ids)) . ')'],
            $event_ids,
            $start,
            $end
        );
    }

    /**
     * @param array<int> $artwork_ids
     * @param array<int> $event_ids
     * @return array<string, int>
     */
    private static function collect_artist_favorites(array $artwork_ids, array $event_ids, string $start, string $end): array
    {
        $counts = [];

        if (!empty($artwork_ids)) {
            $counts = self::merge_metric_counts(
                $counts,
                self::collect_table_counts(
                    self::table('ap_favorites'),
                    'favorited_on',
                    ['object_type = %s', 'object_id IN (' . self::placeholders(count($artwork_ids)) . ')'],
                    array_merge(['artpulse_artwork'], $artwork_ids),
                    $start,
                    $end
                )
            );
        }

        if (!empty($event_ids)) {
            $counts = self::merge_metric_counts(
                $counts,
                self::collect_table_counts(
                    self::table('ap_favorites'),
                    'favorited_on',
                    ['object_type = %s', 'object_id IN (' . self::placeholders(count($event_ids)) . ')'],
                    array_merge(['artpulse_event'], $event_ids),
                    $start,
                    $end
                )
            );
        }

        return $counts;
    }

    /**
     * @param array<int> $event_ids
     * @return array<string, int>
     */
    private static function collect_org_favorites(array $event_ids, string $start, string $end): array
    {
        if (empty($event_ids)) {
            return [];
        }

        return self::collect_table_counts(
            self::table('ap_favorites'),
            'favorited_on',
            ['object_type = %s', 'object_id IN (' . self::placeholders(count($event_ids)) . ')'],
            array_merge(['artpulse_event'], $event_ids),
            $start,
            $end
        );
    }

    private static function collect_member_notifications(int $user_id, string $start, string $end): array
    {
        return self::collect_table_counts(
            self::table('ap_notifications'),
            'created_at',
            ['user_id = %d', 'status = %s'],
            [$user_id, 'read'],
            $start,
            $end
        );
    }

    /**
     * @param array<int|string> $params
     * @return array<string, int>
     */
    private static function collect_table_counts(string $table, string $column, array $conditions, array $params, string $start, string $end): array
    {
        return Metrics::collect_counts($table, $column, $conditions, $params, $start, $end);
    }

    private static function member_cache_key(int $user_id, int $range): string
    {
        return sprintf('ap_analytics_member_%d_%d', max(0, $user_id), $range);
    }

    private static function artist_cache_key(int $user_id, int $range): string
    {
        return sprintf('ap_analytics_artist_%d_%d', max(0, $user_id), $range);
    }

    private static function org_cache_key(int $user_id, int $range): string
    {
        return sprintf('ap_analytics_org_%d_%d', max(0, $user_id), $range);
    }

    private static function purge_cache_set(string ...$keys): void
    {
        foreach ($keys as $key) {
            delete_transient($key);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function range_args(): array
    {
        return [
            'range' => [
                'sanitize_callback' => static function ($value): int {
                    return Metrics::normalize_range((int) $value);
                },
                'validate_callback' => static function ($value): bool {
                    return in_array((int) $value, Metrics::ALLOWED_RANGES, true) || null === $value;
                },
                'default' => Metrics::ALLOWED_RANGES[0],
            ],
        ];
    }

    /**
     * @return array<int>
     */
    private static function get_artist_ids_for_user(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        $meta_ids = array_map('absint', (array) get_user_meta($user_id, '_ap_artist_post_id', false));

        $owned = get_posts([
            'post_type'      => 'artpulse_artist',
            'post_status'    => ['publish', 'draft', 'pending', 'future'],
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'author'         => $user_id,
        ]);

        $primary = get_posts([
            'post_type'      => 'artpulse_artist',
            'post_status'    => ['publish', 'draft', 'pending', 'future'],
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_key'       => '_ap_owner_user',
            'meta_value'     => $user_id,
        ]);

        $team = get_posts([
            'post_type'      => 'artpulse_artist',
            'post_status'    => ['publish', 'draft', 'pending', 'future'],
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_ap_owner_users',
                    'value'   => sprintf(':%d;', $user_id),
                    'compare' => 'LIKE',
                ],
            ],
        ]);

        $all = array_merge($meta_ids, (array) $owned, (array) $primary, (array) $team);
        $ids = array_values(array_unique(array_filter(array_map('absint', $all))));

        return $ids;
    }

    /**
     * @param array<int> $artist_ids
     * @return array<int>
     */
    private static function get_content_ids_for_artists(string $post_type, array $artist_ids): array
    {
        if (empty($artist_ids)) {
            return [];
        }

        $posts = get_posts([
            'post_type'      => $post_type,
            'post_status'    => ['publish', 'draft'],
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_ap_artist_id',
                    'value'   => $artist_ids,
                    'compare' => 'IN',
                ],
            ],
        ]);

        return array_values(array_unique(array_map('absint', (array) $posts)));
    }

    /**
     * @return array<int>
     */
    private static function get_org_ids_for_user(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        $ids = [];
        $meta = get_user_meta($user_id, '_ap_org_ids', true);
        if (is_array($meta)) {
            $ids = array_map('absint', $meta);
        } elseif (!empty($meta)) {
            $ids = [absint($meta)];
        }

        $legacy = absint(get_user_meta($user_id, '_ap_org_post_id', true));
        if ($legacy > 0) {
            $ids[] = $legacy;
        }

        $single = absint(get_user_meta($user_id, 'ap_organization_id', true));
        if ($single > 0) {
            $ids[] = $single;
        }

        return array_values(array_unique(array_filter(array_map('absint', $ids))));
    }

    /**
     * @param array<int> $org_ids
     * @return array<int>
     */
    private static function get_content_ids_for_orgs(string $post_type, array $org_ids): array
    {
        if (empty($org_ids)) {
            return [];
        }

        $posts = get_posts([
            'post_type'      => $post_type,
            'post_status'    => ['publish', 'draft'],
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_key'       => '_ap_org_id',
            'meta_value'     => $org_ids,
            'meta_compare'   => 'IN',
        ]);

        return array_values(array_unique(array_map('absint', (array) $posts)));
    }

    /**
     * @param array<string, int> $existing
     * @param array<string, int> $next
     * @return array<string, int>
     */
    private static function merge_metric_counts(array $existing, array $next): array
    {
        foreach ($next as $day => $count) {
            $existing[$day] = ($existing[$day] ?? 0) + (int) $count;
        }

        return $existing;
    }

    private static function placeholders(int $count): string
    {
        if ($count <= 0) {
            return '';
        }

        return implode(',', array_fill(0, $count, '%d'));
    }

    /**
     * @return array<int>
     */
    private static function get_artist_manager_user_ids(int $artist_id): array
    {
        $post = get_post($artist_id);
        if (!$post instanceof WP_Post) {
            return [];
        }

        $users = [(int) $post->post_author];
        $owner = (int) get_post_meta($artist_id, '_ap_owner_user', true);
        if ($owner > 0) {
            $users[] = $owner;
        }

        $team = get_post_meta($artist_id, '_ap_owner_users', true);
        if (is_array($team)) {
            foreach ($team as $id) {
                $users[] = (int) $id;
            }
        }

        return array_values(array_unique(array_filter(array_map('absint', $users))));
    }

    /**
     * @return array<int>
     */
    private static function get_org_manager_user_ids(int $org_id): array
    {
        $org_id = absint($org_id);

        $query = new WP_User_Query([
            'fields'     => 'ID',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key'   => '_ap_org_ids',
                    'value' => '"' . $org_id . '"',
                    'compare' => 'LIKE',
                ],
                [
                    'key'   => '_ap_org_post_id',
                    'value' => $org_id,
                ],
                [
                    'key'   => 'ap_organization_id',
                    'value' => $org_id,
                ],
            ],
        ]);

        return array_map('absint', $query->get_results());
    }

    private static function table(string $suffix): string
    {
        global $wpdb;

        if ($wpdb instanceof \wpdb) {
            return $wpdb->prefix . ltrim($suffix, '_');
        }

        return $suffix;
    }
}
