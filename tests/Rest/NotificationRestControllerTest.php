<?php

namespace Tests\Rest;

use ArtPulse\Community\NotificationManager;
use WP_UnitTestCase;
use WP_REST_Request;

class NotificationRestControllerTest extends \WP_UnitTestCase
{
    protected $user_id;
    private string $table;

    public function set_up(): void
    {
        parent::set_up();
        $this->user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($this->user_id);

        global $wpdb;
        NotificationManager::install_notifications_table();
        $this->table = $wpdb->prefix . 'ap_notifications';
        $wpdb->query("TRUNCATE TABLE {$this->table}");
    }

    private function create_notification(string $content = 'Test Notification'): int
    {
        global $wpdb;

        NotificationManager::add($this->user_id, 'test', null, null, $content);

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
                $this->user_id
            )
        );
    }

    public function test_can_list_notifications()
    {
        $notification_id = $this->create_notification();

        $request = new WP_REST_Request('GET', '/artpulse/v1/notifications');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertCount(1, $data['notifications']);
        $this->assertEquals($notification_id, $data['notifications'][0]['id']);
        $this->assertEquals('Test Notification', $data['notifications'][0]['content']);
        $this->assertFalse($data['notifications'][0]['read']);
    }

    public function test_can_mark_notification_as_read()
    {
        $notification_id = $this->create_notification();

        $request = new WP_REST_Request('POST', '/artpulse/v1/notifications/read');
        $request->set_body_params(['notification_id' => $notification_id]);
        $response = rest_do_request($request);

        $this->assertSame(200, $response->get_status());

        global $wpdb;
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$this->table} WHERE id = %d",
            $notification_id
        ));

        $this->assertSame('read', $status);
    }

    public function test_can_delete_notification()
    {
        $notification_id = $this->create_notification();

        $request = new WP_REST_Request('DELETE', '/artpulse/v1/notifications');
        $request->set_body_params(['notification_id' => $notification_id]);
        $response = rest_do_request($request);

        $this->assertSame(200, $response->get_status());

        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE id = %d",
            $notification_id
        ));

        $this->assertSame('0', $count);
    }
}
