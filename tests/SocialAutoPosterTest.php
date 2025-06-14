<?php
use PHPUnit\Framework\TestCase;
use Tests\Stubs;
use EAD\Integration\SocialAutoPoster;

class SocialAutoPosterTest extends TestCase
{
    protected function setUp(): void
    {
        Stubs::$actions = [];
        Stubs::$filters = [];
    }

    public function test_register_adds_hook()
    {
        SocialAutoPoster::register();

        $this->assertContains([
            'transition_post_status',
            [SocialAutoPoster::class, 'maybe_post'],
            10,
            3,
        ], Stubs::$actions);
    }
}
