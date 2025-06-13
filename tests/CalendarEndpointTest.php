<?php
use PHPUnit\Framework\TestCase;
use Tests\Stubs;
use EAD\Rest\CalendarEndpoint;

class CalendarEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        Stubs::$posts = [];
        Stubs::$meta = [];
        Stubs::$post_terms = [];
        Stubs::$user_meta = [];
        Stubs::$terms = [];
        Stubs::$db_result = null;
        Stubs::$logged_in = true;
    }

    public function test_get_calendar_events_returns_expected_fields()
    {
        Stubs::$posts = [
            (object) ['ID' => 1, 'post_title' => 'Event 1', 'post_content' => 'Desc1'],
            (object) ['ID' => 2, 'post_title' => 'Event 2', 'post_content' => 'Desc2'],
        ];

        Stubs::$meta = [
            1 => [
                'event_date'      => '2023-10-01',
                'event_location'  => 'Loc1',
                'event_latitude'  => '40.7128',
                'event_longitude' => '-74.0060',
            ],
            2 => [
                'event_date'      => '2023-11-05',
                'event_location'  => 'Loc2',
                'event_latitude'  => '34.0522',
                'event_longitude' => '-118.2437',
            ],
        ];

        Stubs::$post_terms = [
            1 => [
                'ead_event_category' => ['CatA'],
                'post_tag'          => ['tag1'],
            ],
            2 => [
                'ead_event_category' => ['CatB'],
                'post_tag'          => [],
            ],
        ];

        Stubs::$user_meta = ['ead_rsvps' => [1]];

        $endpoint = new CalendarEndpoint();
        $response = $endpoint->get_calendar_events(new WP_REST_Request());

        $this->assertCount(2, $response->data);
        $e1 = $response->data[0];
        $e2 = $response->data[1];

        $this->assertSame(1, $e1['id']);
        $this->assertSame('Event 1', $e1['title']);
        $this->assertSame('2023-10-01', $e1['start']);
        $this->assertTrue($e1['rsvped']);
        $this->assertSame('CatA', $e1['category']);
        $this->assertSame('Loc1', $e1['location']);
        $this->assertSame(['tag1'], $e1['tags']);
        $this->assertSame('Desc1', $e1['description']);
        $this->assertSame(40.7128, $e1['latitude']);
        $this->assertSame(-74.0060, $e1['longitude']);

        $this->assertFalse($e2['rsvped']);
        $this->assertSame(34.0522, $e2['latitude']);
        $this->assertSame(-118.2437, $e2['longitude']);
    }

    public function test_get_event_categories_returns_array()
    {
        Stubs::$terms = [
            'ead_event_category' => [
                (object) ['slug' => 'music', 'name' => 'Music'],
            ],
        ];

        $endpoint = new CalendarEndpoint();
        $response = $endpoint->get_event_categories(new WP_REST_Request());

        $this->assertIsArray($response->data);
        $this->assertSame('music', $response->data[0]['slug']);
        $this->assertSame('Music', $response->data[0]['name']);
    }

    public function test_get_event_locations_returns_array()
    {
        Stubs::$db_result = ['Paris', 'London'];
        $endpoint = new CalendarEndpoint();
        $result = $endpoint->get_event_locations(new WP_REST_Request());

        $this->assertSame(['Paris', 'London'], $result);
    }

    public function test_get_event_tags_returns_array()
    {
        Stubs::$terms = [
            'post_tag' => [
                (object) ['name' => 'tag1'],
                (object) ['name' => 'tag2'],
            ],
        ];

        $endpoint = new CalendarEndpoint();
        $result = $endpoint->get_event_tags(new WP_REST_Request());

        $this->assertIsArray($result);
        $this->assertSame('tag1', $result[0]['name']);
        $this->assertSame('tag2', $result[1]['name']);
    }
}
