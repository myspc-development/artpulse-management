<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class ArtistDashboardEndpoint
 *
 * Handles dashboard data for artists via the REST API.
 */
class ArtistDashboardEndpoint extends WP_REST_Controller {

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
        $this->rest_base  = 'artists/dashboard';

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // GET artist dashboard summary.
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getDashboardData' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
            ]
        );

        // Optional: POST artist dashboard actions (example: mark notifications as read).
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/actions',
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'handleDashboardAction' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
                'args'                => $this->getEndpointArgs(),
            ]
        );
    }

    /**
     * Get artist dashboard data.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function getDashboardData( WP_REST_Request $request ) {
        $userId = get_current_user_id();

        if ( ! $userId ) {
            return new WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.', 'artpulse-management' ), [ 'status' => 401 ] );
        }

        if ( ! user_can( $userId, 'ead_access_artist_dashboard' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to access the artist dashboard.', 'artpulse-management' ), [ 'status' => 403 ] );
        }

        $cache_key = 'ead_artist_dashboard_' . $userId;
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return new WP_REST_Response( $cached, 200 );
        }

        try {
            $data = [
                'artworks_count'  => $this->getUserArtworksCount( $userId ),
                'events_count'    => $this->getUserEventsCount( $userId ),
                'pending_reviews' => $this->getUserPendingReviews( $userId ),
            ];

            set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );

            return new WP_REST_Response( $data, 200 );
        } catch ( \Exception $e ) {
            error_log( 'ArtPulse Management: Error fetching artist dashboard data: ' . $e->getMessage() );

            return new WP_Error( 'rest_server_error', __( 'An unexpected error occurred.', 'artpulse-management' ), [ 'status' => 500 ] );
        }
    }

    /**
     * Handle artist dashboard actions.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handleDashboardAction( WP_REST_Request $request ) {
        $action = sanitize_text_field( $request->get_param( 'action' ) ?? '' );

        if ( empty( $action ) ) {
            return new WP_Error( 'missing_action', __( 'Action parameter is required.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        if ( ! current_user_can( 'ead_manage_artist_dashboard' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to perform this action.', 'artpulse-management' ), [ 'status' => 403 ] );
        }

        // Placeholder — extend with actual actions.
        $message = sprintf( __( "Action '%s' executed successfully (placeholder).", 'artpulse-management' ), $action );

        return new WP_REST_Response(
            [
                'success' => true,
                'message' => $message,
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
     * Define endpoint arguments.
     *
     * @return array
     */
    public function getEndpointArgs() {
        return [
            'action' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => __( 'Action to perform on the dashboard.', 'artpulse-management' ),
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    /**
     * Get user artworks count.
     *
     * @param int $userId User ID.
     *
     * @return int
     */
    private function getUserArtworksCount( int $userId ): int {
        $args = [
            'post_type'      => 'ead_artwork',
            'author'         => $userId,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => - 1,
        ];

        $query = new \WP_Query( $args );

        return count( $query->posts );
    }

    /**
     * Get user events count.
     *
     * @param int $userId User ID.
     *
     * @return int
     */
    private function getUserEventsCount( int $userId ): int {
        $args = [
            'post_type'      => 'ead_event',
            'author'         => $userId,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => - 1,
        ];

        $query = new \WP_Query( $args );

        return count( $query->posts );
    }

    /**
     * Get user pending reviews count.
     *
     * @param int $userId User ID.
     *
     * @return int
     */
    private function getUserPendingReviews( int $userId ): int {
        $args = [
            'post_type'      => 'ead_org_review',
            'author'         => $userId,
            'post_status'    => 'pending',
            'fields'         => 'ids',
            'posts_per_page' => - 1,
        ];

        $query = new \WP_Query( $args );

        return count( $query->posts );
    }
}