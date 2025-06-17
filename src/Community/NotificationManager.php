<?php
namespace ArtPulse\Community;

use ArtPulse\Community\EmailNotifier;

class NotificationManager {
    public static function install_notifications_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_notifications';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            type VARCHAR(40) NOT NULL,
            object_id BIGINT NULL,
            related_id BIGINT NULL,
            content TEXT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'unread',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function add($user_id, $type, $object_id = null, $related_id = null, $content = '', $status = 'unread') {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_notifications';

        $wpdb->insert($table, [
            'user_id'    => $user_id,
            'type'       => $type,
            'object_id'  => $object_id,
            'related_id' => $related_id,
            'content'    => $content,
            'status'     => $status,
            'created_at' => current_time('mysql')
        ]);

        // Trigger optional email notification
        EmailNotifier::maybe_send($user_id, $type, $object_id, $related_id, $content);
    }

    public static function get($user_id, $limit = 25) {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_notifications';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id, $limit
        ));
    }

    public static function mark_all_read($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_notifications';
        $wpdb->update($table, ['status' => 'read'], [
            'user_id' => $user_id,
            'status'  => 'unread'
        ]);
    }

    public static function mark_read($notification_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_notifications';
        $wpdb->update($table, ['status' => 'read'], [
            'id'      => $notification_id,
            'user_id' => $user_id
        ]);
    }
}
