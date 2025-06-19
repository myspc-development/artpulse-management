<?php
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use ArtPulse\Core\AccessControlManager;

class AccessControlManagerTest extends TestCase
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

    public function testRegisterAddsTemplateRedirectHook()
    {
        Functions\expect('add_action')
            ->once()
            ->with('template_redirect', [AccessControlManager::class, 'checkAccess']);

        AccessControlManager::register();
    }

    public function testCheckAccessRedirectsFreeMembers()
    {
        Functions\when('is_singular')->alias(fn($types) => true);
        Functions\when('get_current_user_id')->alias(fn() => 1);
        Functions\when('get_user_meta')->alias(fn() => 'Free');
        Functions\when('home_url')->alias(fn() => 'http://example.test');

        \Patchwork\redefine('exit', function () {});

        Functions\expect('wp_redirect')
            ->once()
            ->with('http://example.test');

        AccessControlManager::checkAccess();

        \Patchwork\restoreAll();
    }

    public function testCheckAccessAllowsProMembers()
    {
        Functions\when('is_singular')->alias(fn($types) => true);
        Functions\when('get_current_user_id')->alias(fn() => 1);
        Functions\when('get_user_meta')->alias(fn() => 'Pro');

        \Patchwork\redefine('exit', function () {});

        Functions\expect('wp_redirect')->never();

        AccessControlManager::checkAccess();

        \Patchwork\restoreAll();
    }
}
