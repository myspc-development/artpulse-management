<?php

use ArtPulse\Core\TitleTools;
use ArtPulse\Frontend\ArtistsDirectory;
use WP_UnitTestCase;

class ArtistsDirectoryTest extends WP_UnitTestCase
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
            'post_type'  => 'artpulse_artist',
            'post_title' => 'Alice Aardvark',
            'post_status'=> 'publish',
        ]);
        $beta = self::factory()->post->create([
            'post_type'  => 'artpulse_artist',
            'post_title' => 'Bruno Brass',
            'post_status'=> 'publish',
        ]);
        $symbol = self::factory()->post->create([
            'post_type'  => 'artpulse_artist',
            'post_title' => '3D Collective',
            'post_status'=> 'publish',
        ]);

        TitleTools::update_post_letter($alpha);
        TitleTools::update_post_letter($beta);
        TitleTools::update_post_letter($symbol);

        $_GET['letter'] = 'all';
        $output = ArtistsDirectory::render_shortcode(['per_page' => 10]);
        unset($_GET['letter']);

        $this->assertStringContainsString('<div class="ap-directory" data-letter="all">', $output);
        $this->assertStringContainsString('<nav class="ap-directory__letters"', $output);
        $this->assertStringContainsString('aria-current="page">All</a>', $output);
        $this->assertStringContainsString('<ul class="ap-directory__list">', $output);
        $this->assertStringContainsString('Alice Aardvark', $output);
        $this->assertStringContainsString('Bruno Brass', $output);
        $this->assertStringContainsString('3D Collective', $output);
    }

    public function test_letter_filter_shows_matching_results(): void
    {
        $alpha = self::factory()->post->create([
            'post_type'  => 'artpulse_artist',
            'post_title' => 'Alice Aardvark',
            'post_status'=> 'publish',
        ]);
        $beta = self::factory()->post->create([
            'post_type'  => 'artpulse_artist',
            'post_title' => 'Bruno Brass',
            'post_status'=> 'publish',
        ]);

        TitleTools::update_post_letter($alpha);
        TitleTools::update_post_letter($beta);

        $_GET['letter'] = 'B';
        $output = ArtistsDirectory::render_shortcode(['per_page' => 10]);
        unset($_GET['letter']);

        $this->assertStringContainsString('data-letter="B"', $output);
        $this->assertStringContainsString('aria-current="page">B</a>', $output);
        $this->assertStringContainsString('Bruno Brass', $output);
        $this->assertStringNotContainsString('Alice Aardvark', $output);
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
