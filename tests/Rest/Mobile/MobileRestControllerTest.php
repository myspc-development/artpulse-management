<?php

namespace Tests\Rest\Mobile;

use ArtPulse\Mobile\Cors;
use ArtPulse\Mobile\EventGeo;
use ArtPulse\Mobile\FollowService;
use ArtPulse\Mobile\JWT;
use ArtPulse\Mobile\NotificationPipeline;
use ArtPulse\Mobile\Notifications\NotificationProviderInterface;
use ArtPulse\Mobile\RefreshTokens;
use WP_REST_Request;
use WP_UnitTestCase;

class MobileRestControllerTest extends WP_UnitTestCase
{
    private int $user_id;
    private string $password = 'secret123!';
    private ?TestNotificationProvider $notificationProvider = null;
    /** @var array<string, mixed>|false */
    private $previousSettings;

    public function set_up(): void
    {
        parent::set_up();

        $this->previousSettings     = get_option('artpulse_settings');
        $this->notificationProvider = null;

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ap_rl_%' OR option_name LIKE '_transient_timeout_ap_rl_%'");

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        delete_option('ap_mobile_metrics_log');
        delete_option('ap_mobile_metrics_summary');

        \ArtPulse\Mobile\EventInteractions::install_tables();
        FollowService::install_table();
        EventGeo::install_table();

        $this->user_id = $this->factory->user->create([
            'role'       => 'subscriber',
            'user_login' => 'mobile-user',
            'user_pass'  => $this->password,
        ]);

        delete_user_meta($this->user_id, 'ap_mobile_muted_topics');

        wp_set_password($this->password, $this->user_id);
        rest_get_server();
        do_action('rest_api_init');
        add_filter('artpulse_mobile_allow_insecure', '__return_true');
        add_filter('artpulse_mobile_notification_provider', [$this, 'registerTestNotificationProvider'], 10, 2);
    }

    public function tear_down(): void
    {
        remove_filter('artpulse_mobile_allow_insecure', '__return_true');
        remove_filter('artpulse_mobile_notification_provider', [$this, 'registerTestNotificationProvider'], 10);

        if (false === $this->previousSettings) {
            delete_option('artpulse_settings');
        } else {
            update_option('artpulse_settings', $this->previousSettings);
        }

        $this->notificationProvider = null;
        parent::tear_down();
    }

    public function registerTestNotificationProvider($provider, string $slug)
    {
        if ('test-double' !== $slug) {
            return $provider;
        }

        $this->notificationProvider = new TestNotificationProvider();

        return $this->notificationProvider;
    }

    public function test_login_returns_token_and_push_token_is_stored(): void
    {
        $request = new WP_REST_Request('POST', '/artpulse/v1/mobile/login');
        $request->set_body_params([
            'username'     => 'mobile-user',
            'password'     => $this->password,
            'push_token'   => 'device-token-123',
            'device_id'    => 'ios-device-1',
            'device_name'  => 'iPhone 15',
            'platform'     => 'iOS',
            'app_version'  => '1.2.3',
        ]);

        $response = rest_do_request($request);
        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('refreshToken', $data);
        $this->assertArrayHasKey('refreshExpires', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertSame('device-token-123', get_user_meta($this->user_id, 'ap_mobile_push_token', true));
        $tokens = get_user_meta($this->user_id, 'ap_mobile_push_tokens', true);
        $this->assertIsArray($tokens);
        $this->assertArrayHasKey('ios-device-1', $tokens);
        $this->assertSame('device-token-123', $tokens['ios-device-1']['token']);
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
            'username'     => 'mobile-user',
            'password'     => $this->password,
            'device_id'    => 'tablet-01',
            'device_name'  => 'iPad Pro',
            'platform'     => 'iPadOS',
            'app_version'  => '5.6.7',
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
        $session = $session_data['sessions'][0];
        $this->assertSame('tablet-01', $session['deviceId']);
        $this->assertSame('iPad Pro', $session['deviceName']);
        $this->assertSame('iPadOS', $session['platform']);
        $this->assertSame('5.6.7', $session['appVersion']);
        $this->assertNotEmpty($session['lastIp']);

        $delete = new WP_REST_Request('DELETE', '/artpulse/v1/mobile/auth/sessions/tablet-01');
        $delete->set_header('Authorization', 'Bearer ' . $token);
        $delete_response = rest_do_request($delete);
        $this->assertSame(200, $delete_response->get_status());

        $sessions = rest_do_request($list);
        $this->assertSame([], $sessions->get_data()['sessions']);
    }

    public function test_sessions_endpoint_accepts_token_within_clock_skew(): void
    {
        $token = JWT::issue($this->user_id, null, [
            'exp' => time() - 60,
        ])['token'];

        $request = new WP_REST_Request('GET', '/artpulse/v1/mobile/auth/sessions');
        $request->set_header('Authorization', 'Bearer ' . $token);

        $response = rest_do_request($request);

        $this->assertSame(200, $response->get_status());
    }

    public function test_sessions_endpoint_rejects_token_beyond_clock_skew(): void
    {
        $token = JWT::issue($this->user_id, null, [
            'exp' => time() - 300,
        ])['token'];

        $request = new WP_REST_Request('GET', '/artpulse/v1/mobile/auth/sessions');
        $request->set_header('Authorization', 'Bearer ' . $token);

        $response = rest_do_request($request);

        $this->assertSame(401, $response->get_status());
        $this->assertSame('auth_expired', $response->get_data()['code']);
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
        $this->assertArrayHasKey('server_tz', $data);
        $this->assertArrayHasKey('server_tz_offset_minutes', $data);
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
        $this->assertArrayHasKey('server_tz', $data);
        $this->assertArrayHasKey('server_tz_offset_minutes', $data);
        $this->assertSame(wp_timezone()->getName(), $data['server_tz']);
        $this->assertIsInt($data['server_tz_offset_minutes']);
        $first_start = $data['items'][0]['start'];
        $this->assertNotNull($first_start);
        $this->assertMatchesRegularExpression('/[+-]\d{2}:\d{2}$/', $first_start);
        $first_start_dt = new \DateTimeImmutable($first_start);
        $this->assertInstanceOf(\DateTimeImmutable::class, $first_start_dt);
        $first_end = $data['items'][0]['end'];
        $this->assertNotNull($first_end);
        $this->assertMatchesRegularExpression('/[+-]\d{2}:\d{2}$/', $first_end);
        $first_end_dt = new \DateTimeImmutable($first_end);
        $this->assertInstanceOf(\DateTimeImmutable::class, $first_end_dt);
        $this->assertCount(3, $data['items']);
        $this->assertSame($ongoing_event, $data['items'][0]['id']);
        $this->assertTrue($data['items'][0]['isOngoing']);
        $this->assertSame($near_event, $data['items'][1]['id']);
        $this->assertFalse($data['items'][1]['isOngoing']);
        $this->assertSame($far_event, $data['items'][2]['id']);
        $this->assertNotNull($data['items'][0]['distance_m']);
        $this->assertGreaterThan(0, $data['items'][2]['distance_m']);
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

        $this->assertCount(2, $data['items']);
        $this->assertSame(wp_timezone()->getName(), $data['server_tz']);
        $this->assertIsInt($data['server_tz_offset_minutes']);
        $ids = wp_list_pluck($data['items'], 'id');
        $this->assertContains($inside_one, $ids);
        $this->assertContains($inside_two, $ids);
        $this->assertNotContains($outside, $ids);
        foreach ($data['items'] as $event) {
            $this->assertArrayHasKey('distance_m', $event);
            $this->assertIsInt($event['distance_m']);
            $this->assertArrayHasKey('isOngoing', $event);
            $this->assertIsBool($event['isOngoing']);
        }
    }

    public function test_geosearch_cursor_pagination_returns_unique_events(): void
    {
        $event_ids = [];
        for ($i = 0; $i < 5; $i++) {
            $event_id = wp_insert_post([
                'post_type'   => 'artpulse_event',
                'post_status' => 'publish',
                'post_title'  => 'Paged Event ' . $i,
            ]);

            update_post_meta($event_id, '_ap_event_start', gmdate('c', strtotime('+' . ($i + 1) . ' day')));
            update_post_meta($event_id, '_ap_event_end', gmdate('c', strtotime('+' . ($i + 1) . ' day +2 hours')));
            update_post_meta($event_id, '_ap_event_location', 'Paged Hall ' . $i);
            update_post_meta($event_id, '_ap_event_latitude', (string) (10.0 + $i * 0.1));
            update_post_meta($event_id, '_ap_event_longitude', (string) (-10.0 + $i * 0.1));
            EventGeo::sync($event_id);

            $event_ids[] = $event_id;
        }

        $token = JWT::issue($this->user_id)['token'];

        $cursor = null;
        $seen   = [];
        $loops  = 0;

        do {
            $request = new WP_REST_Request('GET', '/artpulse/v1/mobile/events');
            $request->set_param('bounds', '9.0,-11.0,11.0,-9.0');
            $request->set_param('limit', 2);
            if (null !== $cursor) {
                $request->set_param('cursor', $cursor);
            }
            $request->set_header('Authorization', 'Bearer ' . $token);

            $response = rest_do_request($request);
            $this->assertSame(200, $response->get_status());

            $data = $response->get_data();
            $this->assertArrayHasKey('items', $data);
            $this->assertArrayHasKey('server_tz', $data);
            $this->assertArrayHasKey('server_tz_offset_minutes', $data);
            $this->assertLessThanOrEqual(2, count($data['items']));

            foreach ($data['items'] as $item) {
                $seen[] = $item['id'];
            }

            if ($data['has_more']) {
                $this->assertIsString($data['next_cursor']);
                $this->assertNotSame('', $data['next_cursor']);
            } else {
                $this->assertNull($data['next_cursor']);
            }

            $cursor = $data['next_cursor'];
            $loops++;
            $this->assertLessThan(10, $loops, 'Cursor pagination did not terminate');
        } while ($data['has_more']);

        $this->assertCount(count($seen), array_unique($seen));
        $this->assertSame(count($event_ids), count($seen));
        sort($event_ids);
        sort($seen);
        $this->assertSame($event_ids, $seen);
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
        $ids  = wp_list_pluck($data['items'], 'id');

        $this->assertArrayHasKey('server_tz', $data);
        $this->assertArrayHasKey('server_tz_offset_minutes', $data);
        $this->assertSame(wp_timezone()->getName(), $data['server_tz']);
        $this->assertIsInt($data['server_tz_offset_minutes']);
        $first = $data['items'][0]['start'];
        $this->assertNotNull($first);
        $this->assertMatchesRegularExpression('/[+-]\d{2}:\d{2}$/', $first);
        $first_dt = new \DateTimeImmutable($first);
        $this->assertInstanceOf(\DateTimeImmutable::class, $first_dt);

        $this->assertContains($event_id, $ids);
    }

    public function test_feed_cursor_pagination_prevents_duplicates(): void
    {
        $org_id = wp_insert_post([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
            'post_title'  => 'Cursor Org',
        ]);

        FollowService::follow($this->user_id, $org_id, 'org');

        $event_ids = [];
        for ($i = 0; $i < 5; $i++) {
            $event_id = wp_insert_post([
                'post_type'   => 'artpulse_event',
                'post_status' => 'publish',
                'post_title'  => 'Cursor Event ' . $i,
            ]);

            update_post_meta($event_id, '_ap_event_start', gmdate('c', strtotime('+' . ($i + 1) . ' day')));
            update_post_meta($event_id, '_ap_event_end', gmdate('c', strtotime('+' . ($i + 1) . ' day +1 hour')));
            update_post_meta($event_id, '_ap_event_location', 'Cursor Hall ' . $i);
            update_post_meta($event_id, '_ap_event_organization', $org_id);
            $event_ids[] = $event_id;
        }

        $token = JWT::issue($this->user_id)['token'];

        $cursor = null;
        $seen   = [];
        $loops  = 0;

        do {
            $request = new WP_REST_Request('GET', '/artpulse/v1/mobile/feed');
            $request->set_param('limit', 2);
            if (null !== $cursor) {
                $request->set_param('cursor', $cursor);
            }
            $request->set_header('Authorization', 'Bearer ' . $token);

            $response = rest_do_request($request);
            $this->assertSame(200, $response->get_status());

            $data = $response->get_data();
            $this->assertArrayHasKey('items', $data);
            $this->assertArrayHasKey('server_tz', $data);
            $this->assertArrayHasKey('server_tz_offset_minutes', $data);
            $this->assertLessThanOrEqual(2, count($data['items']));

            foreach ($data['items'] as $item) {
                $seen[] = $item['id'];
            }

            if ($data['has_more']) {
                $this->assertIsString($data['next_cursor']);
                $this->assertNotSame('', $data['next_cursor']);
            } else {
                $this->assertNull($data['next_cursor']);
            }

            $cursor = $data['next_cursor'];
            $loops++;
            $this->assertLessThan(10, $loops, 'Feed cursor pagination did not terminate');
        } while ($data['has_more']);

        $this->assertCount(count($seen), array_unique($seen));
        sort($event_ids);
        sort($seen);
        $this->assertSame($event_ids, $seen);
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

    public function test_session_cap_enforces_oldest_revoked(): void
    {
        delete_user_meta($this->user_id, 'ap_mobile_refresh_tokens');
        $now = time();

        for ($i = 0; $i < 11; $i++) {
            RefreshTokens::mint($this->user_id, 'device-' . $i, [
                'device_name' => 'Device #' . $i,
                'platform'    => 'TestOS',
                'app_version' => '1.0.' . $i,
                'last_ip'     => '10.0.0.' . $i,
                'last_seen_at'=> $now + $i,
            ]);
        }

        $sessions = RefreshTokens::list_sessions($this->user_id);
        $this->assertCount(10, $sessions);
        $device_ids = wp_list_pluck($sessions, 'deviceId');
        $this->assertNotContains('device-0', $device_ids);
    }

    public function test_tls_required_without_override(): void
    {
        remove_filter('artpulse_mobile_allow_insecure', '__return_true');

        try {
            $request = new WP_REST_Request('POST', '/artpulse/v1/mobile/login');
            $request->set_body_params([
                'username' => 'mobile-user',
                'password' => $this->password,
            ]);

            $response = rest_do_request($request);
            $this->assertSame(403, $response->get_status());
            $this->assertSame('ap_tls_required', $response->get_data()['code']);
        } finally {
            add_filter('artpulse_mobile_allow_insecure', '__return_true');
        }
    }

    public function test_jwt_clock_skew_tolerance(): void
    {
        $token = JWT::issue($this->user_id);
        [$header, $payload, $signature] = explode('.', $token);
        $payload_data = json_decode(base64_decode(strtr($payload, '-_', '+/')) ?: '{}', true);
        $this->assertIsArray($payload_data);

        $payload_data['nbf'] = time() + 100;
        $payload_data['exp'] = time() - 60;
        $payload_json        = wp_json_encode($payload_data);
        $new_payload         = rtrim(strtr(base64_encode($payload_json), '+/', '-_'), '=');

        $state = get_option('ap_mobile_jwt_keys');
        $kid   = json_decode(base64_decode(strtr($header, '-_', '+/')) ?: '{}', true)['kid'] ?? '';
        $secret = base64_decode($state['keys'][$kid]['secret'] ?? '', true);
        $this->assertIsString($secret);

        $new_signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $header . '.' . $new_payload, $secret, true)), '+/', '-_'), '=');
        $token_skew    = $header . '.' . $new_payload . '.' . $new_signature;

        $validated = JWT::validate($token_skew);
        $this->assertIsArray($validated);

        $payload_data['nbf'] = time() + 200;
        $payload_json        = wp_json_encode($payload_data);
        $new_payload         = rtrim(strtr(base64_encode($payload_json), '+/', '-_'), '=');
        $new_signature       = rtrim(strtr(base64_encode(hash_hmac('sha256', $header . '.' . $new_payload, $secret, true)), '+/', '-_'), '=');
        $too_far             = $header . '.' . $new_payload . '.' . $new_signature;

        $invalid = JWT::validate($too_far);
        $this->assertInstanceOf(\WP_Error::class, $invalid);
    }

    public function test_cors_headers_respect_allowed_origins(): void
    {
        rest_get_server();
        register_rest_route('artpulse/v1', '/mobile/cors-check', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => static fn () => ['ok' => true],
        ]);

        $previous_settings = get_option('artpulse_settings');
        $settings           = is_array($previous_settings) ? $previous_settings : [];
        $settings['approved_mobile_origins'] = "https://mobile.example\nhttps://mobile.example:8443";
        update_option('artpulse_settings', $settings);

        header_remove('Access-Control-Allow-Origin');
        header_remove('Access-Control-Allow-Credentials');
        header_remove('Vary');

        try {
            $request = new WP_REST_Request('GET', '/artpulse/v1/mobile/cors-check');
            $request->set_header('Origin', 'https://Mobile.Example');

            $response = rest_do_request($request);
            $this->assertSame(200, $response->get_status());

            Cors::send_headers(null, null, $request);
            $headers = headers_list();
            $this->assertContains('Access-Control-Allow-Origin: https://Mobile.Example', $headers);
            $this->assertContains('Access-Control-Allow-Credentials: true', $headers);
            $this->assertContains('Vary: Origin', $headers);

            header_remove('Access-Control-Allow-Origin');
            header_remove('Access-Control-Allow-Credentials');
            header_remove('Vary');

            $blocked = new WP_REST_Request('GET', '/artpulse/v1/mobile/cors-check');
            $blocked->set_header('Origin', 'https://evil.example');

            $denied = rest_do_request($blocked);
            $this->assertSame(403, $denied->get_status());
            $this->assertSame('cors_forbidden', $denied->get_data()['code']);

            Cors::send_headers(null, null, $blocked);
            $headers = headers_list();
            $this->assertNotContains('Access-Control-Allow-Origin: https://evil.example', $headers);
            $this->assertContains('Vary: Origin', $headers);
        } finally {
            header_remove('Access-Control-Allow-Origin');
            header_remove('Access-Control-Allow-Credentials');
            header_remove('Vary');

            if (false === $previous_settings) {
                delete_option('artpulse_settings');
            } else {
                update_option('artpulse_settings', $previous_settings);
            }
        }
    }

    public function test_me_endpoint_updates_push_token_and_mutes(): void
    {
        $login = new WP_REST_Request('POST', '/artpulse/v1/mobile/login');
        $login->set_body_params([
            'username'  => 'mobile-user',
            'password'  => $this->password,
            'device_id' => 'watch-01',
        ]);
        $login_response = rest_do_request($login);
        $token          = $login_response->get_data()['token'];

        $update = new WP_REST_Request('POST', '/artpulse/v1/mobile/me');
        $update->set_header('Authorization', 'Bearer ' . $token);
        $update->set_body_params([
            'push_token' => 'watch-token',
            'mute_topics'=> ['starting_soon'],
        ]);

        $response = rest_do_request($update);
        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertSame(['starting_soon'], $data['user']['mutedTopics']);

        $tokens = get_user_meta($this->user_id, 'ap_mobile_push_tokens', true);
        $this->assertSame('watch-token', $tokens['watch-01']['token']);
    }

    public function test_notifications_pipeline_batches_events(): void
    {
        $org_id = wp_insert_post([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
            'post_title'  => 'Gallery Org',
        ]);

        FollowService::follow($this->user_id, $org_id, 'org');

        $event_id = wp_insert_post([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Followed Event',
        ]);
        update_post_meta($event_id, '_ap_event_organization', $org_id);
        update_post_meta($event_id, '_ap_event_start', gmdate('c', strtotime('+1 hour')));

        update_user_meta($this->user_id, 'ap_mobile_push_tokens', [
            'ios-device-1' => ['token' => 'device-token', 'updated_at' => time()],
        ]);

        $settings                              = is_array(get_option('artpulse_settings')) ? get_option('artpulse_settings') : [];
        $settings['notification_provider']     = 'test-double';
        update_option('artpulse_settings', $settings);

        NotificationPipeline::run_tick();

        $this->assertNotNull($this->notificationProvider);
        $this->assertNotEmpty($this->notificationProvider->sent);
        $topics = wp_list_pluck($this->notificationProvider->sent, 'topic');
        $this->assertContains('new_followed_event', $topics);

        $first_payload = $this->notificationProvider->sent[0]['payload'];
        $this->assertArrayHasKey('events', $first_payload);
        $this->assertArrayHasKey('token', $first_payload);
        $this->assertSame('device-token', $first_payload['token']);
    }

    public function test_notifications_pipeline_respects_muted_topics(): void
    {
        $org_id = wp_insert_post([
            'post_type'   => 'artpulse_org',
            'post_status' => 'publish',
            'post_title'  => 'Muted Org',
        ]);

        FollowService::follow($this->user_id, $org_id, 'org');

        $event_id = wp_insert_post([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Muted Event',
        ]);
        update_post_meta($event_id, '_ap_event_organization', $org_id);
        update_post_meta($event_id, '_ap_event_start', gmdate('c', strtotime('+1 hour')));

        update_user_meta($this->user_id, 'ap_mobile_push_tokens', [
            'ios-device-1' => ['token' => 'muted-token', 'updated_at' => time()],
        ]);

        update_user_meta($this->user_id, 'ap_mobile_muted_topics', ['new_followed_event']);

        $settings                          = is_array(get_option('artpulse_settings')) ? get_option('artpulse_settings') : [];
        $settings['notification_provider'] = 'test-double';
        update_option('artpulse_settings', $settings);

        NotificationPipeline::run_tick();

        $this->assertNotNull($this->notificationProvider);
        $this->assertEmpty($this->notificationProvider->sent);
    }

    public function test_write_routes_respect_disable_switch(): void
    {
        $event_id = wp_insert_post([
            'post_type'   => 'artpulse_event',
            'post_status' => 'publish',
            'post_title'  => 'Toggle Event',
        ]);

        update_option('ap_enable_mobile_write_routes', '0');

        try {
            $login = new WP_REST_Request('POST', '/artpulse/v1/mobile/login');
            $login->set_body_params([
                'username'  => 'mobile-user',
                'password'  => $this->password,
                'device_id' => 'toggle-device',
            ]);
            $token = rest_do_request($login)->get_data()['token'];

            $like = new WP_REST_Request('POST', '/artpulse/v1/mobile/events/' . $event_id . '/like');
            $like->set_header('Authorization', 'Bearer ' . $token);
            $response = rest_do_request($like);

            $this->assertSame(503, $response->get_status());
            $this->assertSame('ap_mobile_read_only', $response->get_data()['code']);
        } finally {
            update_option('ap_enable_mobile_write_routes', '1');
        }
    }
}

class TestNotificationProvider implements NotificationProviderInterface
{
    /**
     * @var array<int, array{user:int, device:string, topic:string, payload:array<string, mixed>}> 
     */
    public array $sent = [];

    /**
     * @param array<string, mixed> $payload
     */
    public function send(int $user_id, string $device_id, string $topic, array $payload): void
    {
        $this->sent[] = [
            'user'    => $user_id,
            'device'  => $device_id,
            'topic'   => $topic,
            'payload' => $payload,
        ];
    }
}
