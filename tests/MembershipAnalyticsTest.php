<?php
use PHPUnit\Framework\TestCase;
use Tests\Stubs;
use EAD\Admin\MembershipAnalytics;

class MembershipAnalyticsTest extends TestCase {
    protected function setUp(): void {
        Stubs::$users = [1, 2];
        Stubs::$user_meta = [
            'membership_level'    => 'pro',
            'membership_end_date' => '2025-01-01',
        ];
        Stubs::$last_user_query = [];
    }

    public function test_get_member_stats_returns_counts() {
        $stats = MembershipAnalytics::get_member_stats();
        $this->assertSame(2, $stats['total']);
        $this->assertSame(2, $stats['active']);
        $this->assertSame(0, $stats['expired']);
        $this->assertSame(['pro' => 2], $stats['levels']);
        $this->assertSame('membership_level', Stubs::$last_user_query['meta_query'][0]['key']);
        $this->assertSame(-1, Stubs::$last_user_query['number']);
    }
}
