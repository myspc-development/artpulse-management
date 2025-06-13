<?php
namespace EAD\Shortcodes;

class EventsListShortcode {
    const SHORTCODE_TAG = 'ead_events_list';

    public static function register() {
        add_shortcode(self::SHORTCODE_TAG, [self::class, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

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

    public static function render_shortcode($atts) {
        ob_start();
        ?>
        <div id="ead-events-list-filters" class="ead-events-list-filters">
            <label for="ead-filter-type"><?php esc_html_e('Event Type:', 'artpulse-management'); ?></label>
            <select id="ead-filter-type">
                <option value=""><?php esc_html_e('All Types', 'artpulse-management'); ?></option>
                <?php
                $terms = get_terms(['taxonomy' => 'ead_event_type', 'hide_empty' => false]);
                if (!is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        echo '<option value="' . esc_attr($term->slug) . '">' . esc_html($term->name) . '</option>';
                    }
                }
                ?>
            </select>

            <label for="ead-filter-date-from"><?php esc_html_e('From:', 'artpulse-management'); ?></label>
            <input type="date" id="ead-filter-date-from">

            <label for="ead-filter-date-to"><?php esc_html_e('To:', 'artpulse-management'); ?></label>
            <input type="date" id="ead-filter-date-to">

            <button id="ead-filter-button" class="button">
                <?php esc_html_e('Apply Filters', 'artpulse-management'); ?>
            </button>
        </div>

        <div id="ead-events-list" class="ead-events-list">
            <p><?php esc_html_e('Loading events...', 'artpulse-management'); ?></p>
        </div>

        <div id="ead-events-pagination" class="ead-events-pagination"></div>
        <?php
        return ob_get_clean();
    }
}
