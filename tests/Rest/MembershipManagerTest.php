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
}
