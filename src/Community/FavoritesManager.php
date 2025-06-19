namespace ArtPulse\Community;

use ArtPulse\Community\NotificationManager;


class FavoritesManager {
    public static function add_favorite($user_id, $object_id, $object_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_favorites';
        $wpdb->replace($table, [
            'user_id'      => $user_id,
            'object_id'    => $object_id,
            'object_type'  => $object_type,
            'favorited_on' => current_time('mysql')
        ]);

        // --- Notify owner (if not self) ---
        $owner_id = self::get_owner_user_id($object_id, $object_type);
        if ($owner_id && $owner_id !== $user_id) {
            $title = self::get_object_title($object_id, $object_type);
            NotificationManager::add(
                $owner_id,
                'favorite',
                $object_id,
                $user_id,
                sprintf('Your %s "%s" was favorited!', $object_type, $title)
            );
        }
    }

    public static function remove_favorite($user_id, $object_id, $object_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_favorites';
        $wpdb->delete($table, [
            'user_id'     => $user_id,
            'object_id'   => $object_id,
            'object_type' => $object_type,
        ]);
        // No notification on unfavorite (usually)
    }

    public static function is_favorited($user_id, $object_id, $object_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_favorites';
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE user_id = %d AND object_id = %d AND object_type = %s",
                $user_id, $object_id, $object_type
            )
        );
    }

    public static function get_user_favorites($user_id, $object_type = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_favorites';
        $sql = "SELECT object_id, object_type, favorited_on FROM $table WHERE user_id = %d";
        $params = [ $user_id ];
        if ($object_type) {
            $sql .= " AND object_type = %s";
            $params[] = $object_type;
        }
        $sql .= " ORDER BY favorited_on DESC";
        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }

    // 🔽🔽 Helper to get the owner of an object (post author, etc)
    private static function get_owner_user_id($object_id, $object_type) {
        // You may want to map object_type to post types, etc.
        if (in_array($object_type, ['artpulse_artwork', 'artpulse_event', 'artpulse_artist', 'artpulse_org'])) {
            $post = get_post($object_id);
            return $post ? (int)$post->post_author : 0;
        }
        // Extend for other types if needed
        return 0;
    }

    // 🔽🔽 Helper to get the title of the favorited object
    private static function get_object_title($object_id, $object_type) {
        if (in_array($object_type, ['artpulse_artwork', 'artpulse_event', 'artpulse_artist', 'artpulse_org'])) {
            $post = get_post($object_id);
            return $post ? $post->post_title : '';
        }
        return '';
    }

    /**
     * Installer: create the favorites table
     */
    public static function install_favorites_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_favorites';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            object_id BIGINT NOT NULL,
            object_type VARCHAR(32) NOT NULL,
            favorited_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY user_object (user_id, object_id, object_type)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
