<?php
namespace ArtPulse\Core;

class Activator
{
    public static function activate()
    {
        if (get_option('artpulse_settings') === false) {
            add_option('artpulse_settings', [
                'basic_fee'             => '',
                'pro_fee'               => '',
                'org_fee'               => '',
                'currency'              => 'USD',
                'stripe_enabled'        => 0,
                'stripe_pub_key'        => '',
                'stripe_secret'         => '',
                'stripe_webhook_secret' => '',
                'woocommerce_enabled'   => 0,
                'service_worker_enabled' => 0,
                'debug_logging'         => 0,
            ]);
        }

        if (get_option('artpulse_webhook_status') === false) {
            add_option('artpulse_webhook_status', 'Not yet received');
        }

        Capabilities::add_roles_and_capabilities();
    }
}
