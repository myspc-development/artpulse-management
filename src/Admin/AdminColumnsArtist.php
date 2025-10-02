<?php
namespace ArtPulse\Admin;

class AdminColumnsArtist
{
    public static function register()
    {
        add_filter( 'manage_artpulse_artist_posts_columns',        [ __CLASS__, 'add_columns' ] );
        add_action( 'manage_artpulse_artist_posts_custom_column',  [ __CLASS__, 'render_columns' ], 10, 2 );
        add_filter( 'manage_edit-artpulse_artist_sortable_columns', [ __CLASS__, 'make_sortable' ] );
    }

    public static function add_columns( array $columns ): array
    {
        $new = [];
        foreach ( $columns as $key => $label ) {
            if ( 'cb' === $key ) {
                $new['cb']           = $label;
                $new['portrait']     = __( 'Portrait', 'artpulse' );
                $new['artist_name']  = __( 'Name',     'artpulse' );
            }
            $new[ $key ] = $label;
        }
        return $new;
    }

    public static function render_columns( string $column, int $post_id )
    {
        if ( 'portrait' !== $column ) {
            return;
        }

        // 1) custom meta portrait; 2) featured image; else dash
        $id = get_post_meta( $post_id, 'artist_portrait', true );
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
        $columns['artist_name'] = 'artist_name';
        return $columns;
    }
}
