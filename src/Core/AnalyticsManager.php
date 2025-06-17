<?php
namespace ArtPulse\Core;

class AnalyticsManager
{
    /**
     * Register hook to print analytics snippet.
     */
    public static function register()
    {
        add_action('wp_head', [ self::class, 'printTrackingSnippet' ]);
    }

    /**
     * Output GA4 (or Matomo) snippet if enabled.
     */
    public static function printTrackingSnippet()
    {
        $opts = get_option('artpulse_settings', []);
        if ( empty( $opts['analytics_enabled'] ) ) {
            return;
        }

        // Google Analytics 4
        if ( ! empty( $opts['analytics_gtag_id'] ) ) {
            $id = esc_js( $opts['analytics_gtag_id'] ); ?>
<!-- ArtPulse GA4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $id; ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){ dataLayer.push(arguments); }
  gtag('js', new Date());
  gtag('config', '<?php echo $id; ?>');
</script>
<?php
        }

        // Uncomment and configure below if you prefer Matomo:
        /*
        if ( ! empty( $opts['analytics_matomo_url'] ) && ! empty( $opts['analytics_matomo_siteid'] ) ) {
            $url    = esc_url( rtrim( $opts['analytics_matomo_url'], '/' ) );
            $siteId = intval( $opts['analytics_matomo_siteid'] ); ?>
<!-- ArtPulse Matomo -->
<script>
  var _paq = window._paq = window._paq || [];
  _paq.push(['trackPageView']);
  _paq.push(['enableLinkTracking']);
  (function() {
    var u="<?php echo $url; ?>/";
    _paq.push(['setTrackerUrl', u+'matomo.php']);
    _paq.push(['setSiteId', '<?php echo $siteId; ?>']);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
  })();
</script>
<?php
        }
        */
    }
}
