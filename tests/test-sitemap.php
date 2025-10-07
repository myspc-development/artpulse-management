<?php

use ArtPulse\Core\Rewrites;
use WP_UnitTestCase;

if (!function_exists('wpseo_xml_sitemaps_base_url')) {
    function wpseo_xml_sitemaps_base_url(): string
    {
        return home_url('/sitemap_index.xml');
    }
}

class AP_DirectorySitemapTest extends WP_UnitTestCase
{
    public function test_letter_url_map_includes_all_letters(): void
    {
        $urls = Rewrites::get_directory_letter_urls();

        $this->assertArrayHasKey('artists', $urls);
        $this->assertArrayHasKey('galleries', $urls);
        $this->assertCount(28, $urls['artists']);
        $this->assertArrayHasKey('A', $urls['artists']);
        $this->assertArrayHasKey('#', $urls['artists']);
        $this->assertStringContainsString('/artists/letter/A/', $urls['artists']['A']);
    }

    public function test_directory_sitemap_endpoint_outputs_letter_links(): void
    {
        add_filter('ap_directory_sitemap_should_exit', '__return_false');

        $this->go_to(home_url('/sitemap-artpulse-directories.xml'));
        ob_start();
        Rewrites::maybe_render_directory_sitemap();
        $xml = ob_get_clean();

        remove_filter('ap_directory_sitemap_should_exit', '__return_false');

        $this->assertStringContainsString('/artists/letter/A/', $xml);
        $this->assertStringContainsString('/organizations/letter/%23/', $xml);
    }

    public function test_yoast_filter_registers_directory_sitemap(): void
    {
        $entries = Rewrites::add_to_yoast_sitemap([]);
        $this->assertNotEmpty($entries);

        $sitemap_url = home_url('/sitemap-artpulse-directories.xml');
        $this->assertTrue(
            in_array(
                $sitemap_url,
                array_map(static fn ($entry) => $entry['loc'] ?? '', $entries),
                true
            )
        );
    }

    public function test_rankmath_filter_registers_directory_sitemap(): void
    {
        $links = Rewrites::add_to_rankmath_sitemap([]);
        $this->assertNotEmpty($links);

        $sitemap_url = home_url('/sitemap-artpulse-directories.xml');
        $this->assertTrue(
            in_array(
                $sitemap_url,
                array_map(static fn ($entry) => $entry['loc'] ?? '', $links),
                true
            )
        );
    }
}
