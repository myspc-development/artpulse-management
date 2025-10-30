<?php

namespace Tests\Core;

use ArtPulse\Core\Urls;
use WP_UnitTestCase;

class UrlsTest extends WP_UnitTestCase
{
    public function test_get_page_url_returns_null_when_missing(): void
    {
        delete_option('artpulse_pages');

        $this->assertNull(Urls\get_page_url('dashboard_page_id'));
    }

    public function test_get_page_url_can_be_filtered(): void
    {
        delete_option('artpulse_pages');

        $callback = static function () {
            return 'https://example.com/dashboard';
        };

        add_filter('artpulse/page_url/dashboard_page_id', $callback);

        $this->assertSame('https://example.com/dashboard', Urls\get_page_url('dashboard_page_id'));

        remove_filter('artpulse/page_url/dashboard_page_id', $callback);
    }
}
