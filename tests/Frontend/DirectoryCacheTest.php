<?php

use ArtPulse\Frontend\ArtistsDirectory;
use WP_UnitTestCase;

class DirectoryCacheTest extends WP_UnitTestCase
{
    private array $capturedKeys = [];

    protected function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ap_dir:%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ap_dir:%'");
        delete_option('ap_directory_cache_versions');

        $this->capturedKeys = [];
        add_filter('ap_directory_cache_key', [ $this, 'captureCacheKey' ], 10, 3);
        $_GET = [];
    }

    protected function tearDown(): void
    {
        remove_filter('ap_directory_cache_key', [ $this, 'captureCacheKey' ]);
        parent::tearDown();
    }

    public function captureCacheKey($key, $state = [], $post_type = '')
    {
        $this->capturedKeys[] = $key;
        return $key;
    }

    public function test_cache_key_changes_when_artist_title_updates()
    {
        $post_id = self::factory()->post->create([
            'post_type'  => 'artpulse_artist',
            'post_title' => 'Alpha Artist',
            'post_status'=> 'publish',
        ]);

        $_GET['letter'] = 'A';
        ArtistsDirectory::render_shortcode(['per_page' => 5]);
        unset($_GET['letter']);

        $this->assertNotEmpty($this->capturedKeys);
        $first_key = end($this->capturedKeys);

        wp_update_post([
            'ID'         => $post_id,
            'post_title' => 'Apex Artist',
        ]);

        $_GET['letter'] = 'A';
        ArtistsDirectory::render_shortcode(['per_page' => 5]);
        unset($_GET['letter']);

        $second_key = end($this->capturedKeys);
        $this->assertNotSame($first_key, $second_key);
    }

    public function test_cache_key_changes_when_artist_terms_update()
    {
        $term = self::factory()->term->create([
            'taxonomy' => 'artist_specialty',
            'name'     => 'Painting',
            'slug'     => 'painting',
        ]);

        $post_id = self::factory()->post->create([
            'post_type'  => 'artpulse_artist',
            'post_title' => 'Alpha Artist',
            'post_status'=> 'publish',
        ]);

        $_GET['letter'] = 'A';
        ArtistsDirectory::render_shortcode(['per_page' => 5]);
        unset($_GET['letter']);

        $first_key = end($this->capturedKeys);

        wp_set_object_terms($post_id, [$term], 'artist_specialty', true);

        $_GET['letter'] = 'A';
        ArtistsDirectory::render_shortcode(['per_page' => 5]);
        unset($_GET['letter']);

        $second_key = end($this->capturedKeys);
        $this->assertNotSame($first_key, $second_key);
    }

    public function test_cache_key_changes_when_artist_meta_updates()
    {
        $post_id = self::factory()->post->create([
            'post_type'  => 'artpulse_artist',
            'post_title' => 'Alpha Artist',
            'post_status'=> 'publish',
        ]);

        $_GET['letter'] = 'A';
        ArtistsDirectory::render_shortcode(['per_page' => 5]);
        unset($_GET['letter']);

        $first_key = end($this->capturedKeys);

        update_post_meta($post_id, '_ap_artist_location', 'New York');

        $_GET['letter'] = 'A';
        ArtistsDirectory::render_shortcode(['per_page' => 5]);
        unset($_GET['letter']);

        $second_key = end($this->capturedKeys);
        $this->assertNotSame($first_key, $second_key);
    }
}
