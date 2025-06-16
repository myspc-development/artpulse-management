<?php
namespace EAD\Integration;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Synchronize custom post types with the theme portfolio post type.
 */
class PortfolioSync {
    /**
     * Register WordPress hooks.
     */
    public static function register() {
        $post_types = [ 'ead_event', 'ead_artist', 'ead_organization', 'ead_artwork' ];
        foreach ( $post_types as $pt ) {
            add_action( "save_post_{$pt}", [ self::class, 'sync_to_portfolio' ], 10, 3 );
        }
    }

    /**
     * Sync a source post to the portfolio post type.
     *
     * @param int      $post_id Source post ID.
     * @param \WP_Post $post    Post object.
     * @param bool     $update  Whether this is an existing post update.
     */
    public static function sync_to_portfolio( $post_id, $post, $update ) {
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        $allowed = [ 'ead_event', 'ead_artist', 'ead_organization', 'ead_artwork' ];
        if ( ! in_array( $post->post_type, $allowed, true ) ) {
            return;
        }

        $portfolio_id = (int) ead_get_meta( $post_id, '_portfolio_post_id');

        // If the post is not published, remove any existing portfolio entry.
        if ( 'publish' !== $post->post_status ) {
            if ( $portfolio_id ) {
                wp_delete_post( $portfolio_id, true );
                delete_post_meta( $post_id, '_portfolio_post_id' );
            }
            return;
        }

        $portfolio_args = [
            'post_type'   => 'portfolio',
            'post_status' => 'publish',
            'post_title'  => $post->post_title,
            'post_content'=> (string) $post->post_content,
        ];

        if ( $portfolio_id && get_post( $portfolio_id ) ) {
            $portfolio_args['ID'] = $portfolio_id;
            $portfolio_id         = wp_update_post( $portfolio_args );
        } else {
            $portfolio_id = wp_insert_post( $portfolio_args );
            if ( $portfolio_id ) {
                update_post_meta( $post_id, '_portfolio_post_id', $portfolio_id );
            }
        }

        if ( ! $portfolio_id || is_wp_error( $portfolio_id ) ) {
            return;
        }

        // Copy featured image.
        $thumb = get_post_thumbnail_id( $post_id );
        if ( $thumb ) {
            set_post_thumbnail( $portfolio_id, $thumb );
        } else {
            delete_post_thumbnail( $portfolio_id );
        }

        // Map taxonomy terms.
        $taxonomy_map = [
            'event_category'        => 'category',
            'organization_category' => 'category',
            'artist_category'       => 'category',
            'artwork_category'      => 'category',
            'post_tag'              => 'post_tag',
        ];

        foreach ( $taxonomy_map as $source => $target ) {
            if ( taxonomy_exists( $source ) && taxonomy_exists( $target ) ) {
                $terms = wp_get_object_terms( $post_id, $source, [ 'fields' => 'ids' ] );
                if ( ! is_wp_error( $terms ) ) {
                    wp_set_object_terms( $portfolio_id, $terms, $target );
                }
            }
        }
    }
}
