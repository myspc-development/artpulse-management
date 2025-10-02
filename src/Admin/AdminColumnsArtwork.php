<?php
namespace ArtPulse\Admin;

class AdminColumnsArtwork
{
    public static function register()
    {
        add_filter( 'manage_artpulse_artwork_posts_columns',        [ __CLASS__, 'add_columns' ] );
        add_action( 'manage_artpulse_artwork_posts_custom_column',  [ __CLASS__, 'render_columns' ], 10, 2 );
        add_filter( 'manage_edit-artpulse_artwork_sortable_columns', [ __CLASS__, 'make_sortable' ] );
    }

    public static function add_columns( array $columns ): array
    {
        $new = [];
        foreach ( $columns as $key => $label ) {
            if ( 'cb' === $key ) {
                $new['cb']              = $label;
                $new['image']           = __( 'Image', 'artpulse' );
                $new['artwork_title']   = __( 'Title', 'artpulse' );
            }
            $new[ $key ] = $label;
        }
        return $new;
    }

    public static function render_columns( string $column, int $post_id )
    {
        if ( 'image' !== $column ) {
            return;
        }

        $id = get_post_meta( $post_id, 'artwork_image', true );
        if ( $id ) {
            echo wp_get_attachment_image( (int)$id, [60,60] );
        } elseif ( has_post_thumbnail( $post_id ) ) {
            echo get_the_post_thumbnail( $post_id, [60,60] );
        } else {
            echo '&mdash;';
        }
    }

    public static function make_sortable( array $columns ): array
    {
        $columns['artwork_title'] = 'title';
        return $columns;
    }
}
