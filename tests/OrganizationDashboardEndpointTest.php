<?php
use PHPUnit\Framework\TestCase;
use Tests\Stubs;
use EAD\Rest\OrganizationDashboardEndpoint;

class OrganizationDashboardEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        Stubs::$posts = [1, 2];
        Stubs::$last_query_args = [];
        Stubs::$db_result = 5;
        Stubs::$db_last_query = [];
    }

    public function test_get_user_total_rsvps_returns_count()
    {
        $endpoint = new OrganizationDashboardEndpoint();
        $reflection = new ReflectionClass($endpoint);
        $method = $reflection->getMethod('getUserTotalRsvps');
        $method->setAccessible(true);

        $total = $method->invoke($endpoint, 10);

        $this->assertSame(5, $total);

        $args = Stubs::$last_query_args;
        $this->assertSame('ead_event', $args['post_type']);
        $this->assertSame(10, $args['author']);
        $this->assertSame([ 'publish', 'pending', 'draft' ], $args['post_status']);

        $this->assertSame([1,2], Stubs::$db_last_query[1]);
        $this->assertStringContainsString('ead_rsvps', Stubs::$db_last_query[0]);
    }
}
