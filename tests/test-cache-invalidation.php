<?php

use ArtPulse\Core\TitleTools;
use ArtPulse\Frontend\ArtistsDirectory;
use ArtPulse\Frontend\OrgsDirectory;
use WP_Post;
use WP_UnitTestCase;

class AP_DirectoryCacheInvalidationTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        delete_option('ap_directory_cache_versions');
    }

    public function test_artist_title_change_updates_letter_and_bumps_cache(): void
    {
        $post_id = self::factory()->post->create([
            'post_type'   => 'artpulse_artist',
            'post_title'  => 'Alpha Artist',
            'post_status' => 'publish',
        ]);

        TitleTools::update_post_letter($post_id, 'Alpha Artist');
        $this->assertSame('A', get_post_meta($post_id, TitleTools::META_KEY, true));

        delete_option('ap_directory_cache_versions');

        wp_update_post([
            'ID'         => $post_id,
            'post_title' => 'Beta Artist',
        ]);

        $this->assertSame('B', get_post_meta($post_id, TitleTools::META_KEY, true));

        $versions = get_option('ap_directory_cache_versions');
        $this->assertIsArray($versions);
        $this->assertSame(2, (int) ($versions['artpulse_artist'] ?? 0));
    }

    public function test_artist_status_transition_flushes_cache(): void
    {
        $post_id = self::factory()->post->create([
            'post_type'   => 'artpulse_artist',
            'post_title'  => 'Cache Test',
            'post_status' => 'publish',
        ]);

        $post = get_post($post_id);
        $this->assertInstanceOf(WP_Post::class, $post);

        delete_option('ap_directory_cache_versions');

        ArtistsDirectory::flush_cache_on_status('publish', 'draft', $post);
        $versions = get_option('ap_directory_cache_versions');
        $this->assertSame(2, (int) ($versions['artpulse_artist'] ?? 0));
    }

    public function test_artist_terms_and_meta_changes_flush_cache(): void
    {
        $post_id = self::factory()->post->create([
            'post_type'   => 'artpulse_artist',
            'post_title'  => 'Term Cache',
            'post_status' => 'publish',
        ]);

        delete_option('ap_directory_cache_versions');

        ArtistsDirectory::flush_cache_on_terms($post_id, [], [], 'artist_specialty', false, []);
        $versions = get_option('ap_directory_cache_versions');
        $this->assertSame(2, (int) ($versions['artpulse_artist'] ?? 0));

        delete_option('ap_directory_cache_versions');
        ArtistsDirectory::flush_cache_on_meta(null, $post_id, '_ap_artist_location', 'Test');
        $versions = get_option('ap_directory_cache_versions');
        $this->assertSame(2, (int) ($versions['artpulse_artist'] ?? 0));
    }

    public function test_org_meta_and_terms_flush_cache_versions(): void
    {
        $post_id = self::factory()->post->create([
            'post_type'   => 'artpulse_org',
            'post_title'  => 'Gallery Cache',
            'post_status' => 'publish',
        ]);

        delete_option('ap_directory_cache_versions');

        OrgsDirectory::flush_cache_on_meta(null, $post_id, '_ap_org_location', 'Test');
        $versions = get_option('ap_directory_cache_versions');
        $this->assertSame(2, (int) ($versions['artpulse_org'] ?? 0));

        delete_option('ap_directory_cache_versions');
        OrgsDirectory::flush_cache_on_terms($post_id, [], [], 'org_category', false, []);
        $versions = get_option('ap_directory_cache_versions');
        $this->assertSame(2, (int) ($versions['artpulse_org'] ?? 0));
    }
}
