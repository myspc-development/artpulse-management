<?php

namespace ArtPulse\Mobile;

use ArtPulse\Core\PostTypeRegistrar;
use WP_Post;

class EventGeo
{
    public static function boot(): void
    {
        add_action('save_post_' . PostTypeRegistrar::EVENT_POST_TYPE, [self::class, 'sync_from_post'], 20, 3);
        add_action('before_delete_post', [self::class, 'delete_for_post']);
    }

    public static function install_table(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table           = $wpdb->prefix . 'ap_event_geo';
        $charset_collate = $wpdb->get_charset_collate();
        $sql             = "CREATE TABLE $table (
            event_id BIGINT UNSIGNED NOT NULL,
            latitude DOUBLE NOT NULL,
            longitude DOUBLE NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (event_id),
            KEY lat_idx (latitude),
            KEY lng_idx (longitude),
            KEY lat_lng_idx (latitude, longitude)
        ) $charset_collate;";

        dbDelta($sql);
    }

    public static function sync_from_post(int $post_id, WP_Post $post, bool $update): void
    {
        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (PostTypeRegistrar::EVENT_POST_TYPE !== $post->post_type) {
            return;
        }

        self::sync($post_id);
    }

    public static function delete_for_post(int $post_id): void
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || PostTypeRegistrar::EVENT_POST_TYPE !== $post->post_type) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ap_event_geo';
        $wpdb->delete($table, ['event_id' => $post_id]);
    }

    public static function sync(int $event_id): void
    {
        $lat = get_post_meta($event_id, '_ap_event_latitude', true);
        $lng = get_post_meta($event_id, '_ap_event_longitude', true);

        if ('' === $lat || '' === $lng || null === $lat || null === $lng) {
            self::delete_for_post($event_id);
            return;
        }

        $latitude  = (float) $lat;
        $longitude = (float) $lng;

        global $wpdb;
        $table = $wpdb->prefix . 'ap_event_geo';

        $wpdb->replace(
            $table,
            [
                'event_id'   => $event_id,
                'latitude'   => $latitude,
                'longitude'  => $longitude,
                'updated_at' => current_time('mysql', true),
            ],
            [
                '%d',
                '%f',
                '%f',
                '%s',
            ]
        );
    }
}
