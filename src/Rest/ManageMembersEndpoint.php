<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class ManageMembersEndpoint extends WP_REST_Controller {
    protected string $namespace = 'artpulse/v1';
    protected string $rest_base = 'manage-members';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\\d+)',
            [
                'methods'             => [ WP_REST_Server::CREATABLE, WP_REST_Server::EDITABLE ],
                'callback'            => [ $this, 'update_member' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'                => [
                    'id' => [ 'sanitize_callback' => 'absint' ],
                    'membership_level' => [ 'sanitize_callback' => 'sanitize_text_field' ],
                    'membership_end_date' => [ 'sanitize_callback' => 'sanitize_text_field' ],
                    'membership_auto_renew' => [ 'sanitize_callback' => 'rest_sanitize_boolean' ],
                ],
            ]
        );
    }

    public function permissions_check( WP_REST_Request $request ): bool {
        return current_user_can( 'manage_options' );
    }

    public function update_member( WP_REST_Request $request ): WP_REST_Response {
        $user_id = absint( $request['id'] );
        $level   = sanitize_text_field( $request->get_param( 'membership_level' ) );
        $end     = sanitize_text_field( $request->get_param( 'membership_end_date' ) );
        $renew   = rest_sanitize_boolean( $request->get_param( 'membership_auto_renew' ) );

        if ( $level !== '' ) {
            update_user_meta( $user_id, 'membership_level', $level );
            \EAD\Admin\ManageMembers::update_role( $user_id, $level );
        }
        if ( $end !== '' ) {
            update_user_meta( $user_id, 'membership_end_date', $end );
        }
        update_user_meta( $user_id, 'membership_auto_renew', $renew ? '1' : '' );

        return new WP_REST_Response( [ 'success' => true ] );
    }
}
