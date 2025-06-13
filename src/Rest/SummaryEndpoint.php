<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class SummaryEndpoint extends WP_REST_Controller {
    protected string $namespace;
    protected string $rest_base;

    public function __construct() {
        $this->namespace = 'artpulse/v1';
        $this->rest_base  = 'summary';

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getSummary' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
            ]
        );
    }

    public function getSummary( WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.', 'artpulse-management' ), [ 'status' => 401 ] );
        }

        $user_id   = get_current_user_id();
        $favorites = get_user_meta( $user_id, 'ead_likes', true );
        if ( ! is_array( $favorites ) ) {
            $favorites = [];
        }

        $data = [
            'favorites' => count( $favorites ),
        ];

        return new WP_REST_Response( $data, 200 );
    }

    public function permissionsCheck( WP_REST_Request $request ) {
        return is_user_logged_in();
    }
}
