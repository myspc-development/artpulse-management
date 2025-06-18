<?php
// tests/PostTypeRegistrarTest.php

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use ArtPulse\Core\PostTypeRegistrar;

class PostTypeRegistrarTest extends TestCase
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

    public function testRegisterPostTypes()
    {
        $types = [
            'artpulse_event',
            'artpulse_artist',
            'artpulse_artwork',
            'artpulse_org',
        ];

        Functions\expect('register_post_type')
            ->times(count($types))
            ->with(
                $this->isType('string'),
                $this->isType('array')
            );

        PostTypeRegistrar::register();

        // Optional real assertion (to satisfy PHPUnit explicitly)
        $this->assertTrue(true);
    }
}
