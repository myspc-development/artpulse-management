<?php
namespace ArtPulse\Admin;

class EnqueueAssets
{
    public static function register()
    {
        add_action('enqueue_block_editor_assets', [self::class, 'enqueue_block_editor_assets']);
        add_action('enqueue_block_editor_assets', [self::class, 'enqueue_block_editor_styles']);
        // Enqueue frontend CSS for AJAX filter UI and item styles
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_frontend_styles']);
    }

    public static function enqueue_block_editor_assets()
    {
        // Ensure ARTPULSE_PLUGIN_FILE constant is defined and available.
        // It should be defined in your main Plugin class.
        if (!defined('ARTPULSE_PLUGIN_FILE')) {
            // Fallback or error logging if the constant is not defined.
            // This indicates an issue with plugin initialization order.
            // For a robust solution, ensure constants are defined before this class is used.
            // As a temporary fallback, you might try to calculate it, but using the constant is preferred.
            // $artpulse_plugin_file = dirname(__DIR__, 2) . '/artpulse-management.php'; // Assuming EnqueueAssets.php is in src/Admin/
            // error_log('ARTPULSE_PLUGIN_FILE constant not defined in EnqueueAssets.php. Attempting fallback.');
            return; // Or handle error appropriately
        }

        // Sidebar taxonomy selector script
        // Path for filemtime()
        $sidebar_script_path = ARTPULSE_PLUGIN_DIR . 'assets/js/sidebar-taxonomies.js';
        // URL for wp_enqueue_script()
        $sidebar_script_url  = plugins_url('assets/js/sidebar-taxonomies.js', ARTPULSE_PLUGIN_FILE);

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
        $advanced_script_path = ARTPULSE_PLUGIN_DIR . 'assets/js/advanced-taxonomy-filter-block.js';
        $advanced_script_url  = plugins_url('assets/js/advanced-taxonomy-filter-block.js', ARTPULSE_PLUGIN_FILE);

        if (file_exists($advanced_script_path)) {
            wp_enqueue_script(
                'artpulse-advanced-taxonomy-filter-block',
                $advanced_script_url,
                [
                    'wp-blocks',
                    'wp-element',
                    'wp-editor',
                    'wp-components',
                    'wp-data',
                    'wp-api-fetch',
                ],
                filemtime($advanced_script_path)
            );
        }

        // Filtered list shortcode block script
        $filtered_list_script_path = ARTPULSE_PLUGIN_DIR . 'assets/js/filtered-list-shortcode-block.js';
        $filtered_list_script_url  = plugins_url('assets/js/filtered-list-shortcode-block.js', ARTPULSE_PLUGIN_FILE);

        if (file_exists($filtered_list_script_path)) {
            wp_enqueue_script(
                'artpulse-filtered-list-shortcode-block',
                $filtered_list_script_url,
                [
                    'wp-blocks',
                    'wp-element',
                    'wp-editor',
                    'wp-components',
                ],
                filemtime($filtered_list_script_path)
            );
        }

        // AJAX taxonomy filter block script
        $ajax_filter_script_path = ARTPULSE_PLUGIN_DIR . 'assets/js/ajax-filter-block.js';
        $ajax_filter_script_url  = plugins_url('assets/js/ajax-filter-block.js', ARTPULSE_PLUGIN_FILE);

        if (file_exists($ajax_filter_script_path)) {
            wp_enqueue_script(
                'artpulse-ajax-filter-block',
                $ajax_filter_script_url,
                [
                    'wp-blocks',
                    'wp-element',
                    'wp-components',
                    'wp-editor',
                    'wp-api-fetch',
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

        $style_path = ARTPULSE_PLUGIN_DIR . 'assets/css/editor-styles.css';
        $style_url  = plugins_url('assets/css/editor-styles.css', ARTPULSE_PLUGIN_FILE);

        if (file_exists($style_path)) {
            wp_enqueue_style(
                'artpulse-editor-styles',
                $style_url,
                [],
                filemtime($style_path)
            );
        }
    }

    public static function enqueue_frontend_styles()
    {
        if (!defined('ARTPULSE_PLUGIN_FILE') || !defined('ARTPULSE_PLUGIN_DIR')) {
            return;
        }

        // AJAX filter UI styles
        $filter_style_path = ARTPULSE_PLUGIN_DIR . 'assets/css/frontend-filter.css';
        $filter_style_url  = plugins_url('assets/css/frontend-filter.css', ARTPULSE_PLUGIN_FILE);

        if (file_exists($filter_style_path)) {
            wp_enqueue_style(
                'artpulse-frontend-filter',
                $filter_style_url,
                [],
                filemtime($filter_style_path)
            );
        }

        // Filtered item styles (partial)
        $item_style_path = ARTPULSE_PLUGIN_DIR . 'assets/css/frontend-filter-item.css';
        $item_style_url  = plugins_url('assets/css/frontend-filter-item.css', ARTPULSE_PLUGIN_FILE);

        if (file_exists($item_style_path)) {
            wp_enqueue_style(
                'artpulse-frontend-filter-item',
                $item_style_url,
                [],
                filemtime($item_style_path)
            );
        }
    }
}