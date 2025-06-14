<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class MembershipEndpoint extends WP_REST_Controller {
    protected $namespace = 'artpulse/v1';

    public function __construct() {
        add_action('rest_api_init', [ $this, 'register_routes' ]);
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/user-profile',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_user_profile' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/user-badges',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_user_badges' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/membership-status',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_membership_status' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ]
        );
    }

    public function get_user_profile( WP_REST_Request $request ) {
        $u = wp_get_current_user();
        return new WP_REST_Response([
            'ID'               => $u->ID,
            'name'             => $u->display_name,
            'email'            => $u->user_email,
            'role'             => $u->roles[0] ?? 'guest',
            'membership_level' => get_user_meta( $u->ID, 'membership_level', true ),
            'badge_label'      => get_user_meta( $u->ID, 'org_badge_label', true ),
        ], 200 );
    }

    public function get_user_badges( WP_REST_Request $request ) {
        $id    = get_current_user_id();
        $count = (int) get_user_meta( $id, 'rsvp_count', true );
        $badges = [];
        if ( $count >= 3 ) {
            $badges[] = '3 RSVPs';
        }
        if ( $count >= 10 ) {
            $badges[] = '10 RSVPs';
        }
        if ( $count >= 25 ) {
            $badges[] = 'Super Supporter';
        }
        return new WP_REST_Response([
            'rsvp_count' => $count,
            'badges'     => $badges,
        ], 200 );
    }

    public function get_membership_status( WP_REST_Request $request ) {
        $uid = get_current_user_id();
        return new WP_REST_Response([
            'is_member'       => get_user_meta( $uid, 'is_member', true ) === '1',
            'membership_level'=> get_user_meta( $uid, 'membership_level', true ),
            'role'            => wp_get_current_user()->roles[0] ?? 'guest',
        ], 200 );
    }

    public function permissions_check( WP_REST_Request $request ) {
        return current_user_can('read');
    }
}
