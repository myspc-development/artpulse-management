<?php
// File: includes/ajax-handlers.php

// --- State/City AJAX ---
// The address metabox class registers the handlers for loading states and
// searching cities. These hooks used to live here but were duplicated in that
// class, so the implementations and action registrations now reside in
// `EAD\Admin\MetaBoxesAddress`.

function ead_check_rate_limit( $action ) {
    $settings = include plugin_dir_path( __FILE__ ) . 'settings.php';
    $limit    = isset( $settings['ajax_rate_limit'] ) ? (int) $settings['ajax_rate_limit'] : 5;
    $window   = isset( $settings['ajax_rate_window'] ) ? (int) $settings['ajax_rate_window'] : 60;

    $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
    $key = 'ead_rl_' . md5( $action . $ip );
    $count = (int) get_transient( $key );
    if ( $count >= $limit ) {
        return false;
    }

    set_transient( $key, $count + 1, $window );

    return true;
}


// --- RSVP AJAX: save RSVP as custom post type ---
add_action( 'wp_ajax_ead_event_rsvp', 'ead_event_rsvp_ajax' );
add_action( 'wp_ajax_nopriv_ead_event_rsvp', 'ead_event_rsvp_ajax' );

// Dashboard AJAX handler for manually adding RSVPs
add_action( 'wp_ajax_ead_admin_add_rsvp', 'ead_admin_add_rsvp_ajax' );

function ead_event_rsvp_ajax() {
    if ( ! ead_check_rate_limit( 'ead_event_rsvp' ) ) {
        wp_send_json_error( [ 'message' => 'Too many requests.' ], 429 );
    }
    if (
        ! isset( $_POST['ead_event_rsvp_nonce'] ) ||
        ! wp_verify_nonce( $_POST['ead_event_rsvp_nonce'], 'ead_event_rsvp' )
    ) {
        wp_send_json_error( [ 'message' => 'Invalid security token.' ] );
    }

    $email    = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
    $event_id = isset( $_POST['event_id'] ) ? intval( $_POST['event_id'] ) : 0;

    if ( empty( $email ) || ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => 'Please enter a valid email address.' ] );
    }
    if ( ! $event_id || get_post_type( $event_id ) !== 'ead_event' ) {
        wp_send_json_error( [ 'message' => 'Event not found.' ] );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ead_rsvps';

    $inserted = $wpdb->insert(
        $table,
        [
            'event_id'   => $event_id,
            'rsvp_email' => $email,
            'rsvp_date'  => current_time( 'mysql' ),
        ],
        [ '%d', '%s', '%s' ]
    );

    if ( $inserted ) {
        wp_send_json_success( [ 'message' => 'Thank you! Your RSVP has been received.' ] );
    } else {
        wp_send_json_error( [ 'message' => 'Sorry, there was a problem saving your RSVP.' ] );
    }
}

function ead_admin_add_rsvp_ajax() {
    if ( ! current_user_can( 'ead_manage_rsvps' ) ) {
        wp_send_json_error( [ 'message' => __( 'You do not have permission to add RSVPs.', 'artpulse-management' ) ], 403 );
    }
    if ( ! isset( $_POST['ead_event_rsvp_nonce'] ) || ! wp_verify_nonce( $_POST['ead_event_rsvp_nonce'], 'ead_event_rsvp' ) ) {
        wp_send_json_error( [ 'message' => __( 'Invalid security token.', 'artpulse-management' ) ] );
    }

    $email    = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
    $event_id = isset( $_POST['event_id'] ) ? intval( $_POST['event_id'] ) : 0;

    if ( empty( $email ) || ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => __( 'Please enter a valid email address.', 'artpulse-management' ) ] );
    }
    if ( ! $event_id || get_post_type( $event_id ) !== 'ead_event' ) {
        wp_send_json_error( [ 'message' => __( 'Event not found.', 'artpulse-management' ) ] );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ead_rsvps';

    $inserted = $wpdb->insert(
        $table,
        [
            'event_id'   => $event_id,
            'rsvp_email' => $email,
            'rsvp_date'  => current_time( 'mysql' ),
        ],
        [ '%d', '%s', '%s' ]
    );

    if ( $inserted ) {
        wp_send_json_success( [ 'message' => __( 'RSVP saved.', 'artpulse-management' ) ] );
    } else {
        wp_send_json_error( [ 'message' => __( 'Sorry, there was a problem saving your RSVP.', 'artpulse-management' ) ] );
    }
}

// --- RSVP List AJAX for Author Dashboard ---
add_action('wp_ajax_ead_get_my_rsvps', 'ead_get_my_rsvps_ajax');

function ead_get_my_rsvps_ajax() {
    if ( ! ead_check_rate_limit( 'ead_get_my_rsvps' ) ) {
        wp_send_json_error( [ 'html' => '<p>Too many requests.</p>' ], 429 );
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'html' => '<p>You must be logged in.</p>' ] );
    }

    $user_id = get_current_user_id();

    // Get all events authored by this user
    $events = get_posts( [
        'post_type'      => 'ead_event',
        'author'         => $user_id,
        'posts_per_page' => - 1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ] );
    if ( ! $events ) {
        wp_send_json_success( [ 'html' => '<p>You have not created any events yet.</p>' ] );
    }

    global $wpdb;
    $table      = $wpdb->prefix . 'ead_rsvps';
    $placeholders = implode( ',', array_fill( 0, count( $events ), '%d' ) );
    $query        = $wpdb->prepare( "SELECT * FROM {$table} WHERE event_id IN ($placeholders) ORDER BY rsvp_date DESC", $events );
    $rsvps        = $wpdb->get_results( $query );

    if ( ! $rsvps ) {
        wp_send_json_success( [ 'html' => '<p>No RSVPs yet for your events.</p>' ] );
    }

    ob_start();
    ?>
    <table>
        <thead><tr><th>Event</th><th>Email</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ( $rsvps as $rsvp ) :
            $event_title = get_the_title( (int) $rsvp->event_id );
        ?>
            <tr>
                <td><a href="<?php echo esc_url( get_permalink( $rsvp->event_id ) ); ?>"><?php echo esc_html( $event_title ); ?></a></td>
                <td><?php echo esc_html( $rsvp->rsvp_email ); ?></td>
                <td><?php echo esc_html( $rsvp->rsvp_date ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    $html = ob_get_clean();

    wp_send_json_success( [ 'html' => $html ] );
}
