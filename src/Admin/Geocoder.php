<?php
namespace EAD\Admin;

/**
 * Geocoding Support.
 *
 * Automatically fetch lat/lng from event and organization addresses.
 *
 * @package EventArtDirectory
 * @subpackage Admin
 */
class Geocoder {

    public static function register() {
        add_action('save_post_ead_event', [self::class, 'maybe_geocode_event'], 10, 3);
        add_action('save_post_ead_organization', [self::class, 'maybe_geocode_organization'], 10, 3);
    }

    public static function maybe_geocode_event($post_id, $post, $update) {
        if (ead_get_meta($post_id, 'event_lat') && ead_get_meta($post_id, 'event_lng')) {
            return;
        }
        if (ead_get_meta($post_id, '_ead_geocode_manual')) return;

        $address = ead_get_meta($post_id, 'event_address');
        if (!$address) return;

        $coords = self::geocode_address($address);
        if ($coords) {
            update_post_meta($post_id, 'event_lat', $coords['lat']);
            update_post_meta($post_id, 'event_lng', $coords['lng']);
        }
    }

    public static function maybe_geocode_organization($post_id, $post, $update) {
        if (ead_get_meta($post_id, 'org_lat') && ead_get_meta($post_id, 'org_lng')) {
            return;
        }
        if (ead_get_meta($post_id, '_ead_geocode_manual')) return;

        $address = ead_get_meta($post_id, 'org_address');
        if (!$address) return;

        $coords = self::geocode_address($address);
        if ($coords) {
            update_post_meta($post_id, 'org_lat', $coords['lat']);
            update_post_meta($post_id, 'org_lng', $coords['lng']);
        }
    }

    public static function geocode_address($address) {
        $api_key = SettingsPage::get_setting('google_maps_api_key');

        if ($api_key) {
            $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . $api_key;
            $response = wp_remote_get($url);
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                if (isset($data['results'][0]['geometry']['location'])) {
                    return [
                        'lat' => $data['results'][0]['geometry']['location']['lat'],
                        'lng' => $data['results'][0]['geometry']['location']['lng']
                    ];
                }
            }
        }

        // Fallback: OpenStreetMap Nominatim
        $url = 'https://nominatim.openstreetmap.org/search?format=json&q=' . urlencode($address);
        $response = wp_remote_get($url);
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if (!empty($data[0]['lat']) && !empty($data[0]['lon'])) {
                return [
                    'lat' => $data[0]['lat'],
                    'lng' => $data[0]['lon']
                ];
            }
        }

        return false;
    }
}
