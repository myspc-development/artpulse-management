<?php

use ArtPulse\Core\TitleTools;
use ArtPulse\Frontend\ArtistsDirectory;
use ArtPulse\Frontend\OrgsDirectory;
use WP_UnitTestCase;

class DirectoryQueryTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ap_dir:%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ap_dir:%'");
        delete_option('ap_directory_cache_versions');
        $_GET = [];
    }

    public function test_artists_letter_filter_returns_expected_results()
    {
        $alpha = self::factory()->post->create([
            'post_type'  => 'artpulse_artist',
            'post_title' => 'Alpha Artist',
            'post_status'=> 'publish',
        ]);
        $beta = self::factory()->post->create([
            'post_type'  => 'artpulse_artist',
            'post_title' => 'Beta Artist',
            'post_status'=> 'publish',
        ]);

        TitleTools::update_post_letter($alpha);
        TitleTools::update_post_letter($beta);

        $_GET['ap_letter'] = 'A';
        $output = ArtistsDirectory::render_shortcode(['per_page' => 10]);
        unset($_GET['ap_letter']);

        $this->assertStringContainsString('Alpha Artist', $output);
        $this->assertStringNotContainsString('Beta Artist', $output);
    }

    public function test_artists_letter_and_search_combination()
    {
        $alpha = self::factory()->post->create([
            'post_type'  => 'artpulse_artist',
            'post_title' => 'Alpha Painter',
            'post_status'=> 'publish',
        ]);
        $beta = self::factory()->post->create([
            'post_type'  => 'artpulse_artist',
            'post_title' => 'Alpha Sculptor',
            'post_status'=> 'publish',
        ]);

        TitleTools::update_post_letter($alpha);
        TitleTools::update_post_letter($beta);

        $_GET['letter'] = 'A';
        $_GET['s'] = 'Painter';
        $output = ArtistsDirectory::render_shortcode(['per_page' => 10]);
        unset($_GET['letter'], $_GET['s']);

        $this->assertStringContainsString('Alpha Painter', $output);
        $this->assertStringNotContainsString('Alpha Sculptor', $output);
    }

    public function test_artists_taxonomy_filter_with_letter()
    {
        $term = self::factory()->term->create([
            'taxonomy' => 'artist_specialty',
            'slug'     => 'painting',
            'name'     => 'Painting',
        ]);

        $alpha = self::factory()->post->create([
            'post_type'  => 'artpulse_artist',
            'post_title' => 'Alpha Painter',
            'post_status'=> 'publish',
        ]);
        $beta = self::factory()->post->create([
            'post_type'  => 'artpulse_artist',
            'post_title' => 'Alpha Writer',
            'post_status'=> 'publish',
        ]);

        wp_set_object_terms($alpha, [$term], 'artist_specialty');

        TitleTools::update_post_letter($alpha);
        TitleTools::update_post_letter($beta);

        $_GET['letter'] = 'A';
        $_GET['tax'] = [ 'artist_specialty' => ['painting'] ];
        $output = ArtistsDirectory::render_shortcode(['per_page' => 10]);
        unset($_GET['letter'], $_GET['tax']);

        $this->assertStringContainsString('Alpha Painter', $output);
        $this->assertStringNotContainsString('Alpha Writer', $output);
    }

    public function test_artists_all_bucket_includes_all_letters()
    {
        $alpha = self::factory()->post->create([
            'post_type'  => 'artpulse_artist',
            'post_title' => 'Alpha Artist',
            'post_status'=> 'publish',
        ]);
        $beta = self::factory()->post->create([
            'post_type'  => 'artpulse_artist',
            'post_title' => 'Beta Artist',
            'post_status'=> 'publish',
        ]);

        TitleTools::update_post_letter($alpha);
        TitleTools::update_post_letter($beta);

        $_GET['letter'] = 'all';
        $output = ArtistsDirectory::render_shortcode(['per_page' => 10]);
        unset($_GET['letter']);

        $this->assertStringContainsString('Alpha Artist', $output);
        $this->assertStringContainsString('Beta Artist', $output);
    }

    public function test_rewrite_letter_request_sets_query_var()
    {
        $page_id = self::factory()->post->create([
            'post_type'  => 'page',
            'post_title' => 'Artists',
            'post_name'  => 'artists',
            'post_status'=> 'publish',
        ]);

        do_action('init');
        flush_rewrite_rules(false);

        $this->go_to(home_url('/artists/letter/a/'));

        $this->assertSame('A', get_query_var('ap_letter'));
        $this->assertEquals($page_id, get_queried_object_id());
    }

    public function test_rewrite_honours_filtered_base_slug(): void
    {
        $page_id = self::factory()->post->create([
            'post_type'  => 'page',
            'post_title' => 'Organisations',
            'post_name'  => 'organisations',
            'post_status'=> 'publish',
        ]);

        $callback = static fn () => 'organisations';
        add_filter('ap_galleries_directory_base', $callback);

        do_action('init');
        flush_rewrite_rules(false);

        $this->go_to(home_url('/organisations/letter/b/'));

        $this->assertSame('B', get_query_var('ap_letter'));
        $this->assertEquals($page_id, get_queried_object_id());

        remove_filter('ap_galleries_directory_base', $callback);
    }

    public function test_rewrite_sanitizes_filtered_base_slug(): void
    {
        $page_id = self::factory()->post->create([
            'post_type'  => 'page',
            'post_title' => 'Partners',
            'post_name'  => 'partner-spaces',
            'post_status'=> 'publish',
        ]);

        $callback = static fn () => 'partner|spaces';
        add_filter('ap_galleries_directory_base', $callback);

        do_action('init');
        flush_rewrite_rules(false);

        $this->go_to(home_url('/partner-spaces/letter/c/'));

        $this->assertSame('C', get_query_var('ap_letter'));
        $this->assertEquals($page_id, get_queried_object_id());

        remove_filter('ap_galleries_directory_base', $callback);
    }

    public function test_organization_directory_outputs_results()
    {
        $gallery = self::factory()->post->create([
            'post_type'  => 'artpulse_org',
            'post_title' => 'Gallery Example',
            'post_status'=> 'publish',
        ]);

        TitleTools::update_post_letter($gallery);

        $_GET['letter'] = 'G';
        $output = OrgsDirectory::render_shortcode(['per_page' => 10]);
        unset($_GET['letter']);

        $this->assertStringContainsString('Gallery Example', $output);
    }
}
