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
                'permission_callback' => '__return_true', // fully open
            ]
        );
    }

    /**
     * Handle the form submission via REST.
     */
    public static function handle_submission( WP_REST_Request $request ): WP_REST_Response|WP_Error
    {
        $params    = $request->get_json_params();
        $post_type = sanitize_key( $params['post_type'] ?? 'artpulse_event' );

        // 1) Validate post type
        if ( ! in_array( $post_type, self::$allowed_post_types, true ) ) {
            return new WP_Error( 'invalid_post_type', 'Invalid post type.', [ 'status' => 400 ] );
        }

        // 2) Basic validation: title required
        if ( empty( $params['title'] ) ) {
            return new WP_Error( 'missing_title', 'Title is required.', [ 'status' => 400 ] );
        }

        // 3) Create the post
        $post_id = wp_insert_post( [
            'post_type'   => $post_type,
            'post_title'  => sanitize_text_field( $params['title'] ),
            'post_status' => 'publish',
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // 4) Save meta fields
        $meta_fields = self::get_meta_fields_for( $post_type );
        foreach ( $meta_fields as $field_key => $meta_key ) {
            if ( isset( $params[ $field_key ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $params[ $field_key ] ) );
            }
        }

        // 5) Save up to 5 image IDs (optional)
        $saved_image_ids = [];
        if ( ! empty( $params['image_ids'] ) && is_array( $params['image_ids'] ) ) {
            $ids = array_slice( array_map( 'absint', $params['image_ids'] ), 0, 5 );
            if ( $ids ) {
                update_post_meta( $post_id, '_ap_submission_images', $ids );
                $saved_image_ids = $ids;
                set_post_thumbnail( $post_id, $ids[0] );
            }
        }

        // 6) Build response
        $post       = get_post( $post_id );
        $image_urls = array_map(
            fn( $id ) => wp_get_attachment_url( $id ) ?: '',
            $saved_image_ids
        );

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
     * Per-post-type meta key mapping.
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
}
