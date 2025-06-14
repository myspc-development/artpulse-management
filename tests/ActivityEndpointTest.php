<?php
use PHPUnit\Framework\TestCase;
use Tests\Stubs;
use EAD\Rest\ActivityEndpoint;

class ActivityEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        Stubs::$user_meta = [];
        Stubs::$posts = [];
        Stubs::$logged_in = true;
    }

    public function test_get_activity_data_returns_month_counts()
    {
        Stubs::$user_meta = ['ead_rsvps' => [1,2,3]];
        Stubs::$posts = [
            (object)['ID' => 1, 'post_date' => '2023-08-01 10:00:00'],
            (object)['ID' => 2, 'post_date' => '2023-08-15 10:00:00'],
            (object)['ID' => 3, 'post_date' => '2023-09-05 10:00:00'],
        ];

        $endpoint = new ActivityEndpoint();
        $ref = new ReflectionClass($endpoint);
        $method = $ref->getMethod('get_activity_data');
        $method->setAccessible(true);

        $request = new WP_REST_Request();
        $response = $method->invoke($endpoint, $request);

        $this->assertSame(['2023-08', '2023-09'], $response->data['labels']);
        $this->assertSame([2, 1], $response->data['data']);
    }
}
