<?php

namespace ArtPulse\Tests\Frontend;

use ArtPulse\Frontend\OrgsDirectory;

class OrgsDirectoryTest extends \WP_UnitTestCase
{
    protected function tearDown(): void
    {
        OrgsDirectory::flush_cache();
        $_GET = [];
        parent::tearDown();
    }

    public function test_normalized_letter_strips_articles(): void
    {
        $this->assertSame('B', OrgsDirectory::get_normalized_letter('The Blue Whale'));
        $this->assertSame('A', OrgsDirectory::get_normalized_letter('An Apple Farm'));
        $this->assertSame('#', OrgsDirectory::get_normalized_letter('123 Collective'));
    }

    public function test_taxonomy_filter_limits_results(): void
    {
        $music = wp_insert_term('Music', 'organization_category');
        $visual = wp_insert_term('Visual', 'organization_category');

        $this->assertFalse(is_wp_error($music));
        $this->assertFalse(is_wp_error($visual));
        $this->assertIsArray($music);
        $this->assertIsArray($visual);

        $alpha = self::factory()->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
            'post_title'  => 'Alpha Arts',
        ]);
        $beta = self::factory()->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
            'post_title'  => 'Beta Collective',
        ]);

        wp_set_object_terms($alpha, [$music['term_id']], 'organization_category');
        wp_set_object_terms($beta, [$visual['term_id']], 'organization_category');

        $_GET['letter'] = 'All';

        $output = do_shortcode('[ap_orgs_directory taxonomy="organization_category:' . $music['term_id'] . '" show_search="false" letters="A,B,All"]');

        $this->assertStringContainsString('Alpha Arts', $output);
        $this->assertStringNotContainsString('Beta Collective', $output);
    }

    public function test_pagination_respects_page_query(): void
    {
        $first = self::factory()->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
            'post_title'  => 'Atlas Center',
        ]);
        $second = self::factory()->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
            'post_title'  => 'Beacon Arts',
        ]);
        $third = self::factory()->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
            'post_title'  => 'Civic Studio',
        ]);

        $this->assertNotEquals(0, $first + $second + $third);

        $_GET['letter'] = 'All';
        $_GET['page'] = 2;

        $output = do_shortcode('[ap_orgs_directory per_page="1" show_search="false" letters="A-Z,All"]');

        $this->assertStringContainsString('Beacon Arts', $output);
        $this->assertStringNotContainsString('Atlas Center', $output);
        $this->assertStringNotContainsString('Civic Studio', $output);
    }
}
