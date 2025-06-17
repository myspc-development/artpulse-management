<?php
namespace ArtPulse\Core;

class SettingsPage
{
    public static function register()
    {
        add_action('admin_menu',   [ self::class, 'addMenu' ]);
        add_action('admin_init',   [ self::class, 'registerSettings' ]);
    }

    public static function addMenu()
    {
        add_options_page(
            __('ArtPulse Settings', 'artpulse'),
            __('ArtPulse', 'artpulse'),
            'manage_options',
            'artpulse-settings',
            [ self::class, 'render' ]
        );
    }

    public static function registerSettings()
    {
        register_setting('artpulse_settings_group', 'artpulse_settings');

        add_settings_section(
            'ap_membership_section',
            __('Membership Settings', 'artpulse'),
            '__return_false',
            'artpulse-settings'
        );

        $fields = [
            // Membership & Stripe
            'basic_fee'              => 'Basic Member Fee ($)',
            'pro_fee'                => 'Pro Artist Fee ($)',
            'org_fee'                => 'Organization Fee ($)',
            'currency'               => 'Currency (ISO)',
            'stripe_enabled'         => 'Enable Stripe Integration',
            'stripe_pub_key'         => 'Stripe Publishable Key',
            'stripe_secret'          => 'Stripe Secret Key',
            'stripe_webhook_secret'  => 'Stripe Webhook Signing Secret',
            'stripe_test'            => 'Stripe Test Mode',
            'woo_enabled'            => 'Enable WooCommerce Integration',
            'notify_fee'             => 'Email Notification on Fee Change',

            // WooCommerce Membership Products
            'woo_basic_product_id'   => 'Product ID for Basic Membership',
            'woo_pro_product_id'     => 'Product ID for Pro Membership',
            'woo_org_product_id'     => 'Product ID for Organization Membership',

            // Analytics
            'analytics_enabled'      => 'Enable Analytics Tracking',
            'analytics_gtag_id'      => 'Google Analytics 4 Measurement ID (G-XXXXXXX)',

            // Analytics Dashboard Embed
            'analytics_embed_enabled'=> 'Enable Analytics Dashboard Embed',
            'analytics_embed_url'    => 'Analytics Dashboard Embed URL',
        ];

        foreach ($fields as $name => $label) {
            add_settings_field(
                $name,
                __($label, 'artpulse'),
                [ self::class, 'renderField' ],
                'artpulse-settings',
                'ap_membership_section',
                [ 'label_for' => $name ]
            );
        }
    }

    public static function renderField($args)
    {
        $opts = get_option('artpulse_settings', []);
        $val  = $opts[$args['label_for']] ?? '';
        $checkboxFields = [
            'stripe_enabled',
            'stripe_test',
            'woo_enabled',
            'notify_fee',
            'analytics_enabled',
            'analytics_embed_enabled',
        ];

        if (in_array($args['label_for'], $checkboxFields, true)) {
            printf(
                '<input type="checkbox" id="%1$s" name="artpulse_settings[%1$s]" value="1" %2$s />',
                esc_attr($args['label_for']),
                checked($val, 1, false)
            );
        } else {
            printf(
                '<input type="text" id="%1$s" name="artpulse_settings[%1$s]" value="%2$s" style="width:50%%;" />',
                esc_attr($args['label_for']),
                esc_attr($val)
            );
        }
    }

    public static function render()
    {
        echo '<div class="wrap"><h1>' . __('ArtPulse Settings', 'artpulse') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('artpulse_settings_group');
        do_settings_sections('artpulse-settings');
        submit_button();
        echo '</form></div>';
    }
}
