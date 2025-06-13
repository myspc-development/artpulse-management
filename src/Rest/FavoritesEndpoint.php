<?php
namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class FavoritesEndpoint extends WP_REST_Controller {
    protected string $namespace;
    protected string $rest_base;

    public function __construct() {
        $this->namespace = 'artpulse/v1';
        $this->rest_base  = 'favorites';
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getFavorites' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'addFavorite' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
                'args'                => $this->getEndpointArgs(),
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'removeFavorite' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
                'args'                => $this->getEndpointArgs(),
            ]
        );
    }

    public function getFavorites( WP_REST_Request $request ) {
        $user_id   = get_current_user_id();
        $favorites = get_user_meta( $user_id, 'ead_favorites', true );
        if ( ! is_array( $favorites ) ) {
            $favorites = [];
        }

        return new WP_REST_Response( $favorites, 200 );
    }

    public function addFavorite( WP_REST_Request $request ) {
        $user_id   = get_current_user_id();
        $post_id   = (int) $request->get_param( 'post_id' );
        $favorites = get_user_meta( $user_id, 'ead_favorites', true );
        if ( ! is_array( $favorites ) ) {
            $favorites = [];
        }
        if ( ! in_array( $post_id, $favorites, true ) ) {
            $favorites[] = $post_id;
            update_user_meta( $user_id, 'ead_favorites', $favorites );
        }

        return new WP_REST_Response( [ 'favorites' => array_values( $favorites ) ], 200 );
    }

    public function removeFavorite( WP_REST_Request $request ) {
        $user_id   = get_current_user_id();
        $post_id   = (int) $request->get_param( 'post_id' );
        $favorites = get_user_meta( $user_id, 'ead_favorites', true );
        if ( ! is_array( $favorites ) ) {
            $favorites = [];
        }
        $favorites = array_values( array_diff( $favorites, [ $post_id ] ) );
        update_user_meta( $user_id, 'ead_favorites', $favorites );

        return new WP_REST_Response( [ 'favorites' => $favorites ], 200 );
    }

    public function permissionsCheck( WP_REST_Request $request ) {
        return is_user_logged_in();
    }

    public function getEndpointArgs() : array {
        return [
            'post_id' => [
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ],
        ];
    }
}
