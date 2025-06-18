<?php

namespace ArtPulse\Admin;

class EnqueueAssets
{
    public static function register()
    {
        add_action('enqueue_block_editor_assets', [self::class, 'enqueue_block_editor_assets']);
        add_action('enqueue_block_editor_assets', [self::class, 'enqueue_block_editor_styles']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue_block_editor_assets()
    {
        // Ensure ARTPULSE_PLUGIN_FILE constant is defined and available.
        if (!defined('ARTPULSE_PLUGIN_FILE')) {
            return;
        }

        $plugin_url = plugin_dir_url(ARTPULSE_PLUGIN_FILE); // Get the plugin URL

        // Sidebar taxonomy selector script
        $sidebar_script_path = ARTPULSE_PLUGIN_DIR . '/assets/js/sidebar-taxonomies.js';
        $sidebar_script_url = $plugin_url . '/assets/js/sidebar-taxonomies.js';

        if (file_exists($sidebar_script_path)) {
            wp_enqueue_script(
                'artpulse-taxonomy-sidebar',
                $sidebar_script_url,
                [
                    'wp-edit-post',
                    'wp-data',
                    'wp-components',
                    'wp-element',
                    'wp-compose',
                    'wp-plugins',
                ],
                filemtime($sidebar_script_path)
            );
        }

        // Advanced taxonomy filter block script
        $advanced_script_path = ARTPULSE_PLUGIN_DIR . '/assets/js/advanced-taxonomy-filter-block.js';
        $advanced_script_url = $plugin_url . '/assets/js/advanced-taxonomy-filter-block.js';

        if (file_exists($advanced_script_path)) {
            wp_enqueue_script(
                'artpulse-advanced-taxonomy-filter-block',
                $advanced_script_url,
                [
                    'wp-blocks',
                    'wp-data',
                    'wp-components',
                    'wp-element',
                    'wp-compose',
                    'wp-plugins',
                ],
                filemtime($advanced_script_path)
            );
        }

        // Filtered list shortcode block script
        $filtered_list_script_path = ARTPULSE_PLUGIN_DIR . '/assets/js/filtered-list-shortcode-block.js';
        $filtered_list_script_url = $plugin_url . '/assets/js/filtered-list-shortcode-block.js';

        if (file_exists($filtered_list_script_path)) {
            wp_enqueue_script(
                'artpulse-filtered-list-shortcode-block',
                $filtered_list_script_url,
                [
                    'wp-blocks',
                    'wp-element',
                    'wp-editor',
                    'wp-components',
                    'wp-compose',
                    'wp-plugins',
                ],
                filemtime($filtered_list_script_path)
            );
        }

        // AJAX taxonomy filter block script
        $ajax_filter_script_path = ARTPULSE_PLUGIN_DIR . '/assets/js/ajax-filter-block.js';
        $ajax_filter_script_url = $plugin_url . '/assets/js/ajax-filter-block.js';

        if (file_exists($ajax_filter_script_path)) {
            wp_enqueue_script(
                'artpulse-ajax-filter-block',
                $ajax_filter_script_url,
                [
                    'wp-blocks',
                    'wp-data',
                    'wp-components',
                    'wp-element',
                    'wp-compose',
                    'wp-plugins',
                ],
                filemtime($ajax_filter_script_path)
            );
        }
    }

    public static function enqueue_block_editor_styles()
    {
        if (!defined('ARTPULSE_PLUGIN_FILE') || !defined('ARTPULSE_PLUGIN_DIR')) {
            return;
        }

        $plugin_url = plugin_dir_url(ARTPULSE_PLUGIN_FILE); // Get the plugin URL

        $style_path = ARTPULSE_PLUGIN_DIR . '/assets/css/editor-styles.css';
        $style_url = $plugin_url . '/assets/css/editor-styles.css';

        if (file_exists($style_path)) {
            wp_enqueue_style(
                'artpulse-editor-styles',
                $style_url,
                [],
                filemtime($style_path)
            );
        }
    }

    public static function enqueue()
    {
        $screen = get_current_screen();
        if (!isset($screen->id)) return;

        // Enqueue scripts for Engagement Dashboard
        if ($screen->id === 'artpulse-settings_page_artpulse-engagement') {
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);

            $plugin_url = plugin_dir_url(ARTPULSE_PLUGIN_FILE); // Get the plugin URL

            $custom_js_path = ARTPULSE_PLUGIN_DIR . '/assets/js/ap-engagement-dashboard.js';
            $custom_js_url = $plugin_url . '/assets/js/ap-engagement-dashboard.js';

            if (file_exists($custom_js_path)) {
                wp_enqueue_script(
                    'ap-engagement-dashboard',
                    $custom_js_url,
                    ['chart-js'],
                    filemtime($custom_js_path), // Add filemtime for cache busting
                    true
                );
            }
        }
    }
}