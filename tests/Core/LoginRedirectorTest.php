<?php

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use ArtPulse\Core\LoginRedirector;

if (!class_exists('WP_User')) {
    class WP_User
    {
        public $ID;
        public $roles = [];

        public function __construct(int $id = 0)
        {
            $this->ID = $id;
        }
    }
}

final class LoginRedirectorTest extends TestCase
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

    public function testRegisterAddsLoginRedirectFilter(): void
    {
        Functions\expect('add_filter')
            ->once()
            ->with('login_redirect', [LoginRedirector::class, 'filterRedirect'], 10, 3);

        LoginRedirector::register();
    }

    public function testFilterRedirectPrioritizesOrganizationDashboard(): void
    {
        $user = new WP_User(7);
        $user->roles = ['organization'];

        Functions\expect('user_can')
            ->once()
            ->with($user, 'edit_artpulse_org')
            ->andReturn(true);

        Functions\expect('home_url')
            ->once()
            ->with('/dashboard/')
            ->andReturn('https://example.test/dashboard/');

        Functions\expect('add_query_arg')
            ->once()
            ->with('role', 'organization', 'https://example.test/dashboard/')
            ->andReturn('https://example.test/dashboard/?role=organization');

        $redirect = LoginRedirector::filterRedirect('', '', $user);

        $this->assertSame('https://example.test/dashboard/?role=organization', $redirect);
    }

    public function testFilterRedirectFallsBackToArtistDashboard(): void
    {
        $user = new WP_User(42);
        $user->roles = ['member', 'artist'];
        $checked = [];

        Functions\when('user_can')->alias(function ($actual_user, string $cap) use ($user, &$checked): bool {
            $this->assertSame($user, $actual_user);
            $checked[] = $cap;

            return 'edit_artpulse_artist' === $cap;
        });

        Functions\expect('home_url')
            ->once()
            ->with('/dashboard/')
            ->andReturn('https://example.test/dashboard/');

        Functions\expect('add_query_arg')
            ->once()
            ->with('role', 'artist', 'https://example.test/dashboard/')
            ->andReturn('https://example.test/dashboard/?role=artist');

        $redirect = LoginRedirector::filterRedirect('', '', $user);

        $this->assertSame('https://example.test/dashboard/?role=artist', $redirect);
        $this->assertSame(['edit_artpulse_org', 'edit_artpulse_artist'], $checked);
    }

    public function testFilterRedirectUsesSharedDashboardWhenAvailable(): void
    {
        $user = new WP_User(11);
        $user->roles = ['member'];
        $checked = [];

        Functions\when('user_can')->alias(function ($actual_user, string $cap) use ($user, &$checked): bool {
            $this->assertSame($user, $actual_user);
            $checked[] = $cap;

            return 'read' === $cap;
        });

        Functions\expect('home_url')
            ->once()
            ->with('/dashboard/')
            ->andReturn('https://example.test/dashboard/');

        Functions\expect('add_query_arg')
            ->once()
            ->with('role', 'member', 'https://example.test/dashboard/')
            ->andReturn('https://example.test/dashboard/?role=member');

        $redirect = LoginRedirector::filterRedirect('https://example.test/wp-admin/', '', $user);

        $this->assertSame('https://example.test/dashboard/?role=member', $redirect);
        $this->assertSame(
            ['edit_artpulse_org', 'edit_artpulse_artist', 'read'],
            $checked
        );
    }

    public function testFilterRedirectPreservesOriginalDestinationForOtherUsers(): void
    {
        $user = new WP_User(5);
        $user->roles = [];

        Functions\when('user_can')->alias(function ($actual_user, string $cap) use ($user) {
            $this->assertSame($user, $actual_user);

            return $cap === 'view_artpulse_dashboard';
        });

        Functions\expect('home_url')
            ->once()
            ->with('/dashboard/')
            ->andReturn('https://example.test/dashboard/');

        $redirect = LoginRedirector::filterRedirect('https://example.test/wp-admin/', '', $user);

        $this->assertSame('https://example.test/dashboard/', $redirect);
    }

    public function testFilterRedirectPreservesOriginalDestinationWhenNoDashboardAccess(): void
    {
        $user = new WP_User(8);
        $user->roles = [];

        Functions\when('user_can')->alias(function ($actual_user, $cap = null) use ($user) {
            $this->assertSame($user, $actual_user);

            return false;
        });

        $redirect = LoginRedirector::filterRedirect('https://example.test/wp-admin/', '', $user);

        $this->assertSame('https://example.test/wp-admin/', $redirect);
    }

    public function testFilterRedirectIgnoresNonUserValues(): void
    {
        $redirect = LoginRedirector::filterRedirect('https://example.test/wp-admin/', '', null);

        $this->assertSame('https://example.test/wp-admin/', $redirect);
    }
}
