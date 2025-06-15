<?php

namespace EAD\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Handles the Organizations REST API endpoint.
 */
class OrganizationsEndpoint extends WP_REST_Controller {

    protected $namespace;
    protected $rest_base;

    public function __construct() {
        $this->namespace = 'artpulse/v1';
        $this->rest_base  = 'organizations';

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Registers the routes.
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'createOrganization' ],
                'permission_callback' => [ $this, 'permissionsCheck' ],
            ]
        );
    }

    /**
     * Permission check.
     */
    public function permissionsCheck( $request ) {
        return is_user_logged_in() && current_user_can( 'ead_register_organization' );
    }

    /**
     * Handles the creation of an organization.
     */
    public function createOrganization( WP_REST_Request $request ) {
        $current_user = wp_get_current_user();

        if ( ! $current_user->ID ) {
            return new WP_Error( 'not_logged_in', __( 'You must be logged in to register an organization.', 'artpulse-management' ), [ 'status' => 401 ] );
        }

        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( $nonce && ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'artpulse-management' ), [ 'status' => 403 ] );
        }

        $org_name = sanitize_text_field( $request->get_param( 'ead_org_name' ) );
        if ( empty( $org_name ) ) {
            return new WP_Error( 'missing_name', __( 'Organization name is required.', 'artpulse-management' ), [ 'status' => 400 ] );
        }

        $org_description = wp_kses_post( $request->get_param( 'ead_org_description' ) );

        $post_data = [
            'post_title'   => $org_name,
            'post_type'    => 'ead_organization',
            'post_status'  => 'pending',
            'post_author'  => $current_user->ID,
            'post_content' => $org_description,
        ];

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            return new WP_Error( 'create_failed', __( 'Failed to create organization.', 'artpulse-management' ), [ 'status' => 500 ] );
        }

        update_post_meta( $post_id, 'ead_org_name', $org_name );

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $uploaded_files = [];
        $file_fields    = [ 'ead_org_logo_id', 'ead_org_banner_id' ];
        $max_size       = 2 * 1024 * 1024; // 2 MB

        foreach ( $file_fields as $file_field ) {
            if ( ! empty( $_FILES[ $file_field ]['name'] ) ) {
                if ( $_FILES[ $file_field ]['size'] > $max_size ) {
                    wp_delete_post( $post_id, true );

                    return new WP_Error(
                        'file_too_large',
                        __( 'Uploaded file exceeds 2 MB.', 'artpulse-management' ),
                        [ 'status' => 400 ]
                    );
                }

                $attachment_id = media_handle_upload( $file_field, $post_id );

                if ( is_wp_error( $attachment_id ) ) {
                    wp_delete_post( $post_id, true );

                    return new WP_Error(
                        'upload_failed',
                        sprintf( __( 'Failed to upload %s.', 'artpulse-management' ), esc_html( $file_field ) ),
                        [ 'status' => 400 ]
                    );
                }

                update_post_meta( $post_id, $file_field, $attachment_id );
                $uploaded_files[ $file_field ] = $attachment_id;
            }
        }

        $fields = $this->get_fields();
        foreach ( $fields as $field ) {
            $value = $request->get_param( $field );
            if ( $value !== null ) {
                if ( strpos( $field, 'url' ) !== false ) {
                    $value = esc_url_raw( $value );
                } elseif ( strpos( $field, 'email' ) !== false ) {
                    $value = sanitize_email( $value );
                } elseif ( strpos( $field, 'description' ) !== false ) {
                    $value = wp_kses_post( $value );
                } else {
                    $value = sanitize_text_field( $value );
                }

                update_post_meta( $post_id, $field, $value );
            }
        }

        // Address data
        $address_data_json = $request->get_param( 'address_data' );
        if ( $address_data_json ) {
            $address_data = json_decode( $address_data_json, true );
            if ( is_array( $address_data ) ) {
                foreach ( $address_data as $key => $value ) {
                    update_post_meta( $post_id, $key, sanitize_text_field( $value ) );
                }
            }
        }

        // Opening hours
        $opening_hours_json = $request->get_param( 'opening_hours' );
        if ( $opening_hours_json ) {
            $opening_hours = json_decode( $opening_hours_json, true );
            if ( is_array( $opening_hours ) ) {
                foreach ( $opening_hours as $key => $value ) {
                    update_post_meta( $post_id, $key, sanitize_text_field( $value ) );
                }
            }
        }

        // Gallery images - allow IDs from media library or file uploads
        $gallery_ids = [];

        $id_params = $request->get_param( 'ead_org_gallery_images' );
        if ( is_array( $id_params ) && ! empty( $id_params ) ) {
            foreach ( $id_params as $img_id ) {
                $id = absint( $img_id );
                if ( $id ) {
                    $gallery_ids[] = $id;
                    wp_update_post( [ 'ID' => $id, 'post_parent' => $post_id ] );
                }
            }
        } elseif ( ! empty( $_FILES['ead_org_gallery']['name'] ) && is_array( $_FILES['ead_org_gallery']['name'] ) ) {
            $files = $_FILES['ead_org_gallery'];
            for ( $i = 0; $i < count( $files['name'] ); $i++ ) {
                if ( empty( $files['name'][ $i ] ) ) {
                    continue;
                }
                if ( $files['size'][ $i ] > $max_size ) {
                    wp_delete_post( $post_id, true );

                    return new WP_Error(
                        'file_too_large',
                        __( 'Uploaded file exceeds 2 MB.', 'artpulse-management' ),
                        [ 'status' => 400 ]
                    );
                }
                $file_array = [
                    'name'     => $files['name'][ $i ],
                    'type'     => $files['type'][ $i ],
                    'tmp_name' => $files['tmp_name'][ $i ],
                    'error'    => $files['error'][ $i ],
                    'size'     => $files['size'][ $i ],
                ];
                $attachment_id = media_handle_sideload( $file_array, $post_id );
                if ( ! is_wp_error( $attachment_id ) ) {
                    $gallery_ids[] = $attachment_id;
                }
            }

            $order_param = $request->get_param( 'ead_org_gallery_order' );
            if ( $order_param ) {
                $order = array_map( 'intval', array_filter( explode( ',', $order_param ) ) );
                $ordered = [];
                foreach ( $order as $idx ) {
                    if ( isset( $gallery_ids[ $idx ] ) ) {
                        $ordered[] = $gallery_ids[ $idx ];
                    }
                }
                foreach ( $gallery_ids as $idx => $id ) {
                    if ( ! in_array( $idx, $order, true ) ) {
                        $ordered[] = $id;
                    }
                }
                $gallery_ids = $ordered;
            }
        }

        if ( ! empty( $gallery_ids ) ) {
            $gallery_ids = array_slice( array_unique( $gallery_ids ), 0, 5 );
            update_post_meta( $post_id, 'ead_org_gallery_images', $gallery_ids );
        }

        $confirmation_url = apply_filters(
            'ead_organization_confirmation_url',
            home_url( '/organization-registration-success/' )
        );

        return new WP_REST_Response(
            [
                'success'          => true,
                'message'          => __( 'Organization registered successfully!', 'artpulse-management' ),
                'post_id'          => $post_id,
                'uploaded_files'   => $uploaded_files,
                'confirmation_url' => $confirmation_url,
            ],
            200
        );
    }

    private function get_fields() {
        return [
            'ead_org_name', 'ead_org_description', 'ead_org_website_url', 'ead_org_logo_id', 'ead_org_banner_id',
            'ead_org_type', 'ead_org_size', 'ead_org_facebook_url', 'ead_org_twitter_url',
            'ead_org_instagram_url', 'ead_org_linkedin_url', 'ead_org_artsy_url', 'ead_org_pinterest_url',
            'ead_org_youtube_url', 'ead_org_primary_contact_name', 'ead_org_primary_contact_email',
            'ead_org_primary_contact_phone', 'ead_org_primary_contact_role',
            'ead_org_venue_email', 'ead_org_venue_phone', 'ead_org_gallery_order'
        ];
    }
}
