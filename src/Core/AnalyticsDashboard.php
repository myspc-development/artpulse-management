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
            'options-general.php',
            __('ArtPulse Analytics', 'artpulse-management'),
            __('Analytics', 'artpulse-management'),
            'manage_options',
            'artpulse-analytics',
            [ self::class, 'renderPage' ]
        );
    }

    /**
     * Render the iframe or a notice if not configured.
     */
    public static function renderPage()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'artpulse-management'));
        }

        $opts = get_option('artpulse_settings', []);

        if ( empty( $opts['analytics_embed_enabled'] ) || empty( $opts['analytics_embed_url'] ) ) {
            echo '<div class="notice notice-warning"><p>';
            esc_html_e(
                'Please enable and configure your Analytics Dashboard Embed URL on the ArtPulse Settings page first.',
                'artpulse-management'
            );
            echo '</p></div>';
            return;
        }

        $url = esc_url( $opts['analytics_embed_url'] );
        ?>
        <div class="wrap">
          <h1><?php esc_html_e('ArtPulse Analytics', 'artpulse-management'); ?></h1>
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
