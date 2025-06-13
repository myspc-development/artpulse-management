<?php
namespace EAD\Integration;

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * WPBakery Integration Helper for ArtPulse Management Plugin
 */
class WPBakery {
    public static function register() {
        add_action('vc_before_init', [self::class, 'register_shortcodes']);
    }

    public static function register_shortcodes() {
        // Register [ead_events]
        vc_map([
            'name' => __('ArtPulse Events List', 'artpulse-management'),
            'base' => 'ead_events',
            'class' => '',
            'category' => __('ArtPulse', 'artpulse-management'),
            'description' => __('Display a list of events from ArtPulse.', 'artpulse-management'),
            'params' => [
                [
                    'type' => 'textfield',
                    'heading' => __('Number of Events', 'artpulse-management'),
                    'param_name' => 'posts_per_page',
                    'value' => 10,
                    'description' => __('Number of events to display.', 'artpulse-management'),
                ],
                [
                    'type' => 'dropdown',
                    'heading' => __('Order By', 'artpulse-management'),
                    'param_name' => 'orderby',
                    'value' => [
                        __('Date', 'artpulse-management') => 'date',
                        __('Title', 'artpulse-management') => 'title',
                    ],
                    'description' => __('Sort by field.', 'artpulse-management'),
                ],
                [
                    'type' => 'dropdown',
                    'heading' => __('Order', 'artpulse-management'),
                    'param_name' => 'order',
                    'value' => [
                        __('Descending', 'artpulse-management') => 'DESC',
                        __('Ascending', 'artpulse-management') => 'ASC',
                    ],
                ],
            ],
        ]);

        // Register [ead_organization_list]
        vc_map([
            'name' => __('ArtPulse Organization Directory', 'artpulse-management'),
            'base' => 'ead_organization_list',
            'class' => '',
            'category' => __('ArtPulse', 'artpulse-management'),
            'description' => __('Display a directory of organizations with map integration.', 'artpulse-management'),
            'params' => [
                [
                    'type' => 'textfield',
                    'heading' => __('Number of Organizations', 'artpulse-management'),
                    'param_name' => 'posts_per_page',
                    'value' => 10,
                    'description' => __('Number of organizations to display.', 'artpulse-management'),
                ],
                [
                    'type' => 'dropdown',
                    'heading' => __('Order By', 'artpulse-management'),
                    'param_name' => 'orderby',
                    'value' => [
                        __('Date', 'artpulse-management') => 'date',
                        __('Title', 'artpulse-management') => 'title',
                    ],
                    'description' => __('Sort by field.', 'artpulse-management'),
                ],
                [
                    'type' => 'dropdown',
                    'heading' => __('Order', 'artpulse-management'),
                    'param_name' => 'order',
                    'value' => [
                        __('Descending', 'artpulse-management') => 'DESC',
                        __('Ascending', 'artpulse-management') => 'ASC',
                    ],
                ],
            ],
        ]);
    }
}
