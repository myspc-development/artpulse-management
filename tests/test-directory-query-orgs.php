<?php

use ArtPulse\Core\TitleTools;
use ArtPulse\Frontend\OrgsDirectory;
use WP_UnitTestCase;

class AP_OrgsDirectoryQueryIntegrationTest extends WP_UnitTestCase
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

    public function test_letter_filter_returns_expected_organizations(): void
    {
        $alpha = $this->create_org('Arcadia Gallery');
        $beta = $this->create_org('Beacon Studio');
        $hash = $this->create_org('123 Collective');

        foreach ([$alpha, $beta, $hash] as $post_id) {
            TitleTools::update_post_letter($post_id);
        }

        $output = $this->render_directory(['letter' => 'A']);

        $this->assertStringContainsString('Arcadia Gallery', $output);
        $this->assertStringNotContainsString('Beacon Studio', $output);
        $this->assertStringNotContainsString('123 Collective', $output);
    }

    public function test_hash_bucket_includes_non_alpha_organizations(): void
    {
        $numeric = $this->create_org('123 Collective');
        $symbol = $this->create_org('#Art Hub');
        $nonLatin = $this->create_org('서울미술관');
        $alpha = $this->create_org('Aurora Gallery');

        foreach ([$numeric, $symbol, $nonLatin, $alpha] as $post_id) {
            TitleTools::update_post_letter($post_id);
        }

        $output = $this->render_directory(['letter' => '#']);

        $this->assertStringContainsString('123 Collective', $output);
        $this->assertStringContainsString('#Art Hub', $output);
        $this->assertStringContainsString('서울미술관', $output);
        $this->assertStringNotContainsString('Aurora Gallery', $output);
    }

    public function test_search_and_letter_combination_filters_organizations(): void
    {
        $match = $this->create_org('Allied Manor');
        $alsoMatch = $this->create_org('Alliance Manifest');
        $otherLetter = $this->create_org('Beacon Manifest');

        foreach ([$match, $alsoMatch, $otherLetter] as $post_id) {
            TitleTools::update_post_letter($post_id);
        }

        $output = $this->render_directory([
            'ap_letter' => 'A',
            's'         => 'mani',
        ]);

        $this->assertStringContainsString('Allied Manor', $output);
        $this->assertStringContainsString('Alliance Manifest', $output);
        $this->assertStringNotContainsString('Beacon Manifest', $output);
    }

    public function test_pagination_outputs_expected_counts(): void
    {
        $titles = [
            'Gallery One',
            'Gallery Two',
            'Gallery Three',
            'Gallery Four',
        ];

        foreach ($titles as $title) {
            $post_id = $this->create_org($title);
            TitleTools::update_post_letter($post_id);
        }

        $pageOne = $this->render_directory(['letter' => 'G'], ['per_page' => 2]);
        $this->assertStringContainsString('aria-label="Galleries pagination"', $pageOne);
        $this->assertSame(2, substr_count($pageOne, 'ap-directory__item'));
        $this->assertStringContainsString('paged=2', $pageOne);

        $pageTwo = $this->render_directory(['letter' => 'G', 'paged' => 2], ['per_page' => 2]);
        $this->assertSame(2, substr_count($pageTwo, 'ap-directory__item'));
    }

    private function create_org(string $title): int
    {
        return self::factory()->post->create([
            'post_type'   => 'artpulse_org',
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
            return OrgsDirectory::render_shortcode($atts);
        } finally {
            remove_filter('ap_directory_cache_key', [$this, 'filter_cache_key'], 10);
            $_GET = $original;
        }
    }

    public function filter_cache_key($key, array $state): string
    {
        return 'test-org-' . md5(wp_json_encode($state));
    }
}
