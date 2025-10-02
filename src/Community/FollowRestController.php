<?php

namespace ArtPulse\Community;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class FollowRestController
{
    public static function register(): void
    {
        register_rest_route('artpulse/v1', '/follows', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_follows'],
            'permission_callback' => fn() => is_user_logged_in(),
            'args'                => [
                'object_type' => [
                    'type'        => 'string',
                    'required'    => false,
                    'enum'        => ['artpulse_artist', 'artpulse_event', 'artpulse_org'],
                    'description' => 'Optional object type filter.',
                ],
            ],
        ]);

        register_rest_route('artpulse/v1', '/follows', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'add_follow'],
            'permission_callback' => fn() => is_user_logged_in(),
            'args'                => self::get_schema(),
        ]);

        register_rest_route('artpulse/v1', '/follows', [
            'methods'             => 'DELETE',
            'callback'            => [self::class, 'remove_follow'],
            'permission_callback' => fn() => is_user_logged_in(),
            'args'                => self::get_schema(),
        ]);
    }

    public static function get_schema(): array
    {
        return [
            'post_id' => [
                'type'        => 'integer',
                'required'    => true,
                'description' => 'ID of the post to follow or unfollow.',
            ],
            'post_type' => [
                'type'        => 'string',
                'required'    => true,
                'enum'        => ['artpulse_artist', 'artpulse_event', 'artpulse_org'],
                'description' => 'The post type being followed.',
            ],
        ];
    }

    public static function add_follow(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id   = get_current_user_id();
        $post_id   = absint($request['post_id']);
        $post_type = sanitize_key($request['post_type']);

        if (!get_post($post_id)) {
            return new WP_Error('invalid_post', 'Post not found.', ['status' => 404]);
        }

        FollowManager::add_follow($user_id, $post_id, $post_type);

        $follows = array_map(
            static fn($follow) => (int) $follow->object_id,
            FollowManager::get_user_follows($user_id)
        );

        return rest_ensure_response(['status' => 'following', 'follows' => $follows]);
    }

    public static function remove_follow(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id   = get_current_user_id();
        $post_id   = absint($request['post_id']);
        $post_type = sanitize_key($request['post_type']);

        FollowManager::remove_follow($user_id, $post_id, $post_type);

        $follows = array_map(
            static fn($follow) => (int) $follow->object_id,
            FollowManager::get_user_follows($user_id)
        );

        return rest_ensure_response(['status' => 'unfollowed', 'follows' => $follows]);
    }

    public static function get_follows(WP_REST_Request $request): WP_REST_Response
    {
        $user_id     = get_current_user_id();
        $object_type = $request->get_param('object_type');

        $object_type = $object_type ? sanitize_key($object_type) : null;

        $follows = FollowManager::get_user_follow_details($user_id, $object_type);

        return rest_ensure_response($follows);
    }
}
