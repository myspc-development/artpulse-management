<?php

namespace ArtPulse\Frontend\Shared;

use ArtPulse\Core\AuditLogger;
use function get_current_user_id;
use function sanitize_key;

/**
 * Registry for portfolio widgets used in builders and public rendering.
 */
final class PortfolioWidgetRegistry
{
    public static function defaults(): array
    {
        return [
            'hero'     => ['enabled' => true,  'label' => __('Hero', 'artpulse-management')],
            'about'    => ['enabled' => true,  'label' => __('About', 'artpulse-management')],
            'gallery'  => ['enabled' => true,  'label' => __('Gallery', 'artpulse-management')],
            'events'   => ['enabled' => true,  'label' => __('Upcoming Events', 'artpulse-management')],
            'map'      => ['enabled' => false, 'label' => __('Map & Location', 'artpulse-management')],
            'contact'  => ['enabled' => true,  'label' => __('Contact & Links', 'artpulse-management')],
            'press'    => ['enabled' => false, 'label' => __('Press/Highlights', 'artpulse-management')],
            'sponsors' => ['enabled' => false, 'label' => __('Sponsors', 'artpulse-management')],
        ];
    }

    public static function for_post(int $post_id): array
    {
        $stored   = (array) get_post_meta($post_id, '_ap_widgets', true);
        $defaults = self::defaults();

        $order = [];

        foreach ($stored as $key => $config) {
            $normalized = sanitize_key($key);
            if ($normalized === '' || !array_key_exists($normalized, $defaults)) {
                continue;
            }

            if (!in_array($normalized, $order, true)) {
                $order[] = $normalized;
            }
        }

        foreach (array_keys($defaults) as $key) {
            if (!in_array($key, $order, true)) {
                $order[] = $key;
            }
        }

        $merged = [];

        foreach ($order as $key) {
            $stored_config = [];
            if (isset($stored[$key]) && is_array($stored[$key])) {
                $stored_config = $stored[$key];
            }

            $merged[$key]               = array_replace_recursive($defaults[$key], $stored_config);
            $merged[$key]['enabled']    = (bool) ($merged[$key]['enabled'] ?? false);
        }

        return apply_filters('artpulse/portfolio_widgets', $merged, $post_id);
    }

    /**
     * Retrieve widgets as configured for public rendering.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function public_widgets(int $post_id): array
    {
        $stored   = get_post_meta($post_id, '_ap_widgets', true);
        $defaults = self::defaults();

        if (!is_array($stored) || empty($stored)) {
            return $defaults;
        }

        $ordered = [];

        foreach ($stored as $key => $config) {
            $candidate = $key;
            if (is_int($key) && is_array($config)) {
                $candidate = $config['key'] ?? $config['id'] ?? $config['slug'] ?? '';
            }

            $normalized = sanitize_key((string) $candidate);
            if ($normalized === '' || !array_key_exists($normalized, $defaults)) {
                continue;
            }

            $widget_config = $defaults[$normalized];
            if (is_array($config)) {
                $widget_config = array_replace_recursive($widget_config, $config);
            }

            $widget_config['enabled'] = (bool) ($widget_config['enabled'] ?? false);

            $ordered[$normalized] = $widget_config;
        }

        return !empty($ordered) ? $ordered : $defaults;
    }

    public static function save(int $post_id, array $widgets): void
    {
        $previous   = (array) get_post_meta($post_id, '_ap_widgets', true);
        $normalized = self::normalizeForStorage($widgets);

        update_post_meta($post_id, '_ap_widgets', $normalized);

        $diff = self::diffWidgets($previous, $normalized);
        if (!empty($diff)) {
            AuditLogger::info('portfolio.widgets.update', [
                'post_id' => $post_id,
                'user_id' => get_current_user_id(),
                'changes' => $diff,
            ]);
        }
    }

    private static function diffWidgets(array $before, array $after): array
    {
        $diff = [];

        $before_enabled = self::enabledMap($before);
        $after_enabled  = self::enabledMap($after);

        if ($before_enabled !== $after_enabled) {
            $diff['enabled'] = [
                'before' => $before_enabled,
                'after'  => $after_enabled,
            ];
        }

        $before_order = self::normalizeOrder($before);
        $after_order  = self::normalizeOrder($after);

        if ($before_order !== $after_order) {
            $diff['order'] = [
                'before' => $before_order,
                'after'  => $after_order,
            ];
        }

        return $diff;
    }

    private static function enabledMap(array $widgets): array
    {
        $map = [];

        foreach (self::defaults() as $key => $default) {
            $map[$key] = (bool) ($widgets[$key]['enabled'] ?? $default['enabled']);
        }

        return $map;
    }

    private static function normalizeOrder(array $widgets): array
    {
        $order = [];
        $defaults = array_keys(self::defaults());

        foreach ($widgets as $key => $config) {
            if (in_array($key, $defaults, true) && !in_array($key, $order, true)) {
                $order[] = $key;
            }
        }

        foreach ($defaults as $key) {
            if (!in_array($key, $order, true)) {
                $order[] = $key;
            }
        }

        return $order;
    }

    private static function normalizeForStorage(array $widgets): array
    {
        $normalized = [];
        $defaults   = self::defaults();

        foreach ($widgets as $key => $config) {
            $candidate = $key;
            if (!is_string($candidate) || $candidate === '') {
                if (is_array($config)) {
                    $candidate = $config['key'] ?? $config['id'] ?? $config['slug'] ?? '';
                } else {
                    $candidate = '';
                }
            }

            $slug = sanitize_key((string) $candidate);
            if ($slug === '' || !array_key_exists($slug, $defaults)) {
                continue;
            }

            $config_array = is_array($config) ? $config : [];
            $config_array['enabled'] = isset($config_array['enabled'])
                ? (bool) $config_array['enabled']
                : (bool) ($defaults[$slug]['enabled'] ?? false);

            $normalized[$slug] = $config_array;
        }

        return $normalized;
    }
}
