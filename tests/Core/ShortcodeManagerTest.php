<?php

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use ArtPulse\Core\ShortcodeManager;

if (!class_exists('WP_Query')) {
    class WP_Query
    {
        /** @var array<int> */
        public $posts = [];

        public function __construct($args = [])
        {
            $limit      = isset($args['posts_per_page']) ? (int) $args['posts_per_page'] : 1;
            $this->posts = array_fill(0, max(1, $limit), 101);
        }
    }
}

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

    public function testShortcodeManagerRegistersShortcodes(): void
    {
        $this->assertTrue(method_exists(ShortcodeManager::class, 'register'));
    }

    public function testRenderEventsOutputsHtml(): void
    {
        Functions\when('get_the_post_thumbnail')->alias(static fn($post_id, $size) => '');
        Functions\when('get_permalink')->alias(static fn($post_id = null) => 'http://example.test');
        Functions\when('get_the_title')->alias(static fn($post_id = null) => 'Sample');
        Functions\expect('wp_reset_postdata')->once();

        $html = ShortcodeManager::renderEvents(['limit' => 1]);

        $this->assertStringContainsString('ap-portfolio-grid', $html);
        $this->assertStringContainsString('Sample', $html);
    }
}
