<?php

namespace Tests\Rest;

use WP_UnitTestCase;
use WP_REST_Request;

class DirectoryManagerTest extends WP_UnitTestCase
{
    protected $event;

    public function set_up(): void
    {
        parent::set_up();

        $this->event = $this->factory->post->create([
            'post_type'   => 'artpulse_event',
            'post_title'  => 'Sample Event',
            'post_status' => 'publish',
            'meta_input'  => [
                '_ap_event_date'     => '2025-08-01',
                '_ap_event_location' => 'NYC',
            ]
        ]);
    }

    public function test_can_filter_events()
    {
        $request = new WP_REST_Request('GET', '/artpulse/v1/filter');
        $request->set_query_params([
            'type'  => 'event',
            'limit' => 5,
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertNotEmpty($data);
        $this->assertSame('Sample Event', $data[0]['title']);
        $this->assertEquals('NYC', $data[0]['location']);
    }

    public function test_invalid_type_returns_error()
    {
        $request = new WP_REST_Request('GET', '/artpulse/v1/filter');
        $request->set_query_params(['type' => 'invalid_type']);

        $response = rest_do_request($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals(400, $response->get_error_data()['status']);
    }
}