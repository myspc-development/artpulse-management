<?php

namespace ArtPulse\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class NonceController
{
    public static function register(): void
    {
        register_rest_route(
            'artpulse/v1',
            '/nonce',
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'get_nonce'],
                'permission_callback' => [self::class, 'permissions_check'],
            ]
        );
    }

    public static function get_nonce( WP_REST_Request $request ): WP_REST_Response
    {
        return rest_ensure_response([
            'nonce' => wp_create_nonce('ap_portfolio_update'),
        ]);
    }

    public static function permissions_check(): bool|WP_Error
    {
        if ( is_user_logged_in() ) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            __( 'Authentication required to refresh the nonce.', 'artpulse-management' ),
            [ 'status' => rest_authorization_required_code() ]
        );
    }
}
