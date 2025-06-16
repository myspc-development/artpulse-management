<?php
namespace EAD\Admin;

/**
 * Class MetaBoxesOrganisation
 *
 * Handles the meta boxes for the Organization custom post type.
 */
class MetaBoxesOrganisation {

    /**
     * Register the meta boxes.
     */
    public static function register() {
        add_action( 'add_meta_boxes', [ self::class, 'add_meta_boxes' ] );
        add_action( 'save_post_ead_organization', [ self::class, 'save_meta_boxes' ], 10, 2 );
    }

    /**
     * Add the meta boxes to the Organization post type.
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'ead_organisation_details',
            __( 'Organization Details', 'artpulse-management' ),
            [ self::class, 'render_meta_box' ],
            'ead_organization',
            'normal',
            'default'
        );

        add_meta_box(
            'ead_organisation_featured',
            __( 'Featured Organization', 'artpulse-management' ),
            [ self::class, 'render_featured_meta_box' ],
            'ead_organization',
            'side',
            'default'
        );
    }

    /**
     * Define the meta fields for the Organization post type.
     *
     * @return array An array of meta field definitions.
     */
    private static function get_meta_fields() {
        return [
            'ead_org_name'                  => [ 'type' => 'text', 'label' => __( 'Organization Name', 'artpulse-management' ) ],
            'ead_org_description'           => [ 'type' => 'textarea', 'label' => __( 'Description', 'artpulse-management' ) ],
            'ead_org_website_url'           => [ 'type' => 'url', 'label' => __( 'Website URL', 'artpulse-management' ) ],
            'ead_org_logo_id'               => [ 'type' => 'media', 'label' => __( 'Logo', 'artpulse-management' ) ],
            'ead_org_banner_id'             => [ 'type' => 'media', 'label' => __( 'Banner', 'artpulse-management' ) ],
            'ead_org_image1_id'             => [ 'type' => 'media', 'label' => __( 'Gallery Image 1', 'artpulse-management' ) ],
            'ead_org_image2_id'             => [ 'type' => 'media', 'label' => __( 'Gallery Image 2', 'artpulse-management' ) ],
            'ead_org_image3_id'             => [ 'type' => 'media', 'label' => __( 'Gallery Image 3', 'artpulse-management' ) ],
            'ead_org_image4_id'             => [ 'type' => 'media', 'label' => __( 'Gallery Image 4', 'artpulse-management' ) ],
            'ead_org_image5_id'             => [ 'type' => 'media', 'label' => __( 'Gallery Image 5', 'artpulse-management' ) ],
            'ead_org_type'                   => [ 'type' => 'select', 'label' => __( 'Organization Type', 'artpulse-management' ) ],
            'ead_org_size'                   => [ 'type' => 'select', 'label' => __( 'Organization Size', 'artpulse-management' ) ],
            'ead_org_facebook_url'           => [ 'type' => 'url', 'label' => __( 'Facebook URL', 'artpulse-management' ) ],
            'ead_org_twitter_url'            => [ 'type' => 'url', 'label' => __( 'Twitter URL', 'artpulse-management' ) ],
            'ead_org_instagram_url'          => [ 'type' => 'url', 'label' => __( 'Instagram URL', 'artpulse-management' ) ],
            'ead_org_linkedin_url'           => [ 'type' => 'url', 'label' => __( 'LinkedIn URL', 'artpulse-management' ) ],
            'ead_org_artsy_url'              => [ 'type' => 'url', 'label' => __( 'Artsy URL', 'artpulse-management' ) ],
            'ead_org_pinterest_url'          => [ 'type' => 'url', 'label' => __( 'Pinterest URL', 'artpulse-management' ) ],
            'ead_org_youtube_url'            => [ 'type' => 'url', 'label' => __( 'YouTube URL', 'artpulse-management' ) ],
            'ead_org_primary_contact_name'   => [ 'type' => 'text', 'label' => __( 'Primary Contact Name', 'artpulse-management' ) ],
            'ead_org_primary_contact_email'  => [ 'type' => 'email', 'label' => __( 'Primary Contact Email', 'artpulse-management' ) ],
            'ead_org_primary_contact_phone'  => [ 'type' => 'text', 'label' => __( 'Primary Contact Phone', 'artpulse-management' ) ],
            'ead_org_primary_contact_role'   => [ 'type' => 'text', 'label' => __( 'Primary Contact Role', 'artpulse-management' ) ],
            'ead_org_street_address'        => [ 'type' => 'text', 'label' => __( 'Street Address', 'artpulse-management' ) ],
            'ead_org_postal_address'        => [ 'type' => 'text', 'label' => __( 'Postal Address', 'artpulse-management' ) ],
            'ead_org_venue_address'         => [ 'type' => 'text', 'label' => __( 'Venue Address', 'artpulse-management' ) ],
            'ead_org_venue_email'           => [ 'type' => 'email', 'label' => __( 'Venue Email', 'artpulse-management' ) ],
            'ead_org_venue_phone'           => [ 'type' => 'text', 'label' => __( 'Venue Phone', 'artpulse-management' ) ],
            'ead_org_monday_start_time' => [ 'type' => 'time', 'label' => __( 'Monday Opening Time', 'artpulse-management' ) ],
            'ead_org_monday_end_time'   => [ 'type' => 'time', 'label' => __( 'Monday Closing Time', 'artpulse-management' ) ],
            'ead_org_monday_closed'     => [ 'type' => 'checkbox', 'label' => __( 'Closed on Monday', 'artpulse-management' ) ],
            'ead_org_tuesday_start_time'  => [ 'type' => 'time', 'label' => __( 'Tuesday Opening Time', 'artpulse-management' ) ],
            'ead_org_tuesday_end_time'    => [ 'type' => 'time', 'label' => __( 'Tuesday Closing Time', 'artpulse-management' ) ],
            'ead_org_tuesday_closed'      => [ 'type' => 'checkbox', 'label' => __( 'Closed on Tuesday', 'artpulse-management' ) ],
            'ead_org_wednesday_start_time' => [ 'type' => 'time', 'label' => __( 'Wednesday Opening Time', 'artpulse-management' ) ],
            'ead_org_wednesday_end_time'   => [ 'type' => 'time', 'label' => __( 'Wednesday Closing Time', 'artpulse-management' ) ],
            'ead_org_wednesday_closed'     => [ 'type' => 'checkbox', 'label' => __( 'Closed on Wednesday', 'artpulse-management' ) ],
            'ead_org_thursday_start_time'  => [ 'type' => 'time', 'label' => __( 'Thursday Opening Time', 'artpulse-management' ) ],
            'ead_org_thursday_end_time'    => [ 'type' => 'time', 'label' => __( 'Thursday Closing Time', 'artpulse-management' ) ],
            'ead_org_thursday_closed'      => [ 'type' => 'checkbox', 'label' => __( 'Closed on Thursday', 'artpulse-management' ) ],
            'ead_org_friday_start_time'    => [ 'type' => 'time', 'label' => __( 'Friday Opening Time', 'artpulse-management' ) ],
            'ead_org_friday_end_time'      => [ 'type' => 'time', 'label' => __( 'Friday Closing Time', 'artpulse-management' ) ],
            'ead_org_friday_closed'        => [ 'type' => 'checkbox', 'label' => __( 'Closed on Friday', 'artpulse-management' ) ],
            'ead_org_saturday_start_time'  => [ 'type' => 'time', 'label' => __( 'Saturday Opening Time', 'artpulse-management' ) ],
            'ead_org_saturday_end_time'    => [ 'type' => 'time', 'label' => __( 'Saturday Closing Time', 'artpulse-management' ) ],
            'ead_org_saturday_closed'      => [ 'type' => 'checkbox', 'label' => __( 'Closed on Saturday', 'artpulse-management' ) ],
            'ead_org_sunday_start_time'    => [ 'type' => 'time', 'label' => __( 'Sunday Opening Time', 'artpulse-management' ) ],
            'ead_org_sunday_end_time'      => [ 'type' => 'time', 'label' => __( 'Sunday Closing Time', 'artpulse-management' ) ],
            'ead_org_sunday_closed'        => [ 'type' => 'checkbox', 'label' => __( 'Closed on Sunday', 'artpulse-management' ) ],
            '_ead_featured' => [ 'type' => 'checkbox', 'label' => __('Featured', 'artpulse-management')],
            '_ead_featured_priority' => ['type' => 'number', 'label' => __('Featured Priority', 'artpulse-management')],
        ];
    }

    /**
     * Renders the meta box content.
     *
     * @param WP_Post $post The post object.
     */
    public static function render_meta_box( $post ) {
        wp_nonce_field( 'ead_organization_meta_nonce', 'ead_organization_meta_nonce' );

        $fields    = self::get_meta_fields();
        $meta      = [];
        $org_types = [
            'gallery'             => __( 'Art Gallery', 'artpulse-management' ),
            'museum'              => __( 'Museum', 'artpulse-management' ),
            'studio'              => __( 'Artist Studio', 'artpulse-management' ),
            'collective'          => __( 'Artist Collective', 'artpulse-management' ),
            'non-profit'          => __( 'Non-Profit Arts Organization', 'artpulse-management' ),
            'commercial-gallery'  => __( 'Commercial Gallery', 'artpulse-management' ),
            'public-art-space'    => __( 'Public Art Space', 'artpulse-management' ),
            'educational-institution' => __( 'Educational Institution (Arts Dept.)', 'artpulse-management' ),
            'other'               => __( 'Other', 'artpulse-management' ),
        ];
        $org_sizes = [
            'small'  => __( 'Small', 'artpulse-management' ),
            'medium' => __( 'Medium', 'artpulse-management' ),
            'large'  => __( 'Large', 'artpulse-management' ),
            'other'  => __( 'Other', 'artpulse-management' ),
        ];

        foreach ( $fields as $field => $args ) {
            $meta[ $field ] = ead_get_meta( $post->ID, $field);
        }

        echo '<table class="form-table">';
        foreach ( $fields as $field => $args ) {
            // Skip featured related fields here, render in the dedicated metabox
            if (in_array($field, ['_ead_featured', '_ead_featured_priority'])) {
                continue;
            }
            echo '<tr>';
            echo '<th><label for="ead_org_mb_' . esc_attr( $field ) . '">' . esc_html( $args['label'] ) . '</label></th>';
            echo '<td>';
            switch ( $args['type'] ) {
                case 'textarea':
                    echo '<textarea id="ead_org_mb_' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" rows="4" style="width:100%;">' . esc_textarea( $meta[ $field ] ) . '</textarea>';
                    break;
                case 'select':
                    echo '<select id="ead_org_mb_' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" style="width:100%;">';
                    echo '<option value="">' . esc_html__( 'Select Option', 'artpulse-management' ) . '</option>';
                    $options = [];
                    if ( $field === 'ead_org_type' ) {
                        $options = $org_types;
                    } elseif ( $field === 'ead_org_size' ) {
                        $options = $org_sizes;
                    }
                    foreach ( $options as $key => $label ) {
                        echo '<option value="' . esc_attr( $key ) . '" ' . selected( $meta[ $field ], $key, false ) . '>' . esc_html( $label ) . '</option>';
                    }
                    echo '</select>';
                    break;
                case 'media':
                    $image_id  = intval( $meta[ $field ] );
                    $image_src = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
                    echo '<input type="hidden" id="ead_org_mb_' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" value="' . esc_attr( $image_id ) . '">';
                    if ( $image_src ) {
                        echo '<img src="' . esc_url( $image_src ) . '" style="max-width: 200px; display: block; margin-bottom: 10px;">';
                    }
                    echo '<button type="button" class="button button-primary ead-media-upload" data-field="' . esc_attr( $field ) . '">' . esc_html__( 'Upload/Choose Image', 'artpulse-management' ) . '</button>';
                    echo '<button type="button" class="button button-secondary ead-media-remove" data-field="' . esc_attr( $field ) . '" style="margin-left: 10px;">' . esc_html__( 'Remove Image', 'artpulse-management' ) . '</button>';
                    break;
                case 'checkbox':
                    echo '<label>';
                    $checked = checked( $meta[ $field ], '1', false );
                    echo '<input type="checkbox" id="ead_org_mb_' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" value="1" ' . $checked . '>';
                    echo ' ' . esc_html( $args['label'] );
                    echo '</label>';
                    break;
                case 'time':
                    echo '<input type="time" id="ead_org_mb_' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" value="' . esc_attr( $meta[ $field ] ) . '" style="width:100%;">';
                    break;
                default:
                    echo '<input type="' . esc_attr( $args['type'] ) . '" id="ead_org_mb_' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" value="' . esc_attr( $meta[ $field ] ) . '" style="width:100%;">';
                    break;
            }
            echo '</td></tr>';
        }
        echo '</table>';
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.ead-media-upload').click(function(e) {
                    e.preventDefault();
                    const field = $(this).data('field');
                    const custom_uploader = wp.media({
                        title: '<?php esc_html_e( 'Choose Image', 'artpulse-management' ); ?>',
                        button: { text: '<?php esc_html_e( 'Use this image', 'artpulse-management' ); ?>' },
                        multiple: false
                    }).on('select', function() {
                        const attachment = custom_uploader.state().get('selection').first().toJSON();
                        $('#ead_org_mb_' + field).val(attachment.id);
                        $('#ead_org_mb_' + field).siblings('img').attr('src', attachment.url).show();
                    }).open();
                });

                $('.ead-media-remove').click(function(e) {
                    e.preventDefault();
                    const field = $(this).data('field');
                    $('#ead_org_mb_' + field).val('');
                    $('#ead_org_mb_' + field).siblings('img').attr('src', '').hide();
                });
            });
        </script>
        <?php
    }

    public static function render_featured_meta_box( $post ) {
        wp_nonce_field( 'ead_organization_meta_nonce', 'ead_organization_meta_nonce' );

        $featured = ead_get_meta($post->ID, '_ead_featured');
        $priority = ead_get_meta($post->ID, '_ead_featured_priority');

        echo '<p><label><input type="checkbox" name="_ead_featured" value="1" ' . checked($featured, '1', false) . '> ' . esc_html(__('Featured', 'artpulse-management')) . '</label></p>';

        echo '<p><label>' . esc_html(__('Featured Priority', 'artpulse-management')) . '</label>';
        echo '<input type="number" name="_ead_featured_priority" value="' . esc_attr($priority) . '" class="widefat"></p>';
        echo '<p class="description">' . esc_html(__('Lower numbers have higher priority (1 is highest).', 'artpulse-management')) . '</p>';
    }

    /**
     * Saves the meta box data.
     *
     * @param int     $post_id The post ID.
     * @param WP_Post $post    The post object.
     */
    public static function save_meta_boxes( $post_id, $post ) {
        if ( ! isset( $_POST['ead_organization_meta_nonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ead_organization_meta_nonce'] ) ), 'ead_organization_meta_nonce' ) ) {
            return;
        }

        if ( $post->post_type !== 'ead_organization' || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields = self::get_meta_fields();

        foreach ( $fields as $field => $args ) {
            if ( isset( $_POST[ $field ] ) ) {
                $value = $_POST[ $field ];

                switch ( $args['type'] ) {
                    case 'textarea':
                        $sanitized_value = sanitize_textarea_field( wp_unslash( $value ) );
                        break;
                    case 'url':
                        $sanitized_value = esc_url_raw( wp_unslash( $value ) );
                        break;
                    case 'email':
                        $sanitized_value = sanitize_email( wp_unslash( $value ) );
                        break;
                    case 'media':
                        $sanitized_value = absint( wp_unslash( $value ) );
                        break;
                    case 'checkbox':
                        $sanitized_value = isset( $value ) ? '1' : '';  // Store as '1' or ''
                        break;
                    case 'time':
                        $sanitized_value = sanitize_text_field( wp_unslash( $value ) ); // Sanitize time input
                        break;
                    case 'number':
                        $sanitized_value = intval(wp_unslash($value));
                        break;
                    default:
                        $sanitized_value = sanitize_text_field( wp_unslash( $value ) );
                        break;
                }

                update_post_meta( $post_id, $field, $sanitized_value );
                if ( $field === 'ead_org_name' ) {
                    wp_update_post( [ 'ID' => $post_id, 'post_title' => $sanitized_value ] );
                }
            } else {
                // For checkboxes: if not checked, delete meta
                if ( $args['type'] === 'checkbox' ) {
                    delete_post_meta( $post_id, $field ); // Ensure checkboxes are properly cleared
                }
            }
        }

        if ( ! has_post_thumbnail( $post_id ) ) {
            $thumb_id = (int) ead_get_meta( $post_id, 'ead_org_logo_id');
            if ( ! $thumb_id ) {
                $thumb_id = (int) ead_get_meta( $post_id, 'ead_org_banner_id');
            }
            if ( $thumb_id ) {
                set_post_thumbnail( $post_id, $thumb_id );
            }
        }
    }
    public static function is_organisation_featured( $post_id ) {
        return ead_get_meta( $post_id, '_ead_featured');
    }
    public static function get_featured_priority( $post_id ) {
        return ead_get_meta( $post_id, '_ead_featured_priority');
    }
}
