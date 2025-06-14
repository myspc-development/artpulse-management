<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class OrganizationDashboardEndpoint
 *
 * Handles dashboard data for organizations via the REST API.
 */
class OrganizationDashboardEndpoint extends WP_REST_Controller {

    protected $namespace;
    protected $rest_base;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->namespace = 'artpulse/v1';
        $this->rest_base  = 'organizations/dashboard';
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
                'callback'            => [ $this, 'get_dashboard_data' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'                => $this->get_collection_params(),
            ]
        );
    }

    /**
     * Get organization dashboard data.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function get_dashboard_data( WP_REST_Request $request ) {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return new WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.', 'artpulse-management' ), [ 'status' => 401 ] );
        }

        $cache_key = 'ead_org_dashboard_' . $user_id;
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return new WP_REST_Response( $cached, 200 );
        }

        try {
            $data = [
                'events_count'      => $this->getUserEventsCount( $user_id, 'publish' ),
                'pending_events'    => $this->getUserEventsCount( $user_id, 'pending' ),
                'draft_events'      => $this->getUserEventsCount( $user_id, 'draft' ),
                'featured_events'   => $this->getUserFeaturedEventsCount( $user_id ),
                'upcoming_events'   => $this->getUserUpcomingEventsCount( $user_id ),
                'expired_events'    => $this->getUserExpiredEventsCount( $user_id ),
                'artworks_count'    => $this->getUserArtworksCount( $user_id ),
                'pending_reviews'   => $this->getUserPendingReviews( $user_id ),
                'total_rsvps'       => $this->getUserTotalRsvps( $user_id ),
                'bookings_count'    => $this->getUserBookingsCount( $user_id ),
                'event_analytics'   => $this->getUserEventAnalytics( $user_id ),
            ];

            set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );

            return new WP_REST_Response( $data, 200 );
        } catch ( \Exception $e ) {
            error_log( 'ArtPulse Management: Error fetching organization dashboard data: ' . $e->getMessage() );

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
    public function permissions_check( WP_REST_Request $request ) {
        return current_user_can( 'view_dashboard' );
    }

    /**
     * Define collection parameters for GET requests.
     *
     * @return array
     */
    public function get_collection_params() {
        return [
            'context'  => [
                'default'     => 'view',
                'type'        => 'string',
                'description' => 'Scope under which the request is made; determines fields present in response.',
                'enum'        => [ 'view', 'embed', 'edit' ],
                'arg_options' => [
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
            'page'     => [
                'description'       => 'Current page of the collection.',
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'description'       => 'Number of items per page.',
                'type'              => 'integer',
                'default'           => 10,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Get user events count by status.
     *
     * @param int $userId User ID.
     * @param string $status Post status (publish, pending, draft).
     *
     * @return int
     */
    private function getUserEventsCount( int $userId, string $status = 'publish' ): int {
        $args = [
            'post_type'      => 'ead_event',
            'author'         => $userId,
            'post_status'    => $status,
            'fields'         => 'ids',
            'posts_per_page' => - 1,
        ];

        $query = new \WP_Query( $args );

        return count( $query->posts );
    }

    /**
     * Get user featured events count.
     *
     * @param int $userId User ID.
     *
     * @return int
     */
    private function getUserFeaturedEventsCount( int $userId ): int {
        $args = [
            'post_type'      => 'ead_event',
            'author'         => $userId,
            'meta_query'     => [
                [
                    'key'     => '_ead_featured',
                    'value'   => '1',
                    'compare' => '=',
                ],
            ],
            'fields'         => 'ids',
            'posts_per_page' => - 1,
        ];

        $query = new \WP_Query( $args );

        return count( $query->posts );
    }

    /**
     * Get user upcoming events count.
     *
     * @param int $userId User ID.
     *
     * @return int
     */
    private function getUserUpcomingEventsCount( int $userId ): int {
        $today = date( 'Y-m-d' );

        $args = [
            'post_type'      => 'ead_event',
            'author'         => $userId,
            'post_status'    => 'publish',
            'meta_key'       => 'event_end_date',
            'meta_value'     => $today,
            'meta_compare'   => '>=',
            'fields'         => 'ids',
            'posts_per_page' => - 1,
        ];

        $query = new \WP_Query( $args );

        return count( $query->posts );
    }

    /**
     * Get user expired events count.
     *
     * @param int $userId User ID.
     *
     * @return int
     */
    private function getUserExpiredEventsCount( int $userId ): int {
        $today = date( 'Y-m-d' );

        $args = [
            'post_type'      => 'ead_event',
            'author'         => $userId,
            'post_status'    => 'publish',
            'meta_key'       => 'event_end_date',
            'meta_value'     => $today,
            'meta_compare'   => '<',
            'fields'         => 'ids',
            'posts_per_page' => - 1,
        ];

        $query = new \WP_Query( $args );

        return count( $query->posts );
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

    /**
     * Get total RSVPs for user's events.
     *
     * @param int $userId User ID.
     *
     * @return int
     */
    private function getUserTotalRsvps( int $userId ): int {
        // Find all event IDs for this user
        $event_ids = get_posts(
            [
                'post_type'      => 'ead_event',
                'author'         => $userId,
                'fields'         => 'ids',
                'posts_per_page' => -1,
                'post_status'    => [ 'publish', 'pending', 'draft' ],
            ]
        );

        if ( empty( $event_ids ) ) {
            return 0;
        }

        global $wpdb;

        $table       = $wpdb->ead_rsvps;
        $placeholders = implode( ',', array_fill( 0, count( $event_ids ), '%d' ) );
        $sql          = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE event_id IN ($placeholders)", $event_ids );

        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Get user bookings count.
     *
     * @param int $userId User ID.
     *
     * @return int
     */
    private function getUserBookingsCount( int $userId ): int {
        $args = [
            'post_type'      => 'ead_booking',
            'author'         => $userId,
            'post_status'    => [ 'publish', 'pending' ],
            'fields'         => 'ids',
            'posts_per_page' => -1,
        ];

        $query = new \WP_Query( $args );

        return count( $query->posts );
    }

    /**
     * Get view and click totals for each of the user's events.
     *
     * @param int $userId User ID.
     * @return array
     */
    private function getUserEventAnalytics( int $userId ): array {
        $events = get_posts([
            'post_type'      => 'ead_event',
            'author'         => $userId,
            'posts_per_page' => -1,
            'post_status'    => [ 'publish', 'pending', 'draft' ],
            'fields'         => 'ids',
        ]);

        $data = [];
        foreach ( $events as $event_id ) {
            $data[] = [
                'id'     => $event_id,
                'title'  => get_the_title( $event_id ),
                'views'  => (int) get_post_meta( $event_id, '_ead_view_count', true ),
                'clicks' => (int) get_post_meta( $event_id, '_ead_click_count', true ),
            ];
        }

        return $data;
    }
}