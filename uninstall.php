<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$ap_options_to_delete = [
    'ap_enable_org_builder',
    'ap_enable_artist_builder',
    'ap_require_event_review',
    'ap_widget_whitelist',
    'artpulse_settings',
    'ap_mobile_jwt_keys',
    'ap_mobile_metrics_log',
    'ap_mobile_metrics_summary',
    'ap_enable_mobile_write_routes',
    'artpulse_webhook_log',
    'artpulse_webhook_status',
    'artpulse_webhook_last_event',
    'artpulse_letter_index_error',
    'artpulse_db_version',
    'ap_favorites_schema_version',
    'ap_events_settings',
    'ap_uninstall_deep_clean',
];

$ap_table_suffixes = [
    'ap_favorites',
    'ap_follows',
    'ap_notifications',
    'ap_event_likes',
    'ap_event_saves',
    'ap_event_geo',
    'ap_audit_log',
    'ap_device_sessions',
    'ap_refresh_tokens',
];

$ap_cron_hooks = [
    'ap_daily_expiry_check',
    'ap_mobile_purge_inactive_sessions',
    'ap_mobile_purge_metrics',
    'artpulse/mobile/notifs_tick',
];

$ap_user_meta_patterns = ['_ap_%', 'ap_%'];
$ap_user_meta_keys     = [
    'ap_mobile_refresh_tokens',
    'ap_mobile_push_tokens',
    'ap_mobile_push_token',
    'ap_mobile_notif_state',
    'ap_mobile_muted_topics',
];

/**
 * Perform uninstall cleanup for the current site context.
 */
function artpulse_uninstall_site_cleanup(): void
{
    global $wpdb, $ap_options_to_delete, $ap_table_suffixes, $ap_cron_hooks, $ap_user_meta_patterns, $ap_user_meta_keys;

    foreach ($ap_options_to_delete as $option) {
        delete_option($option);
    }

    if ($wpdb instanceof \wpdb) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_ap_%',
                '_site_transient_ap_%'
            )
        );
    }

    $deep_clean = (bool) get_option('ap_uninstall_deep_clean', false);

    if (!$deep_clean || !($wpdb instanceof \wpdb)) {
        return;
    }

    foreach ($ap_table_suffixes as $suffix) {
        $table = $wpdb->prefix . $suffix;
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }

    foreach ($ap_user_meta_patterns as $pattern) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
                $pattern
            )
        );
    }

    if (!empty($ap_user_meta_keys)) {
        $placeholders = implode(', ', array_fill(0, count($ap_user_meta_keys), '%s'));
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ({$placeholders})",
                ...$ap_user_meta_keys
            )
        );
    }

    foreach ($ap_cron_hooks as $hook) {
        wp_clear_scheduled_hook($hook);
    }
}

$blog_ids = [get_current_blog_id()];

if (is_multisite()) {
    $ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
    if (is_array($ids)) {
        $blog_ids = array_map('intval', $ids);
    } else {
        $blog_ids = [];
    }

    $current_id = get_current_blog_id();
    if (!in_array($current_id, $blog_ids, true)) {
        $blog_ids[] = $current_id;
    }
}

foreach ($blog_ids as $blog_id) {
    if (is_multisite()) {
        switch_to_blog((int) $blog_id);
    }

    artpulse_uninstall_site_cleanup();

    if (is_multisite()) {
        restore_current_blog();
    }
}

if (is_multisite() && $wpdb instanceof \wpdb) {
    foreach ($ap_options_to_delete as $option) {
        delete_site_option($option);
    }

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
            '_site_transient_ap_%'
        )
    );
}
