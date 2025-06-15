<?php

namespace EAD\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AdminEventForm {
    public static function register() {
        add_action( 'admin_menu', [ self::class, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
        add_action( 'wp_ajax_ead_admin_add_event', [ self::class, 'ajax_handle_event_submission' ] );
    }

    public static function add_admin_menu() {
        add_submenu_page(
            'artpulse-main-menu',
            __( 'Add Event (Admin)', 'artpulse-management' ),
            __( 'Add Event', 'artpulse-management' ),
            'manage_options',
            'ead-admin-add-event',
            [ self::class, 'render_admin_form' ]
        );
    }

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'ead-admin-add-event' ) === false ) {
            return;
        }
        $plugin_url = EAD_PLUGIN_DIR_URL;

        wp_enqueue_style( 'ead-admin-event-css', $plugin_url . 'assets/css/ead-admin-event.css', [], null );
        wp_enqueue_script( 'ead-admin-event-js', $plugin_url . 'assets/js/ead-admin-event.js', [ 'jquery' ], null, true );
        wp_localize_script( 'ead-admin-event-js', 'EADAdminEvent', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'ead_admin_event_nonce' ),
            'processing' => __( 'Saving...', 'artpulse-management' ),
            'success'   => __( 'Event created successfully!', 'artpulse-management' ),
            'error'     => __( 'There was a problem. Please check the fields.', 'artpulse-management' ),
        ] );
    }

    public static function render_admin_form() {
        // Register default event types if none exist
        if ( ! get_terms( [ 'taxonomy' => 'ead_event_type', 'hide_empty' => false ] ) ) {
            self::register_default_event_types();
        }
        $types = get_terms( [ 'taxonomy' => 'ead_event_type', 'hide_empty' => false ] );
        $orgs = get_posts( [
            'post_type'      => 'ead_organization',
            'post_status'    => [ 'publish', 'pending' ],
            'posts_per_page' => - 1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Add New Event (Admin)', 'artpulse-management' ) . '</h1>';
        echo '<div id="ead-admin-event-message" style="margin-bottom:15px;"></div>';
        ?>
        <form id="ead-admin-add-event-form" enctype="multipart/form-data" method="post" novalidate>
            <?php wp_nonce_field( 'ead_admin_event_action', 'ead_admin_event_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th><label for="event_title"><?php esc_html_e( 'Event Title', 'artpulse-management' ); ?> *</label></th>
                    <td><input type="text" name="event_title" id="event_title" required class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="event_type"><?php esc_html_e( 'Event Type', 'artpulse-management' ); ?> *</label></th>
                    <td>
                        <select name="event_type" id="event_type" required>
                            <option value=""><?php esc_html_e( '-- Select Type --', 'artpulse-management' ); ?></option>
                            <?php foreach ( $types as $type_term ) : ?>
                                <option value="<?php echo esc_attr( $type_term->slug ); ?>"><?php echo esc_html( $type_term->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="event_start_date"><?php esc_html_e( 'Start Date', 'artpulse-management' ); ?> *</label></th>
                    <td><input type="date" name="event_start_date" id="event_start_date" required></td>
                </tr>
                <tr>
                    <th><label for="event_end_date"><?php esc_html_e( 'End Date', 'artpulse-management' ); ?> *</label></th>
                    <td><input type="date" name="event_end_date" id="event_end_date" required></td>
                </tr>
                <tr>
                    <th><label for="event_organisation"><?php esc_html_e( 'Organization', 'artpulse-management' ); ?> *</label></th>
                    <td>
                        <select name="event_organisation" id="event_organisation" required>
                            <option value=""><?php esc_html_e( '-- Select Organization --', 'artpulse-management' ); ?></option>
                            <?php foreach ( $orgs as $org ) : ?>
                                <option value="<?php echo esc_attr( $org->ID ); ?>"><?php echo esc_html( $org->post_title . ' (' . ucfirst( $org->post_status ) . ')' ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small><?php esc_html_e( 'If needed, add new organizations from the "Pending Organizations" or "Published Organizations" screens.', 'artpulse-management' ); ?></small>
                    </td>
                </tr>
                <tr>
                    <th><label for="organizer_name"><?php esc_html_e( 'Organizer Name', 'artpulse-management' ); ?> *</label></th>
                    <td><input type="text" name="organizer_name" id="organizer_name" required class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="organizer_email"><?php esc_html_e( 'Organizer Email', 'artpulse-management' ); ?> *</label></th>
                    <td><input type="email" name="organizer_email" id="organizer_email" required class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="venue_name"><?php esc_html_e( 'Venue Name', 'artpulse-management' ); ?></label></th>
                    <td><input type="text" name="venue_name" id="venue_name" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="event_description"><?php esc_html_e( 'Event Description', 'artpulse-management' ); ?></label></th>
                    <td><textarea name="event_description" id="event_description" rows="5" class="large-text"></textarea></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Event Address', 'artpulse-management' ); ?></th>
                    <td>
                        <input type="text" name="event_street_address" placeholder="<?php esc_attr_e( 'Street Address', 'artpulse-management' ); ?>" class="regular-text"><br>
                        <input type="text" name="event_city" placeholder="<?php esc_attr_e( 'City', 'artpulse-management' ); ?>" class="regular-text"><br>
                        <input type="text" name="event_state" placeholder="<?php esc_attr_e( 'State/Province', 'artpulse-management' ); ?>" class="regular-text"><br>
                        <input type="text" name="event_country" placeholder="<?php esc_attr_e( 'Country', 'artpulse-management' ); ?>" class="regular-text"><br>
                        <input type="text" name="event_postcode" placeholder="<?php esc_attr_e( 'Postcode/Zip', 'artpulse-management' ); ?>" class="regular-text"><br>
                        <input type="text" name="event_suburb" placeholder="<?php esc_attr_e( 'Suburb/District', 'artpulse-management' ); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Map Coordinates', 'artpulse-management' ); ?></th>
                    <td>
                        <input type="text" name="event_latitude" placeholder="<?php esc_attr_e( 'Latitude (e.g., 40.7128)', 'artpulse-management' ); ?>" class="regular-text">
                        <input type="text" name="event_longitude" placeholder="<?php esc_attr_e( 'Longitude (e.g., -74.0060)', 'artpulse-management' ); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="event_gallery"><?php esc_html_e( 'Gallery Images', 'artpulse-management' ); ?></label></th>
                    <td>
                        <input type="file" id="event_gallery" name="event_gallery[]" multiple accept="image/jpeg,image/png,image/gif">
                        <small><?php esc_html_e( 'Max 5 files, 2MB each. JPG, PNG, GIF only. First image becomes featured.', 'artpulse-management' ); ?></small>
                        <div id="ead-event-image-preview-area" style="margin-top: 12px; min-height: 60px;"></div>
                    </td>
                </tr>
                <tr>
                    <th><label for="request_featured"><?php esc_html_e( 'Request Featured', 'artpulse-management' ); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="request_featured" id="request_featured" value="1">
                            <?php esc_html_e( 'Request to make this event featured (admin can override)', 'artpulse-management' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <p>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Submit Event', 'artpulse-management' ); ?></button>
            </p>
        </form>
        <?php
        echo '</div>';
    }

    /**
     * Registers default event types if they don't exist.
     */
    public static function register_default_event_types() {
        $event_types = [
            'Exhibition',
            'Workshop',
            'Art Talk',
            'Walkabout',
            'Performance',
            'Art Auction',
        ];
        foreach ( $event_types as $type ) {
            $slug = sanitize_title( $type );
            if ( ! term_exists( $slug, 'ead_event_type' ) ) {
                wp_insert_term( $type, 'ead_event_type', [ 'slug' => $slug ] );
            }
        }
    }

    public static function ajax_handle_event_submission() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'artpulse-management' ) ] );
        }
        check_ajax_referer( 'ead_admin_event_nonce', 'security' );

        // Collect and sanitize posted data
        $fields = [
            'event_title'            => sanitize_text_field( $_POST['event_title'] ?? '' ),
            'event_type'             => sanitize_text_field( $_POST['event_type'] ?? '' ),
            'event_start_date'       => sanitize_text_field( $_POST['event_start_date'] ?? '' ),
            'event_end_date'         => sanitize_text_field( $_POST['event_end_date'] ?? '' ),
            'event_organisation'     => intval( $_POST['event_organisation'] ?? 0 ),
            'organizer_name'         => sanitize_text_field( $_POST['organizer_name'] ?? '' ),
            'organizer_email'        => sanitize_email( $_POST['organizer_email'] ?? '' ),
            'venue_name'             => sanitize_text_field( $_POST['venue_name'] ?? '' ),
            'event_description'      => wp_kses_post( $_POST['event_description'] ?? '' ),
            'event_street_address'   => sanitize_text_field( $_POST['event_street_address'] ?? '' ),
            'event_city'             => sanitize_text_field( $_POST['event_city'] ?? '' ),
            'event_state'            => sanitize_text_field( $_POST['event_state'] ?? '' ),
            'event_country'          => sanitize_text_field( $_POST['event_country'] ?? '' ),
            'event_postcode'         => sanitize_text_field( $_POST['event_postcode'] ?? '' ),
            'event_suburb'           => sanitize_text_field( $_POST['event_suburb'] ?? '' ),
            'event_latitude'         => isset( $_POST['event_latitude'] ) ? floatval( $_POST['event_latitude'] ) : '',
            'event_longitude'        => isset( $_POST['event_longitude'] ) ? floatval( $_POST['event_longitude'] ) : '',
            'request_featured'       => ! empty( $_POST['request_featured'] ) ? '1' : '',
        ];

        // Validate
        $errors = [];
        foreach ( [ 'event_title', 'event_type', 'event_start_date', 'event_end_date', 'event_organisation', 'organizer_name', 'organizer_email' ] as $field ) {
            if ( empty( $fields[ $field ] ) ) {
                $errors[] = ucfirst( str_replace( '_', ' ', $field ) ) . ' ' . __( 'is required.', 'artpulse-management' );
            }
        }
        if ( ! empty( $fields['organizer_email'] ) && ! is_email( $fields['organizer_email'] ) ) {
            $errors[] = __( 'Valid organizer email is required.', 'artpulse-management' );
        }
        if ( strtotime( $fields['event_start_date'] ) > strtotime( $fields['event_end_date'] ) ) {
            $errors[] = __( 'Start date cannot be after end date.', 'artpulse-management' );
        }

        // Gallery file validation
        $allowed_mimes = [ 'image/jpeg', 'image/png', 'image/gif' ];
        $max_file_size = 2 * 1024 * 1024; // 2MB
        $max_files     = 5;
        $file_count = isset( $_FILES['event_gallery']['name'] ) && is_array( $_FILES['event_gallery']['name'] ) ? count( $_FILES['event_gallery']['name'] ) : 0;
        if ( $file_count > $max_files ) {
            $errors[] = sprintf( __( 'You can upload a maximum of %d gallery images.', 'artpulse-management' ), $max_files );
        }
        if ( $file_count ) {
            foreach ( $_FILES['event_gallery']['tmp_name'] as $key => $tmp_name ) {
                if ( ! empty( $tmp_name ) && $_FILES['event_gallery']['error'][ $key ] === UPLOAD_ERR_OK ) {
                    if ( ! in_array( $_FILES['event_gallery']['type'][ $key ], $allowed_mimes ) ) {
                        $errors[] = sprintf( __( 'File "%s" has an invalid type.', 'artpulse-management' ), esc_html( $_FILES['event_gallery']['name'][ $key ] ) );
                        break;
                    }
                    if ( $_FILES['event_gallery']['size'][ $key ] > $max_file_size ) {
                        $errors[] = sprintf( __( 'File "%s" exceeds max size.', 'artpulse-management' ), esc_html( $_FILES['event_gallery']['name'][ $key ] ) );
                        break;
                    }
                }
            }
        }
        if ( $errors ) {
            wp_send_json_error( [ 'message' => implode( ' ', $errors ) ] );
        }

        // Insert post
        $event_id = wp_insert_post( [
            'post_title'   => $fields['event_title'],
            'post_content' => (string) $fields['event_description'],
            'post_type'    => 'ead_event',
            'post_status'  => 'pending',  // admin review by default
            'post_author'  => get_current_user_id(),
        ] );
        if ( ! $event_id || is_wp_error( $event_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Could not save event.', 'artpulse-management' ) ] );
        }

        // Set taxonomy and meta for event type
        if ( ! empty( $fields['event_type'] ) ) {
            $term_result = wp_set_object_terms( $event_id, $fields['event_type'], 'ead_event_type' );
            if ( is_wp_error( $term_result ) ) {
                error_log( '[ArtPulse Admin] Taxonomy error: ' . $term_result->get_error_message() );
            }
            update_post_meta( $event_id, 'event_type', $fields['event_type'] );
        }

        // Meta fields
        $meta_to_save = [
            'event_start_date'         => $fields['event_start_date'],
            'event_end_date'           => $fields['event_end_date'],
            'venue_name'               => $fields['venue_name'],
            'event_organizer_name'     => $fields['organizer_name'],
            'event_organizer_email'    => $fields['organizer_email'],
            '_ead_event_organisation_id' => $fields['event_organisation'],
            'event_street_address'     => $fields['event_street_address'],
            'event_city'               => $fields['event_city'],
            'event_state'              => $fields['event_state'],
            'event_country'            => $fields['event_country'],
            'event_postcode'           => $fields['event_postcode'],
            'event_suburb'             => $fields['event_suburb'],
            'event_latitude'           => $fields['event_latitude'],
            'event_longitude'          => $fields['event_longitude'],
        ];
        foreach ( $meta_to_save as $key => $value ) {
            if ( $value !== '' ) {
                update_post_meta( $event_id, $key, $value );
            } else {
                delete_post_meta( $event_id, $key );
            }
        }
        if ( $fields['request_featured'] ) {
            update_post_meta( $event_id, '_ead_featured_request', '1' );
        } else {
            delete_post_meta( $event_id, '_ead_featured_request' );
        }

        // Handle gallery uploads
        if ( ! empty( $_FILES['event_gallery']['name'][0] ) ) {
            if ( ! function_exists( 'wp_handle_upload' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $gallery_ids = [];
            foreach ( $_FILES['event_gallery']['name'] as $key => $value ) {
                if ( $_FILES['event_gallery']['error'][ $key ] === UPLOAD_ERR_OK && ! empty( $_FILES['event_gallery']['tmp_name'][ $key ] ) ) {
                    $file_array = [
                        'name'     => $_FILES['event_gallery']['name'][ $key ],
                        'type'     => $_FILES['event_gallery']['type'][ $key ],
                        'tmp_name' => $_FILES['event_gallery']['tmp_name'][ $key ],
                        'error'    => 0,
                        'size'     => $_FILES['event_gallery']['size'][ $key ],
                    ];
                    $temp_file_path = wp_tempnam( $file_array['name'] );
                    if ( move_uploaded_file( $file_array['tmp_name'], $temp_file_path ) ) {
                        $file_for_sideload = $file_array;
                        $file_for_sideload['tmp_name'] = $temp_file_path;
                        $attachment_id = media_handle_sideload( $file_for_sideload, $event_id, $fields['event_title'] . ' Gallery Image ' . ( $key + 1 ) );
                        @unlink( $temp_file_path );
                        if ( ! is_wp_error( $attachment_id ) ) {
                            $gallery_ids[] = $attachment_id;
                        }
                    }
                }
            }
            if ( ! empty( $gallery_ids ) ) {
                update_post_meta( $event_id, 'event_gallery', $gallery_ids );
                if ( ! has_post_thumbnail( $event_id ) && isset( $gallery_ids[0] ) ) {
                    set_post_thumbnail( $event_id, $gallery_ids[0] );
                }
            }
        }
        error_log('[EAD Admin] Created event: ' . $event_id . ' | Status: ' . get_post_status($event_id));
        wp_send_json_success( [ 'message' => __( 'Event created successfully!', 'artpulse-management' ), 'event_id' => $event_id ] );
    }
}
