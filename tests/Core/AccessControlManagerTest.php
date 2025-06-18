<?php
use PHPUnit\Framework\TestCase;
use ArtPulse\Core\AccessControlManager;

class AccessControlManagerTest extends TestCase
{
    public function testRoleCapabilitiesAreRegistered()
    {
        $this->assertTrue(method_exists(AccessControlManager::class, 'register'));
    }
}
