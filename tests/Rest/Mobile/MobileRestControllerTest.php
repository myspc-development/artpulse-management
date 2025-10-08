<?php

namespace Tests\Rest\Mobile;

use ArtPulse\Mobile\EventGeo;
use ArtPulse\Mobile\FollowService;
use ArtPulse\Mobile\JWT;
use WP_REST_Request;
use WP_UnitTestCase;

class MobileRestControllerTest extends WP_UnitTestCase
{
    private int $user_id;
    private string $password = 'secret123!';

    public function set_up(): void
    {
        parent::set_up();

        \ArtPulse\Mobile\EventInteractions::install_tables();
        FollowService::install_table();
        EventGeo::install_table();

        $this->user_id = $this->factory->user->create([
            'role'      => 'subscriber',
            'user_login'=> 'mobile-user',
            'user_pass' => $this->password,
        ]);

        wp_set_password($this->password, $this->user_id);
        rest_get_server();
        do_action('rest_api_init');
    }

    public function test_login_returns_token_and_push_token_is_stored(): void
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/mobile/login');
        $request->set_body_params([
            'username'   => 'mobile-user',
            'password'   => $this->password,
            'push_token' => 'device-token-123',
        ]);

        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertSame('device-token-123', get_user_meta($this->user_id, 'ap_mobile_push_token', true));
    }

    public function test_like_event_is_idempotent_and_counts_update(): void
    {
        $event_id = wp_insert_post([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Geo Event',
        ]);

        update_post_meta($event_id, '_ap_event_start', gmdate('c', strtotime('+2 days')));
        update_post_meta($event_id, '_ap_event_location', 'Gallery');
        update_post_meta($event_id, '_ap_event_latitude', '40.0');
        update_post_meta($event_id, '_ap_event_longitude', '-70.0');
        EventGeo::sync($event_id);

        $token = JWT::issue($this->user_id)['token'];

        $request = new WP_REST_Request('POST', '/artpulse/v1/mobile/events/' . $event_id . '/like');
        $request->set_header('Authorization', 'Bearer ' . $token);

        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['liked']);
        $this->assertSame(1, $data['likes']);

        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertSame(1, $data['likes']);

        $delete = new WP_REST_Request('DELETE', '/artpulse/v1/mobile/events/' . $event_id . '/like');
        $delete->set_header('Authorization', 'Bearer ' . $token);
        $delete_response = rest_do_request($delete);
        $this->assertSame(200, $delete_response->get_status());
        $delete_data = $delete_response->get_data();
        $this->assertFalse($delete_data['liked']);
        $this->assertSame(0, $delete_data['likes']);
    }

    public function test_geosearch_orders_by_distance_then_time(): void
    {
        $near_event = wp_insert_post([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Near Event',
        ]);
        update_post_meta($near_event, '_ap_event_start', gmdate('c', strtotime('+1 day')));
        update_post_meta($near_event, '_ap_event_location', 'Near Hall');
        update_post_meta($near_event, '_ap_event_latitude', '40.0');
        update_post_meta($near_event, '_ap_event_longitude', '-70.0');
        EventGeo::sync($near_event);

        $far_event = wp_insert_post([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Far Event',
        ]);
        update_post_meta($far_event, '_ap_event_start', gmdate('c', strtotime('+2 days')));
        update_post_meta($far_event, '_ap_event_location', 'Far Hall');
        update_post_meta($far_event, '_ap_event_latitude', '41.0');
        update_post_meta($far_event, '_ap_event_longitude', '-71.0');
        EventGeo::sync($far_event);

        $token = JWT::issue($this->user_id)['token'];

        $request = new WP_REST_Request('GET', '/artpulse/v1/mobile/events');
        $request->set_param('lat', 40.0);
        $request->set_param('lng', -70.0);
        $request->set_param('radius', 500);
        $request->set_header('Authorization', 'Bearer ' . $token);

        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertCount(2, $data['events']);
        $this->assertSame($near_event, $data['events'][0]['id']);
        $this->assertSame($far_event, $data['events'][1]['id']);
    }

    public function test_feed_returns_followed_org_events(): void
    {
        $org_id = wp_insert_post([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
            'post_title'  => 'Org',
        ]);

        $event_id = wp_insert_post([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Org Event',
        ]);
        update_post_meta($event_id, '_ap_event_start', gmdate('c', strtotime('+3 days')));
        update_post_meta($event_id, '_ap_event_location', 'Org Hall');
        update_post_meta($event_id, '_ap_event_organization', $org_id);
        update_post_meta($event_id, '_ap_event_latitude', '40.5');
        update_post_meta($event_id, '_ap_event_longitude', '-70.5');
        EventGeo::sync($event_id);

        FollowService::follow($this->user_id, $org_id, 'org');

        $token = JWT::issue($this->user_id)['token'];
        $request = new WP_REST_Request('GET', '/artpulse/v1/mobile/feed');
        $request->set_header('Authorization', 'Bearer ' . $token);

        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $ids  = wp_list_pluck($data['events'], 'id');

        $this->assertContains($event_id, $ids);
    }
}
