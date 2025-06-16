<?php

namespace EAD\Shortcodes;

use EAD\Shortcodes\HoneypotTrait;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ArtworkSubmissionForm {
    use HoneypotTrait;

    const SHORTCODE_TAG = 'ead_artwork_submission_form'; // Define your shortcode tag

    /**
     * Registers the shortcode and enqueues assets when the shortcode is present.
     */
    public static function register() {
        add_shortcode( self::SHORTCODE_TAG, [ self::class, 'render_form_shortcode_callback' ] );
    }

    /**
     * Callback for the shortcode.
     * It calls render_form and also enqueues assets if the shortcode is used.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output of the form.
     */
    public static function render_form_shortcode_callback( $atts = [] ) {
        // Enqueue assets only when the shortcode is actually used on a page
        if ( ! is_admin() ) { // Ensure assets are not enqueued in the admin area unless intended
            self::enqueue_assets();
        }
        return self::render_form( $atts );
    }


    /**
     * Renders the artwork submission/edit form.
     *
     * @param array $atts Shortcode attributes, may include 'id' for editing.
     * @return string HTML output of the form.
     */
    public static function render_form( $atts = [] ) {
        if ( ! is_user_logged_in() ) {
            return '<p>' . __( 'Please log in to submit artwork.', 'artpulse-management' ) . '</p>';
        }

        // Get artwork ID from attributes for editing, if any
        $artwork_id = isset( $atts['id'] ) ? intval( $atts['id'] ) : 0;
        $post       = null;
        $is_editing = false;

        if ( $artwork_id > 0 ) {
            $post = get_post( $artwork_id );
            // Ensure the post exists, is an artwork, and the current user is the author or has edit_posts cap
            if ( ! $post || $post->post_type !== 'ead_artwork' || ! current_user_can( 'edit_post', $artwork_id ) ) {
                return '<p>' . __( 'You do not have permission to edit this artwork, or it does not exist.', 'artpulse-management' ) . '</p>';
            }
            $is_editing = true;
        }

        do_action( 'artpulse_before_form' );
        ob_start();
        ?>
        <div class="ead-artwork-submission-form-wrap">
            <h2 class="ead-form-title"><?php echo $is_editing ? __( 'Edit Artwork', 'artpulse-management' ) : __( 'Submit New Artwork', 'artpulse-management' ); ?></h2>
            <div id="ead-artwork-submission-message" class="ead-form-message" style="display:none;"></div>

            <form id="ead-artwork-submission-form" data-artwork-id="<?php echo esc_attr( $artwork_id ); ?>">
                <?php wp_nonce_field( 'ead_artwork_submission_nonce_action', 'ead_artwork_submission_nonce_field' ); // Action name for nonce should be specific ?>

                <div class="ead-form-section">
                    <h3><?php _e( 'Artwork Details', 'artpulse-management' ); ?></h3>
                    <?php
                    $fields = self::get_artwork_meta_fields_for_frontend();
                    foreach ( $fields as $key => $args ) {
                        list( $type, $label, $placeholder, $required ) = array_pad($args, 4, false); // Add $required with default false
                        $value = $post ? ead_get_meta( $post->ID, '_' . $key ) : '';

                        // Special handling for artwork_artist if the current user is an artist
                        if ( $key === 'artwork_artist' && ! $is_editing ) { // Only prefill for new submissions
                            if ( current_user_can( 'edit_ead_artists' ) ) { // Assuming 'edit_ead_artists' is a capability for artists
                                $current_user = wp_get_current_user();
                                // Try to get a specific artist name meta, fallback to display name
                                $artist_name = get_user_meta( $current_user->ID, 'ead_artist_display_name', true ); // Example meta key
                                if ( empty( $artist_name ) ) {
                                    $artist_name = $current_user->display_name;
                                }
                                $value = $artist_name; // Pre-fill with artist's name
                            }
                        }
                        ?>
                        <p class="ead-form-field ead-field-<?php echo esc_attr($key); ?>">
                            <label for="ead-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?><?php if ($required) echo ' <span class="required">*</span>'; ?></label>
                            <?php
                            $input_name = esc_attr( $key );
                            $input_id   = 'ead-' . esc_attr( $key );
                            $required_attr = $required ? 'required' : '';

                            switch ( $type ) {
                                case 'boolean':
                                    $checked = $value ? 'checked' : '';
                                    echo '<input type="checkbox" name="' . $input_name . '" id="' . $input_id . '" value="1" ' . $checked . ' />';
                                    break;
                                case 'integer':
                                    echo '<input type="number" name="' . $input_name . '" id="' . $input_id . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" ' . $required_attr . '/>';
                                    break;
                                case 'textarea':
                                    echo '<textarea name="' . $input_name . '" id="' . $input_id . '" rows="4" placeholder="' . esc_attr( $placeholder ) . '" ' . $required_attr . '>' . esc_textarea( $value ) . '</textarea>';
                                    break;
                                case 'string':
                                default:
                                    echo '<input type="text" name="' . $input_name . '" id="' . $input_id . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" ' . $required_attr . '/>';
                                    break;
                            }
                            ?>
                        </p>
                        <?php
                    }
                    ?>
                </div>

                <div class="ead-form-section">
                    <h3><?php _e( 'Artwork Images', 'artpulse-management' ); ?></h3>
                    <p><?php _e( 'Upload up to 5 images for your artwork. The first image will be considered the featured image.', 'artpulse-management' ); ?></p>
                    <div class="ead-artwork-image-upload-area">
                        <?php
                        $existing_image_ids = $post ? (string) ead_get_meta( $post->ID, '_ead_artwork_gallery_images') : [];
                        if ( ! is_array( $existing_image_ids ) ) {
                            $existing_image_ids = [];
                        }
                        $max_images = 5; // Define max images
                        for ( $i = 0; $i < $max_images; $i++ ) {
                            $image_id  = isset( $existing_image_ids[ $i ] ) ? intval( $existing_image_ids[ $i ] ) : 0;
                            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : ''; // Use 'medium' or 'thumbnail'
                            ?>
                            <div class="ead-image-upload-container" data-image-index="<?php echo $i; ?>">
                                <div class="ead-image-preview <?php echo $image_id ? 'has-image' : ''; ?>"
                                     style="<?php echo $image_url ? 'background-image: url(\'' . esc_url( $image_url ) . '\');' : ''; ?>">
                                    <?php if ( ! $image_id ) : ?>
                                        <span class="placeholder"><?php printf( esc_html__( 'Image %d', 'artpulse-management' ), $i + 1 ); ?></span>
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" name="artwork_gallery_images[]" class="ead-image-id-input" value="<?php echo esc_attr( $image_id ); ?>"/>
                                <button type="button" class="button ead-upload-image-button"><?php _e( 'Select Image', 'artpulse-management' ); ?></button>
                                <button type="button" class="button ead-remove-image-button <?php echo $image_id ? '' : 'hidden'; ?>"><?php _e( 'Remove Image', 'artpulse-management' ); ?></button>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                </div>

                <p class="ead-form-submit">
                    <?php echo self::render_honeypot( $atts ); ?>
                    <button type="submit" id="ead-submit-artwork-button" class="button button-primary">
                        <?php echo $is_editing ? __( 'Update Artwork', 'artpulse-management' ) : __( 'Submit Artwork', 'artpulse-management' ); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
        $html = ob_get_clean();
        $html = apply_filters( 'artpulse_form_output', $html );
        return $html;
    }

    /**
     * Helper to get artwork meta fields for frontend rendering.
     *
     * @return array
     */
    private static function get_artwork_meta_fields_for_frontend() {
        // [key => [type, label, placeholder, is_required (boolean, optional)]]
        // Ensure meta keys here match what your saving/REST logic expects (often prefixed with '_')
        // For this example, I'm assuming the keys here are the base keys, and '_' will be added when saving/retrieving.
        $fields = [
            'artwork_title'       => ['string',   __( 'Artwork Title', 'artpulse-management' ),      __( 'e.g., Sunset Over the Bay', 'artpulse-management' ), true],
            'artwork_artist'      => ['string',   __( 'Artist Name', 'artpulse-management' ),        __( 'e.g., Jane Doe', 'artpulse-management' ), true],
            'artwork_medium'      => ['string',   __( 'Medium', 'artpulse-management' ),             __( 'e.g., Oil on Canvas, Sculpture, Digital Art', 'artpulse-management' ), true],
            'artwork_dimensions'  => ['string',   __( 'Dimensions', 'artpulse-management' ),         __( 'e.g., 24" x 36" (H x W), 10 x 10 x 15 cm', 'artpulse-management' )],
            'artwork_year'        => ['integer',  __( 'Year Created', 'artpulse-management' ),       __( 'e.g., 2023', 'artpulse-management' ), true],
            'artwork_materials'   => ['textarea', __( 'Materials Used', 'artpulse-management' ),      __( 'e.g., Canvas, wood, bronze, acrylic paint', 'artpulse-management' )],
            'artwork_price'       => ['string',   __( 'Price (USD or specify currency)', 'artpulse-management' ),              __( 'e.g., 1500, POA (Price on Application)', 'artpulse-management' )],
            'artwork_provenance'  => ['textarea', __( 'Provenance (History of Ownership)', 'artpulse-management' ),         __( 'e.g., Collection of John Smith, Acquired from XYZ Gallery', 'artpulse-management' )],
            'artwork_edition'     => ['string',   __( 'Edition (if applicable)', 'artpulse-management' ),            __( 'e.g., 1/1 (Unique), 3/10 (Limited Edition)', 'artpulse-management' )],
            'artwork_tags'        => ['string',   __( 'Tags (comma-separated)', 'artpulse-management' ), __( 'e.g., landscape, abstract, portrait', 'artpulse-management' )],
            'artwork_description' => ['textarea', __( 'Detailed Description', 'artpulse-management' ),        __( 'A detailed description of the artwork, its inspiration, etc.', 'artpulse-management' ), true],
            'artwork_video_url'   => ['string',   __( 'Video URL (YouTube, Vimeo - Optional)', 'artpulse-management' ),          __( 'e.g., https://www.youtube.com/watch?v=example', 'artpulse-management' )],
        ];
        // Note: 'artwork_image' (featured image) and 'artwork_gallery_images' are handled by the dedicated image upload section.
        // 'artwork_featured' (admin toggle) is not for frontend submission.
        return $fields;
    }

    /**
     * Enqueues necessary CSS and JS for the artwork submission form.
     */
    public static function enqueue_assets() {
        // Ensure constants are defined or use plugin_dir_url(__FILE__) approach carefully
        if ( ! defined( 'EAD_PLUGIN_DIR_URL' ) || ! defined( 'EAD_PLUGIN_VERSION' ) ) {
            // Fallback or error, though these should be defined by the main plugin file.
            // For robustness, you might define them here if they aren't, or log an error.
            // For now, assume they are defined.
            return;
        }

        $plugin_url = EAD_PLUGIN_DIR_URL;
        $version    = EAD_PLUGIN_VERSION;

        // Enqueue styles for the form
        wp_enqueue_style( 'ead-frontend-forms', $plugin_url . 'assets/css/ead-frontend-forms.css', [], $version ); // General frontend form styles
        // You might have specific styles for artwork submission too
        // wp_enqueue_style('ead-artwork-submission-style', $plugin_url . 'assets/css/artwork-submission.css', ['ead-frontend-forms'], $version);


        // Enqueue scripts for the form and media uploader
        wp_enqueue_media(); // Crucial for wp.media()

        // A general image uploader script (if you have one for multiple forms)
        // Ensure 'ead-image-uploader.js' exists and handles the generic upload logic
        wp_enqueue_script( 'ead-image-uploader-helper', $plugin_url . 'assets/js/ead-image-uploader.js', [ 'jquery', 'wp-util' ], $version, true );


        // Specific script for artwork submission form interactions
        wp_enqueue_script( 'ead-artwork-submission-js', $plugin_url . 'assets/js/artwork-submission.js', [ 'jquery', 'ead-image-uploader-helper', 'wp-api-request' ], $version, true );

        // Pass necessary data to JavaScript
        wp_localize_script(
            'ead-artwork-submission-js',
            'eadArtworkSubmissionData', // Changed to avoid conflict with class name
            [
                // Use the plugin's REST namespace for artwork CRUD operations
                'rest_url_submit'   => esc_url_raw( rest_url( 'artpulse/v1/artworks' ) ), // Endpoint for POST (create)
                'rest_url_update'   => esc_url_raw( rest_url( 'artpulse/v1/artworks/' ) ), // Base for PUT (update), will append ID
                'nonce'             => wp_create_nonce( 'wp_rest' ), // REST API nonce
                'form_nonce_action' => 'ead_artwork_submission_nonce_action', // Matches wp_nonce_field action
                'form_nonce_name'   => 'ead_artwork_submission_nonce_field',  // Matches wp_nonce_field name
                'labels'            => [
                    'submitting'        => __( 'Submitting...', 'artpulse-management' ),
                    'updating'          => __( 'Updating...', 'artpulse-management' ),
                    'success_submit'    => __( 'Artwork submitted successfully! It will be reviewed shortly.', 'artpulse-management' ),
                    'success_update'    => __( 'Artwork updated successfully!', 'artpulse-management' ),
                    'error_general'     => __( 'An error occurred. Please try again.', 'artpulse-management' ),
                    'select_image_title' => __( 'Select or Upload Artwork Image', 'artpulse-management' ),
                    'use_this_image_button' => __( 'Use this image', 'artpulse-management' ),
                ],
                'max_images' => 5, // Consistent with the loop
                'text_required_fields' => __('Please fill in all required fields.', 'artpulse-management'),
            ]
        );
    }
}
