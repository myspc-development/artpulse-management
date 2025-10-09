<?php

namespace ArtPulse\Mobile;

use ArtPulse\Core\ImageTools;
use ArtPulse\Core\PostTypeRegistrar;
use DateTimeImmutable;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

class MobileRestController
{
    public static function register(): void
    {
        RateLimiter::register();
        RestErrorFormatter::register();
        RefreshTokens::register_hooks();

        register_rest_route('artpulse/v1', '/mobile/login', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'login'],
            'permission_callback' => '__return_true',
            'args'                => [
                'username'     => ['required' => true],
                'password'     => ['required' => true],
                'push_token'   => ['required' => false],
                'device_id'    => ['required' => false],
                'device_name'  => ['required' => false],
                'platform'     => ['required' => false],
                'app_version'  => ['required' => false],
            ],
        ]);

        register_rest_route('artpulse/v1', '/mobile/auth/refresh', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'refresh'],
            'permission_callback' => '__return_true',
            'args'                => [
                'refresh_token' => ['required' => true],
            ],
        ]);

        register_rest_route('artpulse/v1', '/mobile/auth/sessions', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'sessions'],
            'permission_callback' => [self::class, 'require_auth'],
        ]);

        register_rest_route('artpulse/v1', '/mobile/auth/sessions/(?P<device_id>[^/]+)', [
            'methods'             => 'DELETE',
            'callback'            => [self::class, 'revoke_session'],
            'permission_callback' => [self::class, 'require_auth'],
        ]);

        register_rest_route('artpulse/v1', '/mobile/me', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'me'],
            'permission_callback' => [self::class, 'require_auth'],
        ]);

        register_rest_route('artpulse/v1', '/mobile/me', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'update_me'],
            'permission_callback' => [self::class, 'require_auth'],
            'args'                => [
                'push_token' => ['required' => false],
                'mute_topics'=> ['required' => false],
            ],
        ]);

        register_rest_route('artpulse/v1', '/mobile/events', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'geosearch_events'],
            'permission_callback' => [self::class, 'require_auth'],
            'args'                => [
                'lat'    => ['required' => true],
                'lng'    => ['required' => true],
                'radius' => ['required' => false],
                'limit'  => ['required' => false],
            ],
        ]);

        register_rest_route('artpulse/v1', '/mobile/events/(?P<id>\\d+)/like', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'like_event'],
            'permission_callback' => [self::class, 'require_auth'],
        ]);

        register_rest_route('artpulse/v1', '/mobile/events/(?P<id>\\d+)/like', [
            'methods'             => 'DELETE',
            'callback'            => [self::class, 'unlike_event'],
            'permission_callback' => [self::class, 'require_auth'],
        ]);

        register_rest_route('artpulse/v1', '/mobile/events/(?P<id>\\d+)/save', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'save_event'],
            'permission_callback' => [self::class, 'require_auth'],
        ]);

        register_rest_route('artpulse/v1', '/mobile/events/(?P<id>\\d+)/save', [
            'methods'             => 'DELETE',
            'callback'            => [self::class, 'unsave_event'],
            'permission_callback' => [self::class, 'require_auth'],
        ]);

        register_rest_route('artpulse/v1', '/mobile/follow/(?P<type>[a-z]+)/(?P<id>\\d+)', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'follow_target'],
            'permission_callback' => [self::class, 'require_auth'],
        ]);

        register_rest_route('artpulse/v1', '/mobile/follow/(?P<type>[a-z]+)/(?P<id>\\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [self::class, 'unfollow_target'],
            'permission_callback' => [self::class, 'require_auth'],
        ]);

        register_rest_route('artpulse/v1', '/mobile/feed', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'feed'],
            'permission_callback' => [self::class, 'require_auth'],
        ]);
    }

    public static function login(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $tls_error = self::enforce_tls($request);
        if ($tls_error instanceof WP_Error) {
            return $tls_error;
        }

        $username = sanitize_text_field((string) $request->get_param('username'));
        $password = (string) $request->get_param('password');
        $push     = $request->get_param('push_token');

        $user = wp_authenticate($username, $password);
        if ($user instanceof WP_Error) {
            return new WP_Error('ap_invalid_credentials', __('Invalid credentials.', 'artpulse-management'), ['status' => 401]);
        }

        wp_set_current_user($user->ID);

        $device_id    = sanitize_text_field((string) $request->get_param('device_id'));
        $device_name  = sanitize_text_field((string) $request->get_param('device_name'));
        $platform     = sanitize_text_field((string) $request->get_param('platform'));
        $app_version  = sanitize_text_field((string) $request->get_param('app_version'));
        $metadata     = [
            'device_name' => $device_name,
            'platform'    => $platform,
            'app_version' => $app_version,
            'last_ip'     => self::get_request_ip($request),
        ];

        if (!empty($push)) {
            $push_token          = sanitize_text_field((string) $push);
            $metadata['push_token'] = $push_token;
            self::store_push_token($user->ID, $device_id, $push_token);
        }

        $access_token = JWT::issue($user->ID, null, ['device' => $device_id ?: null]);
        $refresh      = RefreshTokens::mint($user->ID, $device_id, $metadata);

        $data = [
            'token'          => $access_token['token'],
            'expires'        => $access_token['expires'],
            'refreshToken'   => $refresh['token'],
            'refreshExpires' => $refresh['expires'],
            'user'           => self::format_user($user->ID),
        ];

        $session = $refresh['session'] ?? [
            'device_id'    => $refresh['device_id'] ?? $device_id,
            'device_name'  => $metadata['device_name'] ?? null,
            'platform'     => $metadata['platform'] ?? null,
            'app_version'  => $metadata['app_version'] ?? null,
            'last_ip'      => $metadata['last_ip'] ?? null,
            'last_seen_at' => $metadata['last_seen_at'] ?? time(),
        ];

        $data['session']         = $session;
        $data['device_id']       = $session['device_id'] ?? null;
        $data['device_name']     = $session['device_name'] ?? null;
        $data['platform']        = $session['platform'] ?? null;
        $data['app_version']     = $session['app_version'] ?? null;
        $data['last_ip']         = $session['last_ip'] ?? null;
        $data['last_seen_at']    = $session['last_seen_at'] ?? null;
        $data['evicted_device_id'] = $refresh['evicted_device_id'] ?? null;

        return rest_ensure_response($data);
    }

    public static function refresh(WP_REST_Request $request)
    {
        $tls_error = self::enforce_tls($request);
        if ($tls_error instanceof WP_Error) {
            return $tls_error;
        }

        $token = (string) $request->get_param('refresh_token');
        $validated = RefreshTokens::validate($token);
        if ($validated instanceof WP_Error) {
            return $validated;
        }

        $user_id = (int) $validated['user_id'];
        $user    = get_userdata($user_id);
        if (!$user) {
            RefreshTokens::revoke_all($user_id);

            return new WP_Error('ap_invalid_refresh', __('User for refresh token no longer exists.', 'artpulse-management'), ['status' => 401]);
        }

        wp_set_current_user($user_id);

        $metadata = [
            'last_ip'      => self::get_request_ip($request),
            'device_name'  => $validated['metadata']['device_name'] ?? null,
            'platform'     => $validated['metadata']['platform'] ?? null,
            'app_version'  => $validated['metadata']['app_version'] ?? null,
            'push_token'   => $validated['metadata']['push_token'] ?? null,
        ];

        $access  = JWT::issue($user_id, null, ['device' => $validated['device_id']]);
        $refresh = RefreshTokens::rotate($validated, $metadata);

        $session = $refresh['session'] ?? [
            'device_id'    => $refresh['device_id'] ?? $validated['device_id'],
            'device_name'  => $metadata['device_name'] ?? null,
            'platform'     => $metadata['platform'] ?? null,
            'app_version'  => $metadata['app_version'] ?? null,
            'last_ip'      => $metadata['last_ip'] ?? null,
            'last_seen_at' => $metadata['last_seen_at'] ?? time(),
        ];

        return rest_ensure_response([
            'token'             => $access['token'],
            'expires'           => $access['expires'],
            'refreshToken'      => $refresh['token'],
            'refreshExpires'    => $refresh['expires'],
            'user'              => self::format_user($user_id),
            'session'           => $session,
            'device_id'         => $session['device_id'] ?? null,
            'device_name'       => $session['device_name'] ?? null,
            'platform'          => $session['platform'] ?? null,
            'app_version'       => $session['app_version'] ?? null,
            'last_ip'           => $session['last_ip'] ?? null,
            'last_seen_at'      => $session['last_seen_at'] ?? null,
            'evicted_device_id' => $refresh['evicted_device_id'] ?? null,
        ]);
    }

    public static function sessions(WP_REST_Request $request): WP_REST_Response
    {
        $user_id  = (int) $request->get_attribute('ap_user_id');
        $sessions = RefreshTokens::list_sessions($user_id);

        return rest_ensure_response([
            'sessions' => $sessions,
        ]);
    }

    public static function revoke_session(WP_REST_Request $request): WP_REST_Response
    {
        $user_id   = (int) $request->get_attribute('ap_user_id');
        $device_id = sanitize_text_field((string) $request->get_param('device_id'));

        RefreshTokens::revoke_device($user_id, $device_id);

        return rest_ensure_response([
            'success' => true,
        ]);
    }

    public static function me(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = (int) $request->get_attribute('ap_user_id');

        return rest_ensure_response([
            'user' => self::format_user($user_id),
        ]);
    }

    public static function update_me(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $tls_error = self::enforce_tls($request);
        if ($tls_error instanceof WP_Error) {
            return $tls_error;
        }

        $user_id   = (int) $request->get_attribute('ap_user_id');
        $device_id = (string) $request->get_attribute('ap_device_id');

        $push = $request->get_param('push_token');
        if (!empty($push) && is_string($push)) {
            $push_token = sanitize_text_field($push);
            self::store_push_token($user_id, $device_id, $push_token);
            RefreshTokens::update_device_metadata($user_id, $device_id, ['push_token' => $push_token]);
        }

        $mute_topics = $request->get_param('mute_topics');
        if (is_array($mute_topics)) {
            $muted = [];
            foreach ($mute_topics as $topic) {
                if (!is_string($topic) || '' === trim($topic)) {
                    continue;
                }

                $muted[] = substr(sanitize_key($topic), 0, 60);
            }

            update_user_meta($user_id, 'ap_mobile_muted_topics', array_values(array_unique($muted)));
        }

        return rest_ensure_response([
            'user' => self::format_user($user_id),
        ]);
    }

    private const GEO_MAX_CANDIDATES = 500;
    private const FEED_MAX_EVENTS    = 50;

    public static function geosearch_events(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $lat    = $request->get_param('lat');
        $lng    = $request->get_param('lng');
        $bounds = $request->get_param('bounds');
        $limit  = (int) ($request->get_param('limit') ?? 25);
        $limit  = max(1, min(100, $limit));
        $cursor = $request->get_param('cursor');

        $use_bounds = is_string($bounds) && '' !== trim($bounds);

        $center_lat = null;
        $center_lng = null;
        $filters    = '';
        $params     = [];

        if ($use_bounds) {
            $parts = array_map('trim', explode(',', (string) $bounds));
            if (4 !== count($parts) || !self::all_numeric($parts)) {
                return self::geo_error(
                    __('Invalid bounds provided.', 'artpulse-management'),
                    ['bounds' => __('Expected format "southWestLat,southWestLng,northEastLat,northEastLng".', 'artpulse-management')]
                );
            }

            [$sw_lat, $sw_lng, $ne_lat, $ne_lng] = array_map('floatval', $parts);

            if ($sw_lat > $ne_lat || abs($sw_lat - $ne_lat) < 0.0001) {
                return self::geo_error(__('Invalid latitude bounds.', 'artpulse-management'), ['bounds' => $parts]);
            }

            if (abs($sw_lat) > 90 || abs($ne_lat) > 90) {
                return self::geo_error(__('Latitude must be between -90 and 90.', 'artpulse-management'), ['bounds' => $parts]);
            }

            if (abs($sw_lng) > 180 || abs($ne_lng) > 180) {
                return self::geo_error(__('Longitude must be between -180 and 180.', 'artpulse-management'), ['bounds' => $parts]);
            }

            $center_lat = ($sw_lat + $ne_lat) / 2;
            $center_lng = self::normalize_longitude(($sw_lng + $ne_lng) / 2);

            $filters .= ' AND geo.latitude BETWEEN %f AND %f';
            $params[] = $sw_lat;
            $params[] = $ne_lat;

            if ($sw_lng <= $ne_lng) {
                if (abs($sw_lng - $ne_lng) < 0.0001) {
                    return self::geo_error(__('Longitude bounds must span an area.', 'artpulse-management'), ['bounds' => $parts]);
                }

                $filters .= ' AND geo.longitude BETWEEN %f AND %f';
                $params[] = $sw_lng;
                $params[] = $ne_lng;
            } else {
                $filters .= ' AND (geo.longitude >= %f OR geo.longitude <= %f)';
                $params[] = $sw_lng;
                $params[] = $ne_lng;
            }
        } else {
            if (!is_numeric($lat) || !is_numeric($lng)) {
                return self::geo_error(
                    __('Latitude and longitude are required.', 'artpulse-management'),
                    ['lat' => $lat, 'lng' => $lng]
                );
            }

            $lat = (float) $lat;
            $lng = (float) $lng;

            if (abs($lat) > 90 || abs($lng) > 180) {
                return self::geo_error(__('Latitude/longitude out of range.', 'artpulse-management'), ['lat' => $lat, 'lng' => $lng]);
            }

            $radius = (float) ($request->get_param('radius') ?? 50);
            $radius = max(1, min(500, $radius));
            $center_lat = $lat;
            $center_lng = self::normalize_longitude($lng);
        }

        global $wpdb;

        $geo_table   = $wpdb->prefix . 'ap_event_geo';
        $posts_table = $wpdb->posts;
        $meta_table  = $wpdb->postmeta;

        $distance_sql = $wpdb->prepare(
            '(6371 * ACOS(LEAST(1.0, COS(RADIANS(%f)) * COS(RADIANS(geo.latitude)) * COS(RADIANS(geo.longitude) - RADIANS(%f)) + SIN(RADIANS(%f)) * SIN(RADIANS(geo.latitude)))))',
            $center_lat,
            $center_lng,
            $center_lat
        );

        $query = $wpdb->prepare(
            "SELECT p.ID as event_id, $distance_sql AS distance_km, start_meta.meta_value AS start_time, end_meta.meta_value AS end_time
             FROM $geo_table geo
             INNER JOIN $posts_table p ON p.ID = geo.event_id
             LEFT JOIN $meta_table start_meta ON start_meta.post_id = geo.event_id AND start_meta.meta_key = %s
             LEFT JOIN $meta_table end_meta ON end_meta.post_id = geo.event_id AND end_meta.meta_key = %s
             WHERE p.post_status = 'publish' AND p.post_type = %s",
            '_ap_event_start',
            '_ap_event_end',
            PostTypeRegistrar::EVENT_POST_TYPE
        );

        if ($filters) {
            $query .= $wpdb->prepare($filters, ...$params);
        }

        if (!$use_bounds) {
            $radius = (float) ($request->get_param('radius') ?? 50);
            $radius = max(1, min(500, $radius));
            if ($radius > 0) {
                $query .= $wpdb->prepare(' HAVING distance_km <= %f', $radius);
            }
        }

        $query .= $wpdb->prepare(' LIMIT %d', self::GEO_MAX_CANDIDATES);

        $rows = $wpdb->get_results($query);

        if (empty($rows)) {
            return rest_ensure_response(array_merge([
                'items'       => [],
                'next_cursor' => null,
                'has_more'    => false,
            ], self::get_server_timezone_context()));
        }

        $event_ids = array_map(static fn($row) => (int) $row->event_id, $rows);
        $user_id   = (int) $request->get_attribute('ap_user_id');
        $states    = EventInteractions::get_states($event_ids, $user_id);
        $now       = current_time('timestamp');

        $cursor_key = self::decode_cursor($cursor);
        if ($cursor_key instanceof WP_Error) {
            return $cursor_key;
        }

        $candidates = [];
        foreach ($rows as $row) {
            $event_id   = (int) $row->event_id;
            $start_iso  = is_string($row->start_time) ? (string) $row->start_time : (string) get_post_meta($event_id, '_ap_event_start', true);
            $end_iso    = is_string($row->end_time) ? (string) $row->end_time : (string) get_post_meta($event_id, '_ap_event_end', true);
            $start_ts   = self::to_timestamp($start_iso) ?? PHP_INT_MAX;
            $distance_km = (float) $row->distance_km;

            $is_ongoing = self::determine_ongoing($start_iso, $end_iso, $now);

            $order_key = self::build_order_key($is_ongoing, $start_ts, $distance_km, $event_id);

            $candidates[] = [
                'event_id'   => $event_id,
                'state'      => $states[$event_id] ?? [
                    'likes' => 0,
                    'liked' => false,
                    'saves' => 0,
                    'saved' => false,
                ],
                'distance'   => $distance_km,
                'is_ongoing' => $is_ongoing,
                'order_key'  => $order_key,
            ];
        }

        usort(
            $candidates,
            static function (array $a, array $b): int {
                return self::compare_order_keys($a['order_key'], $b['order_key']);
            }
        );

        if (is_array($cursor_key)) {
            $candidates = array_values(array_filter(
                $candidates,
                static function (array $candidate) use ($cursor_key): bool {
                    return self::compare_order_keys($candidate['order_key'], $cursor_key) > 0;
                }
            ));
        }

        $page      = array_slice($candidates, 0, $limit + 1);
        $has_more  = count($page) > $limit;
        $page      = array_slice($page, 0, $limit);
        $next      = null;

        if ($has_more && !empty($page)) {
            $last     = $page[count($page) - 1]['order_key'];
            $next     = self::encode_cursor($last);
        }

        $events = [];
        foreach ($page as $candidate) {
            $events[] = self::format_event(
                $candidate['event_id'],
                $candidate['state'],
                $candidate['distance'],
                $candidate['is_ongoing']
            );
        }

        return rest_ensure_response(array_merge([
            'items'       => $events,
            'next_cursor' => $next,
            'has_more'    => $has_more,
        ], self::get_server_timezone_context()));
    }

    public static function like_event(WP_REST_Request $request)
    {
        $guard = self::guard_writes();
        if ($guard instanceof WP_Error) {
            return $guard;
        }

        $user_id = (int) $request->get_attribute('ap_user_id');
        $event_id = (int) $request->get_param('id');

        $state = EventInteractions::like_event($event_id, $user_id);
        if ($state instanceof WP_Error) {
            return $state;
        }

        return rest_ensure_response(self::format_event($event_id, $state));
    }

    public static function unlike_event(WP_REST_Request $request)
    {
        $guard = self::guard_writes();
        if ($guard instanceof WP_Error) {
            return $guard;
        }

        $user_id = (int) $request->get_attribute('ap_user_id');
        $event_id = (int) $request->get_param('id');

        $state = EventInteractions::unlike_event($event_id, $user_id);
        if ($state instanceof WP_Error) {
            return $state;
        }

        return rest_ensure_response(self::format_event($event_id, $state));
    }

    public static function save_event(WP_REST_Request $request)
    {
        $guard = self::guard_writes();
        if ($guard instanceof WP_Error) {
            return $guard;
        }

        $user_id = (int) $request->get_attribute('ap_user_id');
        $event_id = (int) $request->get_param('id');

        $state = EventInteractions::save_event($event_id, $user_id);
        if ($state instanceof WP_Error) {
            return $state;
        }

        return rest_ensure_response(self::format_event($event_id, $state));
    }

    public static function unsave_event(WP_REST_Request $request)
    {
        $guard = self::guard_writes();
        if ($guard instanceof WP_Error) {
            return $guard;
        }

        $user_id = (int) $request->get_attribute('ap_user_id');
        $event_id = (int) $request->get_param('id');

        $state = EventInteractions::unsave_event($event_id, $user_id);
        if ($state instanceof WP_Error) {
            return $state;
        }

        return rest_ensure_response(self::format_event($event_id, $state));
    }

    public static function follow_target(WP_REST_Request $request)
    {
        $guard = self::guard_writes();
        if ($guard instanceof WP_Error) {
            return $guard;
        }

        $user_id = (int) $request->get_attribute('ap_user_id');
        $type    = (string) $request->get_param('type');
        $object_id = (int) $request->get_param('id');

        $state = FollowService::follow($user_id, $object_id, $type);
        if ($state instanceof WP_Error) {
            return $state;
        }

        return rest_ensure_response($state);
    }

    public static function unfollow_target(WP_REST_Request $request)
    {
        $guard = self::guard_writes();
        if ($guard instanceof WP_Error) {
            return $guard;
        }

        $user_id = (int) $request->get_attribute('ap_user_id');
        $type    = (string) $request->get_param('type');
        $object_id = (int) $request->get_param('id');

        $state = FollowService::unfollow($user_id, $object_id, $type);
        if ($state instanceof WP_Error) {
            return $state;
        }

        return rest_ensure_response($state);
    }

    public static function feed(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = (int) $request->get_attribute('ap_user_id');
        $followed = FollowService::get_followed_ids($user_id);

        $org_ids    = array_map('intval', $followed['artpulse_org'] ?? []);
        $artist_ids = array_map('intval', $followed['artpulse_artist'] ?? []);

        $limit  = (int) ($request->get_param('limit') ?? 20);
        $limit  = max(1, min(self::FEED_MAX_EVENTS, $limit));
        $cursor = $request->get_param('cursor');

        $args = [
            'post_type'      => PostTypeRegistrar::EVENT_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => self::FEED_MAX_EVENTS,
            'meta_key'       => '_ap_event_start',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ];

        $query   = new WP_Query($args);
        $event_ids = [];

        foreach ($query->posts as $event_id) {
            $event_id = (int) $event_id;
            $org_id   = (int) get_post_meta($event_id, '_ap_event_organization', true);
            $artists  = get_post_meta($event_id, '_ap_event_artists', true);
            $artist_list = [];
            if (is_array($artists)) {
                $artist_list = array_map('intval', $artists);
            } elseif (!empty($artists)) {
                $artist_list = array_map('intval', (array) $artists);
            }

            $matches_org    = $org_id && in_array($org_id, $org_ids, true);
            $matches_artist = !empty($artist_list) && array_intersect($artist_list, $artist_ids);

            if ($matches_org || $matches_artist) {
                $event_ids[] = $event_id;
            }
        }

        if (empty($event_ids)) {
            $event_ids = array_map('intval', array_slice((array) $query->posts, 0, self::FEED_MAX_EVENTS));
        }

        $cursor_key = self::decode_cursor($cursor);
        if ($cursor_key instanceof WP_Error) {
            return $cursor_key;
        }

        $states = EventInteractions::get_states($event_ids, $user_id);
        $now    = current_time('timestamp');

        $candidates = [];
        foreach ($event_ids as $event_id) {
            $start = (string) get_post_meta($event_id, '_ap_event_start', true);
            $end   = (string) get_post_meta($event_id, '_ap_event_end', true);
            $start_ts = self::to_timestamp($start) ?? PHP_INT_MAX;
            $is_ongoing = self::determine_ongoing($start, $end, $now);

            $order_key = self::build_order_key($is_ongoing, $start_ts, 0.0, $event_id);

            $candidates[] = [
                'event_id'   => $event_id,
                'state'      => $states[$event_id] ?? [
                    'likes' => 0,
                    'liked' => false,
                    'saves' => 0,
                    'saved' => false,
                ],
                'is_ongoing' => $is_ongoing,
                'order_key'  => $order_key,
            ];
        }

        usort(
            $candidates,
            static function (array $a, array $b): int {
                return self::compare_order_keys($a['order_key'], $b['order_key']);
            }
        );

        if (is_array($cursor_key)) {
            $candidates = array_values(array_filter(
                $candidates,
                static function (array $candidate) use ($cursor_key): bool {
                    return self::compare_order_keys($candidate['order_key'], $cursor_key) > 0;
                }
            ));
        }

        $page     = array_slice($candidates, 0, $limit + 1);
        $has_more = count($page) > $limit;
        $page     = array_slice($page, 0, $limit);

        $next_cursor = null;
        if ($has_more && !empty($page)) {
            $last        = $page[count($page) - 1]['order_key'];
            $next_cursor = self::encode_cursor($last);
        }

        $events = [];
        foreach ($page as $candidate) {
            $events[] = self::format_event(
                $candidate['event_id'],
                $candidate['state'],
            );
        }

        return rest_ensure_response(array_merge([
            'items'       => $events,
            'next_cursor' => $next_cursor,
            'has_more'    => $has_more,
        ], self::get_server_timezone_context()));
    }

    public static function require_auth(WP_REST_Request $request)
    {
        $auth = $request->get_header('authorization');
        if (!$auth || !preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            return new WP_Error('ap_missing_token', __('Authentication token required.', 'artpulse-management'), ['status' => 401]);
        }

        $payload = JWT::validate(trim($matches[1]));
        if ($payload instanceof WP_Error) {
            return $payload;
        }

        $user_id = (int) ($payload['sub'] ?? 0);
        if (!$user_id) {
            return new WP_Error('ap_invalid_token', __('Token missing subject.', 'artpulse-management'), ['status' => 401]);
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('ap_invalid_token', __('Token user not found.', 'artpulse-management'), ['status' => 401]);
        }

        $tls_error = self::enforce_tls($request);
        if ($tls_error instanceof WP_Error) {
            return $tls_error;
        }

        wp_set_current_user($user_id);
        $request->set_attribute('ap_user_id', $user_id);
        $device_id = isset($payload['device']) ? sanitize_text_field((string) $payload['device']) : '';
        $request->set_attribute('ap_device_id', $device_id ?: 'unknown');

        return true;
    }

    private static function format_user(int $user_id): array
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return [];
        }

        $push_tokens = get_user_meta($user_id, 'ap_mobile_push_tokens', true);
        if (!is_array($push_tokens)) {
            $push_tokens = [];
        }

        $formatted_tokens = [];
        foreach ($push_tokens as $device_id => $details) {
            if (!is_array($details)) {
                continue;
            }

            $token = isset($details['token']) ? (string) $details['token'] : '';
            if ('' === $token) {
                continue;
            }

            $formatted_tokens[] = [
                'deviceId'  => (string) $device_id,
                'token'     => $token,
                'updatedAt' => isset($details['updated_at']) ? (int) $details['updated_at'] : 0,
            ];
        }

        $muted_topics = get_user_meta($user_id, 'ap_mobile_muted_topics', true);
        if (!is_array($muted_topics)) {
            $muted_topics = [];
        }

        return [
            'id'         => $user_id,
            'displayName'=> $user->display_name,
            'email'      => $user->user_email,
            'roles'      => $user->roles,
            'pushToken'  => $formatted_tokens[0]['token'] ?? (get_user_meta($user_id, 'ap_mobile_push_token', true) ?: null),
            'pushTokens' => $formatted_tokens,
            'mutedTopics'=> array_values(array_unique(array_map('strval', $muted_topics))),
        ];
    }

    private static function store_push_token(int $user_id, string $device_id, string $token): void
    {
        $device_id = sanitize_text_field($device_id ?: 'unknown');
        $tokens    = get_user_meta($user_id, 'ap_mobile_push_tokens', true);
        if (!is_array($tokens)) {
            $tokens = [];
        }

        $tokens[$device_id] = [
            'token'      => $token,
            'updated_at' => time(),
        ];

        update_user_meta($user_id, 'ap_mobile_push_tokens', $tokens);
        update_user_meta($user_id, 'ap_mobile_push_token', $token);
    }

    private static function get_request_ip(WP_REST_Request $request): ?string
    {
        $ip = $request->get_header('X-Forwarded-For');
        if ($ip) {
            $ip = trim(explode(',', (string) $ip)[0]);
        }

        if (!$ip && isset($_SERVER['REMOTE_ADDR'])) {
            $ip = (string) $_SERVER['REMOTE_ADDR'];
        }

        $ip = sanitize_text_field((string) $ip);

        return '' !== $ip ? $ip : null;
    }

    private static function enforce_tls(WP_REST_Request $request)
    {
        $allow_insecure = (bool) apply_filters('artpulse_mobile_allow_insecure', false, $request);
        if ($allow_insecure) {
            return null;
        }

        $is_secure = is_ssl();

        if (!$is_secure) {
            $proto = strtolower((string) $request->get_header('X-Forwarded-Proto'));
            if ('https' === $proto) {
                $is_secure = true;
            }
        }

        if (!$is_secure && isset($_SERVER['HTTPS'])) {
            $https = strtolower((string) $_SERVER['HTTPS']);
            $is_secure = in_array($https, ['on', '1'], true);
        }

        if ($is_secure) {
            return null;
        }

        return new WP_Error('ap_tls_required', __('HTTPS is required for the mobile API.', 'artpulse-management'), ['status' => 403]);
    }

    private static function guard_writes(): ?WP_Error
    {
        if (self::write_routes_enabled()) {
            return null;
        }

        return new WP_Error(
            'ap_mobile_read_only',
            __('Mobile write routes are currently disabled.', 'artpulse-management'),
            ['status' => 503]
        );
    }

    private static function write_routes_enabled(): bool
    {
        $enabled = true;

        if (defined('AP_ENABLE_MOBILE_WRITE_ROUTES')) {
            $enabled = (bool) constant('AP_ENABLE_MOBILE_WRITE_ROUTES');
        } else {
            $option  = get_option('ap_enable_mobile_write_routes', '1');
            $enabled = '0' !== (string) $option;
        }

        /** @psalm-suppress InvalidScalarArgument */
        return (bool) apply_filters('artpulse_mobile_write_routes_enabled', $enabled);
    }

    private static function format_event(int $event_id, array $state, ?float $distance_km = null, ?bool $is_ongoing = null): array
    {
        $post = get_post($event_id);
        if (!$post instanceof WP_Post) {
            return [];
        }

        $thumb_id = get_post_thumbnail_id($event_id);
        $image    = $thumb_id ? ImageTools::best_image_src((int) $thumb_id) : null;

        $start_raw = get_post_meta($event_id, '_ap_event_start', true);
        $end_raw   = get_post_meta($event_id, '_ap_event_end', true);
        $location = get_post_meta($event_id, '_ap_event_location', true);
        $org_id   = (int) get_post_meta($event_id, '_ap_event_organization', true);
        $org      = $org_id ? get_post($org_id) : null;
        $start    = is_string($start_raw) ? (string) $start_raw : null;
        $end      = is_string($end_raw) ? (string) $end_raw : null;
        $ongoing  = null !== $is_ongoing ? (bool) $is_ongoing : self::determine_ongoing($start, $end);
        $timezone = self::get_server_timezone_context();

        return [
            'id'          => $event_id,
            'title'       => get_the_title($event_id),
            'excerpt'     => wp_trim_words($post->post_content, 40),
            'start'       => self::format_datetime_for_response($start),
            'end'         => self::format_datetime_for_response($end),
            'location'    => $location,
            'distanceKm'  => null !== $distance_km ? round($distance_km, 2) : null,
            'distance_m'  => null !== $distance_km ? (int) round($distance_km * 1000) : null,
            'isOngoing'   => $ongoing,
            'likes'       => (int) ($state['likes'] ?? 0),
            'liked'       => (bool) ($state['liked'] ?? false),
            'saves'       => (int) ($state['saves'] ?? 0),
            'saved'       => (bool) ($state['saved'] ?? false),
            'image'       => $image,
            'organization'=> $org ? [
                'id'    => $org_id,
                'title' => $org->post_title,
            ] : null,
        ] + $timezone;
    }

    /**
     * @return array{int,int,float,int}
     */
    private static function build_order_key(bool $is_ongoing, int $start_ts, float $distance, int $event_id): array
    {
        return [
            $is_ongoing ? 0 : 1,
            $start_ts,
            round($distance, 6),
            $event_id,
        ];
    }

    private static function format_datetime_for_response(?string $value): ?string
    {
        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        $timestamp = self::to_timestamp($value);
        if (null === $timestamp) {
            return $value;
        }

        $formatted = wp_date('c', $timestamp);

        return is_string($formatted) && '' !== $formatted ? $formatted : $value;
    }

    /**
     * @return array{server_tz: string, server_tz_offset_minutes: int}
     */
    private static function get_server_timezone_context(): array
    {
        $timezone = wp_timezone();
        if (!$timezone instanceof \DateTimeZone) {
            $timezone = new \DateTimeZone('UTC');
        }

        $now            = new DateTimeImmutable('now', $timezone);
        $offset_minutes = (int) round($timezone->getOffset($now) / 60);

        return [
            'server_tz'                => $timezone->getName(),
            'server_tz_offset_minutes' => $offset_minutes,
        ];
    }

    /**
     * @param array{int,int,float,int} $a
     * @param array{int,int,float,int} $b
     */
    private static function compare_order_keys(array $a, array $b): int
    {
        for ($i = 0; $i < 4; $i++) {
            if ($a[$i] === $b[$i]) {
                continue;
            }

            return $a[$i] <=> $b[$i];
        }

        return 0;
    }

    /**
     * @param array{int,int,float,int} $key
     */
    private static function encode_cursor(array $key): string
    {
        $json = wp_json_encode($key);
        if (!is_string($json)) {
            return '';
        }

        $encoded = base64_encode($json);

        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }

    /**
     * @return array{int,int,float,int}|null|WP_Error
     */
    private static function decode_cursor($cursor)
    {
        if (null === $cursor || '' === $cursor) {
            return null;
        }

        if (!is_string($cursor)) {
            return new WP_Error('ap_invalid_cursor', __('Invalid cursor parameter.', 'artpulse-management'), ['status' => 400]);
        }

        $padded = strtr($cursor, '-_', '+/');
        $padding = strlen($padded) % 4;
        if (0 !== $padding) {
            $padded .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($padded, true);
        if (false === $decoded) {
            return new WP_Error('ap_invalid_cursor', __('Invalid cursor parameter.', 'artpulse-management'), ['status' => 400]);
        }

        $data = json_decode($decoded, true);
        if (!is_array($data) || 4 !== count($data)) {
            return new WP_Error('ap_invalid_cursor', __('Invalid cursor parameter.', 'artpulse-management'), ['status' => 400]);
        }

        return [
            (int) ($data[0] ?? 0),
            (int) ($data[1] ?? 0),
            isset($data[2]) ? (float) $data[2] : 0.0,
            (int) ($data[3] ?? 0),
        ];
    }

    private static function geo_error(string $message, array $details = []): WP_Error
    {
        return new WP_Error(
            RestErrorFormatter::GEO_INVALID_BOUNDS,
            $message,
            [
                'status'  => 400,
                'details' => $details,
            ]
        );
    }

    private static function to_timestamp(?string $value): ?int
    {
        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        $timestamp = strtotime($value);

        return false === $timestamp ? null : $timestamp;
    }

    private static function determine_ongoing(?string $start, ?string $end, ?int $now = null): bool
    {
        $now = $now ?? current_time('timestamp');

        $start_ts = self::to_timestamp($start);
        if (null === $start_ts || $start_ts > $now) {
            return false;
        }

        $end_ts = self::to_timestamp($end);
        if (null === $end_ts) {
            return true;
        }

        return $end_ts >= $now;
    }

    private static function all_numeric(array $values): bool
    {
        foreach ($values as $value) {
            if (!is_numeric($value)) {
                return false;
            }
        }

        return true;
    }

    private static function normalize_longitude(float $longitude): float
    {
        while ($longitude > 180) {
            $longitude -= 360;
        }

        while ($longitude < -180) {
            $longitude += 360;
        }

        return $longitude;
    }
}
