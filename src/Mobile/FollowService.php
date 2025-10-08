<?php

namespace ArtPulse\Mobile;

use WP_Error;
use WP_Post;

class FollowService
{
    private const SUPPORTED_TYPES = [
        'artist' => 'artpulse_artist',
        'org'    => 'artpulse_org',
    ];

    public static function install_table(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table           = $wpdb->prefix . 'ap_follows';
        $charset_collate = $wpdb->get_charset_collate();
        $sql             = "CREATE TABLE $table (
            user_id BIGINT UNSIGNED NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            object_type VARCHAR(32) NOT NULL,
            followed_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, object_id, object_type),
            KEY object_lookup (object_type, object_id),
            KEY followed_on (followed_on)
        ) $charset_collate;";

        dbDelta($sql);
    }

    public static function follow(int $user_id, int $object_id, string $type)
    {
        $post_type = self::map_type($type);
        if ($post_type instanceof WP_Error) {
            return $post_type;
        }

        $post = get_post($object_id);
        if (!$post instanceof WP_Post || $post_type !== $post->post_type) {
            return new WP_Error('ap_follow_invalid', __('Follow target not found.', 'artpulse-management'), ['status' => 404]);
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'ap_follows';
        $current = current_time('mysql', true);
        $sql     = $wpdb->prepare(
            "INSERT INTO $table (user_id, object_id, object_type, followed_on) VALUES (%d, %d, %s, %s) ON DUPLICATE KEY UPDATE followed_on = followed_on",
            $user_id,
            $object_id,
            $post_type,
            $current
        );

        $wpdb->query($sql);
        $inserted = (int) $wpdb->rows_affected > 0;

        if ($inserted) {
            /** @psalm-suppress InvalidArgument */
            do_action('artpulse/target_followed', $user_id, $object_id, $post_type);
        }

        return self::get_state($user_id, $object_id, $post_type);
    }

    public static function unfollow(int $user_id, int $object_id, string $type)
    {
        $post_type = self::map_type($type);
        if ($post_type instanceof WP_Error) {
            return $post_type;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ap_follows';
        $wpdb->delete($table, [
            'user_id'     => $user_id,
            'object_id'   => $object_id,
            'object_type' => $post_type,
        ], [
            '%d',
            '%d',
            '%s',
        ]);

        return self::get_state($user_id, $object_id, $post_type);
    }

    public static function get_state(int $user_id, int $object_id, string $post_type): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_follows';

        $followers = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE object_id = %d AND object_type = %s",
                $object_id,
                $post_type
            )
        );

        $following = false;
        if ($user_id) {
            $following = (bool) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT 1 FROM $table WHERE user_id = %d AND object_id = %d AND object_type = %s",
                    $user_id,
                    $object_id,
                    $post_type
                )
            );
        }

        return [
            'followers' => $followers,
            'following' => $following,
        ];
    }

    /**
     * @return array<string, array<int>>
     */
    public static function get_followed_ids(int $user_id): array
    {
        if (!$user_id) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ap_follows';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT object_id, object_type FROM $table WHERE user_id = %d",
                $user_id
            )
        );

        $grouped = [];
        foreach ($rows as $row) {
            $type = (string) $row->object_type;
            $id   = (int) $row->object_id;
            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $id;
        }

        return $grouped;
    }

    private static function map_type(string $type)
    {
        $type = strtolower($type);
        if (!isset(self::SUPPORTED_TYPES[$type])) {
            return new WP_Error('ap_follow_invalid_type', __('Unsupported follow type.', 'artpulse-management'), ['status' => 400]);
        }

        return self::SUPPORTED_TYPES[$type];
    }
}
