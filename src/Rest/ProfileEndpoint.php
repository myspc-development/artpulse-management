<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class ProfileEndpoint extends WP_REST_Controller {
    protected $namespace;
    protected $rest_base;

    public function __construct() {
        $this->namespace = 'artpulse/v1';
        $this->rest_base = 'profile';
        add_action('rest_api_init', [ $this, 'register_routes' ]);
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'save_profile' ],
                'permission_callback' => [ $this, 'check_user_logged_in' ],
            ]
        );
    }

    public function save_profile( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $name = sanitize_text_field( $request->get_param( 'display_name' ) );
        $city = sanitize_text_field( $request->get_param( 'city' ) );
        $country = sanitize_text_field( $request->get_param( 'country' ) );
        $newsletter = $request->get_param( 'newsletter' ) === 'true' ? 'yes' : 'no';

        wp_update_user( [ 'ID' => $user_id, 'display_name' => $name ] );
        update_user_meta( $user_id, 'ead_city', $city );
        update_user_meta( $user_id, 'ead_country', $country );
        update_user_meta( $user_id, 'ead_newsletter', $newsletter );

        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    public function check_user_logged_in( WP_REST_Request $request ) {
        return is_user_logged_in();
    }
}
