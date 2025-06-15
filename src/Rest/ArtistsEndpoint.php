<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Handles artist registrations via REST API.
 */
class ArtistsEndpoint extends WP_REST_Controller {

    /**
     * REST namespace.
     *
     * Un-typed to maintain compatibility with older
     * WordPress versions where WP_REST_Controller
     * uses an untyped property.
     *
     * @var string
     */
    protected $namespace;

    /**
     * REST route base.
     *
     * Also left untyped for the same reason as $namespace.
     *
     * @var string
     */
    protected $rest_base;

    public function __construct() {
        $this->namespace = 'artpulse/v1';
        $this->rest_base = 'artists';

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register the route.
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'register_artist' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * Handle artist registration.
     */
    public function register_artist( WP_REST_Request $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'artpulse-management' ), [ 'status' => 403 ] );
        }

        if ( is_user_logged_in() ) {
            return new WP_Error( 'already_logged_in', __( 'You are already logged in.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        $username  = sanitize_user( $request->get_param( 'artist_username' ) ?? '' );
        $email     = sanitize_email( $request->get_param( 'registration_email' ) ?? '' );
        $password  = $request->get_param( 'artist_password' );
        $confirm   = $request->get_param( 'artist_password_confirm' );
        $display   = sanitize_text_field( $request->get_param( 'artist_display_name' ) ?? '' );
        $artist_email = sanitize_email( $request->get_param( 'artist_email' ) ?? $email );

        if ( empty( $username ) || empty( $email ) || empty( $password ) || empty( $confirm ) || empty( $display ) ) {
            return new WP_Error( 'missing_fields', __( 'All required fields must be completed.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        if ( $password !== $confirm ) {
            return new WP_Error( 'password_mismatch', __( 'Passwords do not match.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        if ( username_exists( $username ) || email_exists( $email ) ) {
            return new WP_Error( 'user_exists', __( 'Username or email already exists.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        $user_id = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            return new WP_Error( 'create_failed', $user_id->get_error_message(), [ 'status' => 500 ] );
        }

        wp_update_user( [ 'ID' => $user_id, 'display_name' => $display ] );
        $user = new \WP_User( $user_id );
        $user->set_role( 'artist' );

        $post_args = [
            'post_title'  => $display,
            'post_type'   => 'ead_artist',
            'post_status' => 'pending',
            'post_author' => $user_id,
        ];

        $artist_post_id = wp_insert_post( $post_args, true );
        if ( is_wp_error( $artist_post_id ) ) {
            return new WP_Error( 'artist_post_failed', __( 'Error creating artist profile.', 'artpulse-management' ), [ 'status' => 500 ] );
        }

        $fields = [
            'artist_name',
            'artist_email',
            'artist_bio',
            'artist_website',
            'artist_phone',
            'artist_instagram',
            'artist_facebook',
            'artist_twitter',
            'artist_linkedin',
            'ead_country',
            'ead_state',
            'ead_city',
            'ead_suburb',
            'ead_street',
            'ead_postcode',
            'ead_latitude',
            'ead_longitude',
        ];

        foreach ( $fields as $field ) {
            $val = $request->get_param( $field );
            if ( $val !== null ) {
                update_post_meta( $artist_post_id, $field, sanitize_text_field( $val ) );
            }
        }

        update_user_meta( $user_id, 'ead_artist_post_id', $artist_post_id );

        $portrait = $request->get_param( 'artist_portrait' );
        if ( $portrait ) {
            update_post_meta( $artist_post_id, 'artist_portrait', intval( $portrait ) );
        }

        $gallery_ids = $request->get_param( 'artist_gallery_images' );
        if ( is_array( $gallery_ids ) && $gallery_ids ) {
            $ids = array_slice( array_map( 'absint', $gallery_ids ), 0, 5 );
            if ( $ids ) {
                update_post_meta( $artist_post_id, 'artist_gallery_images', $ids );
            }
        }

        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );

        $confirmation_url = apply_filters( 'ead_artist_confirmation_url', home_url( '/artist-dashboard/' ) );

        return new WP_REST_Response(
            [
                'success'          => true,
                'message'          => __( 'Artist registered successfully!', 'artpulse-management' ),
                'confirmation_url' => $confirmation_url,
                'post_id'          => $artist_post_id,
                'user_id'          => $user_id,
            ],
            200
        );
    }
}
