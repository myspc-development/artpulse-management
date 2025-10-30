<?php

namespace ArtPulse\Admin;

use function add_action;
use function add_options_page;
use function add_settings_field;
use function add_settings_section;
use function current_user_can;
use function esc_attr;
use function esc_html__;
use function esc_html;
use function do_settings_sections;
use function get_option;
use function register_setting;
use function settings_fields;
use function submit_button;
use function wp_dropdown_pages;
use function wp_die;
use ArtPulse\Core;

class Settings
{
    private const OPTION = 'artpulse_pages';

    public static function register(): void
    {
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_menu', [self::class, 'register_menu']);
    }

    public static function register_settings(): void
    {
        register_setting(
            'artpulse',
            self::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [self::class, 'sanitize_pages'],
                'default'           => [],
            ]
        );

        add_settings_section(
            'artpulse_page_routing',
            esc_html__('Page Routing', 'artpulse-management'),
            static function (): void {
                echo '<p>' . esc_html__(
                    'Select the WordPress pages that power each ArtPulse experience. These URLs are used for redirects and dashboard links.',
                    'artpulse-management'
                ) . '</p>';
            },
            'artpulse'
        );

        foreach (Core\get_page_options() as $key => $label) {
            add_settings_field(
                $key,
                $label,
                [self::class, 'render_page_field'],
                'artpulse',
                'artpulse_page_routing',
                [
                    'key'   => $key,
                    'label' => $label,
                ]
            );
        }
    }

    public static function register_menu(): void
    {
        add_options_page(
            esc_html__('ArtPulse', 'artpulse-management'),
            esc_html__('ArtPulse', 'artpulse-management'),
            'manage_options',
            'artpulse-settings',
            [self::class, 'render_page']
        );
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'artpulse-management'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('ArtPulse Settings', 'artpulse-management') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('artpulse');
        do_settings_sections('artpulse');
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    /**
     * Sanitize the configured page IDs.
     *
     * @param mixed $value Raw option value.
     *
     * @return array<string, int>
     */
    public static function sanitize_pages($value): array
    {
        if (!is_array($value)) {
            $value = [];
        }

        $sanitized = [];

        foreach (Core\get_page_options() as $key => $_label) {
            $page_id = isset($value[$key]) ? (int) $value[$key] : 0;
            $sanitized[$key] = $page_id > 0 ? $page_id : 0;
        }

        return $sanitized;
    }

    /**
     * Render a dropdown selector for a page mapping.
     *
     * @param array<string, mixed> $args Field arguments.
     */
    public static function render_page_field(array $args): void
    {
        $key    = isset($args['key']) ? (string) $args['key'] : '';
        $label  = isset($args['label']) ? (string) $args['label'] : '';
        $option = get_option(self::OPTION, []);
        $value  = 0;

        if (is_array($option) && isset($option[$key])) {
            $value = (int) $option[$key];
        }

        wp_dropdown_pages([
            'name'              => self::OPTION . '[' . esc_attr($key) . ']',
            'id'                => 'artpulse-' . esc_attr($key),
            'selected'          => $value,
            'show_option_none'  => esc_html__('— Select —', 'artpulse-management'),
            'option_none_value' => '0',
        ]);

        if ($label !== '') {
            printf(
                '<p class="description">%s</p>',
                esc_html__(
                    'Choose the page that should handle this experience. Leave blank to disable the feature.',
                    'artpulse-management'
                )
            );
        }
    }
}

