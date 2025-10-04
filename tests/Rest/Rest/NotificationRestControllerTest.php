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
        $this->user_id = $this->factory->user->create();
        wp_set_current_user($this->user_id);

        global $wpdb;
        NotificationManager::install_notifications_table();
        $this->table = $wpdb->prefix . 'ap_notifications';
        $wpdb->query("TRUNCATE TABLE {$this->table}");
    }

    public function test_get_notifications_returns_array()
    {
        NotificationManager::add($this->user_id, 'test', null, null, 'Hello World');

        $request = new WP_REST_Request('GET', '/artpulse/v1/notifications');
        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('notifications', $data);
        $this->assertNotEmpty($data['notifications']);
        $this->assertSame('Hello World', $data['notifications'][0]['content']);
    }
}
