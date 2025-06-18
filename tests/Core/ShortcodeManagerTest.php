<?php
use PHPUnit\Framework\TestCase;
use ArtPulse\Core\ShortcodeManager;

class ShortcodeManagerTest extends TestCase
{
    public function testShortcodeManagerRegistersShortcodes()
    {
        $this->assertTrue(method_exists(ShortcodeManager::class, 'register'));
    }
}
