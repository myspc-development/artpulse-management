<?php

namespace ArtPulse\Frontend;

use function add_query_arg;
use function esc_url_raw;
use function file_exists;
use function get_query_var;
use function home_url;
use function trailingslashit;

class ArtistRequestStatusRoute
{
    private const QUERY_VAR = 'ap_artist_request_status';
    private const TEMPLATE  = 'templates/dashboard/artist-request-status.php';
    private const BASE_PATH = '/artist-request/status/';

    public static function register(): void
    {
        add_rewrite_rule('^artist-request/status/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top');
        add_filter('query_vars', [self::class, 'register_query_vars']);
        add_filter('template_include', [self::class, 'maybe_use_template']);
    }

    public static function register_query_vars(array $vars): array
    {
        $vars[] = self::QUERY_VAR;
        $vars[] = 'journey';

        return array_values(array_unique($vars));
    }

    public static function maybe_use_template(string $template): string
    {
        if ((int) get_query_var(self::QUERY_VAR, 0) !== 1) {
            return $template;
        }

        $plugin_template = trailingslashit(ARTPULSE_PLUGIN_DIR) . self::TEMPLATE;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        return $template;
    }

    public static function get_status_url(string $journey = 'artist'): string
    {
        $journey_slug = $journey === 'organization' ? 'organization' : 'artist';
        $base         = home_url(self::BASE_PATH);
        $url          = add_query_arg('journey', $journey_slug, $base);

        return esc_url_raw($url);
    }
}
