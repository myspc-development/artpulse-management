<?php
use PHPUnit\Framework\TestCase;
use Tests\Stubs;
use EAD\Rest\UsersEndpoint;

class UsersEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        Stubs::$users = [];
        Stubs::$last_user_query = [];
        Stubs::$logged_in = true;
    }

    public function test_search_users_returns_results()
    {
        Stubs::$users = [
            (object) ['ID' => 1, 'display_name' => 'Alice'],
            (object) ['ID' => 2, 'display_name' => 'Bob'],
        ];

        $endpoint = new UsersEndpoint();
        $ref = new ReflectionClass($endpoint);
        $method = $ref->getMethod('search_users');
        $method->setAccessible(true);

        $request = new WP_REST_Request();
        $request->set_param('term', 'a');

        $response = $method->invoke($endpoint, $request);

        $this->assertCount(2, $response->data);
        $this->assertSame(1, $response->data[0]['id']);
        $this->assertSame('*a*', Stubs::$last_user_query['search']);
    }
}
