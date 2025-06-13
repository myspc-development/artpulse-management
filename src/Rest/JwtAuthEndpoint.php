<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Issues JWTs for mobile clients after verifying credentials.
 */
class JwtAuthEndpoint extends WP_REST_Controller {
    protected $namespace;
    protected $rest_base;

    public function __construct() {
        $this->namespace = 'artpulse/v1';
        $this->rest_base  = 'auth/token';
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register the endpoint route.
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'createToken' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'username' => [
                        'required' => true,
                        'type'     => 'string',
                    ],
                    'password' => [
                        'required' => true,
                        'type'     => 'string',
                    ],
                ],
            ]
        );
    }

    private function base64url_encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    private function generate_jwt( $user_id ) {
        $header  = [ 'alg' => 'HS256', 'typ' => 'JWT' ];
        $payload = [
            'iss'     => get_site_url(),
            'iat'     => time(),
            'exp'     => time() + WEEK_IN_SECONDS,
            'user_id' => $user_id,
        ];

        $base_header  = $this->base64url_encode( wp_json_encode( $header ) );
        $base_payload = $this->base64url_encode( wp_json_encode( $payload ) );
        $signature    = hash_hmac( 'sha256', "$base_header.$base_payload", defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt(), true );
        $base_sig     = $this->base64url_encode( $signature );

        return "$base_header.$base_payload.$base_sig";
    }

    /**
     * Handle token creation.
     */
    public function createToken( WP_REST_Request $request ) {
        $username = sanitize_user( $request->get_param( 'username' ) );
        $password = $request->get_param( 'password' );

        if ( empty( $username ) || empty( $password ) ) {
            return new WP_Error( 'missing_credentials', __( 'Username and password required.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        $user = wp_authenticate( $username, $password );
        if ( is_wp_error( $user ) ) {
            return new WP_Error( 'invalid_credentials', __( 'Invalid username or password.', 'artpulse-management' ), [ 'status' => 403 ] );
        }

        $token = $this->generate_jwt( $user->ID );
        return new WP_REST_Response( [
            'token'      => $token,
            'expires_in' => WEEK_IN_SECONDS,
            'user_id'    => $user->ID,
        ], 200 );
    }
}
