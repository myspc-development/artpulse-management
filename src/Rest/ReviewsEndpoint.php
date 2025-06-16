<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class ReviewsEndpoint
 *
 * Handles reviews via the REST API.
 */
class ReviewsEndpoint extends WP_REST_Controller {

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
        $this->rest_base  = 'reviews';

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // GET all reviews.
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getReviews' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
                'args'                => $this->getCollectionParams(),
            ]
        );

        // POST submit a review.
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'submitReview' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
                'args'                => $this->getEndpointArgs(),
            ]
        );
    }

    /**
     * Get all published reviews.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function getReviews( WP_REST_Request $request ) {
        $per_page = $request->get_param( 'per_page' ) ?: 10;
        $page     = $request->get_param( 'page' ) ?: 1;

        $args = [
            'post_type'      => 'ead_org_review',
            'post_status'    => 'publish',
            'posts_per_page' => absint( $per_page ),
            'paged'          => absint( $page ),
        ];

        $query = new \WP_Query( $args );

        if ( ! $query->have_posts() ) {
            return new WP_Error( 'no_reviews_found', __( 'No reviews found.', 'artpulse-management' ), [ 'status' => 404 ] );
        }

        $reviews = [];

        foreach ( $query->posts as $post ) {
            $reviews[] = [
                'id'      => $post->ID,
                'title'   => get_the_title( $post->ID ),
                'content' => apply_filters( 'the_content', (string) $post->post_content ),
                'rating'  => ead_get_meta( $post->ID, '_ead_rating'),
                'author'  => get_the_author_meta( 'display_name', $post->post_author ),
                'date'    => get_the_date( '', $post->ID ),
            ];
        }

        $response = new WP_REST_Response( $reviews, 200 );
        $response->header( 'X-WP-Total', $query->found_posts );
        $response->header( 'X-WP-TotalPages', $query->max_num_pages );
        return $response;
    }

    /**
     * Submit a new review.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function submitReview( WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', __( 'You must be logged in to submit a review.', 'artpulse-management' ), [ 'status' => 401 ] );
        }

        $title   = sanitize_text_field( $request->get_param( 'title' ) ?? '' );
        $content = wp_kses_post( $request->get_param( 'content' ) ?? '' );
        $rating  = (int) $request->get_param( 'rating' );

        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', __( 'Title is required.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        if ( empty( $content ) ) {
            return new WP_Error( 'missing_content', __( 'Content is required.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        if ( $rating < 1 || $rating > 5 ) {
            return new WP_Error( 'invalid_rating', __( 'Rating must be between 1 and 5.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        $review_post = [
            'post_title'   => $title,
            'post_content' => (string) $content,
            'post_status'  => 'pending',
            'post_type'    => 'ead_org_review',
            'post_author'  => get_current_user_id(),
        ];

        $post_id = wp_insert_post( $review_post, true );

        if ( is_wp_error( $post_id ) ) {
            error_log( 'ArtPulse Management: Error submitting review: ' . $post_id->get_error_message() );

            return new WP_Error( 'failed_to_submit_review', __( 'Failed to submit review.', 'artpulse-management' ), [ 'status' => 500 ] );
        }

        update_post_meta( $post_id, '_ead_rating', $rating );

        $response = new WP_REST_Response(
            [
                'success' => true,
                'message' => __( 'Review submitted successfully and is awaiting moderation.', 'artpulse-management' ),
                'postId'  => $post_id,
            ],
            201
        );

        $response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $post_id ) );
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
        if ( $request->get_method() === 'GET' ) {
            return current_user_can( 'read' ); // Allow anyone to read
        } elseif ( $request->get_method() === 'POST' ) {
            return is_user_logged_in(); // Only logged-in users can create
        }

        return false;
    }

    /**
     * Define collection parameters for GET requests.
     *
     * @return array
     */
    public function getCollectionParams() {
        return [
            'page'     => [
                'description'       => __( 'Current page of the collection.', 'artpulse-management' ),
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'description'       => __( 'Number of items per page.', 'artpulse-management' ),
                'type'              => 'integer',
                'default'           => 10,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Define endpoint arguments for POST requests.
     *
     * @return array
     */
    public function getEndpointArgs() {
        return [
            'title'   => [
                'required'          => true,
                'type'              => 'string',
                'description'       => __( 'Review title.', 'artpulse-management' ),
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'content' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => __( 'Review content.', 'artpulse-management' ),
                'sanitize_callback' => 'wp_kses_post',
            ],
            'rating'  => [
                'required'          => true,
                'type'              => 'integer',
                'description'       => __( 'Review rating (1-5).', 'artpulse-management' ),
                'sanitize_callback' => 'absint',
            ],
        ];
    }
}