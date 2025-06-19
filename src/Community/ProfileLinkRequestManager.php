<?php

namespace ArtPulse\Community;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class ProfileLinkRequestManager
{
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        register_rest_route('artpulse/v1', '/link-requests', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'handle_create_request'],
            'permission_callback' => fn() => is_user_logged_in(),
            'args'                => [
                'target_id' => [
                    'type'        => 'integer',
                    'required'    => true,
                    'description' => 'Target organization or artist post ID.',
                ]
            ]
        ]);
    }

    public static function handle_create_request(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id   = get_current_user_id();
        $target_id = absint($request['target_id']);

        if (!get_post($target_id)) {
            return new WP_Error('invalid_target', 'Target post not found.', ['status' => 404]);
        }

        $request_id = wp_insert_post([
            'post_type'   => 'ap_link_request',
            'post_status' => 'pending',
            'post_title'  => "Link Request: User {$user_id} to {$target_id}",
            'post_author' => $user_id,
            'meta_input'  => ['_ap_target_id' => $target_id],
        ]);

        return rest_ensure_response([
            'request_id' => $request_id,
            'status'     => 'pending',
        ]);
    }
}
