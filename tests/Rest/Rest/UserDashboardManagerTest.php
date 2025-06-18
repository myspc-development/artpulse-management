<?php

namespace Tests\Rest;

use WP_UnitTestCase;
use WP_REST_Request;

class UserDashboardManagerTest extends WP_UnitTestCase
{
    protected $user_id;

    public function set_up(): void
    {
        parent::set_up();
        $this->user_id = $this->factory->user->create();
        wp_set_current_user($this->user_id);
    }

    public function test_get_dashboard_data()
    {
        $request = new WP_REST_Request('GET', '/artpulse/v1/user/dashboard');
        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());
        $this->assertArrayHasKey('membership_level', $response->get_data());
    }
}