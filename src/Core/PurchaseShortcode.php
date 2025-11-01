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
        $url   = MembershipUrls::getPurchaseUrl($level);

        if ($url === '') {
            return '';
        }

        $label = sprintf(__('Purchase %s membership', 'artpulse-management'), $level);

        return sprintf(
            '<a href="%s" class="%s">%s</a>',
            esc_url($url),
            esc_attr($atts['class']),
            esc_html($label)
        );
    }
}
