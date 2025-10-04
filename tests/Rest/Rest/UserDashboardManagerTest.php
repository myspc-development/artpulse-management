<?php

namespace Tests\Rest;

use ArtPulse\Core\RoleDashboards;
use WP_REST_Request;
class UserDashboardManagerTest extends \WP_UnitTestCase
{
    protected $user_id;

    public function set_up(): void
    {
        parent::set_up();
        if (!get_role('member')) {
            add_role('member', 'Member', ['read' => true]);
        }

        $this->user_id = $this->factory->user->create(['role' => 'member']);
        wp_set_current_user($this->user_id);

        RoleDashboards::register();
        do_action('rest_api_init');
    }

    public function test_get_dashboard_data()
    {
        $request = new WP_REST_Request('GET', '/artpulse/v1/dashboard');
        $request->set_param('role', 'member');
        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('favorites', $data);
        $this->assertArrayHasKey('follows', $data);
        $this->assertArrayHasKey('submissions', $data);
        $this->assertArrayHasKey('metrics', $data);
        $this->assertArrayHasKey('profile', $data);
    }
}
