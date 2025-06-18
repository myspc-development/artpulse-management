<?php
namespace ArtPulse\Admin;

class AdminColumnsOrganisation
{
    public static function register()
    {
        add_filter( 'manage_artpulse_org_posts_columns',        [ __CLASS__, 'add_columns' ] );
        add_action( 'manage_artpulse_org_posts_custom_column',  [ __CLASS__, 'render_columns' ], 10, 2 );
        add_filter( 'manage_edit-artpulse_org_sortable_columns', [ __CLASS__, 'make_sortable' ] );
    }

    public static function add_columns( array $columns ): array
    {
        $new = [];
        foreach ( $columns as $key => $label ) {
            if ( 'cb' === $key ) {
                $new['cb']             = $label;
                $new['logo']           = __( 'Logo', 'artpulse' );
                $new['ead_org_name']   = __( 'Name', 'artpulse' );
            }
            $new[ $key ] = $label;
        }
        return $new;
    }

    public static function render_columns( string $column, int $post_id )
    {
        switch ( $column ) {
            case 'logo':
                $url = get_post_meta( $post_id, 'ead_org_logo_url', true );
                if ( $url ) {
                    printf(
                        '<a href="%1$s" target="_blank"><img src="%1$s" style="max-width:80px;height:auto;" /></a>',
                        esc_url( $url )
                    );
                } else {
                    echo '&mdash;';
                }
                break;

            case 'ead_org_name':
                $name = get_post_meta( $post_id, 'ead_org_name', true );
                echo esc_html( $name ?: get_the_title( $post_id ) );
                break;
        }
    }

    public static function make_sortable( array $columns ): array
    {
        $columns['ead_org_name'] = 'ead_org_name';
        return $columns;
    }
}

AdminColumnsOrganisation::register();
