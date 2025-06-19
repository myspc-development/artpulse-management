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
        $this->user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($this->user_id);

        // Add a dummy notification
        update_user_meta($this->user_id, '_ap_notifications', [
            ['id' => 123, 'message' => 'Test Notification', 'read' => false]
        ]);
    }

    public function test_can_list_notifications()
    {
        $request = new WP_REST_Request('GET', '/artpulse/v1/notifications');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertCount(1, $data['notifications']);
        $this->assertEquals('Test Notification', $data['notifications'][0]['message']);
    }

    public function test_can_mark_notification_as_read()
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/notifications/read');
        $request->set_body_params(['notification_id' => 123]);
        $response = rest_do_request($request);

        $this->assertSame(200, $response->get_status());

        $updated = get_user_meta($this->user_id, '_ap_notifications', true);
        $this->assertTrue($updated[0]['read']);
    }

    public function test_can_delete_notification()
    {
        $request = new WP_REST_Request('DELETE', '/artpulse/v1/notifications');
        $request->set_body_params(['notification_id' => 123]);
        $response = rest_do_request($request);

        $this->assertSame(200, $response->get_status());

        $updated = get_user_meta($this->user_id, '_ap_notifications', true);
        $this->assertCount(0, $updated);
    }
}
