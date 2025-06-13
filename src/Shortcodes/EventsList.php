<?php
namespace EAD\Shortcodes;

/**
 * Class EventsList
 *
 * Renders the events list container via a shortcode.
 *
 * @package EAD\Shortcodes
 */
class EventsList {
    /**
     * Register the shortcode and enqueue assets.
     *
     * @return void
     */
    public static function register() {
        add_shortcode('ead_events_list', [self::class, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    /**
     * Render the shortcode output.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function render_shortcode($atts) {
        ob_start();
        ?>
        <div id="ead-events-list" class="ead-events-list">
            <p><?php esc_html_e('Loading events...', 'artpulse-management'); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue frontend JS and localization for AJAX.
     *
     * @return void
     */
    public static function enqueue_assets() {
        $plugin_url = EAD_PLUGIN_DIR_URL;

        wp_enqueue_script(
            'ead-events-list',
            $plugin_url . 'assets/js/ead-events-list.js',
            ['jquery'],
            defined('EAD_MANAGEMENT_VERSION') ? EAD_MANAGEMENT_VERSION : '1.0.0',
            true
        );

        wp_localize_script(
            'ead-events-list',
            'eadEventsApi',
            [
                'restUrl' => esc_url_raw(rest_url('artpulse/v1/events')),
                'nonce'   => wp_create_nonce('wp_rest'),
            ]
        );
    }
}
