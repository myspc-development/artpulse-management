<?php
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use ArtPulse\Core\ShortcodeManager;

class ShortcodeManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testShortcodeManagerRegistersShortcodes()
    {
        $this->assertTrue(method_exists(ShortcodeManager::class, 'register'));
    }

    public function testRenderEventsOutputsHtml()
    {
        if (!class_exists('WP_Query')) {
            class WP_Query {
                private $count = 0;
                public function __construct($args = []) {}
                public function have_posts() { return $this->count++ === 0; }
                public function the_post() {}
            }
        }

        Functions\when('the_post_thumbnail')->alias(function($size){ echo ''; });
        Functions\when('get_permalink')->alias(fn() => 'http://example.test');
        Functions\when('get_the_title')->alias(fn() => 'Sample');
        Functions\expect('wp_reset_postdata')->once();

        $html = ShortcodeManager::renderEvents(['limit' => 1]);

        $this->assertStringContainsString('ap-portfolio-grid', $html);
        $this->assertStringContainsString('Sample', $html);
    }
}
