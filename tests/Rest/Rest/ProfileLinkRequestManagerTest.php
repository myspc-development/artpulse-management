<?php

namespace Tests\Rest;

use WP_UnitTestCase;
use WP_REST_Request;

class ProfileLinkRequestManagerTest extends \WP_UnitTestCase
{
    protected $user_id;
    protected $target_id;

    public function set_up(): void
    {
        parent::set_up();

        $this->user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($this->user_id);

        $this->target_id = $this->factory->post->create([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
        ]);
    }

    public function test_can_create_link_request()
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/link-requests');
        $request->set_body_params([
            'target_id' => $this->target_id
        ]);
        $request->set_header('content-type', 'application/x-www-form-urlencoded');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertIsInt($data['request_id']);
        $this->assertEquals('pending', $data['status']);
    }

    public function test_invalid_target_returns_error()
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/link-requests');
        $request->set_body_params(['target_id' => 999999]);

        $response = rest_do_request($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals(404, $response->get_error_data()['status']);
    }
}