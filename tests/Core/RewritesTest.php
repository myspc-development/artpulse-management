<?php

use ArtPulse\Core\Rewrites;
use ArtPulse\Core\TitleTools;
use WP_UnitTestCase;

class RewritesTest extends WP_UnitTestCase
{
    public function test_directory_sitemap_includes_lastmod_and_changefreq()
    {
        $artist_latest = '2024-01-05 12:00:00';
        $gallery_latest = '2024-03-02 09:00:00';

        $artist_id = self::factory()->post->create([
            'post_type'         => 'artpulse_artist',
            'post_status'       => 'publish',
            'post_title'        => 'Alpha Artist',
            'post_date'         => '2024-01-01 00:00:00',
            'post_date_gmt'     => '2024-01-01 00:00:00',
            'post_modified'     => $artist_latest,
            'post_modified_gmt' => $artist_latest,
        ]);
        update_post_meta($artist_id, TitleTools::META_KEY, 'A');

        $symbol_artist_id = self::factory()->post->create([
            'post_type'         => 'artpulse_artist',
            'post_status'       => 'publish',
            'post_title'        => '# Symbol Artist',
            'post_date'         => '2024-01-02 00:00:00',
            'post_date_gmt'     => '2024-01-02 00:00:00',
            'post_modified'     => '2024-01-04 00:00:00',
            'post_modified_gmt' => '2024-01-04 00:00:00',
        ]);
        update_post_meta($symbol_artist_id, TitleTools::META_KEY, '#');

        $gallery_id = self::factory()->post->create([
            'post_type'         => 'artpulse_org',
            'post_status'       => 'publish',
            'post_title'        => 'Beta Gallery',
            'post_date'         => '2024-02-01 00:00:00',
            'post_date_gmt'     => '2024-02-01 00:00:00',
            'post_modified'     => $gallery_latest,
            'post_modified_gmt' => $gallery_latest,
        ]);
        update_post_meta($gallery_id, TitleTools::META_KEY, 'B');

        set_query_var('ap_directory_sitemap', 1);

        add_filter('ap_directory_sitemap_should_exit', '__return_false');
        ob_start();
        Rewrites::maybe_render_directory_sitemap();
        $xml_output = ob_get_clean();
        remove_filter('ap_directory_sitemap_should_exit', '__return_false');

        $this->assertNotEmpty($xml_output);

        $xml = new SimpleXMLElement($xml_output);
        $this->assertSame('urlset', $xml->getName());

        $this->assertCount(56, $xml->url);

        foreach ($xml->url as $url_node) {
            $this->assertNotEmpty((string) $url_node->lastmod);
            $this->assertSame('weekly', (string) $url_node->changefreq);
        }

        $artist_url = $this->find_url_node($xml, home_url('/artists/letter/A/'));
        $this->assertNotNull($artist_url);
        $this->assertSame(gmdate('c', strtotime($artist_latest . ' UTC')), (string) $artist_url->lastmod);

        $gallery_url = $this->find_url_node($xml, home_url('/organizations/letter/B/'));
        $this->assertNotNull($gallery_url);
        $this->assertSame(gmdate('c', strtotime($gallery_latest . ' UTC')), (string) $gallery_url->lastmod);
    }

    private function find_url_node(SimpleXMLElement $xml, string $loc): ?SimpleXMLElement
    {
        foreach ($xml->url as $url_node) {
            if ((string) $url_node->loc === $loc) {
                return $url_node;
            }
        }

        return null;
    }
}
