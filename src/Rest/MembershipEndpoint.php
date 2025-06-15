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
            '/user-profile',
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_user_profile' ],
                'permission_callback' => [ $this, 'update_permissions_check' ],
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
            'bio'              => get_user_meta( $u->ID, 'description', true ),
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
            'is_member'         => get_user_meta( $uid, 'is_member', true ) === '1',
            'membership_level'  => get_user_meta( $uid, 'membership_level', true ),
            'membership_joined' => get_user_meta( $uid, 'membership_joined', true ),
            'membership_expires'=> get_user_meta( $uid, 'membership_expires', true ),
            'role'              => wp_get_current_user()->roles[0] ?? 'guest',
        ], 200 );
    }

    public function update_user_profile( WP_REST_Request $request ) {
        $user = wp_get_current_user();
        $uid  = $user->ID;

        $name   = sanitize_text_field( $request->get_param( 'name' ) ?? '' );
        $bio    = sanitize_textarea_field( $request->get_param( 'bio' ) ?? '' );
        $badge  = sanitize_text_field( $request->get_param( 'badge_label' ) ?? '' );
        $level  = sanitize_text_field( $request->get_param( 'membership_level' ) ?? '' );

        if ( $name ) {
            wp_update_user([
                'ID'           => $uid,
                'display_name' => $name,
            ]);
        }

        if ( $bio !== null ) {
            update_user_meta( $uid, 'description', $bio );
        }

        if ( in_array( 'member_org', $user->roles, true ) ) {
            update_user_meta( $uid, 'org_badge_label', $badge );
        }

        if ( $level ) {
            update_user_meta( $uid, 'membership_level', $level );
            update_user_meta( $uid, 'is_member', '1' );
            update_user_meta( $uid, 'membership_joined', current_time('mysql') );

            $expires = strtotime('+1 year');
            update_user_meta( $uid, 'membership_expires', date('Y-m-d H:i:s', $expires) );

            $this->assign_role( $uid, $level );
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Profile and membership updated.',
            'data'    => [
                'name'             => $name,
                'bio'              => $bio,
                'badge_label'      => $badge,
                'membership_level' => $level,
            ],
        ], 200 );
    }

    private function assign_role( $user_id, $level ) {
        $user = new \WP_User( $user_id );

        switch ( $level ) {
            case 'basic':
                $user->set_role( 'member_basic' );
                break;
            case 'pro':
                $user->set_role( 'member_pro' );
                break;
            case 'org':
                $user->set_role( 'member_org' );
                break;
            default:
                $user->set_role( 'member_registered' );
        }
    }

    public function permissions_check( WP_REST_Request $request ) {
        $user = wp_get_current_user();
        return in_array( 'member_pro', $user->roles, true );
    }

    public function update_permissions_check( WP_REST_Request $request ) {
        return is_user_logged_in();
    }
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'artpulse/v1', '/membership/update', [
        'methods'  => 'POST',
        'callback' => [ \EAD\Shortcodes\MembershipSignupForm::class, 'handle_rest_submit' ],
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ] );
} );
