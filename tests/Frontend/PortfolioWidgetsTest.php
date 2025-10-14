<?php

namespace Tests\Frontend;

use ArtPulse\Frontend\Shared\PortfolioWidgetRegistry;
use WP_UnitTestCase;

class PortfolioWidgetsTest extends WP_UnitTestCase
{
    public function test_public_widgets_respect_saved_order(): void
    {
        $post_id = self::factory()->post->create([
            'post_type' => 'artpulse_org',
        ]);

        update_post_meta($post_id, '_ap_widgets', [
            ['key' => 'contact', 'enabled' => true],
            ['key' => 'hero', 'enabled' => false],
            'gallery' => ['enabled' => true],
            ['key' => 'about', 'enabled' => true],
        ]);

        $widgets = PortfolioWidgetRegistry::public_widgets($post_id);

        $this->assertSame(['contact', 'hero', 'gallery', 'about'], array_keys($widgets));
        $this->assertTrue($widgets['contact']['enabled']);
        $this->assertFalse($widgets['hero']['enabled']);
        $this->assertTrue($widgets['gallery']['enabled']);
    }
}
