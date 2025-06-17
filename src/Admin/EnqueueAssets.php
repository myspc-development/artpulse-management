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
        // Sidebar taxonomy selector script
        $sidebar_script_path = __DIR__ . '/../../assets/js/sidebar-taxonomies.js';
        $sidebar_script_url  = plugins_url('assets/js/sidebar-taxonomies.js', dirname(__DIR__, 3) . '/artpulse-management.php');

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

        // Advanced taxonomy filter block script
        $advanced_script_path = __DIR__ . '/../../assets/js/advanced-taxonomy-filter-block.js';
        $advanced_script_url  = plugins_url('assets/js/advanced-taxonomy-filter-block.js', dirname(__DIR__, 3) . '/artpulse-management.php');

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

        // Filtered list shortcode block script
        $filtered_list_script_path = __DIR__ . '/../../assets/js/filtered-list-shortcode-block.js';
        $filtered_list_script_url  = plugins_url('assets/js/filtered-list-shortcode-block.js', dirname(__DIR__, 3) . '/artpulse-management.php');

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

        // AJAX taxonomy filter block script
        $ajax_filter_script_path = __DIR__ . '/../../assets/js/ajax-filter-block.js';
        $ajax_filter_script_url  = plugins_url('assets/js/ajax-filter-block.js', dirname(__DIR__, 3) . '/artpulse-management.php');

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

    public static function enqueue_block_editor_styles()
    {
        $style_path = __DIR__ . '/../../assets/css/editor-styles.css';
        $style_url  = plugins_url('assets/css/editor-styles.css', dirname(__DIR__, 3) . '/artpulse-management.php');

        wp_enqueue_style(
            'artpulse-editor-styles',
            $style_url,
            [],
            filemtime($style_path)
        );
    }

    public static function enqueue_frontend_styles()
    {
        // AJAX filter UI styles
        $filter_style_path = __DIR__ . '/../../assets/css/frontend-filter.css';
        $filter_style_url  = plugins_url('assets/css/frontend-filter.css', dirname(__DIR__, 3) . '/artpulse-management.php');

        wp_enqueue_style(
            'artpulse-frontend-filter',
            $filter_style_url,
            [],
            filemtime($filter_style_path)
        );

        // Filtered item styles (partial)
        $item_style_path = __DIR__ . '/../../assets/css/frontend-filter-item.css';
        $item_style_url  = plugins_url('assets/css/frontend-filter-item.css', dirname(__DIR__, 3) . '/artpulse-management.php');

        wp_enqueue_style(
            'artpulse-frontend-filter-item',
            $item_style_url,
            [],
            filemtime($item_style_path)
        );
    }
}
