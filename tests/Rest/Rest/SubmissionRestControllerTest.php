<?php

namespace Tests\Rest;

use WP_UnitTestCase;
use WP_REST_Request;

class SubmissionRestControllerTest extends \WP_UnitTestCase
{
    protected $user_id;

    public function set_up(): void
    {
        parent::set_up();
        $this->user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($this->user_id);
    }

    public function test_submit_event_post()
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $request->set_body_params([
            'post_type'      => 'artpulse_event',
            'title'          => 'Test Event',
            'event_date'     => '2025-12-01',
            'event_location' => 'Berlin',
        ]);

        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('Test Event', $data['title']);
    }
}