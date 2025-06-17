<?php
// tests/WooCommerceIntegrationTest.php

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

        $mockUser = $this->getMockBuilder(stdClass::class)
                         ->addMethods(['set_role'])
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
}
