<?php

namespace ArtPulse\Mobile;

use ArtPulse\Core\ImageTools;
use ArtPulse\Core\PostTypeRegistrar;
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

        register_rest_route('artpulse/v1', '/mobile/login', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'login'],
            'permission_callback' => '__return_true',
            'args'                => [
                'username'    => ['required' => true],
                'password'    => ['required' => true],
                'push_token'  => ['required' => false],
                'device_id'   => ['required' => false],
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

        register_rest_route('artpulse/v1', '/mobile/me', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'me'],
            'permission_callback' => [self::class, 'require_auth'],
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
        $username = sanitize_text_field((string) $request->get_param('username'));
        $password = (string) $request->get_param('password');
        $push     = $request->get_param('push_token');

        $user = wp_authenticate($username, $password);
        if ($user instanceof WP_Error) {
            return new WP_Error('ap_invalid_credentials', __('Invalid credentials.', 'artpulse-management'), ['status' => 401]);
        }

        wp_set_current_user($user->ID);

        if (!empty($push)) {
            update_user_meta($user->ID, 'ap_mobile_push_token', sanitize_text_field((string) $push));
        }

        $device_id    = sanitize_text_field((string) $request->get_param('device_id'));
        $access_token = JWT::issue($user->ID);
        $refresh      = RefreshTokens::mint($user->ID, $device_id);

        $data = [
            'token'          => $access_token['token'],
            'expires'        => $access_token['expires'],
            'refreshToken'   => $refresh['token'],
            'refreshExpires' => $refresh['expires'],
            'user'           => self::format_user($user->ID),
        ];

        return rest_ensure_response($data);
    }

    public static function refresh(WP_REST_Request $request)
    {
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

        $access  = JWT::issue($user_id);
        $refresh = RefreshTokens::rotate($validated);

        return rest_ensure_response([
            'token'          => $access['token'],
            'expires'        => $access['expires'],
            'refreshToken'   => $refresh['token'],
            'refreshExpires' => $refresh['expires'],
            'user'           => self::format_user($user_id),
        ]);
    }

    public static function me(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = (int) $request->get_attribute('ap_user_id');

        return rest_ensure_response([
            'user' => self::format_user($user_id),
        ]);
    }

    public static function geosearch_events(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $lat    = $request->get_param('lat');
        $lng    = $request->get_param('lng');
        $bounds = $request->get_param('bounds');
        $limit  = (int) ($request->get_param('limit') ?? 25);
        $limit  = max(1, min(100, $limit));

        $use_bounds = is_string($bounds) && '' !== trim($bounds);

        $center_lat = null;
        $center_lng = null;
        $filters    = '';
        $params     = [];

        if ($use_bounds) {
            $parts = array_map('trim', explode(',', (string) $bounds));
            if (4 !== count($parts) || !self::all_numeric($parts)) {
                return new WP_Error('ap_geo_invalid', __('Invalid bounds provided.', 'artpulse-management'), ['status' => 400]);
            }

            [$sw_lat, $sw_lng, $ne_lat, $ne_lng] = array_map('floatval', $parts);

            if ($sw_lat > $ne_lat) {
                return new WP_Error('ap_geo_invalid', __('Invalid latitude bounds.', 'artpulse-management'), ['status' => 400]);
            }

            $center_lat = ($sw_lat + $ne_lat) / 2;
            $center_lng = self::normalize_longitude(($sw_lng + $ne_lng) / 2);

            $filters .= ' AND geo.latitude BETWEEN %f AND %f';
            $params[] = $sw_lat;
            $params[] = $ne_lat;

            if ($sw_lng <= $ne_lng) {
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
                return new WP_Error('ap_geo_invalid', __('Latitude and longitude are required.', 'artpulse-management'), ['status' => 400]);
            }

            $lat    = (float) $lat;
            $lng    = (float) $lng;
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
            "SELECT p.ID as event_id, $distance_sql AS distance_km, geo.latitude, geo.longitude, start_meta.meta_value AS start_time
             FROM $geo_table geo
             INNER JOIN $posts_table p ON p.ID = geo.event_id
             LEFT JOIN $meta_table start_meta ON start_meta.post_id = geo.event_id AND start_meta.meta_key = %s
             WHERE p.post_status = 'publish' AND p.post_type = %s",
            '_ap_event_start',
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

        $query .= ' ORDER BY distance_km ASC, start_time ASC';
        $query .= $wpdb->prepare(' LIMIT %d', $limit);

        $rows = $wpdb->get_results($query);

        $event_ids = array_map(static fn($row) => (int) $row->event_id, $rows);
        $user_id   = (int) $request->get_attribute('ap_user_id');
        $states    = EventInteractions::get_states($event_ids, $user_id);

        $events = [];
        foreach ($rows as $row) {
            $event_id = (int) $row->event_id;
            $events[] = self::format_event($event_id, $states[$event_id] ?? [
                'likes' => 0,
                'liked' => false,
                'saves' => 0,
                'saved' => false,
            ], (float) $row->distance_km);
        }

        return rest_ensure_response([
            'events' => $events,
        ]);
    }

    public static function like_event(WP_REST_Request $request)
    {
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

        $args = [
            'post_type'      => PostTypeRegistrar::EVENT_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 50,
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
            $event_ids = array_map('intval', array_slice((array) $query->posts, 0, 10));
        }

        $states = EventInteractions::get_states($event_ids, $user_id);

        $events = [];
        foreach ($event_ids as $event_id) {
            $events[] = self::format_event($event_id, $states[$event_id] ?? [
                'likes' => 0,
                'liked' => false,
                'saves' => 0,
                'saved' => false,
            ]);
        }

        return rest_ensure_response([
            'events' => $events,
        ]);
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

        wp_set_current_user($user_id);
        $request->set_attribute('ap_user_id', $user_id);

        return true;
    }

    private static function format_user(int $user_id): array
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return [];
        }

        return [
            'id'         => $user_id,
            'displayName'=> $user->display_name,
            'email'      => $user->user_email,
            'roles'      => $user->roles,
            'pushToken'  => get_user_meta($user_id, 'ap_mobile_push_token', true) ?: null,
        ];
    }

    private static function format_event(int $event_id, array $state, ?float $distance_km = null): array
    {
        $post = get_post($event_id);
        if (!$post instanceof WP_Post) {
            return [];
        }

        $thumb_id = get_post_thumbnail_id($event_id);
        $image    = $thumb_id ? ImageTools::best_image_src((int) $thumb_id) : null;

        $start    = get_post_meta($event_id, '_ap_event_start', true);
        $location = get_post_meta($event_id, '_ap_event_location', true);
        $org_id   = (int) get_post_meta($event_id, '_ap_event_organization', true);
        $org      = $org_id ? get_post($org_id) : null;

        return [
            'id'          => $event_id,
            'title'       => get_the_title($event_id),
            'excerpt'     => wp_trim_words($post->post_content, 40),
            'start'       => $start,
            'location'    => $location,
            'distanceKm'  => null !== $distance_km ? round($distance_km, 2) : null,
            'distance_m'  => null !== $distance_km ? (int) round($distance_km * 1000) : null,
            'likes'       => (int) ($state['likes'] ?? 0),
            'liked'       => (bool) ($state['liked'] ?? false),
            'saves'       => (int) ($state['saves'] ?? 0),
            'saved'       => (bool) ($state['saved'] ?? false),
            'image'       => $image,
            'organization'=> $org ? [
                'id'    => $org_id,
                'title' => $org->post_title,
            ] : null,
        ];
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
