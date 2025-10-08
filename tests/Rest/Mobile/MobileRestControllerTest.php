<?php

namespace Tests\Rest\Mobile;

use ArtPulse\Mobile\EventGeo;
use ArtPulse\Mobile\FollowService;
use ArtPulse\Mobile\JWT;
use ArtPulse\Mobile\RefreshTokens;
use WP_REST_Request;
use WP_UnitTestCase;

class MobileRestControllerTest extends WP_UnitTestCase
{
    private int $user_id;
    private string $password = 'secret123!';

    public function set_up(): void
    {
        parent::set_up();

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ap_rl_%' OR option_name LIKE '_transient_timeout_ap_rl_%'");

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        \ArtPulse\Mobile\EventInteractions::install_tables();
        FollowService::install_table();
        EventGeo::install_table();

        $this->user_id = $this->factory->user->create([
            'role'       => 'subscriber',
            'user_login' => 'mobile-user',
            'user_pass'  => $this->password,
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
            'device_id'  => 'ios-device-1',
        ]);

        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('refreshToken', $data);
        $this->assertArrayHasKey('refreshExpires', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertSame('device-token-123', get_user_meta($this->user_id, 'ap_mobile_push_token', true));
    }

    public function test_refresh_rotates_and_invalidates_previous_token(): void
    {
        $login = new WP_REST_Request('POST', '/artpulse/v1/mobile/login');
        $login->set_body_params([
            'username'  => 'mobile-user',
            'password'  => $this->password,
            'device_id' => 'android-device',
        ]);

        $login_response = rest_do_request($login);
        $this->assertSame(200, $login_response->get_status());
        $refresh_token = $login_response->get_data()['refreshToken'];

        $refresh = new WP_REST_Request('POST', '/artpulse/v1/mobile/auth/refresh');
        $refresh->set_body_params([
            'refresh_token' => $refresh_token,
        ]);

        $refresh_response = rest_do_request($refresh);
        $this->assertSame(200, $refresh_response->get_status());
        $refresh_data = $refresh_response->get_data();
        $this->assertNotSame($refresh_token, $refresh_data['refreshToken']);

        $second = rest_do_request($refresh);
        $this->assertSame(401, $second->get_status());
        $this->assertSame('refresh_reuse', $second->get_data()['code']);
    }

    public function test_refresh_rejects_expired_token(): void
    {
        $token = RefreshTokens::mint($this->user_id, 'test-device');

        $records = get_user_meta($this->user_id, 'ap_mobile_refresh_tokens', true);
        $this->assertIsArray($records);
        $records[0]['expires_at'] = time() - HOUR_IN_SECONDS;
        update_user_meta($this->user_id, 'ap_mobile_refresh_tokens', $records);

        $request = new WP_REST_Request('POST', '/artpulse/v1/mobile/auth/refresh');
        $request->set_body_params([
            'refresh_token' => $token['token'],
        ]);

        $response = rest_do_request($request);
        $this->assertSame(401, $response->get_status());
        $this->assertSame('ap_refresh_expired', $response->get_data()['code']);
    }

    public function test_refresh_rejects_unknown_user(): void
    {
        $token = RefreshTokens::mint($this->user_id, 'test-device');
        wp_delete_user($this->user_id);

        $request = new WP_REST_Request('POST', '/artpulse/v1/mobile/auth/refresh');
        $request->set_body_params([
            'refresh_token' => $token['token'],
        ]);

        $response = rest_do_request($request);
        $this->assertSame(401, $response->get_status());
        $this->assertSame('ap_invalid_refresh', $response->get_data()['code']);
    }

    public function test_refresh_reuse_revokes_device_sessions(): void
    {
        $login = new WP_REST_Request('POST', '/artpulse/v1/mobile/login');
        $login->set_body_params([
            'username'  => 'mobile-user',
            'password'  => $this->password,
            'device_id' => 'reuse-device',
        ]);

        $login_response = rest_do_request($login);
        $this->assertSame(200, $login_response->get_status());
        $data           = $login_response->get_data();
        $refresh_token  = $data['refreshToken'];

        $refresh_request = new WP_REST_Request('POST', '/artpulse/v1/mobile/auth/refresh');
        $refresh_request->set_body_params([
            'refresh_token' => $refresh_token,
        ]);

        $rotate = rest_do_request($refresh_request);
        $this->assertSame(200, $rotate->get_status());
        $rotate_data = $rotate->get_data();

        $reuse = rest_do_request($refresh_request);
        $this->assertSame(401, $reuse->get_status());
        $this->assertSame('refresh_reuse', $reuse->get_data()['code']);

        $sessions = new WP_REST_Request('GET', '/artpulse/v1/mobile/auth/sessions');
        $sessions->set_header('Authorization', 'Bearer ' . $rotate_data['token']);
        $sessions_response = rest_do_request($sessions);
        $this->assertSame(200, $sessions_response->get_status());
        $this->assertSame([], $sessions_response->get_data()['sessions']);
    }

    public function test_password_change_revokes_refresh_tokens(): void
    {
        $login = new WP_REST_Request('POST', '/artpulse/v1/mobile/login');
        $login->set_body_params([
            'username'  => 'mobile-user',
            'password'  => $this->password,
            'device_id' => 'password-device',
        ]);

        $login_response = rest_do_request($login);
        $this->assertSame(200, $login_response->get_status());
        $refresh_token = $login_response->get_data()['refreshToken'];

        wp_set_password('new-pass-123', $this->user_id);

        $refresh = new WP_REST_Request('POST', '/artpulse/v1/mobile/auth/refresh');
        $refresh->set_body_params([
            'refresh_token' => $refresh_token,
        ]);

        $response = rest_do_request($refresh);
        $this->assertSame(401, $response->get_status());
        $this->assertSame('refresh_reuse', $response->get_data()['code']);
    }

    public function test_sessions_endpoint_lists_devices_and_allows_revocation(): void
    {
        $login = new WP_REST_Request('POST', '/artpulse/v1/mobile/login');
        $login->set_body_params([
            'username'  => 'mobile-user',
            'password'  => $this->password,
            'device_id' => 'tablet-01',
        ]);

        $login_response = rest_do_request($login);
        $this->assertSame(200, $login_response->get_status());
        $token = $login_response->get_data()['token'];

        $list = new WP_REST_Request('GET', '/artpulse/v1/mobile/auth/sessions');
        $list->set_header('Authorization', 'Bearer ' . $token);
        $sessions = rest_do_request($list);
        $this->assertSame(200, $sessions->get_status());
        $session_data = $sessions->get_data();
        $this->assertCount(1, $session_data['sessions']);
        $this->assertSame('tablet-01', $session_data['sessions'][0]['deviceId']);

        $delete = new WP_REST_Request('DELETE', '/artpulse/v1/mobile/auth/sessions/tablet-01');
        $delete->set_header('Authorization', 'Bearer ' . $token);
        $delete_response = rest_do_request($delete);
        $this->assertSame(200, $delete_response->get_status());

        $sessions = rest_do_request($list);
        $this->assertSame([], $sessions->get_data()['sessions']);
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
        $ongoing_event = wp_insert_post([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Ongoing Event',
        ]);
        update_post_meta($ongoing_event, '_ap_event_start', gmdate('c', strtotime('-1 hour')));
        update_post_meta($ongoing_event, '_ap_event_end', gmdate('c', strtotime('+1 hour')));
        update_post_meta($ongoing_event, '_ap_event_location', 'Gallery Live');
        update_post_meta($ongoing_event, '_ap_event_latitude', '40.2');
        update_post_meta($ongoing_event, '_ap_event_longitude', '-70.3');
        EventGeo::sync($ongoing_event);

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
        $this->assertCount(3, $data['events']);
        $this->assertSame($ongoing_event, $data['events'][0]['id']);
        $this->assertTrue($data['events'][0]['isOngoing']);
        $this->assertSame($near_event, $data['events'][1]['id']);
        $this->assertFalse($data['events'][1]['isOngoing']);
        $this->assertSame($far_event, $data['events'][2]['id']);
        $this->assertNotNull($data['events'][0]['distance_m']);
        $this->assertGreaterThan(0, $data['events'][2]['distance_m']);
    }

    public function test_geosearch_with_bounds_filters_results(): void
    {
        $inside_one = wp_insert_post([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Inside One',
        ]);
        update_post_meta($inside_one, '_ap_event_start', gmdate('c', strtotime('+1 day')));
        update_post_meta($inside_one, '_ap_event_location', 'Inside Hall');
        update_post_meta($inside_one, '_ap_event_latitude', '40.0');
        update_post_meta($inside_one, '_ap_event_longitude', '-70.0');
        EventGeo::sync($inside_one);

        $inside_two = wp_insert_post([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Inside Two',
        ]);
        update_post_meta($inside_two, '_ap_event_start', gmdate('c', strtotime('+2 day')));
        update_post_meta($inside_two, '_ap_event_location', 'Inside Hall 2');
        update_post_meta($inside_two, '_ap_event_latitude', '40.5');
        update_post_meta($inside_two, '_ap_event_longitude', '-70.4');
        EventGeo::sync($inside_two);

        $outside = wp_insert_post([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Outside',
        ]);
        update_post_meta($outside, '_ap_event_start', gmdate('c', strtotime('+3 day')));
        update_post_meta($outside, '_ap_event_location', 'Outside Hall');
        update_post_meta($outside, '_ap_event_latitude', '45.0');
        update_post_meta($outside, '_ap_event_longitude', '-80.0');
        EventGeo::sync($outside);

        $token = JWT::issue($this->user_id)['token'];

        $request = new WP_REST_Request('GET', '/artpulse/v1/mobile/events');
        $request->set_param('bounds', '39.5,-70.5,41.0,-69.5');
        $request->set_param('limit', 10);
        $request->set_header('Authorization', 'Bearer ' . $token);

        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();

        $this->assertCount(2, $data['events']);
        $ids = wp_list_pluck($data['events'], 'id');
        $this->assertContains($inside_one, $ids);
        $this->assertContains($inside_two, $ids);
        $this->assertNotContains($outside, $ids);
        foreach ($data['events'] as $event) {
            $this->assertArrayHasKey('distance_m', $event);
            $this->assertIsInt($event['distance_m']);
            $this->assertArrayHasKey('isOngoing', $event);
            $this->assertIsBool($event['isOngoing']);
        }
    }

    public function test_geosearch_invalid_bounds_returns_error(): void
    {
        $token = JWT::issue($this->user_id)['token'];

        $request = new WP_REST_Request('GET', '/artpulse/v1/mobile/events');
        $request->set_param('bounds', 'invalid-bbox');
        $request->set_header('Authorization', 'Bearer ' . $token);

        $response = rest_do_request($request);
        $this->assertSame(400, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('ap_geo_invalid', $data['code']);
        $this->assertArrayHasKey('details', $data);
        $this->assertArrayHasKey('ap_geo_invalid', $data['details']);
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

    public function test_rate_limiter_returns_headers_and_429(): void
    {
        $event_id = wp_insert_post([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Rate Event',
        ]);

        add_filter('artpulse_mobile_rate_limit', static function ($limit, $request, $is_write) {
            if ($is_write && $request instanceof WP_REST_Request && false !== strpos($request->get_route(), '/mobile/events')) {
                return 2;
            }

            return $limit;
        }, 10, 3);

        try {
            $token = JWT::issue($this->user_id)['token'];

            $request = new WP_REST_Request('POST', '/artpulse/v1/mobile/events/' . $event_id . '/like');
            $request->set_header('Authorization', 'Bearer ' . $token);

            $first  = rest_do_request($request);
            $second = rest_do_request($request);

            $this->assertSame(200, $first->get_status());
            $this->assertSame(200, $second->get_status());

            $third = rest_do_request($request);
            $this->assertSame(429, $third->get_status());

            $headers = array_change_key_case($third->get_headers(), CASE_LOWER);
            $this->assertSame('2', $headers['x-ratelimit-limit'] ?? null);
            $this->assertSame('0', $headers['x-ratelimit-remaining'] ?? null);
            $this->assertArrayHasKey('x-ratelimit-reset', $headers);
            $this->assertArrayHasKey('retry-after', $headers);
        } finally {
            remove_all_filters('artpulse_mobile_rate_limit');
            remove_all_filters('artpulse_mobile_rate_window');
        }
    }

    public function test_error_responses_are_standardized(): void
    {
        $request = new WP_REST_Request('GET', '/artpulse/v1/mobile/me');
        $response = rest_do_request($request);

        $this->assertSame(401, $response->get_status());
        $data = $response->get_data();
        $this->assertSame(['code', 'message', 'details'], array_keys($data));
        $this->assertArrayHasKey('ap_missing_token', $data['details']);
        $this->assertNotEmpty($data['details']['ap_missing_token']['messages']);
    }
}
