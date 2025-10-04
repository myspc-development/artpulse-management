<?php

namespace ArtPulse\Frontend;

class TemplateLoader
{
    /**
     * Register hooks to integrate template overrides for Salient.
     */
    public static function register()
    {
        if (did_action('init')) {
            self::maybe_hook_templates();
        } else {
            add_action('init', [self::class, 'maybe_hook_templates']);
        }
    }

    /**
     * Hook template filters when running under the Salient theme.
     */
    public static function maybe_hook_templates()
    {
        if (!self::is_salient_theme()) {
            return;
        }

        add_filter('single_template', [self::class, 'filter_single_template']);
        add_filter('archive_template', [self::class, 'filter_archive_template']);
        add_filter('taxonomy_template', [self::class, 'filter_taxonomy_template']);
    }

    /**
     * Load custom single templates for ArtPulse post types.
     */
    public static function filter_single_template($template)
    {
        if (!is_singular()) {
            return $template;
        }

        $post = get_queried_object();
        if (!$post) {
            return $template;
        }

        $map = [
            'artpulse_event'   => 'content-artpulse_event.php',
            'artpulse_artist'  => 'content-artpulse_artist.php',
            'artpulse_artwork' => 'content-artpulse_artwork.php',
            'artpulse_org'     => 'content-artpulse_org.php',
        ];

        $post_type = get_post_type($post);
        if (!isset($map[$post_type])) {
            return $template;
        }

        $theme_template = locate_template($map[$post_type]);
        if (!empty($theme_template)) {
            return $theme_template;
        }

        $plugin_template = self::get_plugin_template($map[$post_type]);
        if ($plugin_template) {
            return $plugin_template;
        }

        return $template;
    }

    /**
     * Load custom archive templates for ArtPulse post types.
     */
    public static function filter_archive_template($template)
    {
        if (!is_post_type_archive()) {
            return $template;
        }

        $map = [
            'artpulse_event'   => 'archive-artpulse_event.php',
            'artpulse_artist'  => 'archive-artpulse_artist.php',
            'artpulse_artwork' => 'archive-artpulse_artwork.php',
            'artpulse_org'     => 'archive-artpulse_org.php',
        ];

        foreach ($map as $post_type => $file) {
            if (is_post_type_archive($post_type)) {
                $theme_template = locate_template($file);
                if (!empty($theme_template)) {
                    return $theme_template;
                }

                $plugin_template = self::get_plugin_template($file);
                if ($plugin_template) {
                    return $plugin_template;
                }
            }
        }

        return $template;
    }

    /**
     * Load taxonomy templates when available.
     */
    public static function filter_taxonomy_template($template)
    {
        if (!is_tax()) {
            return $template;
        }

        $taxonomy = get_queried_object();
        if (!$taxonomy || empty($taxonomy->taxonomy)) {
            return $template;
        }

        $map = [
            'artpulse_event_type' => 'taxonomy-artpulse_event_type.php',
            'artpulse_medium'     => 'taxonomy-artpulse_medium.php',
        ];

        $taxonomy_name = $taxonomy->taxonomy;
        if (!isset($map[$taxonomy_name])) {
            return $template;
        }

        $theme_template = locate_template($map[$taxonomy_name]);
        if (!empty($theme_template)) {
            return $theme_template;
        }

        $plugin_template = self::get_plugin_template($map[$taxonomy_name]);
        if ($plugin_template) {
            return $plugin_template;
        }

        return $template;
    }

    /**
     * Resolve a template path within the plugin.
     */
    private static function get_plugin_template($template)
    {
        $path = trailingslashit(ARTPULSE_PLUGIN_DIR) . 'templates/salient/' . $template;

        return file_exists($path) ? $path : '';
    }

    /**
     * Determine if Salient or a Salient child theme is active.
     */
    private static function is_salient_theme()
    {
        $theme = wp_get_theme();

        if (empty($theme)) {
            return false;
        }

        $template_slug = method_exists($theme, 'get_template') ? $theme->get_template() : $theme->get('Template');

        $identifiers = [
            strtolower((string) $theme->get('Name')),
            strtolower((string) $theme->get_stylesheet()),
            strtolower((string) $template_slug),
        ];

        if (in_array('salient', $identifiers, true)) {
            return true;
        }

        $parent = $theme->parent();
        if ($parent) {
            $parent_template = method_exists($parent, 'get_template') ? $parent->get_template() : $parent->get('Template');

            $parent_identifiers = [
                strtolower((string) $parent->get('Name')),
                strtolower((string) $parent->get_stylesheet()),
                strtolower((string) $parent_template),
            ];

            if (in_array('salient', $parent_identifiers, true)) {
                return true;
            }
        }

        return false;
    }
}
