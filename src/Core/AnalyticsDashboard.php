<?php
namespace ArtPulse\Core;

class AnalyticsDashboard
{
    /**
     * Hook into admin_menu to register our Analytics page.
     */
    public static function register()
    {
        add_action('admin_menu', [ self::class, 'addMenu' ]);
    }

    /**
     * Add a submenu under “Settings” for ArtPulse Analytics.
     */
    public static function addMenu()
    {
        add_submenu_page(
            'options-general.php',               // parent slug
            __('ArtPulse Analytics','artpulse'), // page title
            __('Analytics','artpulse'),          // menu title
            'manage_options',                    // capability
            'artpulse-analytics',                // menu slug
            [ self::class, 'renderPage' ]        // callback
        );
    }

    /**
     * Render the iframe or a notice if not configured.
     */
    public static function renderPage()
    {
        $opts = get_option('artpulse_settings', []);

        if ( empty( $opts['analytics_embed_enabled'] ) || empty( $opts['analytics_embed_url'] ) ) {
            echo '<div class="notice notice-warning"><p>';
            esc_html_e(
                'Please enable and configure your Analytics Dashboard Embed URL on the ArtPulse Settings page first.',
                'artpulse'
            );
            echo '</p></div>';
            return;
        }

        $url = esc_url( $opts['analytics_embed_url'] );
        ?>
        <div class="wrap">
          <h1><?php _e('ArtPulse Analytics','artpulse'); ?></h1>
          <iframe 
            src="<?php echo $url; ?>" 
            width="100%" 
            height="800" 
            frameborder="0" 
            style="border:0"
            allowfullscreen>
          </iframe>
        </div>
        <?php
    }
}
