<?php

use ArtPulse\Core\TitleTools;
use WP_UnitTestCase;

class TitleToolsTest extends WP_UnitTestCase
{
    public function test_diacritics_are_normalized()
    {
        $this->assertSame('E', TitleTools::normalizeLetter('Édouard Manet'));
    }

    public function test_combining_diacritics_are_stripped()
    {
        $this->assertSame('E', TitleTools::normalizeLetter("E\u{0301}mile Bernard"));
    }

    public function test_extended_latin_characters_are_transliterated()
    {
        $this->assertSame('L', TitleTools::normalizeLetter('Łódź Collective'));
    }

    public function test_leading_articles_are_removed()
    {
        $this->assertSame('A', TitleTools::normalizeLetter('The Armory Show'));
    }

    public function test_symbols_fall_back_to_hash()
    {
        $this->assertSame('#', TitleTools::normalizeLetter('#42 Exhibition'));
    }

    public function test_non_latin_characters_fall_back_to_hash()
    {
        $this->assertSame('#', TitleTools::normalizeLetter('東京ギャラリー'));
    }

    public function test_korean_titles_fall_back_to_hash()
    {
        $this->assertSame('#', TitleTools::normalizeLetter('서울미술관'));
    }
}
