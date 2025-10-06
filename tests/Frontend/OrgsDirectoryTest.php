<?php

use ArtPulse\Core\TitleTools;
use ArtPulse\Frontend\OrgsDirectory;
use WP_UnitTestCase;

class OrgsDirectoryTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDirectoryState();
    }

    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    public function test_shortcode_outputs_directory_structure(): void
    {
        $alpha = self::factory()->post->create([
            'post_type'  => 'artpulse_org',
            'post_title' => 'Atlas Center',
            'post_status'=> 'publish',
        ]);
        $beta = self::factory()->post->create([
            'post_type'  => 'artpulse_org',
            'post_title' => 'Beacon Arts',
            'post_status'=> 'publish',
        ]);

        TitleTools::update_post_letter($alpha);
        TitleTools::update_post_letter($beta);

        $_GET['letter'] = 'all';
        $output = OrgsDirectory::render_shortcode(['per_page' => 10]);
        unset($_GET['letter']);

        $this->assertStringContainsString('<div class="ap-directory ap-directory--orgs" data-letter="all">', $output);
        $this->assertStringContainsString('<nav class="ap-directory__letters"', $output);
        $this->assertStringContainsString('aria-current="page">All</a>', $output);
        $this->assertStringContainsString('<ul class="ap-directory__list">', $output);
        $this->assertStringContainsString('Atlas Center', $output);
        $this->assertStringContainsString('Beacon Arts', $output);
    }

    public function test_taxonomy_filter_limits_results(): void
    {
        $music = self::factory()->term->create([
            'taxonomy' => 'organization_category',
            'slug'     => 'music',
            'name'     => 'Music',
        ]);
        $visual = self::factory()->term->create([
            'taxonomy' => 'organization_category',
            'slug'     => 'visual',
            'name'     => 'Visual',
        ]);

        $alpha = self::factory()->post->create([
            'post_type'  => 'artpulse_org',
            'post_title' => 'Alpha Arts',
            'post_status'=> 'publish',
        ]);
        $beta = self::factory()->post->create([
            'post_type'  => 'artpulse_org',
            'post_title' => 'Beta Collective',
            'post_status'=> 'publish',
        ]);

        wp_set_object_terms($alpha, [$music], 'organization_category');
        wp_set_object_terms($beta, [$visual], 'organization_category');

        TitleTools::update_post_letter($alpha);
        TitleTools::update_post_letter($beta);

        $_GET['letter'] = 'all';
        $_GET['tax'] = [ 'organization_category' => ['music'] ];
        $output = OrgsDirectory::render_shortcode(['per_page' => 10]);
        unset($_GET['letter'], $_GET['tax']);

        $this->assertStringContainsString('Alpha Arts', $output);
        $this->assertStringNotContainsString('Beta Collective', $output);
    }

    public function test_taxonomy_filter_skips_unregistered_taxonomies(): void
    {
        self::factory()->term->create([
            'taxonomy' => 'organization_category',
            'slug'     => 'music',
            'name'     => 'Music',
        ]);

        $_GET['tax'] = [
            'organization_category' => ['music'],
            'post_tag'              => ['featured'],
        ];

        $reflection = new \ReflectionClass(OrgsDirectory::class);
        $method = $reflection->getMethod('parse_tax_filters');
        $method->setAccessible(true);

        $filters = $method->invoke(null);

        unset($_GET['tax']);

        $this->assertArrayHasKey('organization_category', $filters);
        $this->assertSame(['music'], $filters['organization_category']);
        $this->assertArrayNotHasKey('post_tag', $filters);
    }

    public function test_pagination_respects_paged_query(): void
    {
        $first = self::factory()->post->create([
            'post_type'  => 'artpulse_org',
            'post_title' => 'Atlas Center',
            'post_status'=> 'publish',
        ]);
        $second = self::factory()->post->create([
            'post_type'  => 'artpulse_org',
            'post_title' => 'Beacon Arts',
            'post_status'=> 'publish',
        ]);
        $third = self::factory()->post->create([
            'post_type'  => 'artpulse_org',
            'post_title' => 'Civic Studio',
            'post_status'=> 'publish',
        ]);

        TitleTools::update_post_letter($first);
        TitleTools::update_post_letter($second);
        TitleTools::update_post_letter($third);

        $_GET['letter'] = 'all';
        $_GET['paged'] = 2;
        $output = OrgsDirectory::render_shortcode(['per_page' => 1]);
        unset($_GET['letter'], $_GET['paged']);

        $this->assertStringContainsString('Beacon Arts', $output);
        $this->assertStringNotContainsString('Atlas Center', $output);
        $this->assertStringNotContainsString('Civic Studio', $output);
    }

    private function resetDirectoryState(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ap_dir:%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ap_dir:%'");
        delete_option('ap_directory_cache_versions');
        $_GET = [];
    }
}
