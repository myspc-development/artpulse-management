<?php
namespace ArtPulse\Core;

/**
 * Handles custom rewrite rules and sitemap integrations for ArtPulse directories.
 */
class Rewrites
{
    private const DIRECTORY_SITEMAP_QUERY = 'ap_directory_sitemap';

    /**
     * Bootstrap the rewrite subsystem.
     */
    public static function register(): void
    {
        add_action('init', [self::class, 'add_rewrite_tags']);
        add_action('init', [self::class, 'add_rewrite_rules']);
        add_action('init', [self::class, 'register_directory_sitemap_route']);
        add_filter('query_vars', [self::class, 'register_query_vars']);
        add_action('template_redirect', [self::class, 'maybe_render_directory_sitemap']);
        add_filter('wpseo_sitemap_entries', [self::class, 'add_to_yoast_sitemap']);
        add_filter('rank_math/sitemap/index/links', [self::class, 'add_to_rankmath_sitemap']);
    }

    /**
     * Register rewrite tags used by the directory rewrites.
     */
    public static function add_rewrite_tags(): void
    {
        add_rewrite_tag('%ap_letter%', '([A-Za-z]|%23|#|all)');
    }

    /**
     * Register rewrite rules for artist and gallery directory letters.
     */
    public static function add_rewrite_rules(): void
    {
        $artists_base = self::get_directory_base_slug('artists');
        $orgs_base    = self::get_directory_base_slug('galleries');
        $pattern      = 'letter/([A-Za-z]|%23|#|all)';

        if ($artists_base !== '') {
            add_rewrite_rule(
                '^' . $artists_base . '/' . $pattern . '/?$',
                'index.php?pagename=' . $artists_base . '&ap_letter=$matches[1]&ap_directory=artists',
                'top'
            );
        }

        if ($orgs_base !== '') {
            add_rewrite_rule(
                '^' . $orgs_base . '/' . $pattern . '/?$',
                'index.php?pagename=' . $orgs_base . '&ap_letter=$matches[1]&ap_directory=galleries',
                'top'
            );
        }
    }

    /**
     * Register the custom sitemap endpoint.
     */
    public static function register_directory_sitemap_route(): void
    {
        add_rewrite_rule('^sitemap-artpulse-directories\.xml$', 'index.php?' . self::DIRECTORY_SITEMAP_QUERY . '=1', 'top');
    }

    /**
     * Add query vars for rewrites and sitemap.
     */
    public static function register_query_vars(array $vars): array
    {
        $vars[] = 'ap_letter';
        $vars[] = 'letter';
        $vars[] = 'ap_directory';
        $vars[] = self::DIRECTORY_SITEMAP_QUERY;

        return array_values(array_unique($vars));
    }

    /**
     * Render the fallback sitemap when requested.
     */
    public static function maybe_render_directory_sitemap(): void
    {
        if ((int) get_query_var(self::DIRECTORY_SITEMAP_QUERY, 0) !== 1) {
            return;
        }

        $urls = self::get_directory_letter_urls();

        header('Content-Type: application/xml; charset=UTF-8');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $type => $letter_urls) {
            foreach ($letter_urls as $letter => $url) {
                $lastmod = self::get_directory_letter_lastmod($type, $letter);
                if (!$lastmod) {
                    $lastmod = gmdate('c');
                }

                echo '  <url>' . "\n";
                echo '    <loc>' . esc_url($url) . '</loc>' . "\n";
                echo '    <lastmod>' . esc_html($lastmod) . '</lastmod>' . "\n";
                echo '    <changefreq>weekly</changefreq>' . "\n";
                echo '  </url>' . "\n";
            }
        }

        echo '</urlset>';
        if (apply_filters('ap_directory_sitemap_should_exit', true)) {
            exit;
        }
    }

    /**
     * Include the directory sitemap in Yoast's sitemap index.
     */
    public static function add_to_yoast_sitemap(array $entries): array
    {
        if (!function_exists('wpseo_xml_sitemaps_base_url')) {
            return $entries;
        }

        $sitemap_url = esc_url_raw(self::get_directory_sitemap_url());
        foreach ($entries as $entry) {
            if (isset($entry['loc']) && $entry['loc'] === $sitemap_url) {
                return $entries;
            }
        }

        $entries[] = [
            'loc'     => $sitemap_url,
            'lastmod' => gmdate('c'),
        ];

        return $entries;
    }

    /**
     * Include the directory sitemap in RankMath's sitemap index.
     */
    public static function add_to_rankmath_sitemap(array $links): array
    {
        $sitemap_url = esc_url_raw(self::get_directory_sitemap_url());
        foreach ($links as $link) {
            if (is_array($link) && isset($link['loc']) && $link['loc'] === $sitemap_url) {
                return $links;
            }
        }

        $links[] = [
            'loc' => $sitemap_url,
            'mod' => gmdate('c'),
        ];

        return $links;
    }

    /**
     * Helper to retrieve the base slug for a directory type.
     */
    public static function get_directory_base_slug(string $type): string
    {
        $default = $type === 'artists' ? 'artists' : 'galleries';
        $slug    = apply_filters('ap_' . $type . '_directory_base', $default);

        return trim((string) $slug, '/');
    }

    /**
     * Build the URL for a specific directory letter page.
     */
    public static function get_directory_letter_url(string $type, string $letter, array $query = []): string
    {
        $base = self::get_directory_base_slug($type);
        if ('' === $base) {
            return home_url('/');
        }

        $normalized = strtolower($letter) === 'all' ? 'all' : strtoupper(rawurldecode($letter));
        if ('#' === $normalized) {
            $segment = rawurlencode('#');
        } elseif ('all' === strtolower($letter)) {
            $segment = 'all';
        } elseif (preg_match('/^[A-Z]$/', $normalized)) {
            $segment = strtoupper($normalized);
        } else {
            $segment = rawurlencode('#');
        }

        $path = trailingslashit($base) . 'letter/' . trailingslashit($segment);

        $url = home_url('/' . ltrim($path, '/'));
        if (!empty($query)) {
            $url = add_query_arg($query, $url);
        }

        return $url;
    }

    /**
     * Return all letter URLs for both directory types.
     */
    public static function get_directory_letter_urls(): array
    {
        $letters = array_merge(['all'], range('A', 'Z'), ['#']);
        $urls    = [];

        foreach (array_keys(self::get_directory_types()) as $type) {
            foreach ($letters as $letter) {
                if (!isset($urls[$type])) {
                    $urls[$type] = [];
                }

                $urls[$type][$letter] = self::get_directory_letter_url($type, $letter);
            }
        }

        return $urls;
    }

    /**
     * URL of the combined directory sitemap.
     */
    public static function get_directory_sitemap_url(): string
    {
        return home_url('/sitemap-artpulse-directories.xml');
    }

    /**
     * Map directory types to their corresponding post types.
     */
    private static function get_directory_types(): array
    {
        return [
            'artists'   => 'artpulse_artist',
            'galleries' => 'artpulse_org',
        ];
    }

    /**
     * Fetch the most recent modification time for a given directory letter bucket.
     */
    private static function get_directory_letter_lastmod(string $type, string $letter): ?string
    {
        static $cache = [];

        $letter = strtolower($letter) === 'all' ? 'all' : ('#' === $letter ? '#' : strtoupper($letter));

        if (!isset($cache[$type])) {
            $cache[$type] = self::build_directory_letter_lastmod_cache($type);
        }

        $map = $cache[$type];

        if ('all' === $letter) {
            return $map['_all'] ?? null;
        }

        return $map[$letter] ?? ($map['_all'] ?? null);
    }

    /**
     * Build a cache of last modification times keyed by directory letter.
     */
    private static function build_directory_letter_lastmod_cache(string $type): array
    {
        global $wpdb;

        $types = self::get_directory_types();
        if (!isset($types[$type])) {
            return [];
        }

        $post_type = $types[$type];

        $cache = [];

        $all_modified = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            $post_type
        ));

        if (!empty($all_modified)) {
            $formatted = mysql2date('c', $all_modified, false);
            if ($formatted) {
                $cache['_all'] = $formatted;
            }
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(NULLIF(m.meta_value, ''), '#') AS letter, MAX(p.post_modified_gmt) AS lastmod_gmt
             FROM {$wpdb->posts} AS p
             LEFT JOIN {$wpdb->postmeta} AS m ON (p.ID = m.post_id AND m.meta_key = %s)
             WHERE p.post_type = %s AND p.post_status = 'publish'
             GROUP BY COALESCE(NULLIF(m.meta_value, ''), '#')",
            TitleTools::META_KEY,
            $post_type
        ), ARRAY_A);

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $letter = isset($row['letter']) ? (string) $row['letter'] : '#';
                $letter = strtolower($letter) === 'all' ? 'all' : ('#' === $letter ? '#' : strtoupper($letter));

                if ('all' !== $letter && '#' !== $letter && !preg_match('/^[A-Z]$/', $letter)) {
                    continue;
                }

                if (empty($row['lastmod_gmt'])) {
                    continue;
                }

                $formatted = mysql2date('c', $row['lastmod_gmt'], false);
                if ($formatted) {
                    $cache[$letter] = $formatted;
                }
            }
        }

        return $cache;
    }
}
