<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class UserProfileEndpoint
 *
 * Handles user profile data via the REST API.
 */
class UserProfileEndpoint extends WP_REST_Controller {

    /**
     * The namespace.
     *
     * @var string
     */
    protected $namespace;

    /**
     * The REST base.
     *
     * @var string
     */
    protected $rest_base;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->namespace = 'artpulse/v1';
        $this->rest_base  = 'user/profile';

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getUserProfile' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'updateUserProfile' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
                'args'                => $this->getEndpointArgs(),
            ]
        );
    }

    /**
     * Get current user's profile data.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function getUserProfile( WP_REST_Request $request ) {
        $userId = get_current_user_id();

        if ( ! $userId ) {
            return new WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.', 'artpulse-management' ), [ 'status' => 401 ] );
        }

        $user = get_userdata( $userId );

        if ( ! $user ) {
            return new WP_Error( 'rest_user_invalid_id', __( 'Invalid user ID.', 'artpulse-management' ), [ 'status' => 404 ] );
        }

        $profile = [
            'id'       => $user->ID,
            'username' => $user->user_login,
            'email'    => $user->user_email,
            'name'     => $user->display_name,
            'bio'      => get_user_meta( $userId, 'description', true ),
        ];

        return new WP_REST_Response( $profile, 200 );
    }

    /**
     * Update current user's profile.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function updateUserProfile( WP_REST_Request $request ) {
        $userId = get_current_user_id();

        if ( ! $userId ) {
            return new WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.', 'artpulse-management' ), [ 'status' => 401 ] );
        }

        $name = sanitize_text_field( $request->get_param( 'name' ) ?? '' );
        $bio  = sanitize_textarea_field( $request->get_param( 'bio' ) ?? '' );

        $updateData = [
            'ID'           => $userId,
            'display_name' => $name,
        ];

        $updateResult = wp_update_user( $updateData );

        if ( is_wp_error( $updateResult ) ) {
            error_log( 'ArtPulse Management: Error updating user profile: ' . $updateResult->get_error_message() );

            return new WP_Error( 'failed_to_update_user', __( 'Failed to update user profile.', 'artpulse-management' ), [ 'status' => 500 ] );
        }

        update_user_meta( $userId, 'description', $bio );

        return new WP_REST_Response(
            [
                'success' => true,
                'message' => __( 'Profile updated successfully.', 'artpulse-management' ),
            ],
            200
        );
    }

    /**
     * Permission check callback.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool
     */
    public function permissionsCheck( WP_REST_Request $request ) {
        return is_user_logged_in();
    }

    /**
     * Define endpoint arguments for updating profile.
     *
     * @return array
     */
    public function getEndpointArgs() {
        return [
            'name' => [
                'required'          => false,
                'type'              => 'string',
                'description'       => __( 'User display name.', 'artpulse-management' ),
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'bio'  => [
                'required'          => false,
                'type'              => 'string',
                'description'       => __( 'User biographical information.', 'artpulse-management' ),
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
        ];
    }
}