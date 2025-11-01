<?php

namespace ArtPulse\Mobile;

use ArtPulse\Core\PostTypeRegistrar;
use WP_Error;
use WP_Post;

class EventInteractions
{
    public static function install_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $likes_table = $wpdb->prefix . 'ap_event_likes';
        $likes_sql   = "CREATE TABLE $likes_table (
            event_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            liked_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (event_id, user_id),
            KEY user_lookup (user_id, liked_on),
            KEY liked_on (liked_on)
        ) $charset;";

        $saves_table = $wpdb->prefix . 'ap_event_saves';
        $saves_sql   = "CREATE TABLE $saves_table (
            event_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            saved_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (event_id, user_id),
            KEY user_lookup (user_id, saved_on),
            KEY saved_on (saved_on)
        ) $charset;";

        dbDelta($likes_sql);
        dbDelta($saves_sql);
    }

    public static function like_event(int $event_id, int $user_id)
    {
        $error = self::validate_event($event_id);
        if ($error instanceof WP_Error) {
            return $error;
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'ap_event_likes';
        $current = current_time('mysql', true);
        $sql     = $wpdb->prepare(
            "INSERT INTO $table (event_id, user_id, liked_on) VALUES (%d, %d, %s) ON DUPLICATE KEY UPDATE liked_on = liked_on",
            $event_id,
            $user_id,
            $current
        );

        $wpdb->query($sql);
        $inserted = (int) $wpdb->rows_affected > 0;

        if ($inserted) {
            /** @psalm-suppress InvalidArgument */
            do_action('artpulse/event_liked', $event_id, $user_id);
        }

        if (class_exists('\\ArtPulse\\Rest\\AnalyticsController')) {
            \ArtPulse\Rest\AnalyticsController::invalidate_event_caches($event_id);
        }

        return self::get_event_state($event_id, $user_id);
    }

    public static function unlike_event(int $event_id, int $user_id)
    {
        $error = self::validate_event($event_id);
        if ($error instanceof WP_Error) {
            return $error;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ap_event_likes';
        $wpdb->delete($table, [
            'event_id' => $event_id,
            'user_id'  => $user_id,
        ], [
            '%d',
            '%d',
        ]);

        if (class_exists('\\ArtPulse\\Rest\\AnalyticsController')) {
            \ArtPulse\Rest\AnalyticsController::invalidate_event_caches($event_id);
        }

        return self::get_event_state($event_id, $user_id);
    }

    public static function save_event(int $event_id, int $user_id)
    {
        $error = self::validate_event($event_id);
        if ($error instanceof WP_Error) {
            return $error;
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'ap_event_saves';
        $current = current_time('mysql', true);
        $sql     = $wpdb->prepare(
            "INSERT INTO $table (event_id, user_id, saved_on) VALUES (%d, %d, %s) ON DUPLICATE KEY UPDATE saved_on = saved_on",
            $event_id,
            $user_id,
            $current
        );

        $wpdb->query($sql);

        if (class_exists('\\ArtPulse\\Rest\\MemberDashboardController')) {
            \ArtPulse\Rest\MemberDashboardController::invalidate_overview_cache((int) $user_id);
        }

        if (class_exists('\\ArtPulse\\Rest\\AnalyticsController')) {
            \ArtPulse\Rest\AnalyticsController::invalidate_member_cache((int) $user_id);
            \ArtPulse\Rest\AnalyticsController::invalidate_event_caches($event_id);
        }

        return self::get_event_state($event_id, $user_id);
    }

    public static function unsave_event(int $event_id, int $user_id)
    {
        $error = self::validate_event($event_id);
        if ($error instanceof WP_Error) {
            return $error;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ap_event_saves';
        $wpdb->delete($table, [
            'event_id' => $event_id,
            'user_id'  => $user_id,
        ], [
            '%d',
            '%d',
        ]);

        if (class_exists('\\ArtPulse\\Rest\\MemberDashboardController')) {
            \ArtPulse\Rest\MemberDashboardController::invalidate_overview_cache((int) $user_id);
        }

        if (class_exists('\\ArtPulse\\Rest\\AnalyticsController')) {
            \ArtPulse\Rest\AnalyticsController::invalidate_member_cache((int) $user_id);
            \ArtPulse\Rest\AnalyticsController::invalidate_event_caches($event_id);
        }

        return self::get_event_state($event_id, $user_id);
    }

    public static function get_event_state(int $event_id, int $user_id): array
    {
        return [
            'likes' => self::get_count_for_event($event_id, 'ap_event_likes'),
            'liked' => self::is_marked($event_id, $user_id, 'ap_event_likes'),
            'saves' => self::get_count_for_event($event_id, 'ap_event_saves'),
            'saved' => self::is_marked($event_id, $user_id, 'ap_event_saves'),
        ];
    }

    /**
     * @param array<int> $event_ids
     *
     * @return array<int, array{likes:int,liked:bool,saves:int,saved:bool}>
     */
    public static function get_states(array $event_ids, int $user_id): array
    {
        $event_ids = array_values(array_unique(array_filter(array_map('intval', $event_ids))));
        if (empty($event_ids)) {
            return [];
        }

        global $wpdb;
        $likes_table = $wpdb->prefix . 'ap_event_likes';
        $saves_table = $wpdb->prefix . 'ap_event_saves';

        $placeholders = implode(',', array_fill(0, count($event_ids), '%d'));

        $like_totals = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT event_id, COUNT(*) AS total FROM $likes_table WHERE event_id IN ($placeholders) GROUP BY event_id",
                ...$event_ids
            ),
            OBJECT_K
        );

        $save_totals = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT event_id, COUNT(*) AS total FROM $saves_table WHERE event_id IN ($placeholders) GROUP BY event_id",
                ...$event_ids
            ),
            OBJECT_K
        );

        $liked_rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT event_id FROM $likes_table WHERE event_id IN ($placeholders) AND user_id = %d",
                ...array_merge($event_ids, [$user_id])
            )
        );

        $saved_rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT event_id FROM $saves_table WHERE event_id IN ($placeholders) AND user_id = %d",
                ...array_merge($event_ids, [$user_id])
            )
        );

        $liked_lookup = array_fill_keys(array_map('intval', $liked_rows), true);
        $saved_lookup = array_fill_keys(array_map('intval', $saved_rows), true);

        $states = [];
        foreach ($event_ids as $event_id) {
            $likes = isset($like_totals[$event_id]) ? (int) $like_totals[$event_id]->total : 0;
            $saves = isset($save_totals[$event_id]) ? (int) $save_totals[$event_id]->total : 0;

            $states[$event_id] = [
                'likes' => $likes,
                'liked' => isset($liked_lookup[$event_id]),
                'saves' => $saves,
                'saved' => isset($saved_lookup[$event_id]),
            ];
        }

        return $states;
    }

    private static function get_count_for_event(int $event_id, string $table_suffix): int
    {
        global $wpdb;
        $table = $wpdb->prefix . $table_suffix;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE event_id = %d",
                $event_id
            )
        );
    }

    private static function is_marked(int $event_id, int $user_id, string $table_suffix): bool
    {
        if (!$user_id) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . $table_suffix;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1 FROM $table WHERE event_id = %d AND user_id = %d",
                $event_id,
                $user_id
            )
        );
    }

    private static function validate_event(int $event_id)
    {
        $post = get_post($event_id);
        if (!$post instanceof WP_Post || PostTypeRegistrar::EVENT_POST_TYPE !== $post->post_type || 'trash' === $post->post_status) {
            return new WP_Error('ap_event_not_found', __('Event not found.', 'artpulse-management'), ['status' => 404]);
        }

        return true;
    }
}
