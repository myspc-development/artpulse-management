<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class BookingsEndpoint
 *
 * Handles bookings via the REST API.
 */
class BookingsEndpoint extends WP_REST_Controller {

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
        $this->rest_base  = 'bookings';

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // GET bookings for current user.
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getUserBookings' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
            ]
        );

        // POST new booking.
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'createBooking' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
                'args'                => $this->getEndpointArgs(),
            ]
        );
    }

    /**
     * Get bookings for current user.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function getUserBookings( WP_REST_Request $request ) {
        $userId = get_current_user_id();

        if ( ! $userId ) {
            return new WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.', 'artpulse-management' ), [ 'status' => 401 ] );
        }

        if ( ! current_user_can( 'ead_view_bookings' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to view bookings.', 'artpulse-management' ), [ 'status' => 403 ] );
        }

        $args = [
            'post_type'      => 'ead_booking',
            'post_status'    => 'publish',
            'author'         => $userId,
            'posts_per_page' => - 1,
        ];

        $query = new \WP_Query( $args );

        if ( is_wp_error( $query ) ) {
            error_log( 'ArtPulse Management: Error retrieving bookings: ' . $query->get_error_message() );

            return new WP_Error( 'failed_to_get_bookings', __( 'Failed to retrieve bookings.', 'artpulse-management' ), [ 'status' => 500 ] );
        }

        $bookings = [];

        foreach ( $query->posts as $post ) {
            $bookings[] = [
                'id'    => $post->ID,
                'title' => get_the_title( $post->ID ),
                'date'  => ead_get_meta( $post->ID, '_ead_booking_date'), // Assuming you store the date in a meta field
                'status' => get_post_status( $post->ID ),
            ];
        }

        return new WP_REST_Response( $bookings, 200 );
    }

    /**
     * Create a new booking.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function createBooking( WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', __( 'You must be logged in to create a booking.', 'artpulse-management' ), [ 'status' => 401 ] );
        }

        if ( ! current_user_can( 'ead_create_bookings' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to create bookings.', 'artpulse-management' ), [ 'status' => 403 ] );
        }

        $title = sanitize_text_field( $request->get_param( 'title' ) ?? '' );
        $date  = sanitize_text_field( $request->get_param( 'date' ) ?? '' );
        $bookingDetails = wp_kses_post( $request->get_param( 'booking_details' ) ?? '' ); // Sanitize booking details

        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', __( 'Title is required.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        if ( empty( $date ) ) {
            return new WP_Error( 'missing_date', __( 'Date is required.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        // Insert booking post.
        $bookingPost = [
            'post_title'   => $title,
            'post_content' => (string) $bookingDetails, // Store booking details in content
            'post_status'  => 'pending', // Or 'publish' depending on your workflow
            'post_type'    => 'ead_booking',
            'post_author'  => get_current_user_id(),
        ];

        $postId = wp_insert_post( $bookingPost, true );

        if ( is_wp_error( $postId ) ) {
            error_log( 'ArtPulse Management: Error creating booking: ' . $postId->get_error_message() );

            return new WP_Error( 'failed_to_create_booking', __( 'Failed to create booking.', 'artpulse-management' ), [ 'status' => 500 ] );
        }

        // Save booking date.
        update_post_meta( $postId, '_ead_booking_date', $date );

        $response = new WP_REST_Response(
            [
                'success' => true,
                'message' => __( 'Booking created successfully and is awaiting confirmation.', 'artpulse-management' ),
                'postId'  => $postId,
            ],
            201
        );

        $response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $postId ) );
        return $response;
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
     * Define endpoint arguments.
     *
     * @return array
     */
    public function getEndpointArgs() {
        return [
            'title'           => [
                'required'          => true,
                'type'              => 'string',
                'description'       => __( 'Booking title.', 'artpulse-management' ),
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'date'            => [
                'required'          => true,
                'type'              => 'string',
                'description'       => __( 'Booking date.', 'artpulse-management' ),
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'booking_details' => [
                'required'          => false,
                'type'              => 'string',
                'description'       => __( 'Booking details.', 'artpulse-management' ),
                'sanitize_callback' => 'wp_kses_post',
            ],
        ];
    }
}