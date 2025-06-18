<?php
namespace ArtPulse\Core;

class PurchaseShortcode {
    public static function register() {
        add_shortcode('ap_membership_purchase', [self::class, 'render']);
    }

    public static function render($atts = []) {
        $atts = shortcode_atts([
            'level' => 'Pro',
            'class' => 'ap-purchase-link'
        ], $atts, 'ap_membership_purchase');

        $level = sanitize_text_field($atts['level']);
        $url   = home_url('/purchase-membership');

        if (function_exists('wc_get_checkout_url')) {
            $url = add_query_arg('level', strtolower($level), wc_get_checkout_url());
        } else {
            $url = add_query_arg('level', strtolower($level), $url);
        }

        $label = sprintf(__('Purchase %s membership', 'artpulse'), $level);

        return sprintf(
            '<a href="%s" class="%s">%s</a>',
            esc_url($url),
            esc_attr($atts['class']),
            esc_html($label)
        );
    }
}
