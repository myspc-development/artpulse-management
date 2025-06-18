<?php
use PHPUnit\Framework\TestCase;
use ArtPulse\Core\MetaBoxRegistrar;

class MetaBoxRegistrarTest extends TestCase
{
    public function testMetaBoxRegistrarHasRegisterMethod()
    {
        $this->assertTrue(method_exists(MetaBoxRegistrar::class, 'register'));
    }
}
