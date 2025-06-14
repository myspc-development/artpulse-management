<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class DashboardEndpoint
 *
 * Handles dashboard data via the REST API.
 */
class DashboardEndpoint extends WP_REST_Controller {

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
        $this->rest_base  = 'dashboard';

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // GET dashboard summary.
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getDashboardSummary' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
            ]
        );
    }

    /**
     * Get dashboard summary data.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function getDashboardSummary( WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.', 'artpulse-management' ), [ 'status' => 401 ] );
        }

        if ( ! current_user_can( 'ead_view_dashboard' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to view the dashboard.', 'artpulse-management' ), [ 'status' => 403 ] );
        }

        $cache_key = 'ead_dashboard_summary_' . get_current_user_id();
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return new WP_REST_Response( $cached, 200 );
        }

        try {
            $summary = [
                'total_events'     => $this->getTotalEvents(),
                'total_artworks'   => $this->getTotalArtworks(),
                'total_reviews'    => $this->getTotalReviews(),
                'pending_comments' => $this->getPendingComments(),
            ];

            set_transient( $cache_key, $summary, 5 * MINUTE_IN_SECONDS );

            return new WP_REST_Response( $summary, 200 );
        } catch ( \Exception $e ) {
            error_log( 'ArtPulse Management: Error fetching dashboard summary: ' . $e->getMessage() );

            return new WP_Error( 'rest_server_error', __( 'An unexpected error occurred.', 'artpulse-management' ), [ 'status' => 500 ] );
        }
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
     * Get total events count.
     *
     * @return int
     */
    private function getTotalEvents(): int {
        $args = [
            'post_type'      => 'ead_event',
            'post_status'    => 'publish',
            'posts_per_page' => - 1,
            'fields'         => 'ids',
            'no_found_rows'  => true, // Improve performance
        ];

        $query = new \WP_Query( $args );

        return $query->found_posts;
    }

    /**
     * Get total artworks count.
     *
     * @return int
     */
    private function getTotalArtworks(): int {
        $args = [
            'post_type'      => 'ead_artwork',
            'post_status'    => 'publish',
            'posts_per_page' => - 1,
            'fields'         => 'ids',
            'no_found_rows'  => true, // Improve performance
        ];

        $query = new \WP_Query( $args );

        return $query->found_posts;
    }

    /**
     * Get total reviews count.
     *
     * @return int
     */
    private function getTotalReviews(): int {
        $args = [
            'post_type'      => 'ead_org_review',
            'post_status'    => 'publish',
            'posts_per_page' => - 1,
            'fields'         => 'ids',
            'no_found_rows'  => true, // Improve performance
        ];

        $query = new \WP_Query( $args );

        return $query->found_posts;
    }

    /**
     * Get pending comments count.
     *
     * @return int
     */
    private function getPendingComments(): int {
        $args = [
            'status' => 'hold',
            'count'  => true,
        ];

        return get_comments( $args );
    }
}