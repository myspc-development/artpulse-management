<?php

use ArtPulse\Frontend\ArtistsDirectory;

class ArtistsDirectoryTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ArtistsDirectory::register();
        ArtistsDirectory::clear_cache();
    }

    protected function tearDown(): void
    {
        ArtistsDirectory::clear_cache();
        parent::tearDown();
    }

    public function testShortcodeGroupsArtistsByLetter(): void
    {
        self::factory()->post->create([
            'post_type' => 'artpulse_artist',
            'post_title' => 'Alice Aardvark',
            'post_status' => 'publish',
        ]);
        self::factory()->post->create([
            'post_type' => 'artpulse_artist',
            'post_title' => 'Bruno Brass',
            'post_status' => 'publish',
        ]);
        self::factory()->post->create([
            'post_type' => 'artpulse_artist',
            'post_title' => '3D Collective',
            'post_status' => 'publish',
        ]);

        $output = do_shortcode('[ap_artists_directory]');

        $this->assertStringContainsString('ap-directory-filter', $output, 'Expected alphabet filter navigation to be present.');
        $this->assertMatchesRegularExpression('/<section[^>]+data-letter="All"[^>]*>.*Alice Aardvark.*Bruno Brass/s', $output);
        $this->assertMatchesRegularExpression('/<section[^>]+data-letter="A"[^>]*>.*Alice Aardvark/s', $output);
        $this->assertMatchesRegularExpression('/<section[^>]+data-letter="B"[^>]*>.*Bruno Brass/s', $output);
        $this->assertMatchesRegularExpression('/<section[^>]+data-letter="#"[^>]*>.*3D Collective/s', $output);
        $this->assertDoesNotMatchRegularExpression('/<section[^>]+data-letter="B"[^>]*>.*Alice Aardvark/s', $output, 'Letter sections should only include matching artists.');
    }
}
