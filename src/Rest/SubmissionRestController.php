<?php

namespace ArtPulse\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class SubmissionRestController
{
    /**
     * Allowed post types for submission.
     */
    protected static array $allowed_post_types = [
        'artpulse_event',
        'artpulse_artist',
        'artpulse_artwork',
        'artpulse_org',
    ];

    /**
     * Register the submission endpoint.
     */
    public static function register(): void
    {
        register_rest_route(
            'artpulse/v1',
            '/submissions',
            [
                'methods'             => 'POST',
                'callback'            => [ self::class, 'handle_submission' ],
                'permission_callback' => [ self::class, 'permissions_check' ],
                'args'                => self::get_endpoint_args(),
            ]
        );

        // Optional: GET handler to avoid 404 if frontend pings it
        register_rest_route(
            'artpulse/v1',
            '/submissions',
            [
                'methods'             => 'GET',
                'callback'            => fn() => rest_ensure_response(['message' => 'Use POST to submit a form.']),
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * Alias to maintain consistency with other controllers.
     */
    public static function register_routes(): void
    {
        self::register();
    }

    /**
     * Handle the form submission via REST.
     */
    public static function handle_submission( WP_REST_Request $request ): WP_REST_Response|WP_Error
    {
        $json_params = (array) $request->get_json_params();
        $body_params = (array) $request->get_body_params();

        if ( empty( $json_params ) ) {
            $params = $body_params;
        } elseif ( empty( $body_params ) ) {
            $params = $json_params;
        } else {
            $params = array_merge( $body_params, $json_params );
        }

        $post_type = sanitize_key( $params['post_type'] ?? '' );

        if ( empty( $post_type ) ) {
            return new WP_Error(
                'rest_missing_post_type',
                __( 'The post type is required.', 'artpulse-management' ),
                [ 'status' => 400 ]
            );
        }

        if ( ! in_array( $post_type, self::$allowed_post_types, true ) ) {
            return new WP_Error(
                'rest_invalid_post_type',
                __( 'The requested post type is not allowed.', 'artpulse-management' ),
                [ 'status' => 400 ]
            );
        }

        if ( empty( $params['title'] ) ) {
            return new WP_Error(
                'rest_missing_title',
                __( 'A title is required for submission.', 'artpulse-management' ),
                [ 'status' => 400 ]
            );
        }

        $post_id = wp_insert_post( [
            'post_type'   => $post_type,
            'post_title'  => sanitize_text_field( $params['title'] ),
            'post_status' => 'pending',
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $meta_fields = self::get_meta_fields_for( $post_type );
        foreach ( $meta_fields as $field_key => $meta_key ) {
            if ( isset( $params[ $field_key ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $params[ $field_key ] ) );
            }
        }

        $saved_image_ids = [];
        if ( ! empty( $params['image_ids'] ) && is_array( $params['image_ids'] ) ) {
            $ids = array_slice( array_map( 'absint', $params['image_ids'] ), 0, 5 );
            $valid_ids = array_filter( $ids, fn( $id ) => get_post_type( $id ) === 'attachment' );
            if ( $valid_ids ) {
                update_post_meta( $post_id, '_ap_submission_images', $valid_ids );
                set_post_thumbnail( $post_id, $valid_ids[0] );
                $saved_image_ids = $valid_ids;
            }
        }

        $post       = get_post( $post_id );
        $image_urls = array_values(array_filter(array_map(
            fn( $id ) => wp_get_attachment_url( $id ),
            $saved_image_ids
        )));

        return rest_ensure_response( [
            'id'        => $post_id,
            'title'     => $post->post_title,
            'content'   => $post->post_content,
            'link'      => get_permalink( $post_id ),
            'type'      => $post->post_type,
            'image_ids' => $saved_image_ids,
            'images'    => $image_urls,
        ] );
    }

    /**
     * Schema arguments for endpoint validation.
     */
    public static function get_endpoint_args(): array
    {
        return [
            'post_type' => [
                'type'        => 'string',
                'required'    => true,
                'description' => 'The type of post to create.',
                'enum'        => self::$allowed_post_types,
            ],
            'title' => [
                'type'        => 'string',
                'required'    => true,
                'description' => 'Title of the post.',
            ],
            'event_date' => [
                'type'        => 'string',
                'format'      => 'date',
                'required'    => false,
                'description' => 'Date of the event.',
            ],
            'event_location' => [
                'type'        => 'string',
                'required'    => false,
                'description' => 'Location of the event.',
            ],
            'image_ids' => [
                'type'        => 'array',
                'items'       => [
                    'type' => 'integer',
                ],
                'required'    => false,
                'description' => 'List of image attachment IDs.',
            ],
        ];
    }

    /**
     * Map field keys to meta keys for each post type.
     */
    private static function get_meta_fields_for( string $post_type ): array
    {
        return match ( $post_type ) {
            'artpulse_event'   => [
                'event_date'     => '_ap_event_date',
                'event_location' => '_ap_event_location',
            ],
            'artpulse_artist'  => [
                'artist_bio' => '_ap_artist_bio',
                'artist_org' => '_ap_artist_org',
            ],
            'artpulse_artwork' => [
                'artwork_medium'     => '_ap_artwork_medium',
                'artwork_dimensions' => '_ap_artwork_dimensions',
                'artwork_materials'  => '_ap_artwork_materials',
            ],
            'artpulse_org'     => [
                'org_website' => '_ap_org_website',
                'org_email'   => '_ap_org_email',
            ],
            default            => [],
        };
    }

    /**
     * Ensure the current user has access to submit content.
     */
    public static function permissions_check( WP_REST_Request $request ): bool|WP_Error
    {
        if ( is_user_logged_in() || current_user_can( 'read' ) ) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            __( 'You are not allowed to submit content.', 'artpulse-management' ),
            [ 'status' => rest_authorization_required_code() ]
        );
    }
}
