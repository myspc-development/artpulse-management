<?php

namespace ArtPulse\Rest;

use ArtPulse\Core\PostTypeRegistrar;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use function esc_url_raw;
use function get_permalink;
use function get_post;
use function get_the_post_thumbnail_url;
use function get_the_title;
use function is_user_logged_in;
use function mysql2date;
use function rest_ensure_response;
use function sanitize_key;
use function wp_strip_all_tags;

class MemberDashboardController
{
    private const OVERVIEW_CACHE_PREFIX = 'ap_me_overview_';
    private const OVERVIEW_TTL          = 5 * MINUTE_IN_SECONDS;
    private const FAVORITE_TYPE_MAP     = [
        'artwork' => 'artpulse_artwork',
        'event'   => PostTypeRegistrar::EVENT_POST_TYPE,
    ];
    private const FOLLOW_TYPE_MAP       = [
        'artist' => 'artpulse_artist',
        'org'    => 'artpulse_org',
    ];

    public static function register(): void
    {
        register_rest_route('artpulse/v1', '/me/overview', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_overview'],
            'permission_callback' => [self::class, 'ensure_logged_in'],
        ]);

        register_rest_route('artpulse/v1', '/me/favorites', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_favorites'],
            'permission_callback' => [self::class, 'ensure_logged_in'],
            'args'                => [
                'type'      => [
                    'sanitize_callback' => [self::class, 'sanitize_type_param'],
                    'validate_callback' => [self::class, 'validate_favorite_type'],
                ],
                'per_page'  => [
                    'sanitize_callback' => 'absint',
                ],
                'page'      => [
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route('artpulse/v1', '/me/follows', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_follows'],
            'permission_callback' => [self::class, 'ensure_logged_in'],
            'args'                => [
                'type'      => [
                    'sanitize_callback' => [self::class, 'sanitize_type_param'],
                    'validate_callback' => [self::class, 'validate_follow_type'],
                ],
                'per_page'  => [
                    'sanitize_callback' => 'absint',
                ],
                'page'      => [
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route('artpulse/v1', '/me/notifications', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_notifications'],
            'permission_callback' => [self::class, 'ensure_logged_in'],
            'args'                => [
                'per_page' => [
                    'sanitize_callback' => 'absint',
                ],
                'page'     => [
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route('artpulse/v1', '/me/notifications/mark-read', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'mark_notifications_read'],
            'permission_callback' => [self::class, 'ensure_logged_in'],
        ]);
    }

    public static function ensure_logged_in(): bool|WP_Error
    {
        if (is_user_logged_in()) {
            return true;
        }

        return new WP_Error(
            'forbidden',
            __('You must be logged in to access this resource.', 'artpulse-management'),
            ['status' => 403]
        );
    }

    public static function invalidate_overview_cache(int $user_id): void
    {
        if ($user_id <= 0) {
            return;
        }

        delete_transient(self::get_overview_cache_key($user_id));
    }

    public static function get_overview(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = get_current_user_id();
        $cache   = get_transient(self::get_overview_cache_key($user_id));
        if (false !== $cache) {
            return rest_ensure_response($cache);
        }

        $counts     = self::build_counts($user_id);
        $next_events = self::get_next_events($user_id);

        $payload = [
            'counts'     => $counts,
            'nextEvents' => $next_events,
        ];

        set_transient(self::get_overview_cache_key($user_id), $payload, self::OVERVIEW_TTL);

        return rest_ensure_response($payload);
    }

    public static function get_favorites(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $user_id    = get_current_user_id();
        $type_param = $request->get_param('type');
        $object_type = self::map_favorite_type($type_param);

        $per_page = self::sanitize_per_page((int) $request->get_param('per_page'));
        $page     = self::sanitize_page((int) $request->get_param('page'));
        $offset   = ($page - 1) * $per_page;

        $table = $wpdb->prefix . 'ap_favorites';

        $params = [$user_id];
        $where  = 'WHERE user_id = %d';
        if (null !== $object_type) {
            $where   .= ' AND object_type = %s';
            $params[] = $object_type;
        }

        $query_params = array_merge($params, [$per_page, $offset]);
        $sql          = $wpdb->prepare(
            "SELECT object_id, object_type, favorited_on FROM $table $where ORDER BY favorited_on DESC LIMIT %d OFFSET %d",
            ...$query_params
        );

        $rows  = $wpdb->get_results($sql);
        $items = [];

        foreach ($rows as $row) {
            $post_id   = (int) $row->object_id;
            $post_type = (string) $row->object_type;
            $post      = get_post($post_id);

            if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
                continue;
            }

            $items[] = [
                'id'        => $post_id,
                'type'      => self::format_favorite_type($post_type),
                'title'     => get_the_title($post_id),
                'thumbnail' => self::maybe_esc_url(get_the_post_thumbnail_url($post_id, 'thumbnail')),
                'permalink' => self::maybe_esc_url(get_permalink($post_id)),
                'saved_at'  => self::format_datetime($row->favorited_on),
            ];
        }

        $count_sql   = $wpdb->prepare("SELECT COUNT(*) FROM $table $where", ...$params);
        $total       = (int) $wpdb->get_var($count_sql);
        $total_pages = (int) ceil($total / $per_page);

        $response = [
            'items'      => array_values($items),
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => max(1, $total_pages),
            ],
        ];

        return rest_ensure_response($response);
    }

    public static function get_follows(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $user_id    = get_current_user_id();
        $type_param = $request->get_param('type');
        $object_type = self::map_follow_type($type_param);

        $per_page = self::sanitize_per_page((int) $request->get_param('per_page'));
        $page     = self::sanitize_page((int) $request->get_param('page'));
        $offset   = ($page - 1) * $per_page;

        $table = $wpdb->prefix . 'ap_follows';

        $params = [$user_id];
        $where  = 'WHERE user_id = %d';
        if (null !== $object_type) {
            $where   .= ' AND object_type = %s';
            $params[] = $object_type;
        }

        $query_params = array_merge($params, [$per_page, $offset]);
        $sql          = $wpdb->prepare(
            "SELECT object_id, object_type, followed_on FROM $table $where ORDER BY followed_on DESC LIMIT %d OFFSET %d",
            ...$query_params
        );

        $rows  = $wpdb->get_results($sql);
        $items = [];

        foreach ($rows as $row) {
            $post_id   = (int) $row->object_id;
            $post_type = (string) $row->object_type;
            $post      = get_post($post_id);

            if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
                continue;
            }

            $items[] = [
                'id'          => $post_id,
                'type'        => self::format_follow_type($post_type),
                'name'        => get_the_title($post_id),
                'avatar'      => self::maybe_esc_url(get_the_post_thumbnail_url($post_id, 'thumbnail')),
                'permalink'   => self::maybe_esc_url(get_permalink($post_id)),
                'followed_at' => self::format_datetime($row->followed_on),
            ];
        }

        $count_sql   = $wpdb->prepare("SELECT COUNT(*) FROM $table $where", ...$params);
        $total       = (int) $wpdb->get_var($count_sql);
        $total_pages = (int) ceil($total / $per_page);

        $response = [
            'items'      => array_values($items),
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => max(1, $total_pages),
            ],
        ];

        return rest_ensure_response($response);
    }

    public static function get_notifications(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $user_id = get_current_user_id();

        $per_page = self::sanitize_per_page((int) $request->get_param('per_page'));
        $page     = self::sanitize_page((int) $request->get_param('page'));
        $offset   = ($page - 1) * $per_page;

        $table = $wpdb->prefix . 'ap_notifications';

        $sql = $wpdb->prepare(
            "SELECT id, content, status, created_at FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id,
            $per_page,
            $offset
        );

        $rows = $wpdb->get_results($sql);

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id'         => (int) $row->id,
                'message'    => self::prepare_notification_message($row->content ?? ''),
                'created_at' => self::format_datetime($row->created_at ?? ''),
                'read'       => 'read' === ($row->status ?? ''),
            ];
        }

        $total_sql   = $wpdb->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE user_id = %d', $user_id);
        $total       = (int) $wpdb->get_var($total_sql);
        $total_pages = (int) ceil($total / $per_page);

        $response = [
            'items'      => $items,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => max(1, $total_pages),
            ],
        ];

        return rest_ensure_response($response);
    }

    public static function mark_notifications_read(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $user_id = get_current_user_id();
        $ids     = $request->get_param('ids');

        if (!is_array($ids)) {
            return new WP_Error(
                'invalid_ids',
                __('You must provide an array of notification IDs.', 'artpulse-management'),
                ['status' => 400]
            );
        }

        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        if (empty($ids)) {
            return new WP_Error(
                'invalid_ids',
                __('You must provide at least one valid notification ID.', 'artpulse-management'),
                ['status' => 400]
            );
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $params       = array_merge([$user_id], $ids);

        $sql = $wpdb->prepare(
            "UPDATE {$wpdb->prefix}ap_notifications SET status = 'read' WHERE user_id = %d AND id IN ($placeholders)",
            ...$params
        );
        $wpdb->query($sql);

        $unread = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ap_notifications WHERE user_id = %d AND status = %s",
                $user_id,
                'unread'
            )
        );

        return rest_ensure_response([
            'status' => 'read',
            'unread' => $unread,
        ]);
    }

    private static function build_counts(int $user_id): array
    {
        global $wpdb;

        $favorites = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ap_favorites WHERE user_id = %d",
                $user_id
            )
        );

        $follows = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ap_follows WHERE user_id = %d",
                $user_id
            )
        );

        $notifications = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ap_notifications WHERE user_id = %d AND status = %s",
                $user_id,
                'unread'
            )
        );

        $upcoming_events = self::count_upcoming_events($user_id);

        return [
            'favorites'     => $favorites,
            'follows'       => $follows,
            'upcoming'      => $upcoming_events,
            'notifications' => $notifications,
        ];
    }

    private static function get_next_events(int $user_id): array
    {
        $events = self::get_future_events($user_id);

        $next = [];
        foreach (array_slice($events, 0, 5) as $event) {
            $next[] = [
                'id'        => $event['id'],
                'title'     => $event['title'],
                'date'      => $event['date'],
                'permalink' => $event['permalink'],
            ];
        }

        return $next;
    }

    private static function count_upcoming_events(int $user_id): int
    {
        return count(self::get_future_events($user_id));
    }

    /**
     * @return array<int, array{id:int,title:string,date:string,permalink:string,timestamp:int}>
     */
    private static function get_future_events(int $user_id): array
    {
        $event_ids = self::get_user_event_ids($user_id);
        if (empty($event_ids)) {
            return [];
        }

        $now     = current_time('timestamp', true);
        $details = [];

        foreach ($event_ids as $event_id) {
            $post = get_post($event_id);
            if (!$post instanceof WP_Post || $post->post_status !== 'publish' || $post->post_type !== PostTypeRegistrar::EVENT_POST_TYPE) {
                continue;
            }

            $datetime = self::get_event_datetime($event_id);
            if (!$datetime) {
                continue;
            }

            if ($datetime['timestamp'] < $now) {
                continue;
            }

            $details[] = [
                'id'        => $event_id,
                'title'     => get_the_title($event_id),
                'date'      => $datetime['iso'],
                'permalink' => self::maybe_esc_url(get_permalink($event_id)),
                'timestamp' => $datetime['timestamp'],
            ];
        }

        usort($details, static function (array $a, array $b): int {
            return $a['timestamp'] <=> $b['timestamp'];
        });

        return $details;
    }

    /**
     * @return array<int>
     */
    private static function get_user_event_ids(int $user_id): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_event_saves';

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT event_id FROM $table WHERE user_id = %d",
                $user_id
            )
        );

        if (empty($ids)) {
            return [];
        }

        $ids = array_map('intval', $ids);

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @return array{timestamp:int,iso:string}|null
     */
    private static function get_event_datetime(int $event_id): ?array
    {
        $start = get_post_meta($event_id, '_ap_event_start', true);
        if (is_string($start) && $start !== '') {
            $timestamp = (int) mysql2date('U', $start, false);
            if ($timestamp > 0) {
                return [
                    'timestamp' => $timestamp,
                    'iso'       => mysql2date(DATE_ATOM, $start, false),
                ];
            }
        }

        $fallback = get_post_meta($event_id, '_ap_event_date', true);
        if (is_string($fallback) && $fallback !== '') {
            $timestamp = strtotime($fallback . ' 00:00:00');
            if ($timestamp) {
                return [
                    'timestamp' => (int) $timestamp,
                    'iso'       => gmdate(DATE_ATOM, $timestamp),
                ];
            }
        }

        return null;
    }

    private static function sanitize_type_param($value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return sanitize_key($value);
    }

    public static function validate_favorite_type($value): bool
    {
        if (null === $value || '' === $value) {
            return true;
        }

        $value = sanitize_key((string) $value);

        return isset(self::FAVORITE_TYPE_MAP[$value]);
    }

    public static function validate_follow_type($value): bool
    {
        if (null === $value || '' === $value) {
            return true;
        }

        $value = sanitize_key((string) $value);

        return isset(self::FOLLOW_TYPE_MAP[$value]);
    }

    private static function map_favorite_type($value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $key = sanitize_key($value);

        return self::FAVORITE_TYPE_MAP[$key] ?? null;
    }

    private static function map_follow_type($value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $key = sanitize_key($value);

        return self::FOLLOW_TYPE_MAP[$key] ?? null;
    }

    private static function format_favorite_type(string $post_type): string
    {
        foreach (self::FAVORITE_TYPE_MAP as $label => $type) {
            if ($type === $post_type) {
                return $label;
            }
        }

        return $post_type;
    }

    private static function format_follow_type(string $post_type): string
    {
        foreach (self::FOLLOW_TYPE_MAP as $label => $type) {
            if ($type === $post_type) {
                return $label;
            }
        }

        return $post_type;
    }

    private static function sanitize_per_page(int $value): int
    {
        if ($value <= 0) {
            return 20;
        }

        return min($value, 50);
    }

    private static function sanitize_page(int $value): int
    {
        return max(1, $value ?: 1);
    }

    private static function get_overview_cache_key(int $user_id): string
    {
        return self::OVERVIEW_CACHE_PREFIX . $user_id;
    }

    private static function format_datetime($value): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }

        $formatted = mysql2date(DATE_ATOM, $value, false);
        if ($formatted) {
            return $formatted;
        }

        $timestamp = strtotime($value);
        if ($timestamp) {
            return gmdate(DATE_ATOM, $timestamp);
        }

        return '';
    }

    private static function prepare_notification_message(string $content): string
    {
        $content = wp_strip_all_tags($content);
        $content = trim($content);

        return $content;
    }

    private static function maybe_esc_url($value): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }

        return esc_url_raw($value);
    }
}
