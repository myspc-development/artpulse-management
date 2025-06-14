<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class ChangePasswordEndpoint extends WP_REST_Controller {
    protected $namespace = 'artpulse/v1';
    protected $rest_base = 'change-password';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'change_password' ],
                'permission_callback' => [ $this, 'check_user_logged_in' ],
            ]
        );
    }

    public function change_password( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $user    = wp_get_current_user();

        $current = $request->get_param( 'current_password' );
        $new     = $request->get_param( 'new_password' );
        $confirm = $request->get_param( 'confirm_password' );

        if ( empty( $current ) || empty( $new ) || empty( $confirm ) ) {
            return new WP_REST_Response( [ 'error' => 'Missing fields.' ], 400 );
        }

        if ( $new !== $confirm ) {
            return new WP_REST_Response( [ 'error' => 'New passwords do not match.' ], 400 );
        }

        if ( ! wp_check_password( $current, $user->user_pass, $user_id ) ) {
            return new WP_REST_Response( [ 'error' => 'Current password is incorrect.' ], 403 );
        }

        wp_set_password( $new, $user_id );
        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    public function check_user_logged_in( WP_REST_Request $request ) {
        return is_user_logged_in();
    }
}
