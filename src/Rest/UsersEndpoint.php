<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class UsersEndpoint extends WP_REST_Controller {
    protected $namespace;
    protected $rest_base;

    public function __construct() {
        $this->namespace = 'artpulse/v1';
        $this->rest_base  = 'users';
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/search',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'search_users' ],
                'permission_callback' => [ $this, 'check_user_logged_in' ],
            ]
        );
    }

    public function search_users( WP_REST_Request $request ) {
        $term = sanitize_text_field( $request->get_param( 'term' ) );

        $users = get_users(
            [
                'search'         => "*{$term}*",
                'search_columns' => [ 'user_login', 'display_name' ],
                'number'         => 10,
                'fields'         => [ 'ID', 'display_name' ],
            ]
        );

        $results = array_map(
            static function ( $user ) {
                return [
                    'id'   => $user->ID,
                    'name' => $user->display_name,
                ];
            },
            $users
        );

        return new WP_REST_Response( $results, 200 );
    }

    public function check_user_logged_in( WP_REST_Request $request ) {
        return is_user_logged_in();
    }
}
