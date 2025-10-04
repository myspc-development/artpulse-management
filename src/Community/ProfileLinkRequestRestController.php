<?php
namespace ArtPulse\Community;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class ProfileLinkRequestRestController {

    public static function register() {
        // ✅ Properly defer route registration to rest_api_init
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes() {
        register_rest_route('artpulse/v1', '/link-request', [
            'methods'  => 'POST',
            'callback' => [self::class, 'create_request'],
            'permission_callback' => function() { return is_user_logged_in(); },
            'args' => [
                'org_id'  => ['type' => 'integer', 'required' => true],
                'message' => ['type' => 'string', 'required' => false],
            ],
        ]);

        register_rest_route('artpulse/v1', '/link-request/(?P<id>\d+)/approve', [
            'methods'  => 'POST',
            'callback' => [self::class, 'approve_request'],
            'permission_callback' => function() { return current_user_can('edit_others_posts'); },
        ]);

        register_rest_route('artpulse/v1', '/link-request/(?P<id>\d+)/deny', [
            'methods'  => 'POST',
            'callback' => [self::class, 'deny_request'],
            'permission_callback' => function() { return current_user_can('edit_others_posts'); },
        ]);

        register_rest_route('artpulse/v1', '/link-requests', [
            'methods'  => 'GET',
            'callback' => [self::class, 'list_requests'],
            'permission_callback' => function() { return current_user_can('edit_others_posts'); },
            'args' => [
                'org_id' => ['type' => 'integer', 'required' => true],
                'status' => ['type' => 'string', 'required' => false, 'enum' => ['pending', 'approved', 'denied', 'all']],
            ],
        ]);

        register_rest_route('artpulse/v1', '/link-requests/bulk', [
            'methods'  => 'POST',
            'callback' => [self::class, 'bulk_update'],
            'permission_callback' => function () { return current_user_can('edit_others_posts'); },
            'args' => [
                'ids'    => ['type' => 'array', 'required' => true, 'items' => ['type' => 'integer']],
                'action' => ['type' => 'string', 'enum' => ['approve', 'deny'], 'required' => true],
            ],
        ]);
    }

    public static function create_request(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $artist_user_id = get_current_user_id();
        $org_id = intval($request['org_id'] ?? 0);
        $message = sanitize_textarea_field($request['message'] ?? '');

        if (!$artist_user_id) {
            return new WP_Error('not_authenticated', 'You must be logged in to create a request.', ['status' => 401]);
        }

        if (!$org_id) {
            return new WP_Error('missing_org', 'Missing org_id', ['status' => 400]);
        }

        $result = ProfileLinkRequestManager::create($artist_user_id, $org_id, $message);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'success'    => true,
            'request_id' => $result,
            'status'     => ProfileLinkRequestManager::STATUS_PENDING,
        ]);
    }

    public static function approve_request(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $id = intval($request['id']);
        $moderator_id = get_current_user_id();

        if (!$moderator_id) {
            return new WP_Error('not_authenticated', 'You must be logged in to approve requests.', ['status' => 401]);
        }

        $result = ProfileLinkRequestManager::approve($id, $moderator_id);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'success'    => true,
            'request_id' => $result,
            'status'     => ProfileLinkRequestManager::STATUS_APPROVED,
        ]);
    }

    public static function deny_request(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $id = intval($request['id']);
        $moderator_id = get_current_user_id();

        if (!$moderator_id) {
            return new WP_Error('not_authenticated', 'You must be logged in to deny requests.', ['status' => 401]);
        }

        $result = ProfileLinkRequestManager::deny($id, $moderator_id);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'success'    => true,
            'request_id' => $result,
            'status'     => ProfileLinkRequestManager::STATUS_DENIED,
        ]);
    }

    public static function list_requests($request) {
        $org_id = intval($request['org_id'] ?? 0);
        $status = $request['status'] ?? 'pending';

        if (!$org_id) {
            return new \WP_Error('no_org', 'No org_id given', ['status' => 400]);
        }

        $meta_query = [['key' => 'org_id', 'value' => $org_id]];

        if ($status && $status !== 'all') {
            $meta_query[] = ['key' => 'status', 'value' => $status];
        }

        $args = [
            'post_type'   => \ArtPulse\Community\ProfileLinkRequestManager::POST_TYPE,
            'post_status' => 'publish',
            'meta_query'  => $meta_query,
            'numberposts' => 200,
        ];

        $requests = get_posts($args);
        $out = [];

        foreach ($requests as $r) {
            $artist_id = get_post_meta($r->ID, 'artist_user_id', true);
            $artist_user = get_userdata($artist_id);
            $org_id_val = get_post_meta($r->ID, 'org_id', true);
            $org_post = get_post($org_id_val);

            $out[] = [
                'ID'             => $r->ID,
                'artist_user_id' => $artist_id,
                'artist_user'    => $artist_user ? [
                    'ID'           => $artist_user->ID,
                    'user_login'   => $artist_user->user_login,
                    'display_name' => $artist_user->display_name,
                ] : null,
                'org_id'         => $org_id_val,
                'org_title'      => $org_post ? $org_post->post_title : '',
                'message'        => get_post_meta($r->ID, 'message', true),
                'requested_on'   => get_post_meta($r->ID, 'requested_on', true),
                'status'         => get_post_meta($r->ID, 'status', true),
            ];
        }

        return rest_ensure_response($out);
    }

    public static function bulk_update($request) {
        $ids = $request->get_param('ids');
        $action = $request->get_param('action');

        if (!is_array($ids) || !in_array($action, ['approve', 'deny'], true)) {
            return new \WP_Error('invalid_args', 'Invalid arguments', ['status' => 400]);
        }

        foreach ($ids as $id) {
            $id = intval($id);
            if ($action === 'approve') {
                $result = ProfileLinkRequestManager::approve($id, get_current_user_id());
            } else {
                $result = ProfileLinkRequestManager::deny($id, get_current_user_id());
            }

            if (is_wp_error($result)) {
                return $result;
            }
        }

        return rest_ensure_response(['updated' => $ids, 'action' => $action]);
    }
}
