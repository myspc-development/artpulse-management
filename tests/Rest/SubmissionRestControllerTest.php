<?php

namespace Tests\Rest;

use WP_UnitTestCase;
use WP_REST_Request;

class SubmissionRestControllerTest extends WP_UnitTestCase
{
    protected $user_id;

    public function set_up(): void
    {
        parent::set_up();
        $this->user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($this->user_id];
    }

    public function test_can_submit_event()
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $request->set_body_params([
            'post_type'      => 'artpulse_event',
            'title'          => 'Sample Event',
            'event_date'     => '2025-06-30',
            'event_location' => 'Virtual'
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertEquals('Sample Event', $data['title']);
        $this->assertEquals('artpulse_event', $data['type']);

        $meta_date = get_post_meta($data['id'], '_ap_event_date', true);
        $this->assertEquals('2025-06-30', $meta_date);
    }

    public function test_invalid_post_type_rejected()
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/submissions');
        $request->set_body_params([
            'post_type' => 'invalid_type',
            'title'     => 'Ignored'
        ]);

        $response = rest_do_request($request);
        $this->assertSame(400, $response->get_status());
    }
}
