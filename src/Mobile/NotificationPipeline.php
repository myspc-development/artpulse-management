<?php

namespace ArtPulse\Mobile;

use ArtPulse\Mobile\Notifications\NotificationProviderInterface;
use ArtPulse\Mobile\Notifications\NullNotificationProvider;

class NotificationPipeline
{
    private const CRON_HOOK        = 'artpulse/mobile/notifs_tick';
    private const STATE_META       = 'ap_mobile_notif_state';
    private const TOPIC_NEW_EVENT  = 'new_followed_event';
    private const TOPIC_STARTING   = 'starting_soon';
    private const FOLLOW_LOOKBACK  = 6 * HOUR_IN_SECONDS;
    private const STARTING_WINDOW  = 2 * HOUR_IN_SECONDS;

    private static ?NotificationProviderInterface $provider = null;
    private static ?string $provider_slug = null;

    public static function boot(): void
    {
        add_action('cron_schedules', [self::class, 'register_interval']);
        add_action(self::CRON_HOOK, [self::class, 'run_tick']);

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'ap_mobile_notifs', self::CRON_HOOK);
        }
    }

    /**
     * @param array<string, mixed> $schedules
     * @return array<string, mixed>
     */
    public static function register_interval(array $schedules): array
    {
        if (!isset($schedules['ap_mobile_notifs'])) {
            $schedules['ap_mobile_notifs'] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => __('ArtPulse mobile notifications', 'artpulse-management'),
            ];
        }

        return $schedules;
    }

    public static function run_tick(): void
    {
        self::reset_provider();

        $users = self::get_users_with_devices();
        if (empty($users)) {
            return;
        }

        foreach ($users as $user_id => $devices) {
            $state       = self::get_state($user_id);
            $last_follow = isset($state['last_follow_scan']) ? (int) $state['last_follow_scan'] : time() - self::FOLLOW_LOOKBACK;
            $followed    = self::collect_new_followed_events($user_id, $last_follow);
            $starting    = self::collect_starting_soon($user_id, $state['starting_soon'] ?? []);

            $state['last_follow_scan'] = time();
            $state['starting_soon']    = self::build_starting_state($starting, $state['starting_soon'] ?? []);
            self::save_state($user_id, $state);

            foreach ($devices as $device_id => $token) {
                if (!self::is_topic_muted($user_id, $device_id, self::TOPIC_NEW_EVENT) && !empty($followed)) {
                    self::dispatch_notification($user_id, $device_id, self::TOPIC_NEW_EVENT, [
                        'events' => $followed,
                        'token'  => $token,
                    ]);
                }

                if (!self::is_topic_muted($user_id, $device_id, self::TOPIC_STARTING) && !empty($starting)) {
                    self::dispatch_notification($user_id, $device_id, self::TOPIC_STARTING, [
                        'events' => $starting,
                        'token'  => $token,
                    ]);
                }
            }
        }
    }

    private static function reset_provider(): void
    {
        self::$provider      = null;
        self::$provider_slug = null;
    }

    private static function get_provider(): NotificationProviderInterface
    {
        $settings = get_option('artpulse_settings');
        $slug     = 'null';

        if (is_array($settings) && !empty($settings['notification_provider'])) {
            $slug = sanitize_key((string) $settings['notification_provider']);
        }

        if (self::$provider && self::$provider_slug === $slug) {
            return self::$provider;
        }

        $provider = apply_filters('artpulse_mobile_notification_provider', null, $slug);
        if ($provider instanceof NotificationProviderInterface) {
            self::$provider      = $provider;
            self::$provider_slug = $slug;

            return self::$provider;
        }

        self::$provider      = new NullNotificationProvider();
        self::$provider_slug = $slug;

        return self::$provider;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function get_users_with_devices(): array
    {
        global $wpdb;
        $meta_key = 'ap_mobile_push_tokens';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
                $meta_key
            )
        );

        $users = [];
        foreach ($rows as $row) {
            $devices = maybe_unserialize($row->meta_value ?? '');
            if (!is_array($devices)) {
                continue;
            }

            $filtered = [];
            foreach ($devices as $device_id => $data) {
                if (!is_array($data) || empty($data['token'])) {
                    continue;
                }

                $filtered[(string) $device_id] = (string) $data['token'];
            }

            if (!empty($filtered)) {
                $users[(int) $row->user_id] = $filtered;
            }
        }

        return $users;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<int, array<string, mixed>>
     */
    private static function collect_starting_soon(int $user_id, array $state): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ap_event_saves';
        $now   = time();
        $max   = $now + self::STARTING_WINDOW;

        $event_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT event_id FROM $table WHERE user_id = %d",
                $user_id
            )
        );

        if (empty($event_ids)) {
            return [];
        }

        $events = [];
        foreach ($event_ids as $event_id) {
            $event_id = (int) $event_id;
            $start    = get_post_meta($event_id, '_ap_event_start', true);
            if (!is_string($start) || '' === $start) {
                continue;
            }

            $timestamp = strtotime($start);
            if (false === $timestamp || $timestamp < $now || $timestamp > $max) {
                continue;
            }

            $already = isset($state[$event_id]) ? (int) $state[$event_id] : 0;
            if ($already && $already + DAY_IN_SECONDS > $now) {
                continue;
            }

            $events[] = [
                'id'    => $event_id,
                'title' => get_the_title($event_id),
                'start' => gmdate('c', $timestamp),
            ];
        }

        return $events;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @param array<string, mixed> $previous
     * @return array<string, int>
     */
    private static function build_starting_state(array $events, array $previous): array
    {
        $now = time();
        $state = is_array($previous) ? $previous : [];

        foreach ($events as $event) {
            $id = isset($event['id']) ? (int) $event['id'] : 0;
            if ($id <= 0) {
                continue;
            }

            $state[$id] = $now;
        }

        return $state;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function collect_new_followed_events(int $user_id, int $since): array
    {
        $followed = FollowService::get_followed_ids($user_id);
        $orgs     = array_map('intval', $followed['artpulse_org'] ?? []);
        $artists  = array_map('intval', $followed['artpulse_artist'] ?? []);

        if (empty($orgs) && empty($artists)) {
            return [];
        }

        $query = new \WP_Query([
            'post_type'      => 'artpulse_event',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'date_query'     => [
                [
                    'after'     => gmdate('Y-m-d H:i:s', max(0, $since)),
                    'inclusive' => true,
                ],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ]);

        if (empty($query->posts)) {
            return [];
        }

        $events = [];
        foreach ($query->posts as $event_id) {
            $event_id = (int) $event_id;
            $org_id   = (int) get_post_meta($event_id, '_ap_event_organization', true);
            $artist_meta = get_post_meta($event_id, '_ap_event_artists', true);
            $artist_ids  = is_array($artist_meta) ? array_map('intval', $artist_meta) : [];

            $match = false;
            if ($org_id && in_array($org_id, $orgs, true)) {
                $match = true;
            }

            if (!$match && !empty($artist_ids) && array_intersect($artist_ids, $artists)) {
                $match = true;
            }

            if (!$match) {
                continue;
            }

            $events[] = [
                'id'    => $event_id,
                'title' => get_the_title($event_id),
                'start' => get_post_meta($event_id, '_ap_event_start', true),
            ];
        }

        return $events;
    }

    /**
     * @return array<string, mixed>
     */
    private static function get_state(int $user_id): array
    {
        $state = get_user_meta($user_id, self::STATE_META, true);
        if (!is_array($state)) {
            $state = [];
        }

        return $state;
    }

    private static function save_state(int $user_id, array $state): void
    {
        update_user_meta($user_id, self::STATE_META, $state);
    }

    private static function is_topic_muted(int $user_id, string $device_id, string $topic): bool
    {
        $muted_meta = get_user_meta($user_id, 'ap_mobile_muted_topics', true);
        $muted      = is_array($muted_meta) ? array_map('strval', $muted_meta) : [];

        $filtered = apply_filters('artpulse_mobile_notifications_muted_topics', $muted, $user_id, $device_id);
        $filtered = array_map('strval', (array) $filtered);

        return in_array($topic, $filtered, true);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function dispatch_notification(int $user_id, string $device_id, string $topic, array $payload): void
    {
        $provider = self::get_provider();
        $provider->send($user_id, $device_id, $topic, $payload);

        self::log_delivery($user_id, $device_id, $topic, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function log_delivery(int $user_id, string $device_id, string $topic, array $payload): void
    {
        if (!function_exists('wp_json_encode')) {
            return;
        }

        $log = wp_json_encode([
            'event'     => 'mobile_notification',
            'user_id'   => $user_id,
            'device_id' => $device_id,
            'topic'     => $topic,
            'payload'   => $payload,
            'timestamp' => time(),
        ]);

        if ($log) {
            error_log($log); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }

        /** @psalm-suppress TooManyArguments */
        do_action('artpulse/mobile/notification_logged', $user_id, $device_id, $topic, $payload);
    }
}
