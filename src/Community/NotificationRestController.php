<?php
namespace ArtPulse\Community;

class NotificationRestController {
    public static function register() {
        register_rest_route('artpulse/v1', '/notifications', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'list'],
            'permission_callback' => function() { return is_user_logged_in(); }
        ]);

        register_rest_route('artpulse/v1', '/notifications/(?P<id>\d+)/read', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'mark_read'],
            'permission_callback' => function() { return is_user_logged_in(); }
        ]);

        // ✅ New route for marking all notifications as read
        register_rest_route('artpulse/v1', '/notifications/mark-all-read', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'mark_all_read'],
            'permission_callback' => function() { return is_user_logged_in(); }
        ]);
    }

    public static function list($req) {
        $user_id = get_current_user_id();
        $items = NotificationManager::get($user_id, 50);
        return rest_ensure_response($items);
    }

    public static function mark_read($req) {
        $user_id = get_current_user_id();
        $id = intval($req['id']);
        NotificationManager::mark_read($id, $user_id);
        return ['success' => true];
    }

    // ✅ Handler for "Mark All Read"
    public static function mark_all_read($req) {
        $user_id = get_current_user_id();
        NotificationManager::mark_all_read($user_id);
        return ['success' => true];
    }
}

// Register the REST API routes on rest_api_init
add_action(
    'rest_api_init',
    [\ArtPulse\Community\NotificationRestController::class, 'register']
);
