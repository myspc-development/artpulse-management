<?php
namespace EAD\Shortcodes;

use EAD\Shortcodes\HoneypotTrait;

class SubmitEventForm {
    use HoneypotTrait;
    public static function register() {
        add_shortcode('ead_submit_event_form', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets_if_shortcode_present']);
    }

    public static function enqueue_assets_if_shortcode_present() {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'ead_submit_event_form')) {
            self::enqueue_assets();
        }
    }

    public static function enqueue_assets() {
        $plugin_url = EAD_PLUGIN_DIR_URL;

        // Get plugin settings for API keys/features
        $plugin_settings    = get_option('artpulse_plugin_settings', []);
        $gmaps_api_key      = isset($plugin_settings['google_maps_api_key']) ? $plugin_settings['google_maps_api_key'] : '';
        $gmaps_places_enabled = !empty($plugin_settings['enable_google_places_api']);
        $geonames_enabled     = !empty($plugin_settings['enable_geonames_api']);

        // Core styles/scripts
        // Correct path to the submit event stylesheet
        wp_enqueue_style('ead-submit-event-css', $plugin_url . 'assets/css/ead-submit-event.css', [], defined('EAD_PLUGIN_VERSION') ? EAD_PLUGIN_VERSION : '1.0.0');
        // Needed for the WordPress media modal
        wp_enqueue_media();
        wp_enqueue_style('select2', $plugin_url . 'assets/select2/css/select2.min.css');
        wp_enqueue_script('select2', $plugin_url . 'assets/select2/js/select2.min.js', ['jquery'], null, true);

        // Address loader (shared with org registration)
        wp_enqueue_script('ead-address', $plugin_url . 'assets/js/ead-address.js', ['jquery', 'select2'], defined('EAD_PLUGIN_VERSION') ? EAD_PLUGIN_VERSION : '1.0.0', true);

        // Main event submission JS
        wp_enqueue_script('ead-submit-event', $plugin_url . 'assets/js/ead-submit-event.js', ['jquery', 'ead-address'], defined('EAD_PLUGIN_VERSION') ? EAD_PLUGIN_VERSION : '1.0.0', true);

        // Localize address settings
        wp_localize_script('ead-address', 'eadAddress', [
            'countriesJson' => $plugin_url . 'data/countries.json',
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'statesNonce'  => wp_create_nonce('ead_load_states'),
            'citiesNonce'  => wp_create_nonce('ead_search_cities'),
            'gmapsApiKey'  => $gmaps_api_key,
            'gmapsPlacesEnabled' => $gmaps_places_enabled,
            'geonamesEnabled' => $geonames_enabled,
        ]);

        // Localize REST info for submission
        wp_localize_script('ead-submit-event', 'eadSubmitEvent', [
            'restUrl'   => esc_url_raw(rest_url('artpulse/v1/events/submit')),
            'restNonce' => wp_create_nonce('wp_rest'),
        ]);

        wp_localize_script(
            'ead-submit-event',
            'eadEventGallery',
            [
                'select_image_title' => __( 'Select or Upload Image', 'artpulse-management' ),
                'use_image_button'   => __( 'Use this image', 'artpulse-management' ),
                'placeholder_prefix' => __( 'Image ', 'artpulse-management' ),
            ]
        );

        // Optionally enqueue Google Maps/Places if enabled
        if ($gmaps_places_enabled && $gmaps_api_key) {
            wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . $gmaps_api_key . '&libraries=places', [], null, true);
        }
    }

    public static function render($atts, $content = null) {
        if (!is_user_logged_in()) {
            return '<div class="ead-notice ead-login-required">You must be logged in to submit an event.</div>';
        }

        if (!current_user_can('manage_events')) {
            return '<div class="ead-notice ead-permission-denied">You do not have permission to submit events.</div>';
        }

        $event_types = get_terms([
            'taxonomy'   => 'ead_event_type',
            'hide_empty' => false,
        ]);

        ob_start();
        ?>
        <div class="ead-dashboard-card ead-submit-event-wrapper">
        <form id="ead-submit-event-form" enctype="multipart/form-data" autocomplete="off">
            <div id="ead-submit-event-message" style="display:none;"></div>
            <label for="ead_event_title">Event Title*</label>
            <input type="text" id="ead_event_title" name="title" required />

            <label for="ead_event_description">Description*</label>
            <textarea id="ead_event_description" name="description" required></textarea>

            <label for="ead_event_type">Event Type*</label>
            <select id="ead_event_type" name="event_type" required>
                <option value=""><?php esc_html_e('-- Select Type --', 'artpulse-management'); ?></option>
                <?php foreach ($event_types as $type) : ?>
                    <option value="<?php echo esc_attr($type->term_id); ?>"><?php echo esc_html($type->name); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="ead_event_start_date">Start Date*</label>
            <input type="date" id="ead_event_start_date" name="event_start_date" required />

            <label for="ead_event_end_date">End Date*</label>
            <input type="date" id="ead_event_end_date" name="event_end_date" required />

            <label for="ead_venue_name">Venue Name</label>
            <input type="text" id="ead_venue_name" name="venue_name" />

            <!-- Unified Address Block (geo/mapping features auto-enabled via JS) -->
            <div class="ead-address-fields">
                <label><strong><?php _e('Event Address', 'artpulse-management'); ?></strong></label>
                <select id="ead_country" name="event_country" required></select>
                <select id="ead_state" name="event_state" disabled required></select>
                <select id="ead_city" name="event_city" disabled required></select>
                <input type="text" id="ead_suburb" name="event_suburb" placeholder="<?php esc_attr_e('Suburb/District', 'artpulse-management'); ?>" />
                <input type="text" id="ead_street" name="event_street_address" placeholder="<?php esc_attr_e('Street Address', 'artpulse-management'); ?>" />
                <input type="text" id="ead_postcode" name="event_postcode" placeholder="<?php esc_attr_e('Postcode', 'artpulse-management'); ?>" />
                <input type="hidden" id="ead_latitude" name="latitude" />
                <input type="hidden" id="ead_longitude" name="longitude" />
                <div class="ead-map-controls">
                    <button type="button" id="ead-geolocate-btn"><?php _e('Use My Location', 'artpulse-management'); ?></button>
                </div>
                <div id="ead-map" style="width:100%;height:260px;margin-top:1rem;display:none;"></div>
            </div>

            <label for="ead_event_organizer">Organizer</label>
            <input type="text" id="ead_event_organizer" name="organizer" />

            <label for="ead_event_organizer_email">Organizer Email</label>
            <input type="email" id="ead_event_organizer_email" name="organizer_email" />

            <label><?php esc_html_e( 'Gallery Images (max 5, 2MB each)', 'artpulse-management' ); ?></label>
            <div class="ead-event-image-upload-area">
                <?php
                $max_images = 5;
                for ( $i = 0; $i < $max_images; $i++ ) :
                    ?>
                    <div class="ead-image-upload-container" data-image-index="<?php echo $i; ?>">
                        <div class="ead-image-preview"><span class="placeholder"><?php printf( esc_html__( 'Image %d', 'artpulse-management' ), $i + 1 ); ?></span></div>
                        <input type="hidden" name="event_gallery_images[]" class="ead-image-id-input" value="">
                        <button type="button" class="button ead-upload-image-button"><?php esc_html_e( 'Select Image', 'artpulse-management' ); ?></button>
                        <button type="button" class="button ead-remove-image-button hidden"><?php esc_html_e( 'Remove Image', 'artpulse-management' ); ?></button>
                    </div>
                <?php endfor; ?>
            </div>

            <label class="ead-featured-request">
                <input type="checkbox" id="ead_event_featured" name="event_featured" value="1" />
                <?php esc_html_e('Request Featured Listing', 'artpulse-management'); ?>
            </label>

            <?php echo self::render_honeypot( $atts ); ?>
            <button type="submit" id="ead-submit-event-button">Submit Event</button>
            <span id="ead-submit-event-loading" style="display:none;">Submitting...</span>
        </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
