<?php

namespace ArtPulse\Tests\Admin;

use ArtPulse\Admin\EventApprovals;
use WP_UnitTestCase;

class RedirectCaptured extends \Exception {}

class EventApprovalsTest extends WP_UnitTestCase
{
    private ?array $capturedMail = null;

    protected function setUp(): void
    {
        parent::setUp();
        EventApprovals::register();
        $this->capturedMail = null;
    }

    protected function tearDown(): void
    {
        remove_filter('wp_mail', [ $this, 'captureMail' ]);
        parent::tearDown();
        wp_set_current_user(0);
        $_GET     = [];
        $_POST    = [];
        $_REQUEST = [];
    }

    public function test_cannot_approve_without_capability(): void
    {
        $event_id = self::factory()->post->create([
            'post_type'   => 'artpulse_event',
            'post_status' => 'pending',
        ]);

        $user_id = self::factory()->user->create([
            'role' => 'subscriber',
        ]);

        wp_set_current_user($user_id);

        $_GET = [
            'event_id' => (string) $event_id,
            '_wpnonce' => wp_create_nonce('artpulse_approve_event_' . $event_id),
        ];
        $_REQUEST = $_GET;

        $redirect = $this->captureRedirect(function () {
            do_action('admin_post_artpulse_approve_event');
        });

        $this->assertSame('pending', get_post_status($event_id));
        $this->assertStringContainsString('artpulse_notice=no-cap', $redirect);
    }

    public function test_approve_action_updates_status_and_sends_email(): void
    {
        $author_id = self::factory()->user->create([
            'role'  => 'subscriber',
            'email' => 'author@example.com',
        ]);

        $event_id = self::factory()->post->create([
            'post_type'   => 'artpulse_event',
            'post_status' => 'pending',
            'post_author' => $author_id,
        ]);

        $admin_role = get_role('administrator');
        if ($admin_role && ! $admin_role->has_cap('publish_artpulse_events')) {
            $admin_role->add_cap('publish_artpulse_events');
        }

        $manager_id = self::factory()->user->create([
            'role' => 'administrator',
        ]);

        wp_set_current_user($manager_id);

        add_filter('wp_mail', [ $this, 'captureMail' ]);

        $_GET = [
            'event_id' => (string) $event_id,
            '_wpnonce' => wp_create_nonce('artpulse_approve_event_' . $event_id),
        ];
        $_REQUEST = $_GET;

        $redirect = $this->captureRedirect(function () {
            do_action('admin_post_artpulse_approve_event');
        });

        $this->assertSame('publish', get_post_status($event_id));
        $this->assertStringContainsString('artpulse_notice=single-approved', $redirect);

        $this->assertIsArray($this->capturedMail);
        $this->assertSame('author@example.com', $this->capturedMail['to']);
        $this->assertStringContainsString('approved', strtolower($this->capturedMail['subject']));
    }

    private function captureMail(array $args): array
    {
        $this->capturedMail = $args;
        return $args;
    }

    private function captureRedirect(callable $callback): string
    {
        $redirect = '';

        $handler = function (string $location) use (&$redirect) {
            $redirect = $location;
            throw new RedirectCaptured();
        };

        add_filter('wp_redirect', $handler);

        try {
            $callback();
        } catch (RedirectCaptured $ignored) {
            // swallow redirect exception
        }

        remove_filter('wp_redirect', $handler);

        return $redirect;
    }
}
