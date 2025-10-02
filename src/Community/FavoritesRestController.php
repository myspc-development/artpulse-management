<?php

namespace ArtPulse\Community;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class FavoritesRestController
{
    private const REST_NAMESPACE = 'artpulse/v1';

    public static function register(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            '/favorites/add',
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'handle_add'],
                'permission_callback' => [self::class, 'check_permissions'],
                'args'                => self::get_item_args(),
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/favorites/remove',
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'handle_remove'],
                'permission_callback' => [self::class, 'check_permissions'],
                'args'                => self::get_item_args(),
            ]
        );
    }

    public static function check_permissions(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Schema used for both add/remove requests.
     */
    private static function get_item_args(): array
    {
        return [
            'object_id' => [
                'type'        => 'integer',
                'required'    => true,
                'description' => __('The ID of the object being favorited.', 'artpulse'),
            ],
            'object_type' => [
                'type'              => 'string',
                'required'          => true,
                'description'       => __('The object type or post type for the favorite.', 'artpulse'),
                'sanitize_callback' => 'sanitize_key',
            ],
        ];
    }

    public static function handle_add(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id     = get_current_user_id();
        $object_id   = (int) $request->get_param('object_id');
        $object_type = (string) $request->get_param('object_type');

        $validation_error = self::validate_object($object_id, $object_type);
        if ($validation_error instanceof WP_Error) {
            return $validation_error;
        }

        FavoritesManager::add_favorite($user_id, $object_id, $object_type);

        return rest_ensure_response([
            'status'      => 'favorited',
            'object_id'   => $object_id,
            'object_type' => $object_type,
        ]);
    }

    public static function handle_remove(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id     = get_current_user_id();
        $object_id   = (int) $request->get_param('object_id');
        $object_type = (string) $request->get_param('object_type');

        $validation_error = self::validate_object($object_id, $object_type);
        if ($validation_error instanceof WP_Error) {
            return $validation_error;
        }

        FavoritesManager::remove_favorite($user_id, $object_id, $object_type);

        return rest_ensure_response([
            'status'      => 'unfavorited',
            'object_id'   => $object_id,
            'object_type' => $object_type,
        ]);
    }

    private static function validate_object(int $object_id, string $object_type): WP_Error|bool
    {
        if ($object_id <= 0) {
            return new WP_Error('invalid_object', __('A valid object ID must be provided.', 'artpulse'), ['status' => 400]);
        }

        if (!post_type_exists($object_type)) {
            return new WP_Error('invalid_object_type', __('Unsupported object type.', 'artpulse'), ['status' => 400]);
        }

        $post = get_post($object_id);
        if (!$post || $post->post_type !== $object_type) {
            return new WP_Error('object_not_found', __('The specified favorite target could not be found.', 'artpulse'), ['status' => 404]);
        }

        return true;
    }
}
