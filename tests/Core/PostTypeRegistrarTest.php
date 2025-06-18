<?php
use PHPUnit\Framework\TestCase;
use ArtPulse\Core\PostTypeRegistrar;

class PostTypeRegistrarTest extends TestCase
{
    public function testPostTypeRegistrarHasRegisterMethod()
    {
        $this->assertTrue(method_exists(PostTypeRegistrar::class, 'register'));
    }
}
