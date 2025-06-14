<?php
/**
 * Provides easy access to plugin settings used across Ajax handlers.
 */

$all_options = get_option( 'artpulse_plugin_settings', [] );

return [
    'geonames_username'     => isset( $all_options['geonames_username'] ) ? sanitize_text_field( $all_options['geonames_username'] ) : '',
    'google_maps_api_key'   => isset( $all_options['google_maps_api_key'] ) ? sanitize_text_field( $all_options['google_maps_api_key'] ) : '',
    'google_places_api_key' => isset( $all_options['google_places_api_key'] ) ? sanitize_text_field( $all_options['google_places_api_key'] ) : '',
    'ajax_rate_limit'       => isset( $all_options['ajax_rate_limit'] ) ? absint( $all_options['ajax_rate_limit'] ) : 5,
    'ajax_rate_window'      => isset( $all_options['ajax_rate_window'] ) ? absint( $all_options['ajax_rate_window'] ) : 60,
];
