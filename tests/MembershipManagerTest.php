<?php
// tests/MembershipManagerTest.php

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use ArtPulse\Core\MembershipManager;

class MembershipManagerTest extends TestCase
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

    public function testAssignFreeMembershipSetsMetaAndRole()
    {
        $user_id = 123;

        $mockUser = $this->getMockBuilder(stdClass::class)
                         ->addMethods(['set_role'])
                         ->getMock();
        $mockUser->user_email = 'user@example.test';

        Functions\when('get_userdata')->alias(fn($id) => $mockUser);

        $mockUser->expects($this->once())
                 ->method('set_role')
                 ->with('subscriber');

        Functions\expect('update_user_meta')
            ->once()
            ->with($user_id, 'ap_membership_level', 'Free');

        Functions\expect('wp_mail')
            ->once()
            ->with(
                'user@example.test',
                $this->stringContains('Welcome to ArtPulse'),
                $this->stringContains('Free membership')
            );

        MembershipManager::assignFreeMembership($user_id);
    }

    public function testProcessExpirationsDowngradesExpiredUsers()
    {
        $now = 1700000000;

        Functions\when('current_time')->alias(fn($type) => $now);

        $user1 = $this->getMockBuilder(stdClass::class)
                     ->addMethods(['set_role'])
                     ->getMock();
        $user1->ID = 1;
        $user1->user_email = 'one@example.test';

        $user2 = $this->getMockBuilder(stdClass::class)
                     ->addMethods(['set_role'])
                     ->getMock();
        $user2->ID = 2;
        $user2->user_email = 'two@example.test';

        $user1->expects($this->once())
              ->method('set_role')
              ->with('subscriber');
        $user2->expects($this->once())
              ->method('set_role')
              ->with('subscriber');

        $capturedArgs = null;
        Functions\when('get_users')->alias(function($args) use ($user1, $user2, &$capturedArgs) {
            $capturedArgs = $args;
            return [ $user1, $user2 ];
        });

        Functions\expect('update_user_meta')
            ->once()
            ->with($user1->ID, 'ap_membership_level', 'Free');
        Functions\expect('update_user_meta')
            ->once()
            ->with($user2->ID, 'ap_membership_level', 'Free');

        Functions\expect('wp_mail')->twice();

        MembershipManager::processExpirations();

        $this->assertEquals([
            'meta_key'     => 'ap_membership_expires',
            'meta_value'   => $now,
            'meta_compare' => '<=',
        ], $capturedArgs);
    }

    public function testHandleStripeWebhookCheckoutCompletedAssignsPro()
    {
        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'client_reference_id' => 7,
                'customer' => 'cus_123',
            ]],
        ]);

        \Patchwork\redefine('Stripe\\Webhook::constructEvent', function() use ($payload) {
            return json_decode($payload);
        });

        Functions\when('get_option')->alias(fn() => ['stripe_secret' => 'sk', 'stripe_webhook_secret' => 'wh']);
        Functions\when('sanitize_text_field')->alias(fn($v) => $v);

        $request = $this->getMockBuilder(stdClass::class)
                        ->addMethods(['get_body'])
                        ->getMock();
        $request->method('get_body')->willReturn($payload);

        Functions\expect('update_user_meta')
            ->once()
            ->with(7, 'stripe_customer_id', 'cus_123');
        Functions\expect('update_user_meta')
            ->once()
            ->with(7, 'ap_membership_level', 'Pro');
        Functions\expect('update_user_meta')
            ->once()
            ->with(7, 'ap_membership_expires', $this->type('int'));

        MembershipManager::handleStripeWebhook($request);

        \Patchwork\restoreAll();
    }

    public function testHandleStripeWebhookPaymentFailedDowngrades()
    {
        $payload = json_encode([
            'type' => 'invoice.payment_failed',
            'data' => ['object' => [
                'customer' => 'cus_999',
            ]],
        ]);

        \Patchwork\redefine('Stripe\\Webhook::constructEvent', function() use ($payload) {
            return json_decode($payload);
        });

        Functions\when('get_option')->alias(fn() => ['stripe_secret' => 'sk', 'stripe_webhook_secret' => 'wh']);
        Functions\when('current_time')->alias(fn() => 1700000000);

        $usr = $this->getMockBuilder(stdClass::class)
                   ->addMethods(['set_role'])
                   ->getMock();
        $usr->user_email = 'fail@example.test';

        Functions\when('get_userdata')->alias(fn($id) => $usr);
        Functions\when('get_users')->alias(fn() => [42]);

        $request = $this->getMockBuilder(stdClass::class)
                        ->addMethods(['get_body'])
                        ->getMock();
        $request->method('get_body')->willReturn($payload);

        $usr->expects($this->once())
            ->method('set_role')
            ->with('subscriber');

        Functions\expect('update_user_meta')
            ->once()
            ->with(42, 'ap_membership_level', 'Free');
        Functions\expect('update_user_meta')
            ->once()
            ->with(42, 'ap_membership_expires', 1700000000);

        Functions\expect('wp_mail')
            ->once()
            ->with(
                'fail@example.test',
                $this->stringContains('cancelled'),
                $this->stringContains('payment failed')
            );

        MembershipManager::handleStripeWebhook($request);

        \Patchwork\restoreAll();
    }

    public function testHandleStripeWebhookRenewalUpdatesExpiry()
    {
        $payload = json_encode([
            'type' => 'invoice.payment_succeeded',
            'data' => ['object' => [
                'customer' => 'cus_555',
                'current_period_end' => 1800000000,
            ]],
        ]);

        \Patchwork\redefine('Stripe\\Webhook::constructEvent', function() use ($payload) {
            return json_decode($payload);
        });

        Functions\when('get_option')->alias(fn() => ['stripe_secret' => 'sk', 'stripe_webhook_secret' => 'wh']);
        Functions\when('sanitize_text_field')->alias(fn($v) => $v);

        Functions\when('get_users')->alias(fn() => [13]);

        Functions\expect('update_user_meta')
            ->once()
            ->with(13, 'ap_membership_level', 'Pro');
        Functions\expect('update_user_meta')
            ->once()
            ->with(13, 'ap_membership_expires', 1800000000);

        $request = $this->getMockBuilder(stdClass::class)
                        ->addMethods(['get_body'])
                        ->getMock();
        $request->method('get_body')->willReturn($payload);

        MembershipManager::handleStripeWebhook($request);

        \Patchwork\restoreAll();
    }
}
