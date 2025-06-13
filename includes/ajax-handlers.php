<?php
// File: includes/ajax-handlers.php

// --- State/City AJAX ---
add_action('wp_ajax_ead_load_states', 'ead_load_states_handler');
add_action('wp_ajax_nopriv_ead_load_states', 'ead_load_states_handler');
add_action('wp_ajax_ead_search_cities', 'ead_search_cities_handler');
add_action('wp_ajax_nopriv_ead_search_cities', 'ead_search_cities_handler');

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

function ead_load_states_handler() {
    if ( ! ead_check_rate_limit( 'ead_load_states' ) ) {
        wp_send_json_error( [ 'error' => 'Too many requests.' ], 429 );
    }
    if (!isset($_GET['security']) || !wp_verify_nonce($_GET['security'], 'ead_load_states')) {
        wp_send_json_error(['error' => 'Invalid security token.']);
    }

    $country = sanitize_text_field($_GET['country_code'] ?? '');
    if (!$country) {
        wp_send_json_error(['error' => 'Missing country code.']);
    }

    $states_file = plugin_dir_path(__FILE__) . '../data/states.json';
    $states_data = json_decode(file_get_contents($states_file), true);

    if (isset($states_data[$country])) {
        wp_send_json_success($states_data[$country]);
    }

    $settings = include(plugin_dir_path(__FILE__) . '../includes/settings.php');
    $geonames_username = $settings['geonames_username'] ?? '';

    if (!$geonames_username) {
        wp_send_json_error(['error' => 'GeoNames username not configured.']);
    }

    $api_url = "http://api.geonames.org/childrenJSON?geonameId={$country}&username={$geonames_username}";
    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        wp_send_json_error(['error' => 'Failed to fetch states from GeoNames.']);
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    $states = [];

    if (!empty($data['geonames'])) {
        foreach ($data['geonames'] as $item) {
            $states[] = [
                'code' => $item['adminCode1'] ?? '',
                'name' => $item['name'] ?? ''
            ];
        }
        $states_data[$country] = $states;
        file_put_contents($states_file, json_encode($states_data, JSON_PRETTY_PRINT));
        wp_send_json_success($states);
    } else {
        wp_send_json_error(['error' => 'No states found.']);
    }
}

function ead_search_cities_handler() {
    if ( ! ead_check_rate_limit( 'ead_search_cities' ) ) {
        wp_send_json_error( [ 'error' => 'Too many requests.' ], 429 );
    }
    if (!isset($_GET['security']) || !wp_verify_nonce($_GET['security'], 'ead_search_cities')) {
        wp_send_json_error(['error' => 'Invalid security token.']);
    }

    $country    = sanitize_text_field($_GET['country_code'] ?? '');
    $state      = sanitize_text_field($_GET['state_code'] ?? '');
    $term       = sanitize_text_field($_GET['term'] ?? '');
    $use_cache  = filter_var($_GET['use_cache'] ?? true, FILTER_VALIDATE_BOOLEAN);

    if (!$country || !$state) {
        wp_send_json_error(['error' => 'Missing country or state.']);
    }

    $cities_file = plugin_dir_path(__FILE__) . '../data/cities.json';
    $cities_data = json_decode(file_get_contents($cities_file), true) ?: [];
    $cache_key = "{$country}-{$state}";

    if ( $use_cache && isset( $cities_data[ $cache_key ] ) ) {
        $cached = $cities_data[ $cache_key ];
        $results = [];
        foreach ( $cached as $city ) {
            $name = is_array( $city ) ? ( $city['name'] ?? '' ) : $city;
            if ( $name === '' ) {
                continue;
            }
            if ( $term && stripos( $name, $term ) === false ) {
                continue;
            }
            $results[] = is_array( $city ) ? $city : [ 'name' => $name ];
        }
        wp_send_json_success( $results );
    }

    $settings = include(plugin_dir_path(__FILE__) . '../includes/settings.php');
    $geonames_username = $settings['geonames_username'] ?? '';

    if (!$geonames_username) {
        wp_send_json_error(['error' => 'GeoNames username not configured.']);
    }

    $api_url = "http://api.geonames.org/searchJSON?country={$country}&adminCode1={$state}&featureClass=P&maxRows=1000&username={$geonames_username}";
    if (!empty($term)) {
        $api_url .= "&name_startsWith=" . urlencode($term);
    }

    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        wp_send_json_error(['error' => 'Failed to fetch cities from GeoNames.']);
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    $cities = [];

    if (!empty($data['geonames'])) {
        foreach ($data['geonames'] as $item) {
            $cities[] = [
                'name' => $item['name'] ?? ''
            ];
        }
        $cities_data[$cache_key] = $cities;
        file_put_contents($cities_file, json_encode($cities_data, JSON_PRETTY_PRINT));
        wp_send_json_success($cities);
    } else {
        wp_send_json_error(['error' => 'No cities found.']);
    }
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
