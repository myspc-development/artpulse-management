<?php
namespace EAD\Shortcodes;

use EAD\Shortcodes\HoneypotTrait;

class OrganizationRegistrationForm {
    use HoneypotTrait;

    /**
     * Register shortcode and asset hooks.
     */
    public static function register() {
        add_shortcode('ead_organization_registration_form', [self::class, 'render_form']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    /**
     * Enqueue CSS/JS for the registration form.
     */
    public static function enqueue_assets() {
        $plugin_url = EAD_PLUGIN_DIR_URL;
        $version    = defined('EAD_PLUGIN_VERSION') ? EAD_PLUGIN_VERSION : '1.0.0';

        wp_enqueue_style(
            'ead-organization-registration',
            $plugin_url . 'assets/css/organization-registration.css',
            [],
            $version
        );

        // Needed for the WordPress media modal
        wp_enqueue_media();

        wp_enqueue_script(
            'ead-organization-gallery',
            $plugin_url . 'assets/js/organization-gallery.js',
            [ 'jquery' ],
            $version,
            true
        );

        wp_localize_script(
            'ead-organization-gallery',
            'eadOrgGallery',
            [
                'select_image_title'  => __( 'Select or Upload Image', 'artpulse-management' ),
                'use_image_button'    => __( 'Use this image', 'artpulse-management' ),
                'placeholder_prefix'  => __( 'Image ', 'artpulse-management' ),
            ]
        );

        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);

        wp_enqueue_script(
            'ead-address',
            $plugin_url . 'assets/js/ead-address.js',
            [ 'jquery', 'select2' ],
            $version,
            true
        );

        $settings = get_option('artpulse_plugin_settings', []);
        $gmaps_api_key = isset($settings['google_maps_api_key']) ? $settings['google_maps_api_key'] : '';
        $gmaps_places_enabled = !empty($settings['enable_google_places_api']);
        $geonames_enabled = !empty($settings['enable_geonames_api']);

        wp_localize_script(
            'ead-address',
            'eadAddress',
            [
                'countriesJson'    => $plugin_url . 'data/countries.json',
                'ajaxUrl'          => admin_url('admin-ajax.php'),
                'statesNonce'      => wp_create_nonce('ead_load_states'),
                'citiesNonce'      => wp_create_nonce('ead_search_cities'),
                'gmapsApiKey'      => $gmaps_api_key,
                'gmapsPlacesEnabled' => $gmaps_places_enabled,
                'geonamesEnabled'  => $geonames_enabled,
            ]
        );

        wp_enqueue_script(
            'ead-organization-registration',
            $plugin_url . 'assets/js/organization-registration.js',
            [ 'jquery', 'ead-organization-gallery', 'ead-address' ],
            $version,
            true
        );

        wp_localize_script(
            'ead-organization-registration',
            'EAD_VARS',
            [
                'restUrl'           => esc_url_raw( rest_url( 'artpulse/v1/organizations' ) ),
                'registrationNonce' => wp_create_nonce( 'wp_rest' ),
            ]
        );
    }

    /**
     * Render the organization registration form.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function render_form( $atts = [] ) {
        if ( ! is_user_logged_in() ) {
            return '<p>' . esc_html__( 'Please log in to register an organization.', 'artpulse-management' ) . '</p>';
        }

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

        ob_start();
        ?>
        <form id="ead-organization-registration-form" class="ead-organization-form" enctype="multipart/form-data" method="post">
            <?php wp_nonce_field( 'ead_org_register', 'ead_org_register_nonce' ); ?>

            <div class="form-group">
                <label for="ead_org_name"><?php esc_html_e( 'Organization Name', 'artpulse-management' ); ?> <span class="required">*</span></label>
                <input type="text" id="ead_org_name" name="ead_org_name" required>
            </div>

            <div class="form-group">
                <label for="ead_org_description"><?php esc_html_e( 'Description', 'artpulse-management' ); ?></label>
                <textarea id="ead_org_description" name="ead_org_description" rows="4"></textarea>
            </div>

            <div class="form-group">
                <label for="ead_org_website_url"><?php esc_html_e( 'Website URL', 'artpulse-management' ); ?></label>
                <input type="url" id="ead_org_website_url" name="ead_org_website_url">
            </div>

            <div class="form-group">
                <label for="ead_org_logo_id"><?php esc_html_e( 'Logo Image', 'artpulse-management' ); ?></label>
                <input type="file" id="ead_org_logo_id" name="ead_org_logo_id" accept="image/*">
                <img id="org-logo-image-preview" src="#" alt="<?php esc_attr_e( 'Logo preview', 'artpulse-management' ); ?>" style="display:none;" />
            </div>

            <div class="form-group">
                <label for="ead_org_banner_id"><?php esc_html_e( 'Banner Image', 'artpulse-management' ); ?></label>
                <input type="file" id="ead_org_banner_id" name="ead_org_banner_id" accept="image/*">
                <img id="org-banner-image-preview" src="#" alt="<?php esc_attr_e( 'Banner preview', 'artpulse-management' ); ?>" style="display:none;" />
            </div>

            <div class="form-group">
                <label for="ead_org_type"><?php esc_html_e( 'Organization Type', 'artpulse-management' ); ?></label>
                <select id="ead_org_type" name="ead_org_type">
                    <option value=""><?php esc_html_e( 'Select Type', 'artpulse-management' ); ?></option>
                    <?php foreach ( $org_types as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="ead_org_size"><?php esc_html_e( 'Organization Size', 'artpulse-management' ); ?></label>
                <select id="ead_org_size" name="ead_org_size">
                    <option value=""><?php esc_html_e( 'Select Size', 'artpulse-management' ); ?></option>
                    <?php foreach ( $org_sizes as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="ead_org_facebook_url"><?php esc_html_e( 'Facebook URL', 'artpulse-management' ); ?></label>
                <input type="url" id="ead_org_facebook_url" name="ead_org_facebook_url">
            </div>

            <div class="form-group">
                <label for="ead_org_twitter_url"><?php esc_html_e( 'Twitter URL', 'artpulse-management' ); ?></label>
                <input type="url" id="ead_org_twitter_url" name="ead_org_twitter_url">
            </div>

            <div class="form-group">
                <label for="ead_org_instagram_url"><?php esc_html_e( 'Instagram URL', 'artpulse-management' ); ?></label>
                <input type="url" id="ead_org_instagram_url" name="ead_org_instagram_url">
            </div>

            <div class="form-group">
                <label for="ead_org_linkedin_url"><?php esc_html_e( 'LinkedIn URL', 'artpulse-management' ); ?></label>
                <input type="url" id="ead_org_linkedin_url" name="ead_org_linkedin_url">
            </div>

            <div class="form-group">
                <label for="ead_org_artsy_url"><?php esc_html_e( 'Artsy URL', 'artpulse-management' ); ?></label>
                <input type="url" id="ead_org_artsy_url" name="ead_org_artsy_url">
            </div>

            <div class="form-group">
                <label for="ead_org_pinterest_url"><?php esc_html_e( 'Pinterest URL', 'artpulse-management' ); ?></label>
                <input type="url" id="ead_org_pinterest_url" name="ead_org_pinterest_url">
            </div>

            <div class="form-group">
                <label for="ead_org_youtube_url"><?php esc_html_e( 'YouTube URL', 'artpulse-management' ); ?></label>
                <input type="url" id="ead_org_youtube_url" name="ead_org_youtube_url">
            </div>

            <div class="form-group">
                <label for="ead_org_primary_contact_name"><?php esc_html_e( 'Primary Contact Name', 'artpulse-management' ); ?></label>
                <input type="text" id="ead_org_primary_contact_name" name="ead_org_primary_contact_name">
            </div>

            <div class="form-group">
                <label for="ead_org_primary_contact_email"><?php esc_html_e( 'Primary Contact Email', 'artpulse-management' ); ?></label>
                <input type="email" id="ead_org_primary_contact_email" name="ead_org_primary_contact_email">
            </div>

            <div class="form-group">
                <label for="ead_org_primary_contact_phone"><?php esc_html_e( 'Primary Contact Phone', 'artpulse-management' ); ?></label>
                <input type="tel" id="ead_org_primary_contact_phone" name="ead_org_primary_contact_phone">
            </div>

            <div class="form-group">
                <label for="ead_org_primary_contact_role"><?php esc_html_e( 'Primary Contact Role', 'artpulse-management' ); ?></label>
                <input type="text" id="ead_org_primary_contact_role" name="ead_org_primary_contact_role">
            </div>

            <div class="form-group">
                <label for="ead_org_venue_email"><?php esc_html_e( 'Venue Email', 'artpulse-management' ); ?></label>
                <input type="email" id="ead_org_venue_email" name="ead_org_venue_email">
            </div>

            <div class="form-group">
                <label for="ead_org_venue_phone"><?php esc_html_e( 'Venue Phone', 'artpulse-management' ); ?></label>
                <input type="tel" id="ead_org_venue_phone" name="ead_org_venue_phone">
            </div>

            <fieldset>
                <legend><?php esc_html_e( 'Address', 'artpulse-management' ); ?></legend>
                <label for="ead_country"><?php esc_html_e( 'Country', 'artpulse-management' ); ?></label>
                <select id="ead_country" name="ead_country" required></select>

                <label for="ead_state"><?php esc_html_e( 'State', 'artpulse-management' ); ?></label>
                <select id="ead_state" name="ead_state" disabled required></select>

                <label for="ead_city"><?php esc_html_e( 'City', 'artpulse-management' ); ?></label>
                <select id="ead_city" name="ead_city" disabled required></select>

                <label for="ead_suburb"><?php esc_html_e( 'Suburb', 'artpulse-management' ); ?></label>
                <input type="text" id="ead_suburb" name="ead_suburb">

                <label for="ead_street"><?php esc_html_e( 'Street Address', 'artpulse-management' ); ?></label>
                <input type="text" id="ead_street" name="ead_street">

                <label for="ead_postcode"><?php esc_html_e( 'Postcode', 'artpulse-management' ); ?></label>
                <input type="text" id="ead_postcode" name="ead_postcode">

                <input type="hidden" id="ead_latitude" name="ead_latitude">
                <input type="hidden" id="ead_longitude" name="ead_longitude">
            </fieldset>

            <fieldset>
                <legend><?php esc_html_e( 'Opening Hours', 'artpulse-management' ); ?></legend>
                <?php
                $days = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];
                foreach ( $days as $day ) :
                    $label = ucfirst( $day );
                    ?>
                    <div class="ead-opening-hours-row">
                        <label><?php echo esc_html( $label ); ?></label>
                        <input type="time" name="ead_org_venue_<?php echo esc_attr( $day ); ?>_start_time" id="ead_org_venue_<?php echo esc_attr( $day ); ?>_start_time">
                        <input type="time" name="ead_org_venue_<?php echo esc_attr( $day ); ?>_end_time" id="ead_org_venue_<?php echo esc_attr( $day ); ?>_end_time">
                        <label><input type="checkbox" value="1" name="ead_org_venue_<?php echo esc_attr( $day ); ?>_closed" id="ead_org_venue_<?php echo esc_attr( $day ); ?>_closed"> <?php esc_html_e( 'Closed', 'artpulse-management' ); ?></label>
                    </div>
                <?php endforeach; ?>
            </fieldset>

            <div class="form-group">
                <label><?php esc_html_e( 'Gallery Images', 'artpulse-management' ); ?></label>
                <div class="ead-org-image-upload-area">
                    <?php
                    $max_images = 5;
                    for ( $i = 0; $i < $max_images; $i++ ) :
                        ?>
                        <div class="ead-image-upload-container" data-image-index="<?php echo $i; ?>">
                            <div class="ead-image-preview"><span class="placeholder"><?php printf( esc_html__( 'Image %d', 'artpulse-management' ), $i + 1 ); ?></span></div>
                            <input type="hidden" name="ead_org_gallery_images[]" class="ead-image-id-input" value="">
                            <button type="button" class="button ead-upload-image-button"><?php esc_html_e( 'Select Image', 'artpulse-management' ); ?></button>
                            <button type="button" class="button ead-remove-image-button hidden"><?php esc_html_e( 'Remove Image', 'artpulse-management' ); ?></button>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <?php echo self::render_honeypot( $atts ); ?>
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Register Organization', 'artpulse-management' ); ?></button>
        </form>
        <?php
        return ob_get_clean();
    }
}
