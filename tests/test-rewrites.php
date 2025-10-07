<?php

use ArtPulse\Core\Rewrites;
use WP_UnitTestCase;

class AP_DirectoryRewritesTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        update_option('permalink_structure', '/%postname%/');
    }

    public function test_artists_letter_route_sets_query_var(): void
    {
        $page_id = self::factory()->post->create([
            'post_type'   => 'page',
            'post_title'  => 'Artists',
            'post_name'   => 'artists',
            'post_status' => 'publish',
        ]);

        do_action('init');
        flush_rewrite_rules(false);

        $this->go_to(home_url('/artists/letter/a/'));

        $this->assertSame('A', get_query_var('ap_letter'));
        $this->assertSame('artists', get_query_var('pagename'));
        $this->assertSame($page_id, get_queried_object_id());
    }

    public function test_galleries_hash_route_maps_to_hash_letter(): void
    {
        $page_id = self::factory()->post->create([
            'post_type'   => 'page',
            'post_title'  => 'Organizations',
            'post_name'   => 'organizations',
            'post_status' => 'publish',
        ]);

        do_action('init');
        flush_rewrite_rules(false);

        $this->go_to(home_url('/organizations/letter/%23/'));

        $this->assertSame('#', get_query_var('ap_letter'));
        $this->assertSame('organizations', get_query_var('pagename'));
        $this->assertSame($page_id, get_queried_object_id());
    }

    public function test_url_builder_generates_canonical_links(): void
    {
        $artists = Rewrites::get_directory_letter_url('artists', 'B', ['s' => 'modern']);
        $this->assertStringContainsString('/artists/letter/B/', $artists);
        $this->assertStringContainsString('s=modern', $artists);

        $galleries = Rewrites::get_directory_letter_url('galleries', '#');
        $this->assertStringContainsString('/organizations/letter/%23/', $galleries);
    }
}
