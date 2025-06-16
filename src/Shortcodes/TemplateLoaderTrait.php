<?php
namespace EAD\Shortcodes;

/**
 * Utility trait to load shortcode templates with theme overrides.
 */
trait TemplateLoaderTrait {
    /**
     * Render a template, checking the active theme first.
     *
     * @param string $template Name of the template file within ead-templates.
     * @param array  $vars     Variables to extract into the template scope.
     * @return string|false    Rendered HTML or false if template missing.
     */
    protected static function load_template( string $template, array $vars = [] ) {
        $theme_template = locate_template( 'ead-templates/' . $template );
        $plugin_template = trailingslashit( EAD_PLUGIN_DIR_PATH ) . 'templates/' . $template;

        $path = '';
        if ( $theme_template ) {
            $path = $theme_template;
        } elseif ( file_exists( $plugin_template ) ) {
            $path = $plugin_template;
        }

        if ( ! $path ) {
            return false;
        }

        if ( ! empty( $vars ) ) {
            extract( $vars, EXTR_SKIP );
        }

        ob_start();
        include $path;
        return ob_get_clean();
    }
}

