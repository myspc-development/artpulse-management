<?php
namespace ArtPulse\Community;

class FollowRestController {
    public static function register_routes() {
        register_rest_route('artpulse/v1', '/follow', [
            'methods' => 'POST',
            'callback' => [self::class, 'toggle_follow'],
            'permission_callback' => function() { return is_user_logged_in(); }
        ]);
        register_rest_route('artpulse/v1', '/follows', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_follows'],
            'permission_callback' => function() { return is_user_logged_in(); }
        ]);
    }

    public static function toggle_follow($request) {
        $user_id = get_current_user_id();
        $object_id = intval($request['object_id']);
        $object_type = sanitize_text_field($request['object_type']);
        $action = sanitize_text_field($request['action']); // "follow" or "unfollow"

        if (!$object_id || !$object_type) {
            return new \WP_Error('invalid', 'Missing object', ['status' => 400]);
        }

        if ($action === 'follow') {
            FollowManager::add_follow($user_id, $object_id, $object_type);
            $state = true;
        } else {
            FollowManager::remove_follow($user_id, $object_id, $object_type);
            $state = false;
        }

        return [
            'success' => true,
            'following' => $state,
        ];
    }

    public static function get_follows($request) {
        $user_id = get_current_user_id();
        $type = sanitize_text_field($request['object_type'] ?? '');
        $follows = FollowManager::get_user_follows($user_id, $type ?: null);

        $results = [];
        foreach ($follows as $f) {
            // Adjust post_type prefix if needed
            $possible_types = [
                'artist'  => 'artpulse_artist',
                'event'   => 'artpulse_event',
                'artwork' => 'artpulse_artwork',
                'org'     => 'artpulse_org'
            ];
            $post_type = $possible_types[$f->object_type] ?? 'artpulse_' . $f->object_type;

            $post = get_post($f->object_id);
            if ($post && $post->post_type === $post_type) {
                $results[] = [
                    'object_id'     => $f->object_id,
                    'object_type'   => $f->object_type,
                    'followed_on'   => $f->followed_on,
                    'title'         => get_the_title($f->object_id),
                    'permalink'     => get_permalink($f->object_id),
                    'featured_media_url' => get_the_post_thumbnail_url($f->object_id, 'medium'),
                ];
            }
        }
        return rest_ensure_response($results);
    }
}

add_action('rest_api_init', [\ArtPulse\Community\FollowRestController::class, 'register_routes']);
