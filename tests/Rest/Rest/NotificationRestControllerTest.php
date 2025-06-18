<?php

namespace Tests\Rest;

use WP_UnitTestCase;
use WP_REST_Request;

class NotificationRestControllerTest extends WP_UnitTestCase
{
    protected $user_id;

    public function set_up(): void
    {
        parent::set_up();
        $this->user_id = $this->factory->user->create();
        wp_set_current_user($this->user_id);
    }

    public function test_get_notifications_returns_array()
    {
        $request = new WP_REST_Request('GET', '/artpulse/v1/notifications');
        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());
        $this->assertArrayHasKey('notifications', $response->get_data());
    }
}