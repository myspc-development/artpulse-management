<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class TaxonomyEndpoint
 *
 * Handles taxonomy data via the REST API.
 */
class TaxonomyEndpoint extends WP_REST_Controller {

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
        $this->rest_base  = 'taxonomies';

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // GET all taxonomies.
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getTaxonomies' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
            ]
        );

        // GET terms of a specific taxonomy.
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<taxonomy>[a-zA-Z0-9_-]+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getTerms' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
                'args'                => [
                    'taxonomy' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => [ $this, 'validateTaxonomy' ],
                    ],
                ],
            ]
        );
    }

    /**
     * Get all public taxonomies.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function getTaxonomies( WP_REST_Request $request ) {
        if ( ! current_user_can( 'read' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to view taxonomies.', 'artpulse-management' ), [ 'status' => 403 ] );
        }

        $taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
        $output     = [];

        foreach ( $taxonomies as $taxonomy ) {
            $output[] = [
                'name'         => $taxonomy->name,
                'label'        => $taxonomy->label,
                'hierarchical' => $taxonomy->hierarchical,
            ];
        }

        return new WP_REST_Response( $output, 200 );
    }

    /**
     * Get terms of a specific taxonomy.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function getTerms( WP_REST_Request $request ) {
        $taxonomy = $request->get_param( 'taxonomy' );

        if ( ! taxonomy_exists( $taxonomy ) ) {
            return new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        if ( ! current_user_can( 'read' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to view taxonomy terms.', 'artpulse-management' ), [ 'status' => 403 ] );
        }

        $terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            ]
        );

        if ( is_wp_error( $terms ) ) {
            error_log( 'ArtPulse Management: Error getting terms for taxonomy ' . $taxonomy . ': ' . $terms->get_error_message() );

            return new WP_Error( 'failed_to_get_terms', __( 'Failed to get terms for taxonomy.', 'artpulse-management' ), [ 'status' => 500 ] );
        }

        $output = [];

        foreach ( $terms as $term ) {
            $output[] = [
                'id'    => $term->term_id,
                'name'  => $term->name,
                'slug'  => $term->slug,
                'count' => $term->count,
            ];
        }

        return new WP_REST_Response( $output, 200 );
    }

    /**
     * Validate taxonomy callback.
     *
     * @param string $taxonomy Taxonomy name.
     *
     * @return bool
     */
    public function validateTaxonomy( $taxonomy ) {
        return taxonomy_exists( $taxonomy );
    }

    /**
     * Permission check callback.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool
     */
    public function permissionsCheck( WP_REST_Request $request ) {
        return current_user_can( 'read' );
    }
}