<?php

namespace EAD\Admin;

use EAD\Admin\CSVImportExport;

class SettingsPage {
    private static $main_option_name = 'artpulse_plugin_settings';
    private static $settings_page_slug = 'artpulse-settings';

    public static function register() {
        add_action( 'admin_menu', [ self::class, 'add_settings_page_menu_item' ] );
        add_action( 'admin_init', [ self::class, 'register_plugin_settings_and_fields' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin_scripts' ] );
        add_action( 'wp_ajax_ead_test_geonames_api', [ self::class, 'ajax_test_geonames_api' ] );
        add_action( 'wp_ajax_ead_preview_email_template', [ self::class, 'ajax_preview_email_template' ] );
        add_action( 'wp_ajax_nopriv_ead_preview_email_template', [ self::class, 'ajax_preview_email_template' ] );
        add_action( 'wp_ajax_ead_save_email_template', [ self::class, 'ajax_save_email_template' ] );
        add_action( 'wp_ajax_nopriv_ead_save_email_template', [ self::class, 'ajax_save_email_template' ] );
        add_action( 'admin_post_ead_clear_fallback_json_action', [ self::class, 'process_clear_fallback_json_data' ] );
        add_action( 'admin_notices', [ self::class, 'admin_notices' ] ); // Add admin notices action
    }

    public static function add_settings_page_menu_item() {
        add_menu_page(
            __( 'ArtPulse Settings', 'artpulse-management' ),
            __( 'ArtPulse Settings', 'artpulse-management' ),
            'manage_options',
            self::$settings_page_slug,
            [ self::class, 'render_settings_page_with_tabs' ],
            'dashicons-admin-generic',
            34
        );
    }

    private static function get_active_tab() {
        return isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'apis';
    }

    private static function get_settings_tabs() {
        return apply_filters( 'artpulse_settings_tabs', [
            'apis'             => __( 'API Keys & Services', 'artpulse-management' ),
            'social_autopost'  => __( 'Social Auto-Posting', 'artpulse-management' ),
            'email_providers'  => __( 'Email Providers', 'artpulse-management' ),
            'field_mapping'    => __( 'Field Mapping', 'artpulse-management' ),
            'data_management'  => __( 'Data Management', 'artpulse-management' ),
            'import_export'   => __( 'CSV Import/Export', 'artpulse-management' ),
            'email_templates'  => __( 'Email Templates', 'artpulse-management' ),
            'payments'        => __( 'Payment Integration', 'artpulse-management' ),
            'uninstall'        => __( 'Uninstall Settings', 'artpulse-management' ), // Added Uninstall Settings tab
        ] );
    }

    public static function render_settings_page_with_tabs() {
        $active_tab = self::get_active_tab();
        $tabs       = self::get_settings_tabs();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'ArtPulse Management Settings', 'artpulse-management' ) . '</h1>';
        settings_errors();

        // Display admin notice if set
        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'fallback_cleared' ) {
            $message_type = isset( $_GET['message_type'] ) ? sanitize_key( $_GET['message_type'] ) : 'success';
            $message_text = isset( $_GET['message_text'] ) ? urldecode( sanitize_text_field( $_GET['message_text'] ) ) : '';

            if ( ! empty( $message_text ) ) {
                echo '<div class="' . esc_attr( $message_type ) . ' notice is-dismissible">';
                echo '<p>' . esc_html( $message_text ) . '</p>';
                echo '<button type="button" class="notice-dismiss">';
                echo '<span class="screen-reader-text">' . esc_html__( 'Dismiss this notice.', 'artpulse-management' ) . '</span>';
                echo '</button>';
                echo '</div>';
            }
        }


        echo '<h2 class="nav-tab-wrapper">';
        foreach ( $tabs as $tab_slug => $tab_name ) {
            $class = ( $tab_slug === $active_tab ) ? 'nav-tab-active' : '';
            echo '<a href="?page=' . esc_attr( self::$settings_page_slug ) . '&tab=' . esc_attr( $tab_slug ) . '" class="nav-tab ' . esc_attr( $class ) . '">' . esc_html( $tab_name ) . '</a>';
        }
        echo '</h2>';

        if ( $active_tab === 'import_export' ) {
            CSVImportExport::render_admin_page();
            echo '</div>';
            return;
        }

        echo '<form method="post" action="options.php">';
        settings_fields( self::$main_option_name );
        do_settings_sections( self::$settings_page_slug . '-' . $active_tab );
        submit_button();
        echo '</form>';

        // Data Management Tab - Clear Fallback JSON
        if ( $active_tab === 'data_management' ) {
            echo '<hr>';
            echo '<h2>' . esc_html__( 'Fallback JSON Management', 'artpulse-management' ) . '</h2>';
            echo '<p>' . esc_html__( 'Clear the fallback JSON files used for states, cities, and countries.', 'artpulse-management' ) . '</p>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            echo '<input type="hidden" name="action" value="ead_clear_fallback_json_action">';
            wp_nonce_field( 'ead_clear_fallback_json_action_nonce', '_ead_clear_json_nonce' );
            submit_button( __( 'Clear Fallback JSON Files', 'artpulse-management' ), 'delete', 'clear_fallback_json_submit' );
            echo '</form>';
        }

        echo '</div>';
    }

    public static function register_plugin_settings_and_fields() {
        register_setting(
            self::$main_option_name,
            self::$main_option_name,
            [ self::class, 'sanitize_all_settings' ]
        );

        // --- API Keys & Services Tab ---
        $api_tab_slug = self::$settings_page_slug . '-apis';
        add_settings_section(
            'artpulse_api_keys_section',
            __( 'External API Keys', 'artpulse-management' ),
            function () {
                echo '<p>Configure API keys for services like Google Maps, GeoNames, and reCAPTCHA.</p>';
            },
            $api_tab_slug
        );
        add_settings_field( 'google_maps_api_key', __( 'Google Maps API Key', 'artpulse-management' ), [
            self::class,
            'render_text_field_callback',
        ], $api_tab_slug, 'artpulse_api_keys_section', [
            'id'          => 'google_maps_api_key',
            'description' => __( 'Enter your Google Maps API key.', 'artpulse-management' ),
        ] );
        add_settings_field( 'geonames_username', __( 'GeoNames Username', 'artpulse-management' ), [
            self::class,
            'render_text_field_callback',
        ], $api_tab_slug, 'artpulse_api_keys_section', [
            'id'          => 'geonames_username',
            'description' => __( 'Enter your GeoNames username.', 'artpulse-management' ),
        ] );
        add_settings_field( 'google_places_api_key', __( 'Google Places API Key', 'artpulse-management' ), [
            self::class,
            'render_text_field_callback',
        ], $api_tab_slug, 'artpulse_api_keys_section', [
            'id'          => 'google_places_api_key',
            'description' => __( 'Enter your Google Places API key.', 'artpulse-management' ),
        ] );
        add_settings_field( 'enable_google_places_api', __( 'Enable Google Places Autocomplete', 'artpulse-management' ), [
            self::class,
            'render_checkbox_field_callback',
        ], $api_tab_slug, 'artpulse_api_keys_section', [ 'id' => 'enable_google_places_api' ] );
        add_settings_field( 'enable_geonames_api', __( 'Enable GeoNames Lookups', 'artpulse-management' ), [
            self::class,
            'render_checkbox_field_callback',
        ], $api_tab_slug, 'artpulse_api_keys_section', [ 'id' => 'enable_geonames_api' ] );
        // ... (other API settings)

        // --- Social Auto-Posting Tab ---
        $social_tab_slug = self::$settings_page_slug . '-social_autopost';
        add_settings_section(
            'artpulse_social_autopost_section',
            __( 'Social Media Auto-Posting', 'artpulse-management' ),
            function () {
                echo '<p>Configure settings for auto-posting to social media platforms.</p>';
            },
            $social_tab_slug
        );
        add_settings_field( 'social_facebook_enable', __( 'Enable Facebook Auto-Posting', 'artpulse-management' ), [
            self::class,
            'render_checkbox_field_callback',
        ], $social_tab_slug, 'artpulse_social_autopost_section', [ 'id' => 'social_facebook_enable' ] );
        add_settings_field(
            'social_facebook_token',
            __( 'Facebook Token', 'artpulse-management' ),
            [ self::class, 'render_text_field_callback' ],
            $social_tab_slug,
            'artpulse_social_autopost_section',
            [
                'id'          => 'social_facebook_token',
                'description' => __( 'Get your token from the Facebook Developer dashboard.', 'artpulse-management' ),
            ]
        );

        add_settings_field( 'social_instagram_enable', __( 'Enable Instagram Auto-Posting', 'artpulse-management' ), [
            self::class,
            'render_checkbox_field_callback',
        ], $social_tab_slug, 'artpulse_social_autopost_section', [ 'id' => 'social_instagram_enable' ] );
        add_settings_field(
            'social_instagram_token',
            __( 'Instagram Token', 'artpulse-management' ),
            [ self::class, 'render_text_field_callback' ],
            $social_tab_slug,
            'artpulse_social_autopost_section',
            [
                'id'          => 'social_instagram_token',
                'description' => __( 'Create an Instagram token in the Meta developer portal.', 'artpulse-management' ),
            ]
        );

        add_settings_field( 'social_twitter_enable', __( 'Enable Twitter Auto-Posting', 'artpulse-management' ), [
            self::class,
            'render_checkbox_field_callback',
        ], $social_tab_slug, 'artpulse_social_autopost_section', [ 'id' => 'social_twitter_enable' ] );
        add_settings_field(
            'social_twitter_token',
            __( 'Twitter Token', 'artpulse-management' ),
            [ self::class, 'render_text_field_callback' ],
            $social_tab_slug,
            'artpulse_social_autopost_section',
            [
                'id'          => 'social_twitter_token',
                'description' => __( 'Generate a token from your Twitter developer account.', 'artpulse-management' ),
            ]
        );

        add_settings_field( 'social_pinterest_enable', __( 'Enable Pinterest Auto-Posting', 'artpulse-management' ), [
            self::class,
            'render_checkbox_field_callback',
        ], $social_tab_slug, 'artpulse_social_autopost_section', [ 'id' => 'social_pinterest_enable' ] );
        add_settings_field(
            'social_pinterest_token',
            __( 'Pinterest Token', 'artpulse-management' ),
            [ self::class, 'render_text_field_callback' ],
            $social_tab_slug,
            'artpulse_social_autopost_section',
            [
                'id'          => 'social_pinterest_token',
                'description' => __( 'Create an app on Pinterest to obtain this token.', 'artpulse-management' ),
            ]
        );

        // --- Email Providers Tab ---
        $email_tab_slug = self::$settings_page_slug . '-email_providers';
        add_settings_section(
            'artpulse_email_provider_section',
            __( 'Outgoing Mail Provider', 'artpulse-management' ),
            function () {
                echo '<p>' . esc_html__( 'Configure third-party services for sending emails.', 'artpulse-management' ) . '</p>';
            },
            $email_tab_slug
        );
        add_settings_field(
            'email_default_provider',
            __( 'Default Provider', 'artpulse-management' ),
            [ self::class, 'render_select_field_callback' ],
            $email_tab_slug,
            'artpulse_email_provider_section',
            [
                'id'      => 'email_default_provider',
                'choices' => [
                    'wp_mail'  => __( 'WordPress wp_mail', 'artpulse-management' ),
                    'sendgrid' => __( 'SendGrid', 'artpulse-management' ),
                    'mailgun'  => __( 'Mailgun', 'artpulse-management' ),
                ],
            ]
        );
        add_settings_field(
            'sendgrid_api_key',
            __( 'SendGrid API Key', 'artpulse-management' ),
            [ self::class, 'render_text_field_callback' ],
            $email_tab_slug,
            'artpulse_email_provider_section',
            [
                'id'          => 'sendgrid_api_key',
                'description' => __( 'Your SendGrid API key.', 'artpulse-management' ),
            ]
        );
        add_settings_field(
            'mailgun_api_key',
            __( 'Mailgun API Key', 'artpulse-management' ),
            [ self::class, 'render_text_field_callback' ],
            $email_tab_slug,
            'artpulse_email_provider_section',
            [
                'id'          => 'mailgun_api_key',
                'description' => __( 'Your Mailgun API key.', 'artpulse-management' ),
            ]
        );
        add_settings_field(
            'mailgun_domain',
            __( 'Mailgun Domain', 'artpulse-management' ),
            [ self::class, 'render_text_field_callback' ],
            $email_tab_slug,
            'artpulse_email_provider_section',
            [
                'id'          => 'mailgun_domain',
                'description' => __( 'Domain configured in Mailgun.', 'artpulse-management' ),
            ]
        );

        // --- Field Mapping Tab ---
        $mapping_tab_slug = self::$settings_page_slug . '-field_mapping';
        add_settings_section(
            'artpulse_field_mapping_section',
            __( 'CSV Field Mapping', 'artpulse-management' ),
            function () {
                echo '<p>' . esc_html__( 'Define how CSV columns map to post meta keys for each post type.', 'artpulse-management' ) . '</p>';
            },
            $mapping_tab_slug
        );
        add_settings_field(
            'event_field_mapping',
            __( 'Events Mapping', 'artpulse-management' ),
            [ self::class, 'render_simple_textarea_callback' ],
            $mapping_tab_slug,
            'artpulse_field_mapping_section',
            [
                'id'          => 'event_field_mapping',
                'description' => __( 'JSON mapping of CSV columns to event meta keys.', 'artpulse-management' ),
            ]
        );
        add_settings_field(
            'organization_field_mapping',
            __( 'Organizations Mapping', 'artpulse-management' ),
            [ self::class, 'render_simple_textarea_callback' ],
            $mapping_tab_slug,
            'artpulse_field_mapping_section',
            [
                'id'          => 'organization_field_mapping',
                'description' => __( 'JSON mapping for organization imports.', 'artpulse-management' ),
            ]
        );
        add_settings_field(
            'artist_field_mapping',
            __( 'Artists Mapping', 'artpulse-management' ),
            [ self::class, 'render_simple_textarea_callback' ],
            $mapping_tab_slug,
            'artpulse_field_mapping_section',
            [
                'id'          => 'artist_field_mapping',
                'description' => __( 'JSON mapping for artist imports.', 'artpulse-management' ),
            ]
        );
        add_settings_field(
            'artwork_field_mapping',
            __( 'Artworks Mapping', 'artpulse-management' ),
            [ self::class, 'render_simple_textarea_callback' ],
            $mapping_tab_slug,
            'artpulse_field_mapping_section',
            [
                'id'          => 'artwork_field_mapping',
                'description' => __( 'JSON mapping for artwork imports.', 'artpulse-management' ),
            ]
        );

        // --- Data Management Tab ---
        $data_tab_slug = self::$settings_page_slug . '-data_management';
        add_settings_section(
            'artpulse_fallback_json_section',
            __( 'Fallback Data Files', 'artpulse-management' ),
            function () {
                echo '<p>Manage locally stored JSON data files.</p>';
            },
            $data_tab_slug
        );
        add_settings_field( 'enable_fallback_updates', __( 'Auto-update fallback JSON', 'artpulse-management' ), [
            self::class,
            'render_checkbox_field_callback',
        ], $data_tab_slug, 'artpulse_fallback_json_section', [ 'id' => 'enable_fallback_updates' ] );

        add_settings_section(
            'artpulse_rate_limit_section',
            __( 'AJAX Rate Limiting', 'artpulse-management' ),
            function () {
                echo '<p>' . esc_html__( 'Control per-IP limits for AJAX handlers.', 'artpulse-management' ) . '</p>';
            },
            $data_tab_slug
        );
        add_settings_field( 'ajax_rate_limit', __( 'Requests Per Window', 'artpulse-management' ), [
            self::class,
            'render_text_field_callback',
        ], $data_tab_slug, 'artpulse_rate_limit_section', [
            'id'          => 'ajax_rate_limit',
            'description' => __( 'Maximum requests allowed from one IP during the window.', 'artpulse-management' ),
        ] );
        add_settings_field( 'ajax_rate_window', __( 'Window Seconds', 'artpulse-management' ), [
            self::class,
            'render_text_field_callback',
        ], $data_tab_slug, 'artpulse_rate_limit_section', [
            'id'          => 'ajax_rate_window',
            'description' => __( 'Length of the rate limit window in seconds.', 'artpulse-management' ),
        ] );

        // --- Email Templates Tab ---
        $email_templates_tab_slug = self::$settings_page_slug . '-email_templates';
        add_settings_section(
            'artpulse_email_templates_section',
            __( 'Email Templates', 'artpulse-management' ),
            function () {
                echo '<p>Configure email templates here. Use shortcodes for dynamic content.</p>';
            },
            $email_templates_tab_slug
        );
        $email_templates = [
            'email_template_1' => __( 'Welcome Email', 'artpulse-management' ),
            'email_template_2' => __( 'Event Reminder', 'artpulse-management' ),
            // Add more templates with descriptive names
        ];
        foreach ( $email_templates as $template_key => $template_name ) {
            add_settings_field(
                $template_key,
                $template_name,
                [ self::class, 'render_textarea_field_callback' ],
                $email_templates_tab_slug,
                'artpulse_email_templates_section',
                [
                    'id'           => $template_key,
                    'description'  => __( 'Content for the ' . strtolower( $template_name ) . ' template.', 'artpulse-management' ),
                    'template_key' => $template_key,
                ]
            );
        }

        // --- Payments Tab ---
        $payments_tab_slug = self::$settings_page_slug . '-payments';
        add_settings_section(
            'artpulse_payments_section',
            __( 'WooCommerce/Stripe', 'artpulse-management' ),
            function () {
                echo '<p>' . esc_html__( 'Configure WooCommerce and Stripe integration settings.', 'artpulse-management' ) . '</p>';
            },
            $payments_tab_slug
        );
        add_settings_field(
            'wc_featured_product_id',
            __( 'Featured Product ID', 'artpulse-management' ),
            [ self::class, 'render_text_field_callback' ],
            $payments_tab_slug,
            'artpulse_payments_section',
            [ 'id' => 'wc_featured_product_id', 'description' => __( 'WooCommerce product ID used for featured listings.', 'artpulse-management' ) ]
        );
        add_settings_field(
            'featured_duration_days',
            __( 'Featured Duration (days)', 'artpulse-management' ),
            [ self::class, 'render_text_field_callback' ],
            $payments_tab_slug,
            'artpulse_payments_section',
            [ 'id' => 'featured_duration_days', 'description' => __( 'Number of days a listing stays featured after payment.', 'artpulse-management' ) ]
        );
        add_settings_field(
            'stripe_publishable_key',
            __( 'Stripe Publishable Key', 'artpulse-management' ),
            [ self::class, 'render_text_field_callback' ],
            $payments_tab_slug,
            'artpulse_payments_section',
            [ 'id' => 'stripe_publishable_key' ]
        );
        add_settings_field(
            'stripe_secret_key',
            __( 'Stripe Secret Key', 'artpulse-management' ),
            [ self::class, 'render_text_field_callback' ],
            $payments_tab_slug,
            'artpulse_payments_section',
            [ 'id' => 'stripe_secret_key' ]
        );

        // --- Uninstall Settings Tab ---
        $uninstall_tab_slug = self::$settings_page_slug . '-uninstall';
        add_settings_section(
            'artpulse_uninstall_section',
            __( 'Uninstall Settings', 'artpulse-management' ),
            function () {
                echo '<p>Configure plugin data deletion on uninstall.</p>';
            },
            $uninstall_tab_slug
        );
        add_settings_field(
            'artpulse_delete_data_on_uninstall', // Unique ID
            __( 'Delete Data on Uninstall', 'artpulse-management' ),
            [ self::class, 'render_checkbox_field_callback' ],
            $uninstall_tab_slug,
            'artpulse_uninstall_section',
            [
                'id'          => 'artpulse_delete_data_on_uninstall',
                'description' => __( 'Delete all plugin data when the plugin is uninstalled.', 'artpulse-management' ),
            ]
        );
    }

    public static function render_text_field_callback( $args ) {
        $options     = get_option( self::$main_option_name, [] );
        $id          = $args['id'];
        $value       = isset( $options[ $id ] ) ? esc_attr( $options[ $id ] ) : '';
        $description = isset( $args['description'] ) ? '<p class="description">' . esc_html( $args['description'] ) . '</p>' : '';

        echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::$main_option_name . '[' . $id . ']' ) . '" value="' . esc_attr( $value ) . '" class="regular-text">';
        echo $description;
    }

    public static function render_checkbox_field_callback( $args ) {
        $options     = get_option( self::$main_option_name, [] );
        $id          = $args['id'];
        $checked     = isset( $options[ $id ] ) && $options[ $id ] ? 'checked' : '';
        $description = isset( $args['description'] ) ? '<p class="description">' . esc_html( $args['description'] ) . '</p>' : '';

        echo '<label>';
        echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::$main_option_name . '[' . $id . ']' ) . '" value="1" ' . $checked . '>';
        echo $description;
        echo '</label>';
    }

    public static function render_select_field_callback( $args ) {
        $options    = get_option( self::$main_option_name, [] );
        $id         = $args['id'];
        $value      = isset( $options[ $id ] ) ? $options[ $id ] : '';
        $choices    = isset( $args['choices'] ) && is_array( $args['choices'] ) ? $args['choices'] : [];
        $description = isset( $args['description'] ) ? '<p class="description">' . esc_html( $args['description'] ) . '</p>' : '';

        echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( self::$main_option_name . '[' . $id . ']' ) . '">';
        foreach ( $choices as $choice_val => $label ) {
            $selected = selected( $value, $choice_val, false );
            echo '<option value="' . esc_attr( $choice_val ) . '" ' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo $description;
    }

    public static function render_textarea_field_callback( $args ) {
        $options     = get_option( self::$main_option_name, [] );
        $id          = $args['id'];
        $value       = isset( $options[ $id ] ) ? esc_textarea( $options[ $id ] ) : '';
        $description = isset( $args['description'] ) ? '<p class="description">' . esc_html( $args['description'] ) . '</p>' : '';
        $templateKey = $args['template_key'];

        echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( self::$main_option_name . '[' . $id . ']' ) . '" rows="8" cols="70" class="large-text code">' . $value . '</textarea>';
        echo '<br>';
        echo '<button type="button" class="button ead-email-template-preview-btn" data-template-key="' . esc_attr( $templateKey ) . '">' . esc_html__( 'Preview', 'artpulse-management' ) . '</button>';
        echo '<button type="button" class="button button-primary ead-email-template-save-btn" data-template-key="' . esc_attr( $templateKey ) . '">' . esc_html__( 'Save', 'artpulse-management' ) . '</button>';
        echo '<div id="' . esc_attr( $id ) . '-preview" style="margin-top: 10px;"></div>';  // Preview container
        echo $description;
    }

    public static function render_simple_textarea_callback( $args ) {
        $options     = get_option( self::$main_option_name, [] );
        $id          = $args['id'];
        $value       = isset( $options[ $id ] ) ? esc_textarea( $options[ $id ] ) : '';
        $description = isset( $args['description'] ) ? '<p class="description">' . esc_html( $args['description'] ) . '</p>' : '';

        echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( self::$main_option_name . '[' . $id . ']' ) . '" rows="5" cols="70" class="large-text code">' . $value . '</textarea>';
        echo $description;
    }

    public static function sanitize_all_settings( $input ) {
        $output = get_option( self::$main_option_name, [] );

        // Sanitize API settings
        if ( isset( $input['google_maps_api_key'] ) ) {
            $output['google_maps_api_key'] = sanitize_text_field( $input['google_maps_api_key'] );
        }
        if ( isset( $input['geonames_username'] ) ) {
            $output['geonames_username'] = sanitize_text_field( $input['geonames_username'] );
        }
        if ( isset( $input['google_places_api_key'] ) ) {
            $output['google_places_api_key'] = sanitize_text_field( $input['google_places_api_key'] );
        }
        if ( isset( $input['enable_google_places_api'] ) ) {
            $output['enable_google_places_api'] = (bool) $input['enable_google_places_api'];
        } else {
            $output['enable_google_places_api'] = false;
        }
        if ( isset( $input['enable_geonames_api'] ) ) {
            $output['enable_geonames_api'] = (bool) $input['enable_geonames_api'];
        } else {
            $output['enable_geonames_api'] = false;
        }

        // ... (other API settings)

        // Sanitize Social Auto-Posting settings
        if ( isset( $input['social_facebook_enable'] ) ) {
            $output['social_facebook_enable'] = (bool) $input['social_facebook_enable'];
        }
        if ( isset( $input['social_facebook_token'] ) ) {
            $output['social_facebook_token'] = sanitize_text_field( $input['social_facebook_token'] );
        }
        if ( isset( $input['social_instagram_enable'] ) ) {
            $output['social_instagram_enable'] = (bool) $input['social_instagram_enable'];
        }
        if ( isset( $input['social_instagram_token'] ) ) {
            $output['social_instagram_token'] = sanitize_text_field( $input['social_instagram_token'] );
        }
        if ( isset( $input['social_twitter_enable'] ) ) {
            $output['social_twitter_enable'] = (bool) $input['social_twitter_enable'];
        }
        if ( isset( $input['social_twitter_token'] ) ) {
            $output['social_twitter_token'] = sanitize_text_field( $input['social_twitter_token'] );
        }
        if ( isset( $input['social_pinterest_enable'] ) ) {
            $output['social_pinterest_enable'] = (bool) $input['social_pinterest_enable'];
        }
        if ( isset( $input['social_pinterest_token'] ) ) {
            $output['social_pinterest_token'] = sanitize_text_field( $input['social_pinterest_token'] );
        }

        // Sanitize Email Provider settings
        if ( isset( $input['email_default_provider'] ) ) {
            $allowed = [ 'wp_mail', 'sendgrid', 'mailgun' ];
            $provider = sanitize_text_field( $input['email_default_provider'] );
            $output['email_default_provider'] = in_array( $provider, $allowed, true ) ? $provider : 'wp_mail';
        }
        if ( isset( $input['sendgrid_api_key'] ) ) {
            $output['sendgrid_api_key'] = sanitize_text_field( $input['sendgrid_api_key'] );
        }
        if ( isset( $input['mailgun_api_key'] ) ) {
            $output['mailgun_api_key'] = sanitize_text_field( $input['mailgun_api_key'] );
        }
        if ( isset( $input['mailgun_domain'] ) ) {
            $output['mailgun_domain'] = sanitize_text_field( $input['mailgun_domain'] );
        }

        // Sanitize Field Mapping settings
        if ( isset( $input['event_field_mapping'] ) ) {
            $output['event_field_mapping'] = sanitize_textarea_field( $input['event_field_mapping'] );
        }
        if ( isset( $input['organization_field_mapping'] ) ) {
            $output['organization_field_mapping'] = sanitize_textarea_field( $input['organization_field_mapping'] );
        }
        if ( isset( $input['artist_field_mapping'] ) ) {
            $output['artist_field_mapping'] = sanitize_textarea_field( $input['artist_field_mapping'] );
        }
        if ( isset( $input['artwork_field_mapping'] ) ) {
            $output['artwork_field_mapping'] = sanitize_textarea_field( $input['artwork_field_mapping'] );
        }

        // Sanitize Payment settings
        if ( isset( $input['wc_featured_product_id'] ) ) {
            $output['wc_featured_product_id'] = absint( $input['wc_featured_product_id'] );
        }
        if ( isset( $input['featured_duration_days'] ) ) {
            $output['featured_duration_days'] = absint( $input['featured_duration_days'] );
        }
        if ( isset( $input['stripe_publishable_key'] ) ) {
            $output['stripe_publishable_key'] = sanitize_text_field( $input['stripe_publishable_key'] );
        }
        if ( isset( $input['stripe_secret_key'] ) ) {
            $output['stripe_secret_key'] = sanitize_text_field( $input['stripe_secret_key'] );
        }

        // Sanitize Data Management settings
        if ( isset( $input['enable_fallback_updates'] ) ) {
            $output['enable_fallback_updates'] = (bool) $input['enable_fallback_updates'];
        }
        if ( isset( $input['ajax_rate_limit'] ) ) {
            $output['ajax_rate_limit'] = absint( $input['ajax_rate_limit'] );
        }
        if ( isset( $input['ajax_rate_window'] ) ) {
            $output['ajax_rate_window'] = absint( $input['ajax_rate_window'] );
        }

        // Sanitize Email Templates
        foreach ( self::getEmailTemplateKeys() as $template_key ) {
            if ( isset( $input[ $template_key ] ) ) {
                $output[ $template_key ] = wp_kses_post( $input[ $template_key ] ); // Use wp_kses_post for email content
            }
        }

        // Sanitize Uninstall settings
        $output['artpulse_delete_data_on_uninstall'] = isset( $input['artpulse_delete_data_on_uninstall'] ) ? 1 : 0;

        return $output;
    }

    private static function getEmailTemplateKeys(): array {
        return [
            'email_template_1',
            'email_template_2',
            // Add more template keys here to ensure they're sanitized
        ];
    }

    public static function ajax_test_geonames_api() {
        check_ajax_referer( 'ead_test_geonames_api_nonce', 'security' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'artpulse-management' ) ], 403 );
        }

        $all_options = get_option( self::$main_option_name, [] );
        $username    = isset( $all_options['geonames_username'] ) ? sanitize_text_field( $all_options['geonames_username'] ) : '';

        if ( empty( $username ) ) {
            wp_send_json_error( [ 'message' => __( 'GeoNames username is not configured in API settings.', 'artpulse-management' ) ] );
        }

        $url      = "http://api.geonames.org/countryInfoJSON?username=" . urlencode( $username );
        $response = wp_remote_get( $url, [ 'timeout' => 15 ] );

        $current_time = time();

        if ( is_wp_error( $response ) ) {
            $error_message = __( 'GeoNames API request failed: ', 'artpulse-management' ) . $response->get_error_message();

            $all_options['geonames_api_last_error']    = $error_message;
            $all_options['geonames_api_failures_today'] = ( isset( $all_options['geonames_api_failures_today'] ) ? intval( $all_options['geonames_api_failures_today'] ) : 0 ) + 1;
            update_option( self::$main_option_name, $all_options );

            wp_send_json_error( [ 'message' => $error_message ] );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            $error_message = sprintf( __( 'GeoNames API returned an unexpected response code: %d.', 'artpulse-management' ), $code );
            if ( ! empty( $body ) ) {
                $error_message .= ' ' . __( 'Response:', 'artpulse-management' ) . ' ' . esc_html( substr( strip_tags( $body ), 0, 200 ) );
            }

            $all_options['geonames_api_last_error']    = $error_message;
            $all_options['geonames_api_failures_today'] = ( isset( $all_options['geonames_api_failures_today'] ) ? intval( $all_options['geonames_api_failures_today'] ) : 0 ) + 1;
            update_option( self::$main_option_name, $all_options );

            wp_send_json_error( [ 'message' => $error_message ] );
        }

        $data = json_decode( $body, true );

        if ( isset( $data['geonames'] ) && ! empty( $data['geonames'] ) ) {
            $all_options['geonames_api_last_success']    = $current_time;
            $all_options['geonames_api_last_error']      = ''; // Clear last error
            $all_options['geonames_api_failures_today'] = 0; // Reset failures
            update_option( self::$main_option_name, $all_options );

            wp_send_json_success( [ 'message' => __( 'GeoNames API test successful! Countries retrieved.', 'artpulse-management' ) ] );
        } elseif ( isset( $data['status']['message'] ) ) {
            $error_message = __( 'GeoNames API Error: ', 'artpulse-management' ) . $data['status']['message'];

            $all_options['geonames_api_last_error']    = $error_message;
            $all_options['geonames_api_failures_today'] = ( isset( $all_options['geonames_api_failures_today'] ) ? intval( $all_options['geonames_api_failures_today'] ) : 0 ) + 1;
            update_option( self::$main_option_name, $all_options );

            wp_send_json_error( [ 'message' => $error_message ] );
        } else {
            $error_message = __( 'GeoNames API returned no data or an unexpected format.', 'artpulse-management' );

            $all_options['geonames_api_last_error']    = $error_message;
            $all_options['geonames_api_failures_today'] = ( isset( $all_options['geonames_api_failures_today'] ) ? intval( $all_options['geonames_api_failures_today'] ) : 0 ) + 1;
            update_option( self::$main_option_name, $all_options );

            wp_send_json_error( [ 'message' => $error_message ] );
        }
    }

    public static function enqueue_admin_scripts( $hook ) {
        $current_screen = get_current_screen();

        if ( ! $current_screen || $current_screen->id !== 'toplevel_page_' . self::$settings_page_slug ) {
            return;
        }

        wp_enqueue_script(
            'ead-admin-settings-js',
            EAD_PLUGIN_DIR_URL . 'assets/js/ead-admin-settings.js',
            [ 'jquery' ],
            EAD_PLUGIN_VERSION,
            true
        );

        wp_localize_script( 'ead-admin-settings-js', 'eadAdminSettings', [
            'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
            'testGeoNamesNonce' => wp_create_nonce( 'ead_test_geonames_api_nonce' ),
            'emailTemplateNonce' => wp_create_nonce( 'ead_preview_email_template_nonce' ),
            'emailSaveNonce'   => wp_create_nonce( 'ead_save_email_template_nonce' ),
        ] );
    }

    /**
     * AJAX handler for previewing email templates.
     */
    public static function ajax_preview_email_template() {
        if ( ! check_ajax_referer( 'ead_preview_email_template_nonce', 'security', false ) ) {
            wp_send_json_error( [ 'message' => 'Security check failed.' ] );

            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'You do not have permission to preview email templates.' ] );

            return;
        }

        $template_key     = sanitize_key( $_POST['template_key'] ?? '' );
        $template_content = isset( $_POST['template_content'] ) ? wp_kses_post( $_POST['template_content'] ) : '';

        if ( empty( $template_key ) ) {
            wp_send_json_error( [ 'message' => 'Template key is missing.' ] );

            return;
        }

        // Basic shortcode replacement (expand as needed)
        $replacements = [
            '[site_title]'  => get_bloginfo( 'name' ),
            '[current_date]' => date( 'Y-m-d' ),
        ];

        $preview_content = strtr( $template_content, $replacements );

        wp_send_json_success( [ 'preview' => wp_kses_post( $preview_content ) ] );
    }

    /**
     * AJAX handler for saving email templates.
     */
    public static function ajax_save_email_template() {
        if ( ! check_ajax_referer( 'ead_save_email_template_nonce', 'security', false ) ) {
            wp_send_json_error( [ 'message' => 'Security check failed.' ] );

            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'You do not have permission to save email templates.' ] );

            return;
        }

        $template_key     = sanitize_key( $_POST['template_key'] ?? '' );
        $template_content = isset( $_POST['template_content'] ) ? wp_kses_post( $_POST['template_content'] ) : '';

        if ( empty( $template_key ) ) {
            wp_send_json_error( [ 'message' => 'Template key is missing.' ] );

            return;
        }

        $options                     = get_option( self::$main_option_name, [] );
        $options[ $template_key ] = $template_content;

        if ( update_option( self::$main_option_name, $options ) ) {
            wp_send_json_success( [ 'message' => 'Template saved successfully.' ] );
        } else {
            wp_send_json_error( [ 'message' => 'Failed to save template.' ] );
        }
    }

    /**
     * Retrieves a plugin setting value.
     *
     * Public so other classes can access saved options.
     *
     * @param string $key     Settings array key.
     * @param mixed  $default Optional. Default value if the setting is not found.
     * @return mixed
     */
    public static function get_setting( $key, $default = '' ) {
        $options = get_option( self::$main_option_name, [] );

        return isset( $options[ $key ] ) ? $options[ $key ] : $default;
    }

    /**
     * Processes the clearing of fallback JSON data.
     * This should be called by the admin_post_{action_name} hook.
     */
    public static function process_clear_fallback_json_data() {
        if ( ! isset( $_POST['_ead_clear_json_nonce'] ) || ! wp_verify_nonce( $_POST['_ead_clear_json_nonce'], 'ead_clear_fallback_json_action_nonce' ) ) {
            wp_die( 'Security check failed for clearing fallback JSON.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to perform this action.' );
        }

        $files_cleared = 0;
        $files_failed  = 0;

        $fallback_data_dir = apply_filters( 'ead_fallback_data_directory', EAD_PLUGIN_DIR_PATH . 'data/' );
        $json_files        = [ 'states.json', 'cities.json', 'countries.json' ];

        foreach ( $json_files as $filename ) {
            $file_path = $fallback_data_dir . $filename;

            if ( file_exists( $file_path ) ) {
                if ( is_writable( $file_path ) ) {
                    if ( file_put_contents( $file_path, json_encode( [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) !== false ) {
                        $files_cleared ++;
                    } else {
                        $files_failed ++;
                    }
                } else {
                    $files_failed ++;
                }
            }
        }

        $message_type = 'updated'; // WordPress 'updated' class for success
        $message      = '';

        if ( $files_cleared > 0 ) {
            $message .= sprintf( _n( '%d fallback file cleared successfully.', '%d fallback files cleared successfully.', $files_cleared, 'artpulse-management' ), $files_cleared );
        }

        if ( $files_failed > 0 ) {
            $message      .= ( $files_cleared > 0 ? ' ' : '' ) . sprintf( _n( '%d fallback file could not be cleared (check file permissions).', '%d fallback files could not be cleared (check file permissions).', $files_failed, 'artpulse-management' ), $files_failed );
            $message_type = 'error'; // Change to error if any failed
        }

        if ( empty( $message ) ) {
            $message      = __( 'No fallback files found to clear or action already performed.', 'artpulse-management' );
            $message_type = 'info';
        }

        // Add a query arg for the admin notice
        $redirect_url = add_query_arg( [
            'page'             => self::$settings_page_slug,
            'tab'              => 'data_management',
            'settings-updated' => 'fallback_cleared', // Custom query arg
            'message_type'     => $message_type,
            'message_text'     => urlencode( $message ),
        ], admin_url( 'admin.php' ) ); // Use admin.php for menu pages

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Displays admin notices based on query parameters.
     */
    public static function admin_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Display notice when fallback JSON files were cleared.
        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'fallback_cleared' ) {
            $message_type = isset( $_GET['message_type'] ) ? sanitize_key( $_GET['message_type'] ) : 'success';
            $message_text = isset( $_GET['message_text'] ) ? urldecode( sanitize_text_field( $_GET['message_text'] ) ) : '';

            if ( ! empty( $message_text ) ) {
                ?>
                <div class="<?php echo esc_attr( $message_type ); ?> notice is-dismissible">
                    <p><?php echo esc_html( $message_text ); ?></p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text"><?php _e( 'Dismiss this notice.', 'artpulse-management' ); ?></span>
                    </button>
                </div>
                <?php
            }
        }

        // Warn when GeoNames username is missing.
        $options = get_option( self::$main_option_name, [] );
        if ( empty( $options['geonames_username'] ) ) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php
                    echo esc_html__(
                        'GeoNames username is missing. Location lookups may not work until you set it in ArtPulse Settings.',
                        'artpulse-management'
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }
}