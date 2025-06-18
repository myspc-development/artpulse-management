<?php
namespace ArtPulse\Admin;

class AdminColumnsEvent
{
    public static function register()
    {
        add_filter( 'manage_artpulse_event_posts_columns',        [ __CLASS__, 'add_columns' ] );
        add_action( 'manage_artpulse_event_posts_custom_column',  [ __CLASS__, 'render_columns' ], 10, 2 );
        add_filter( 'manage_edit-artpulse_event_sortable_columns', [ __CLASS__, 'make_sortable' ] );
    }

    public static function add_columns( array $columns ): array
    {
        $new = [];
        foreach ( $columns as $key => $label ) {
            if ( 'cb' === $key ) {
                $new['cb']              = $label;
                $new['event_banner']    = __( 'Banner',  'artpulse' );
                $new['event_dates']     = __( 'Dates',   'artpulse' );
                $new['event_venue']     = __( 'Venue',   'artpulse' );
                $new['event_featured']  = __( '⭐ Featured', 'artpulse' );
            }
            $new[ $key ] = $label;
        }
        return $new;
    }

    public static function render_columns( string $column, int $post_id )
    {
        switch ( $column ) {
            case 'event_banner':
                $id = get_post_meta( $post_id, 'event_banner_id', true );
                if ( $id ) {
                    echo wp_get_attachment_image( (int)$id, [60,60] );
                } else {
                    echo '&mdash;';
                }
                break;

            case 'event_dates':
                $start = get_post_meta( $post_id, 'event_start_date', true );
                $end   = get_post_meta( $post_id, 'event_end_date',   true );
                echo esc_html( $start );
                if ( $end && $end !== $start ) {
                    echo ' – ' . esc_html( $end );
                }
                break;

            case 'event_venue':
                $venue = get_post_meta( $post_id, 'venue_name', true );
                echo esc_html( $venue ?: '—' );
                break;

            case 'event_featured':
                $flag = get_post_meta( $post_id, 'event_featured', true );
                echo '1' === $flag ? '⭐' : '&mdash;';
                break;
        }
    }

    public static function make_sortable( array $columns ): array
    {
        $columns['event_featured'] = 'event_featured';
        $columns['event_dates']    = 'event_start_date';
        return $columns;
    }
}

AdminColumnsEvent::register();
