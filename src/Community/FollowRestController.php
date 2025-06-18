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

        $follows = get_user_meta($user_id, '_ap_follows', true) ?: [];
        if (!in_array($post_id, $follows, true)) {
            $follows[] = $post_id;
            update_user_meta($user_id, '_ap_follows', $follows);
        }

        return rest_ensure_response(['status' => 'following', 'follows' => $follows]);
    }

    public static function remove_follow(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id = get_current_user_id();
        $post_id = absint($request['post_id']);

        $follows = get_user_meta($user_id, '_ap_follows', true) ?: [];
        if (($key = array_search($post_id, $follows)) !== false) {
            unset($follows[$key]);
            update_user_meta($user_id, '_ap_follows', array_values($follows));
        }

        return rest_ensure_response(['status' => 'unfollowed', 'follows' => $follows]);
    }
}
