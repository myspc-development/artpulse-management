<?php
namespace ArtPulse\Core;

class MyFollowsShortcode {
    public static function register() {
        add_shortcode('ap_my_follows', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue() {
        wp_enqueue_script(
            'ap-my-follows-js',
            plugins_url('assets/js/ap-my-follows.js', dirname(__DIR__, 2)),
            ['wp-api-fetch'],
            '1.0.0',
            true
        );
        wp_localize_script('ap-my-follows-js', 'ArtPulseFollowsApi', [
            'root'  => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
        wp_enqueue_style(
            'ap-directory-css',
            plugins_url('assets/css/ap-directory.css', dirname(__DIR__, 2)),
            [],
            '1.0.0'
        );
    }

    public static function render($atts) {
        // Output a simple container for JS to populate
        return '<div class="ap-my-follows"><div class="ap-directory-results"></div></div>';
    }
}
