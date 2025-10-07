<?php

use ArtPulse\Core\TitleTools;
use ArtPulse\Frontend\ArtistsDirectory;
use WP_UnitTestCase;

class AP_ArtistsDirectoryQueryIntegrationTest extends WP_UnitTestCase
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

    public function test_letter_a_bucket_returns_only_matching_artists(): void
    {
        $alphaOne = $this->create_artist('Amelia Abstract');
        $alphaTwo = $this->create_artist('Armando Mural');
        $beta = $this->create_artist('Benedict Brush');
        $hash = $this->create_artist('#42 Collective');

        foreach ([$alphaOne, $alphaTwo, $beta, $hash] as $post_id) {
            TitleTools::update_post_letter($post_id);
        }

        $output = $this->render_directory(['ap_letter' => 'A']);

        $this->assertStringContainsString('Amelia Abstract', $output);
        $this->assertStringContainsString('Armando Mural', $output);
        $this->assertStringNotContainsString('Benedict Brush', $output);
        $this->assertStringNotContainsString('#42 Collective', $output);
        $this->assertStringContainsString('aria-current="page"', $output);
    }

    public function test_hash_bucket_returns_numeric_and_symbol_titles(): void
    {
        $hashOne = $this->create_artist('3rd Dimension Studio');
        $hashTwo = $this->create_artist('#42 Collective');
        $nonLatin = $this->create_artist('東京ギャラリー');
        $alpha = $this->create_artist('Alpha Artist');

        foreach ([$hashOne, $hashTwo, $nonLatin, $alpha] as $post_id) {
            TitleTools::update_post_letter($post_id);
        }

        $output = $this->render_directory(['ap_letter' => '#']);

        $this->assertStringContainsString('3rd Dimension Studio', $output);
        $this->assertStringContainsString('#42 Collective', $output);
        $this->assertStringContainsString('東京ギャラリー', $output);
        $this->assertStringNotContainsString('Alpha Artist', $output);
    }

    public function test_search_combined_with_letter_filters_results(): void
    {
        $match = $this->create_artist('Amanda Manhattan');
        $alsoMatch = $this->create_artist('Amelia Manifesto');
        $nonMatchLetter = $this->create_artist('Bruno Manifesto');

        foreach ([$match, $alsoMatch, $nonMatchLetter] as $post_id) {
            TitleTools::update_post_letter($post_id);
        }

        $output = $this->render_directory([
            'letter' => 'A',
            's'      => 'mani',
        ]);

        $this->assertStringContainsString('Amanda Manhattan', $output);
        $this->assertStringContainsString('Amelia Manifesto', $output);
        $this->assertStringNotContainsString('Bruno Manifesto', $output);
    }

    public function test_pagination_honours_letter_filter(): void
    {
        $titles = [
            'Babs Brush',
            'Benedict Brush',
            'Briana Bronze',
            'Boris Blueprint',
            'Bianca Bloom',
        ];

        foreach ($titles as $title) {
            $post_id = $this->create_artist($title);
            TitleTools::update_post_letter($post_id);
        }

        $pageOne = $this->render_directory(['letter' => 'B'], ['per_page' => 2]);
        $this->assertStringContainsString('aria-label="Artists pagination"', $pageOne);
        $this->assertSame(2, substr_count($pageOne, 'ap-directory__item'));
        $this->assertStringContainsString('paged=2', $pageOne);

        $pageThree = $this->render_directory(['letter' => 'B', 'paged' => 3], ['per_page' => 2]);
        $this->assertSame(1, substr_count($pageThree, 'ap-directory__item'));
    }

    private function create_artist(string $title): int
    {
        return self::factory()->post->create([
            'post_type'   => 'artpulse_artist',
            'post_title'  => $title,
            'post_status' => 'publish',
        ]);
    }

    private function render_directory(array $query = [], array $atts = []): string
    {
        $original = $_GET;
        foreach ($query as $key => $value) {
            if (null === $value) {
                unset($_GET[$key]);
                continue;
            }

            $_GET[$key] = $value;
        }

        $atts = wp_parse_args($atts, ['per_page' => 10]);

        add_filter('ap_directory_cache_key', [$this, 'filter_cache_key'], 10, 3);

        try {
            return ArtistsDirectory::render_shortcode($atts);
        } finally {
            remove_filter('ap_directory_cache_key', [$this, 'filter_cache_key'], 10);
            $_GET = $original;
        }
    }

    public function filter_cache_key($key, array $state): string
    {
        return 'test-' . md5(wp_json_encode($state));
    }
}
