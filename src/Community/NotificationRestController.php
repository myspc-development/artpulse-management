<?php

namespace ArtPulse\Community;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use function wp_strip_all_tags;

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
        $notifications = NotificationManager::get($user_id, 50);

        return rest_ensure_response([
            'notifications' => array_map([self::class, 'prepare_notification_response'], $notifications),
        ]);
    }

    public static function mark_as_read(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id = get_current_user_id();
        $id = absint($request['notification_id']);
        NotificationManager::mark_read($id, $user_id);

        return rest_ensure_response(['status' => 'read', 'id' => $id]);
    }

    public static function delete_notification(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id = get_current_user_id();
        $id = absint($request['notification_id']);
        NotificationManager::delete($id, $user_id);

        return rest_ensure_response(['status' => 'deleted', 'id' => $id]);
    }

    private static function prepare_notification_response($notification): array
    {
        $content = is_object( $notification ) && property_exists( $notification, 'content' )
            ? (string) $notification->content
            : '';

        $safe_content = wp_strip_all_tags( $content );

        return [
            'id'         => (int) $notification->id,
            'type'       => $notification->type,
            'object_id'  => null !== $notification->object_id ? (int) $notification->object_id : null,
            'related_id' => null !== $notification->related_id ? (int) $notification->related_id : null,
            'content'    => $safe_content,
            'status'     => $notification->status,
            'read'       => 'read' === $notification->status,
            'message'    => $safe_content ?: $notification->type,
            'created_at' => $notification->created_at,
        ];
    }
}
