<?php
namespace ArtPulse\Community;

class FollowManager {
    /**
     * Create the follows table if not exists.
     */
    public static function install_follows_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_follows';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            object_id BIGINT NOT NULL,
            object_type VARCHAR(32) NOT NULL,
            followed_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY user_object (user_id, object_id, object_type)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add a follow record and trigger notification.
     */
    public static function add_follow($user_id, $object_id, $object_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_follows';
        $wpdb->replace($table, [
            'user_id'      => $user_id,
            'object_id'    => $object_id,
            'object_type'  => $object_type,
            'followed_on'  => current_time('mysql')
        ]);

        // --- Notify the owner (if not following self) ---
        $owner_id = self::get_owner_user_id($object_id, $object_type);
        if ($owner_id && $owner_id !== $user_id && class_exists('\ArtPulse\Community\NotificationManager')) {
            $title = self::get_object_title($object_id, $object_type);
            \ArtPulse\Community\NotificationManager::add(
                $owner_id,
                'follower',
                $object_id,
                $user_id,
                sprintf('You have a new follower on your %s "%s".', $object_type, $title)
            );
        }
    }

    /**
     * Remove a follow.
     */
    public static function remove_follow($user_id, $object_id, $object_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_follows';
        $wpdb->delete($table, [
            'user_id'     => $user_id,
            'object_id'   => $object_id,
            'object_type' => $object_type,
        ]);
    }

    /**
     * Is the user following this object?
     */
    public static function is_following($user_id, $object_id, $object_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_follows';
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE user_id = %d AND object_id = %d AND object_type = %s",
                $user_id, $object_id, $object_type
            )
        );
    }

    /**
     * Get all follows for a user (optionally filtered by type).
     * Returns array of objects: object_id, object_type, followed_on.
     */
    public static function get_user_follows($user_id, $object_type = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_follows';
        $sql = "SELECT object_id, object_type, followed_on FROM $table WHERE user_id = %d";
        $params = [ $user_id ];
        if ($object_type) {
            $sql .= " AND object_type = %s";
            $params[] = $object_type;
        }
        $sql .= " ORDER BY followed_on DESC";
        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }

    /**
     * Helper: Get the owner user ID of an object.
     */
    private static function get_owner_user_id($object_id, $object_type) {
        // For all post types, the post_author is the owner
        if (post_type_exists($object_type)) {
            $post = get_post($object_id);
            return $post ? (int) $post->post_author : 0;
        }
        return 0;
    }

    /**
     * Helper: Get object title (post title).
     */
    private static function get_object_title($object_id, $object_type) {
        if (post_type_exists($object_type)) {
            $post = get_post($object_id);
            return $post ? $post->post_title : '';
        }
        return '';
    }
}
