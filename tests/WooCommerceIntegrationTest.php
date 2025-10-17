<?php
// tests/WooCommerceIntegrationTest.php

if ( ! class_exists('WP_User') ) {
    class WP_User
    {
        public $user_email;

        public function set_role( $role ): void
        {
        }
    }
}

if ( ! class_exists('WC_Order') ) {
    class WC_Order
    {
        public function get_user_id()
        {
            return 0;
        }

        public function get_items(): array
        {
            return [];
        }
    }
}

namespace ArtPulse\Core;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use ArtPulse\Core\WooCommerceIntegration;

class WooCommerceIntegrationTest extends TestCase
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

    public function testAssignMembershipSetsMetaAndSendsEmail()
    {
        $user_id = 456;
        $level   = 'Pro';

        $mockUser = $this->getMockBuilder(\WP_User::class)
                         ->onlyMethods(['set_role'])
                         ->getMock();
        $mockUser->user_email = 'buyer@example.test';

        Functions\when('get_userdata')->alias(fn($id) => $mockUser);

        $mockUser->expects($this->once())
                 ->method('set_role')
                 ->with('subscriber');

        Functions\expect('update_user_meta')
            ->once()
            ->with($user_id, 'ap_membership_level', $level);

        Functions\expect('update_user_meta')
            ->once()
            ->with(
                $user_id,
                'ap_membership_expires',
                $this->callback(fn($arg) => is_int($arg))
            );

        Functions\expect('wp_mail')
            ->once()
            ->with(
                'buyer@example.test',
                $this->stringContains('Your ArtPulse membership'),
                $this->stringContains('expires on')
            );

        $ref = new ReflectionMethod(WooCommerceIntegration::class, 'assignMembership');
        $ref->setAccessible(true);
        $ref->invoke(null, $user_id, $level);
    }

    public function testAssignMembershipReturnsEarlyWhenUserMissing(): void
    {
        $user_id = 789;

        Functions\when('get_userdata')->justReturn(false);

        Functions\expect('error_log')
            ->once()
            ->with($this->stringContains('assignMembership'));

        Functions\expect('update_user_meta')->never();
        Functions\expect('wp_mail')->never();

        $ref = new ReflectionMethod(WooCommerceIntegration::class, 'assignMembership');
        $ref->setAccessible(true);
        $ref->invoke(null, $user_id, 'Basic');
    }

    public function testHandleRefundOrCancelReturnsEarlyWhenUserMissing(): void
    {
        $order_id = 246;
        $user_id  = 135;

        $order = $this->getMockBuilder(\WC_Order::class)
                      ->onlyMethods(['get_user_id'])
                      ->getMock();
        $order->expects($this->once())
              ->method('get_user_id')
              ->willReturn($user_id);

        Functions\when('wc_get_order')->justReturn($order);
        Functions\when('get_userdata')->justReturn(false);

        Functions\expect('error_log')
            ->once()
            ->with($this->stringContains('handleRefundOrCancel'));

        Functions\expect('update_user_meta')->never();
        Functions\expect('wp_mail')->never();

        WooCommerceIntegration::handleRefundOrCancel($order_id);
    }
}
