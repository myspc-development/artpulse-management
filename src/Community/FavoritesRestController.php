<?php

namespace ArtPulse\Community;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class FavoritesRestController
{
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        // Existing routes
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

        // New routes
        register_rest_route('artpulse/v1', '/notifications', [
            'methods'  => 'GET',
            'callback' => [self::class, 'list'],
            'permission_callback' => fn() => is_user_logged_in(),
        ]);

        register_rest_route('artpulse/v1', '/notifications/(?P<id>\d+)/read', [
            'methods'  => 'POST',
            'callback' => [self::class, 'mark_read'],
            'permission_callback' => fn() => is_user_logged_in(),
        ]);

        register_rest_route('artpulse/v1', '/notifications/mark-all-read', [
            'methods'  => 'POST',
            'callback' => [self::class, 'mark_all_read'],
            'permission_callback' => fn() => is_user_logged_in(),
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

    // Placeholders for newly registered methods
    public static function list(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(['message' => 'Notification list not yet implemented.'], 501);
    }

    public static function mark_read(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(['message' => 'Mark read not yet implemented.'], 501);
    }

    public static function mark_all_read(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(['message' => 'Mark all as read not yet implemented.'], 501);
    }
}
