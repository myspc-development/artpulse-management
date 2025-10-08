<?php

namespace ArtPulse\Mobile;

class NotificationPipeline
{
    public static function boot(): void
    {
        add_action('artpulse/event_liked', [self::class, 'log_event_liked'], 10, 2);
        add_action('artpulse/target_followed', [self::class, 'log_target_followed'], 10, 3);
    }

    public static function log_event_liked(int $event_id, int $user_id): void
    {
        $user = get_userdata($user_id);
        $name = $user ? $user->display_name : 'Unknown';
        $token = $user ? get_user_meta($user_id, 'ap_mobile_push_token', true) : '';

        error_log(sprintf('[ArtPulse Notifications] %s (ID %d) liked event %d. Push token: %s', $name, $user_id, $event_id, $token ?: 'n/a'));
    }

    public static function log_target_followed(int $user_id, int $object_id, string $object_type): void
    {
        $user = get_userdata($user_id);
        $name = $user ? $user->display_name : 'Unknown';
        $token = $user ? get_user_meta($user_id, 'ap_mobile_push_token', true) : '';

        error_log(sprintf('[ArtPulse Notifications] %s (ID %d) followed %s %d. Push token: %s', $name, $user_id, $object_type, $object_id, $token ?: 'n/a'));
    }
}
