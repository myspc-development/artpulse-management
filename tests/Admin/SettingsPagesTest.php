<?php

namespace Tests\Admin;

use ArtPulse\Admin\Settings;
use WP_UnitTestCase;

class SettingsPagesTest extends WP_UnitTestCase
{
    public function test_sanitize_pages_accepts_positive_ids(): void
    {
        $input = [
            'dashboard_page_id'      => '42',
            'artist_builder_page_id' => 75,
            'org_builder_page_id'    => '100',
            'contact_page_id'        => 0,
        ];

        $result = Settings::sanitize_pages($input);

        $this->assertSame(
            [
                'dashboard_page_id'      => 42,
                'artist_builder_page_id' => 75,
                'org_builder_page_id'    => 100,
                'contact_page_id'        => 0,
            ],
            $result
        );
    }

    public function test_sanitize_pages_discards_invalid_values(): void
    {
        $input = [
            'dashboard_page_id'      => 'foo',
            'artist_builder_page_id' => -12,
            'org_builder_page_id'    => null,
            'contact_page_id'        => ' ',
        ];

        $result = Settings::sanitize_pages($input);

        $this->assertSame(
            [
                'dashboard_page_id'      => 0,
                'artist_builder_page_id' => 0,
                'org_builder_page_id'    => 0,
                'contact_page_id'        => 0,
            ],
            $result
        );
    }
}
