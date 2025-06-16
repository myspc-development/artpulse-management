<?php
namespace EAD\Admin;
use EAD\Admin\SettingsPage;

/**
* Class MetaBoxesAddress
* Handles address-related metabox functionality for EAD post types.
*/
class MetaBoxesAddress {
    // Removed: private static $current_post_type = ''; // Not needed with the new approach

    /**
     * Registers the address metabox for the given post type(s).
     * @param string|array $post_types A single post type slug or an array of post type slugs.
     */
    public static function register($post_types) {
        if (!is_array($post_types)) {
            $post_types = [$post_types]; // Ensure it's an array for iteration
        }

        foreach ($post_types as $single_post_type) {
            // Use a unique hook tag for add_action to avoid issues if register is called multiple times
            // or ensure add_meta_boxes is hooked only once.
            // A simpler way is to hook add_meta_boxes once and check the post type inside.
            // However, for save_post_{$post_type}, iterating is necessary.

            // Add metabox for each specific post type
            add_action('add_meta_boxes_' . $single_post_type, function($post) {
                 // $post_type argument of add_meta_boxes_{$post_type} is the post_type string.
                 // $post is the WP_Post object.
                add_meta_box(
                    'ead_address_' . $post->post_type, // Unique ID per post type if needed, or keep generic
                    __('Address', 'artpulse-management'),
                    [self::class, 'render_address_meta_box'],
                    $post->post_type, // Pass the current post type string
                    'normal',
                    'default'
                );
            });


            // Add save action for each specific post type
            add_action("save_post_{$single_post_type}", [self::class, 'handle_save_meta_box'], 10, 2);
        }

        // AJAX actions can be registered once
        add_action('wp_ajax_ead_load_states', [self::class, 'ajax_load_states']);
        add_action('wp_ajax_nopriv_ead_load_states', [self::class, 'ajax_load_states']); // If needed for frontend
        add_action('wp_ajax_ead_search_cities', [self::class, 'ajax_search_cities']);
        add_action('wp_ajax_nopriv_ead_search_cities', [self::class, 'ajax_search_cities']); // If needed for frontend
    }

    // get_geonames_username remains the same

    public static function render_address_meta_box($post) { // $post is WP_Post object
        wp_nonce_field('ead_save_address', 'ead_address_nonce');

        // Retrieve meta values. Ensure they are strings.
        $country_raw = ead_get_meta($post->ID, 'ead_country');
        $state_raw = ead_get_meta($post->ID, 'ead_state');
        $city_raw = ead_get_meta($post->ID, 'ead_city');
        $street_address_raw = ead_get_meta($post->ID, 'ead_street_address');

        // Defensive check: if a meta value is an array, take the first element or an empty string.
        // This handles cases where data might have been improperly saved as an array previously.
        $country = is_array($country_raw) ? ($country_raw[0] ?? '') : $country_raw;
        $state = is_array($state_raw) ? ($state_raw[0] ?? '') : $state_raw;
        $city = is_array($city_raw) ? ($city_raw[0] ?? '') : $city_raw;
        $street_address = is_array($street_address_raw) ? ($street_address_raw[0] ?? '') : $street_address_raw;

        echo '<p><label for="ead_country"><strong>' . esc_html__('Country', 'artpulse-management') . ':</strong></label><br>';
        echo '<select id="ead_country" name="ead_country" style="width:100%;" autocomplete="off">';
        // Line 27 (Original problematic line)
        echo $country ? '<option value="' . esc_attr($country) . '" selected>' . esc_html($country) . '</option>' : '';
        echo '</select></p>';

        echo '<p><label for="ead_state"><strong>' . esc_html__('State/Province', 'artpulse-management') . ':</strong></label><br>';
        echo '<select id="ead_state" name="ead_state" style="width:100%;"' . (empty($country) ? ' disabled' : '') . ' autocomplete="off">';
        echo $state ? '<option value="' . esc_attr($state) . '" selected>' . esc_html($state) . '</option>' : '';
        echo '</select></p>';

        echo '<p><label for="ead_city"><strong>' . esc_html__('City', 'artpulse-management') . ':</strong></label><br>';
        echo '<select id="ead_city" name="ead_city" style="width:100%;"' . (empty($state) ? ' disabled' : '') . ' autocomplete="off">';
        echo $city ? '<option value="' . esc_attr($city) . '" selected>' . esc_html($city) . '</option>' : '';
        echo '</select></p>';

        echo '<p><label for="ead_street_address"><strong>' . esc_html__('Street Address', 'artpulse-management') . ':</strong></label><br>';
        echo '<input type="text" id="ead_street_address" name="ead_street_address" value="' . esc_attr($street_address) . '" style="width:100%;" /></p>';

        // Call enqueue scripts. It's better to hook this to admin_enqueue_scripts conditionally.
        // self::enqueue_admin_scripts(); // See note below
        // Instead, hook enqueue_admin_scripts properly:
        add_action('admin_enqueue_scripts', [self::class, 'conditionally_enqueue_admin_scripts']);
    }

    /**
     * Conditionally enqueue scripts for the address metabox.
     * Hooked to admin_enqueue_scripts.
     */
    public static function conditionally_enqueue_admin_scripts($hook_suffix) {
        // Determine if we are on a relevant post edit screen
        $screen = get_current_screen();
        // Get the post types this metabox is registered for (you'll need to store this or pass it)
        // For now, let's assume it's for 'ead_organization' and 'ead_event' as per your main plugin
        $registered_post_types = ['ead_organization', 'ead_event']; // This should ideally be dynamic if register() is called with different types

        if (in_array($hook_suffix, ['post.php', 'post-new.php']) && $screen && in_array($screen->post_type, $registered_post_types)) {
            self::enqueue_admin_scripts_content();
        }
    }

    /**
     * Actual script enqueueing logic.
     */
    private static function enqueue_admin_scripts_content() { // Renamed to avoid conflict
        if (wp_script_is('ead-address', 'enqueued')) { // Prevent double enqueueing
            return;
        }
        // Path to artpulse-management.php (main plugin file)
        // Assuming this MetaBoxesAddress.php is in wp-content/plugins/artpulse-management/src/Admin/
        // Compute path to the main plugin file. This file lives two directories
        // above this admin class within the plugin root.
        $main_plugin_file = dirname(__DIR__, 2) . '/artpulse-management.php';

        // Use bundled Select2 assets instead of CDN
        $select2_css = plugins_url('assets/select2/css/select2.min.css', $main_plugin_file);
        $select2_js  = plugins_url('assets/select2/js/select2.min.js', $main_plugin_file);
        wp_enqueue_style('select2', $select2_css, [], '4.1.0');
        wp_enqueue_script('select2', $select2_js, ['jquery'], '4.1.0', true);

        $assets_url_js = plugins_url('assets/js/ead-address.js', $main_plugin_file);
        $countries_json_url = plugins_url('data/countries.json', $main_plugin_file);

        wp_enqueue_script('ead-address', $assets_url_js, ['jquery', 'select2'], EAD_PLUGIN_VERSION, true); // Use plugin version
        wp_localize_script('ead-address', 'eadAddress', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'statesNonce' => wp_create_nonce('ead_load_states'),
            'citiesNonce' => wp_create_nonce('ead_search_cities'),
            'countriesJson' => $countries_json_url,
            'text' => [ // Add translatable text here
                'loadingStates' => esc_html__('Loading states...', 'artpulse-management'),
                'loadingCities' => esc_html__('Loading cities...', 'artpulse-management'),
                'selectCountry' => esc_html__('Select a country', 'artpulse-management'),
                'selectState'   => esc_html__('Select a state/province', 'artpulse-management'),
                'selectCity'    => esc_html__('Type to search for a city', 'artpulse-management'),
            ]
        ]);
    }


    public static function handle_save_meta_box($post_id, $post_object) { // $post is passed, use it for post_type check if needed
        // Nonce check
        if (!isset($_POST['ead_address_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ead_address_nonce'])), 'ead_save_address')) {
            return;
        }

        // Check user capabilities
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Don't save if autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check post type if you want to be extra sure, though save_post_{$post_type} should handle this.
        // $allowed_post_types = ['ead_organization', 'ead_event']; // Example
        // if (!in_array($post_object->post_type, $allowed_post_types)) {
        //    return;
        // }

        $fields = ['ead_country', 'ead_state', 'ead_city', 'ead_street_address'];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                // Ensure we are saving a string. wp_unslash is important.
                $value = sanitize_text_field(wp_unslash($_POST[$field]));
                update_post_meta($post_id, $field, $value);
            } else {
                // If you want to clear the meta if the field is not sent (e.g., an unchecked checkbox)
                // For text/select fields, they are usually sent empty if not filled.
                // delete_post_meta($post_id, $field); // Be cautious with this for text inputs.
                                                    // An empty string might be a valid value.
                                                    // If an empty string is submitted, it will be saved.
                                                    // This 'else' might only be for checkboxes or similar.
            }
        }
    }

    // add_admin_notice, log_api_failure, ajax_load_states, get_states_for_country, ajax_search_cities, get_cities_for_state
    // remain largely the same, but ensure they are correctly implemented and paths are robust.
    // ... (rest of your AJAX handlers and helper methods) ...
    // Ensure file paths in get_states_for_country and get_cities_for_state are correct:
    // plugin_dir_path(__FILE__) will be src/Admin/
    // So ../../data/ will be plugins/your-plugin/data/
    private static function get_geonames_username() {
        $username = SettingsPage::get_setting('geonames_username');
        // Removed the add_admin_notice from here as it's not directly related to rendering the metabox itself
        // and might fire too often. Consider placing such notices on the settings page.
        return $username ?: 'demo'; // Return demo if not set, to avoid API errors if GeoNames is used.
    }

    private static function add_admin_notice($message, $type = 'error') {
        add_action('admin_notices', function() use ($message, $type) {
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible">';
            echo '<p>' . esc_html($message) . '</p>';
            echo '</div>';
        });
    }
    private static function log_api_failure($context, $error_message) {
        error_log("[EAD Address API Failure] Context: {$context} - Error: {$error_message}");
    }

    public static function ajax_load_states() {
        check_ajax_referer('ead_load_states', 'security'); // 'security' is the default query arg name
        $country_code = isset($_GET['country_code']) ? sanitize_text_field(wp_unslash($_GET['country_code'])) : ''; // Assuming you send country code
        if (empty($country_code)) {
            wp_send_json_error(['message' => 'Country code is required.']);
            return;
        }
        $states = self::get_states_for_country($country_code);
        $results = [];
        foreach($states as $state_code => $state_name) { // Assuming states.json is "US": {"AL": "Alabama", ...}
            $results[] = ['id' => $state_code, 'text' => $state_name];
        }
        wp_send_json_success(['results' => $results]);
    }

    private static function get_states_for_country($country_code) {
        $cache_key = 'ead_states_' . $country_code;
        $cached_states = get_transient($cache_key);
        if ($cached_states !== false) {
            return $cached_states;
        }

        // Path to main plugin file within plugin root.
        $main_plugin_file = dirname(__DIR__, 2) . '/artpulse-management.php';
        $file_path = dirname($main_plugin_file) . '/data/states.json'; // Correct path to data/states.json

        if (!file_exists($file_path)) {
            self::log_api_failure('States', 'states.json not found at ' . $file_path);
            return [];
        }
        $json_content = file_get_contents($file_path);
        $all_states_data = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($all_states_data)) {
            self::log_api_failure('States', 'Malformed JSON in states.json or not an array. Error: ' . json_last_error_msg());
            return [];
        }

        $states_for_country = isset($all_states_data[$country_code]) ? $all_states_data[$country_code] : [];
        set_transient($cache_key, $states_for_country, DAY_IN_SECONDS); // Cache for 1 day
        return $states_for_country;
    }

    public static function ajax_search_cities() {
        check_ajax_referer('ead_search_cities', 'security');
        $country_code = isset($_GET['country_code']) ? sanitize_text_field(wp_unslash($_GET['country_code'])) : '';
        $state_code = isset($_GET['state_code']) ? sanitize_text_field(wp_unslash($_GET['state_code'])) : '';
        $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';

        if (empty($country_code) || empty($state_code) || empty($term)) {
            wp_send_json_error(['message' => 'Country, state, and search term are required.']);
            return;
        }

        // For GeoNames API (Example - you might be using local JSON)
        // $username = self::get_geonames_username();
        // $api_url = "http://api.geonames.org/searchJSON?country={$country_code}&adminCode1={$state_code}&name_startsWith=" . urlencode($term) . "&maxRows=10&featureClass=P&username={$username}";
        // $response = wp_remote_get($api_url);
        // ... handle response ... (This is just an example if you were to switch to API)

        // Using local cities.json (as per your original code)
        $cities = self::get_cities_from_json($country_code, $state_code, $term);
        $results = array_map(fn($city_name) => ['id' => $city_name, 'text' => $city_name], $cities);
        wp_send_json_success(['results' => $results]);
    }

    private static function get_cities_from_json($country_code, $state_code, $term) {
        // Assuming cities.json is structured like: { "US": { "CA": ["Los Angeles", "San Francisco"], ... } }
        // Or perhaps simpler: { "US-CA": ["Los Angeles", ...], "US-AL": [...] }
        // Your current get_cities_for_state implies a simpler structure: { "STATE_CODE_OR_NAME": ["City1", "City2"] }
        // Let's adapt to that simpler structure for now.

        // Locate the plugin root to access bundled JSON data.
        $main_plugin_file = dirname(__DIR__, 2) . '/artpulse-management.php';
        $file_path = dirname($main_plugin_file) . '/data/cities.json';

        if (!file_exists($file_path)) {
            self::log_api_failure('Cities', 'cities.json not found at ' . $file_path);
            return [];
        }
        $json_content = file_get_contents($file_path);
        $all_cities_data = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($all_cities_data)) {
            self::log_api_failure('Cities', 'Malformed JSON in cities.json. Error: ' . json_last_error_msg());
            return [];
        }

        // Assuming $state_code is the key in your cities.json for the list of cities
        $cities_in_state = isset($all_cities_data[$state_code]) ? $all_cities_data[$state_code] : [];
        if (!is_array($cities_in_state)) return []; // Ensure it's an array

        $filtered_cities = [];
        if (!empty($term)) {
            foreach ($cities_in_state as $city_name) {
                if (stripos($city_name, $term) !== false) {
                    $filtered_cities[] = $city_name;
                }
            }
        } else {
            $filtered_cities = $cities_in_state; // Return all if no term
        }
        return array_slice($filtered_cities, 0, 20); // Limit results
    }
}
