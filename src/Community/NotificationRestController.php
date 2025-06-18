<?php

namespace ArtPulse\Community;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class NotificationRestController
{
    public static function register(): void
    {
        register_rest_route('artpulse/v1', '/notifications', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_notifications'],
            'permission_callback' => fn() => is_user_logged_in(),
        ]);

        register_rest_route('artpulse/v1', '/notifications/read', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'mark_as_read'],
            'permission_callback' => fn() => is_user_logged_in(),
            'args'                => self::get_schema(),
        ]);

        register_rest_route('artpulse/v1', '/notifications', [
            'methods'             => 'DELETE',
            'callback'            => [self::class, 'delete_notification'],
            'permission_callback' => fn() => is_user_logged_in(),
            'args'                => self::get_schema(),
        ]);
    }

    public static function get_schema(): array
    {
        return [
            'notification_id' => [
                'type'        => 'integer',
                'required'    => true,
                'description' => 'ID of the notification to update or delete.',
            ],
        ];
    }

    public static function get_notifications(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = get_current_user_id();
        $notifications = get_user_meta($user_id, '_ap_notifications', true) ?: [];

        return rest_ensure_response([
            'notifications' => array_values($notifications)
        ]);
    }

    public static function mark_as_read(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id = get_current_user_id();
        $id = absint($request['notification_id']);
        $notifications = get_user_meta($user_id, '_ap_notifications', true) ?: [];

        foreach ($notifications as &$n) {
            if ((int) $n['id'] === $id) {
                $n['read'] = true;
            }
        }

        update_user_meta($user_id, '_ap_notifications', $notifications);

        return rest_ensure_response(['status' => 'read', 'id' => $id]);
    }

    public static function delete_notification(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id = get_current_user_id();
        $id = absint($request['notification_id']);
        $notifications = get_user_meta($user_id, '_ap_notifications', true) ?: [];

        $filtered = array_filter($notifications, fn($n) => (int) $n['id'] !== $id);
        update_user_meta($user_id, '_ap_notifications', array_values($filtered));

        return rest_ensure_response(['status' => 'deleted', 'id' => $id]);
    }
}
