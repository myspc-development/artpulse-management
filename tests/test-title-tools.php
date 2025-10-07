<?php

use WP_UnitTestCase;

class AP_TitleToolsFunctionsTest extends WP_UnitTestCase
{
    public function test_armory_show_normalizes_to_a(): void
    {
        $this->assertSame('A', ap_normalize_letter('The Armory Show'));
    }

    public function test_diacritics_are_transliterated_to_e(): void
    {
        $this->assertSame('E', ap_normalize_letter('Édouard Manet'));
    }

    public function test_numeric_title_maps_to_hash_bucket(): void
    {
        $this->assertSame('#', ap_normalize_letter('#42 Collective'));
    }

    public function test_non_latin_title_falls_back_to_hash(): void
    {
        $this->assertSame('#', ap_normalize_letter('Авангард'));
    }

    public function test_articles_filter_allows_custom_prefixes(): void
    {
        $callback = static function (array $articles): array {
            return ['el '];
        };

        add_filter('ap_articles', $callback);

        try {
            $this->assertSame('M', ap_normalize_letter('El Museo del Barrio'));
        } finally {
            remove_filter('ap_articles', $callback);
        }
    }
}
