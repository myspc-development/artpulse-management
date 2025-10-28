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
            ->with('template_redirect', [AccessControlManager::class, 'checkAccess'], 7);

        AccessControlManager::register();
    }

    public function testCheckAccessRedirectsGuestsFromProtectedPage()
    {
        Functions\when('is_user_logged_in')->alias(fn() => false);
        Functions\when('get_option')->alias(fn() => 0);
        Functions\when('is_page')->alias(fn() => true);
        Functions\when('get_queried_object_id')->alias(fn() => 42);
        Functions\when('get_permalink')->alias(fn() => 'http://example.test/dashboard/');
        Functions\when('sanitize_key')->alias(fn($key) => $key);
        Functions\when('sanitize_text_field')->alias(fn($value) => $value);
        Functions\when('add_query_arg')->alias(fn($args, $url) => $url);
        Functions\when('wp_login_url')->alias(fn($url) => 'http://example.test/wp-login.php?redirect_to=' . rawurlencode($url));
        Functions\when('get_query_var')->alias(fn() => '');

        \Patchwork\redefine('exit', function () {});

        Functions\expect('wp_safe_redirect')
            ->once()
            ->with('http://example.test/wp-login.php?redirect_to=' . rawurlencode('http://example.test/dashboard/'));

        AccessControlManager::checkAccess();

        \Patchwork\restoreAll();
    }

    public function testCheckAccessRedirectsFreeMembers()
    {
        Functions\when('is_user_logged_in')->alias(fn() => true);
        Functions\when('is_page')->alias(fn() => false);
        Functions\when('is_singular')->alias(fn($types) => true);
        Functions\when('get_current_user_id')->alias(fn() => 1);
        Functions\when('get_user_meta')->alias(fn() => 'Free');
        Functions\when('home_url')->alias(fn() => 'http://example.test');
        Functions\when('get_option')->alias(fn() => 0);

        \Patchwork\redefine('exit', function () {});

        Functions\expect('wp_safe_redirect')->never();
        Functions\expect('wp_redirect')
            ->once()
            ->with('http://example.test');

        AccessControlManager::checkAccess();

        \Patchwork\restoreAll();
    }

    public function testCheckAccessAllowsProMembers()
    {
        Functions\when('is_user_logged_in')->alias(fn() => true);
        Functions\when('is_page')->alias(fn() => false);
        Functions\when('is_singular')->alias(fn($types) => true);
        Functions\when('get_current_user_id')->alias(fn() => 1);
        Functions\when('get_user_meta')->alias(fn() => 'Pro');
        Functions\when('get_option')->alias(fn() => 0);

        \Patchwork\redefine('exit', function () {});

        Functions\expect('wp_redirect')->never();

        AccessControlManager::checkAccess();

        \Patchwork\restoreAll();
    }

    public function testCheckAccessAllowsDashboardPages()
    {
        Functions\when('is_user_logged_in')->alias(fn() => true);
        Functions\when('is_page')->alias(fn() => true);
        Functions\when('get_option')->alias(fn() => 0);

        Functions\expect('is_singular')->never();
        Functions\expect('wp_redirect')->never();
        Functions\expect('wp_safe_redirect')->never();

        AccessControlManager::checkAccess();
    }
}
