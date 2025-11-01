<?php

namespace ArtPulse\Rest;

use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function __;
use function absint;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function check_ajax_referer;
use function current_user_can;
use function delete_post_meta;
use function delete_post_thumbnail;
use function delete_transient;
use function esc_url_raw;
use function get_current_user_id;
use function get_edit_post_link;
use function get_permalink;
use function get_post;
use function get_post_meta;
use function get_post_time;
use function get_post_type;
use function get_posts;
use function get_the_post_thumbnail_url;
use function get_the_title;
use function get_transient;
use function get_user_meta;
use function is_user_logged_in;
use function rest_authorization_required_code;
use function rest_ensure_response;
use function rest_parse_date;
use function rest_sanitize_boolean;
use function sanitize_key;
use function sanitize_text_field;
use function set_post_thumbnail;
use function set_transient;
use function taxonomy_exists;
use function update_post_meta;
use function wp_delete_post;
use function wp_insert_post;
use function wp_kses_post;
use function wp_set_post_terms;
use function wp_trash_post;
use function wp_update_post;
use function wp_verify_nonce;

final class ArtistDashboardController
{
    private const OVERVIEW_CACHE_PREFIX = 'ap_artist_overview_';
    private const OVERVIEW_TTL = 5 * MINUTE_IN_SECONDS;
    private const OWNERSHIP_STATUSES = ['publish', 'draft', 'pending', 'future'];
    private const ALLOWED_STATUSES = ['publish', 'draft'];
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 50;
    private const RECENT_LIMIT = 5;

    public static function register(): void
    {
        register_rest_route('artpulse/v1', '/artist/overview', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'get_overview'],
            'permission_callback' => [self::class, 'ensure_artist_access'],
        ]);

        register_rest_route('artpulse/v1', '/artist/artworks', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [self::class, 'list_artworks'],
                'permission_callback' => [self::class, 'ensure_artist_access'],
                'args'                => self::collection_args(),
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [self::class, 'create_artwork'],
                'permission_callback' => [self::class, 'validate_write_access'],
                'args'                => self::write_args_schema(),
            ],
        ]);

        register_rest_route('artpulse/v1', '/artist/artworks/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [self::class, 'update_artwork'],
                'permission_callback' => [self::class, 'validate_write_access'],
                'args'                => array_merge([
                    'id' => [
                        'sanitize_callback' => 'absint',
                        'validate_callback' => [self::class, 'validate_positive_int'],
                    ],
                ], self::write_args_schema()),
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [self::class, 'delete_artwork'],
                'permission_callback' => [self::class, 'validate_write_access'],
                'args'                => [
                    'id' => [
                        'sanitize_callback' => 'absint',
                        'validate_callback' => [self::class, 'validate_positive_int'],
                    ],
                    'force' => [
                        'sanitize_callback' => 'rest_sanitize_boolean',
                    ],
                ],
            ],
        ]);

        register_rest_route('artpulse/v1', '/artist/events', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [self::class, 'list_events'],
                'permission_callback' => [self::class, 'ensure_artist_access'],
                'args'                => self::collection_args(),
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [self::class, 'create_event'],
                'permission_callback' => [self::class, 'validate_write_access'],
                'args'                => self::event_write_args_schema(),
            ],
        ]);

        register_rest_route('artpulse/v1', '/artist/events/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [self::class, 'update_event'],
                'permission_callback' => [self::class, 'validate_write_access'],
                'args'                => array_merge([
                    'id' => [
                        'sanitize_callback' => 'absint',
                        'validate_callback' => [self::class, 'validate_positive_int'],
                    ],
                ], self::event_write_args_schema()),
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [self::class, 'delete_event'],
                'permission_callback' => [self::class, 'validate_write_access'],
                'args'                => [
                    'id' => [
                        'sanitize_callback' => 'absint',
                        'validate_callback' => [self::class, 'validate_positive_int'],
                    ],
                    'force' => [
                        'sanitize_callback' => 'rest_sanitize_boolean',
                    ],
                ],
            ],
        ]);
    }

    public static function invalidate_overview_cache(int $user_id): void
    {
        if ($user_id <= 0) {
            return;
        }

        delete_transient(self::OVERVIEW_CACHE_PREFIX . $user_id);
    }

    public static function ensure_artist_access(WP_REST_Request $request): bool|WP_Error
    {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('You must be logged in to access this resource.', 'artpulse-management'),
                ['status' => rest_authorization_required_code()]
            );
        }

        $user_id    = get_current_user_id();
        $artist_ids = self::get_user_artist_ids($user_id);

        if (current_user_can('ap_is_artist') || !empty($artist_ids)) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            __('You do not have access to the artist dashboard.', 'artpulse-management'),
            ['status' => 403]
        );
    }

    public static function validate_write_access(WP_REST_Request $request): bool|WP_Error
    {
        $access = self::ensure_artist_access($request);
        if ($access instanceof WP_Error) {
            return $access;
        }

        if (!self::verify_request_nonce($request)) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid security token.', 'artpulse-management'),
                ['status' => rest_authorization_required_code()]
            );
        }

        return true;
    }

    public static function get_overview(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = get_current_user_id();
        $cache   = get_transient(self::OVERVIEW_CACHE_PREFIX . $user_id);
        if (false !== $cache) {
            return rest_ensure_response($cache);
        }

        $artist_ids = self::get_user_artist_ids($user_id);

        $counts = [
            'artworks'  => self::count_owned_posts('artpulse_artwork', $user_id, $artist_ids),
            'events'    => self::count_owned_posts('artpulse_event', $user_id, $artist_ids),
            'favorites' => self::count_favorites($user_id, $artist_ids),
            'follows'   => self::count_follows($artist_ids),
        ];

        $recent = [
            'artworks' => self::get_recent_posts('artpulse_artwork', $user_id, $artist_ids),
            'events'   => self::get_recent_posts('artpulse_event', $user_id, $artist_ids),
        ];

        $payload = [
            'counts' => $counts,
            'recent' => $recent,
        ];

        set_transient(self::OVERVIEW_CACHE_PREFIX . $user_id, $payload, self::OVERVIEW_TTL);

        return rest_ensure_response($payload);
    }

    public static function list_artworks(WP_REST_Request $request): WP_REST_Response
    {
        $user_id    = get_current_user_id();
        $artist_ids = self::get_user_artist_ids($user_id);
        $status_param = $request->get_param('status');
        $status       = self::normalize_status($status_param ?: 'publish');
        $per_page   = self::sanitize_per_page((int) $request->get_param('per_page'));
        $page       = self::sanitize_page((int) $request->get_param('page'));
        [$ids, $total] = self::query_owned_posts('artpulse_artwork', $status, $user_id, $artist_ids, $per_page, $page);

        $items = array_values(array_filter(array_map([self::class, 'format_post_item'], array_map('get_post', $ids))));

        $response = [
            'items'      => $items,
            'pagination' => self::build_pagination($total, $page, $per_page),
        ];

        return rest_ensure_response($response);
    }

    public static function list_events(WP_REST_Request $request): WP_REST_Response
    {
        $user_id    = get_current_user_id();
        $artist_ids = self::get_user_artist_ids($user_id);
        $status_param = $request->get_param('status');
        $status       = self::normalize_status($status_param ?: 'publish');
        $per_page   = self::sanitize_per_page((int) $request->get_param('per_page'));
        $page       = self::sanitize_page((int) $request->get_param('page'));
        [$ids, $total] = self::query_owned_posts('artpulse_event', $status, $user_id, $artist_ids, $per_page, $page);

        $items = array_values(array_filter(array_map([self::class, 'format_post_item'], array_map('get_post', $ids))));

        $response = [
            'items'      => $items,
            'pagination' => self::build_pagination($total, $page, $per_page),
        ];

        return rest_ensure_response($response);
    }

    public static function create_artwork(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if (!current_user_can('create_artpulse_artworks')) {
            return new WP_Error(
                'rest_forbidden',
                __('You are not allowed to create artworks.', 'artpulse-management'),
                ['status' => rest_authorization_required_code()]
            );
        }

        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = [];
        }

        $title = sanitize_text_field($payload['title'] ?? '');
        if ('' === $title) {
            return new WP_Error(
                'rest_invalid_param',
                __('Title is required.', 'artpulse-management'),
                ['status' => 400]
            );
        }

        $artist_ids = self::get_user_artist_ids($user_id);
        $artist_id  = self::resolve_target_artist($artist_ids, $payload);

        $status = self::normalize_status($payload['status'] ?? null);

        $postarr = [
            'post_type'    => 'artpulse_artwork',
            'post_title'   => $title,
            'post_content' => isset($payload['content']) ? wp_kses_post($payload['content']) : '',
            'post_excerpt' => isset($payload['excerpt']) ? wp_kses_post($payload['excerpt']) : '',
            'post_status'  => $status,
            'post_author'  => $user_id,
        ];

        $post_id = wp_insert_post($postarr, true);
        if ($post_id instanceof WP_Error) {
            return $post_id;
        }

        if ($artist_id > 0) {
            update_post_meta($post_id, '_ap_artist_id', $artist_id);
        }

        self::handle_featured_media($post_id, $payload);
        self::handle_tax_input($post_id, $payload);

        self::invalidate_overview_cache($user_id);

        $post = get_post($post_id);

        return rest_ensure_response([
            'item' => self::format_post_item($post),
        ]);
    }

    public static function update_artwork(WP_REST_Request $request)
    {
        $post_id = (int) $request['id'];
        $post    = get_post($post_id);
        if (!$post instanceof WP_Post || 'artpulse_artwork' !== $post->post_type) {
            return new WP_Error(
                'rest_not_found',
                __('Artwork not found.', 'artpulse-management'),
                ['status' => 404]
            );
        }

        $user_id = get_current_user_id();
        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You cannot edit this artwork.', 'artpulse-management'),
                ['status' => rest_authorization_required_code()]
            );
        }

        $artist_ids = self::get_user_artist_ids($user_id);
        if (!self::user_can_manage_post($post, $user_id, $artist_ids)) {
            return new WP_Error(
                'rest_forbidden',
                __('You cannot edit this artwork.', 'artpulse-management'),
                ['status' => 403]
            );
        }

        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = [];
        }

        $updates = ['ID' => $post_id];
        if (array_key_exists('title', $payload)) {
            $title = sanitize_text_field($payload['title']);
            if ('' === $title) {
                return new WP_Error(
                    'rest_invalid_param',
                    __('Title is required.', 'artpulse-management'),
                    ['status' => 400]
                );
            }
            $updates['post_title'] = $title;
        }

        if (array_key_exists('content', $payload)) {
            $updates['post_content'] = wp_kses_post((string) $payload['content']);
        }

        if (array_key_exists('excerpt', $payload)) {
            $updates['post_excerpt'] = wp_kses_post((string) $payload['excerpt']);
        }

        if (array_key_exists('status', $payload)) {
            $updates['post_status'] = self::normalize_status($payload['status']);
        }

        $result = wp_update_post($updates, true);
        if ($result instanceof WP_Error) {
            return $result;
        }

        if (array_key_exists('featured_media', $payload)) {
            self::handle_featured_media($post_id, $payload);
        }

        if (array_key_exists('taxonomies', $payload) || array_key_exists('tax_input', $payload)) {
            self::handle_tax_input($post_id, $payload);
        }

        if (array_key_exists('artist_id', $payload)) {
            $artist_id = absint($payload['artist_id']);
            if ($artist_id > 0 && in_array($artist_id, $artist_ids, true)) {
                update_post_meta($post_id, '_ap_artist_id', $artist_id);
            }
        }

        self::invalidate_overview_cache($user_id);

        $post = get_post($post_id);

        return rest_ensure_response([
            'item' => self::format_post_item($post),
        ]);
    }

    public static function delete_artwork(WP_REST_Request $request)
    {
        $post_id = (int) $request['id'];
        $post    = get_post($post_id);
        if (!$post instanceof WP_Post || 'artpulse_artwork' !== $post->post_type) {
            return new WP_Error(
                'rest_not_found',
                __('Artwork not found.', 'artpulse-management'),
                ['status' => 404]
            );
        }

        $user_id = get_current_user_id();
        if (!current_user_can('delete_post', $post_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You cannot delete this artwork.', 'artpulse-management'),
                ['status' => rest_authorization_required_code()]
            );
        }

        $artist_ids = self::get_user_artist_ids($user_id);
        if (!self::user_can_manage_post($post, $user_id, $artist_ids)) {
            return new WP_Error(
                'rest_forbidden',
                __('You cannot delete this artwork.', 'artpulse-management'),
                ['status' => 403]
            );
        }

        $force = rest_sanitize_boolean($request->get_param('force'));

        if ($force) {
            $deleted = wp_delete_post($post_id, true);
        } else {
            $deleted = wp_trash_post($post_id);
        }

        if (!$deleted) {
            return new WP_Error(
                'rest_cannot_delete',
                __('Unable to delete the artwork.', 'artpulse-management'),
                ['status' => 500]
            );
        }

        self::invalidate_overview_cache($user_id);

        return rest_ensure_response([
            'deleted' => true,
            'force'   => (bool) $force,
            'id'      => $post_id,
        ]);
    }

    public static function create_event(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if (!current_user_can('create_artpulse_events')) {
            return new WP_Error(
                'rest_forbidden',
                __('You are not allowed to create events.', 'artpulse-management'),
                ['status' => rest_authorization_required_code()]
            );
        }

        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = [];
        }

        $title = sanitize_text_field($payload['title'] ?? '');
        if ('' === $title) {
            return new WP_Error(
                'rest_invalid_param',
                __('Title is required.', 'artpulse-management'),
                ['status' => 400]
            );
        }

        $artist_ids = self::get_user_artist_ids($user_id);
        $artist_id  = self::resolve_target_artist($artist_ids, $payload);

        $status = self::normalize_status($payload['status'] ?? null);

        $postarr = [
            'post_type'    => 'artpulse_event',
            'post_title'   => $title,
            'post_content' => isset($payload['content']) ? wp_kses_post($payload['content']) : '',
            'post_excerpt' => isset($payload['excerpt']) ? wp_kses_post($payload['excerpt']) : '',
            'post_status'  => $status,
            'post_author'  => $user_id,
        ];

        $post_id = wp_insert_post($postarr, true);
        if ($post_id instanceof WP_Error) {
            return $post_id;
        }

        $meta_result = self::persist_event_meta($post_id, $payload, $artist_id, $artist_ids);
        if ($meta_result instanceof WP_Error) {
            wp_delete_post($post_id, true);
            return $meta_result;
        }
        self::handle_featured_media($post_id, $payload);
        self::handle_tax_input($post_id, $payload);

        self::invalidate_overview_cache($user_id);

        $post = get_post($post_id);

        return rest_ensure_response([
            'item' => self::format_post_item($post),
        ]);
    }

    public static function update_event(WP_REST_Request $request)
    {
        $post_id = (int) $request['id'];
        $post    = get_post($post_id);
        if (!$post instanceof WP_Post || 'artpulse_event' !== $post->post_type) {
            return new WP_Error(
                'rest_not_found',
                __('Event not found.', 'artpulse-management'),
                ['status' => 404]
            );
        }

        $user_id = get_current_user_id();
        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You cannot edit this event.', 'artpulse-management'),
                ['status' => rest_authorization_required_code()]
            );
        }

        $artist_ids = self::get_user_artist_ids($user_id);
        if (!self::user_can_manage_post($post, $user_id, $artist_ids)) {
            return new WP_Error(
                'rest_forbidden',
                __('You cannot edit this event.', 'artpulse-management'),
                ['status' => 403]
            );
        }

        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = [];
        }

        $updates = ['ID' => $post_id];
        if (array_key_exists('title', $payload)) {
            $title = sanitize_text_field($payload['title']);
            if ('' === $title) {
                return new WP_Error(
                    'rest_invalid_param',
                    __('Title is required.', 'artpulse-management'),
                    ['status' => 400]
                );
            }
            $updates['post_title'] = $title;
        }

        if (array_key_exists('content', $payload)) {
            $updates['post_content'] = wp_kses_post((string) $payload['content']);
        }

        if (array_key_exists('excerpt', $payload)) {
            $updates['post_excerpt'] = wp_kses_post((string) $payload['excerpt']);
        }

        if (array_key_exists('status', $payload)) {
            $updates['post_status'] = self::normalize_status($payload['status']);
        }

        $result = wp_update_post($updates, true);
        if ($result instanceof WP_Error) {
            return $result;
        }

        $meta_result = self::persist_event_meta($post_id, $payload, null, $artist_ids);
        if ($meta_result instanceof WP_Error) {
            return $meta_result;
        }

        if (array_key_exists('featured_media', $payload)) {
            self::handle_featured_media($post_id, $payload);
        }

        if (array_key_exists('taxonomies', $payload) || array_key_exists('tax_input', $payload)) {
            self::handle_tax_input($post_id, $payload);
        }

        self::invalidate_overview_cache($user_id);

        $post = get_post($post_id);

        return rest_ensure_response([
            'item' => self::format_post_item($post),
        ]);
    }

    public static function delete_event(WP_REST_Request $request)
    {
        $post_id = (int) $request['id'];
        $post    = get_post($post_id);
        if (!$post instanceof WP_Post || 'artpulse_event' !== $post->post_type) {
            return new WP_Error(
                'rest_not_found',
                __('Event not found.', 'artpulse-management'),
                ['status' => 404]
            );
        }

        $user_id = get_current_user_id();
        if (!current_user_can('delete_post', $post_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You cannot delete this event.', 'artpulse-management'),
                ['status' => rest_authorization_required_code()]
            );
        }

        $artist_ids = self::get_user_artist_ids($user_id);
        if (!self::user_can_manage_post($post, $user_id, $artist_ids)) {
            return new WP_Error(
                'rest_forbidden',
                __('You cannot delete this event.', 'artpulse-management'),
                ['status' => 403]
            );
        }

        $force = rest_sanitize_boolean($request->get_param('force'));

        if ($force) {
            $deleted = wp_delete_post($post_id, true);
        } else {
            $deleted = wp_trash_post($post_id);
        }

        if (!$deleted) {
            return new WP_Error(
                'rest_cannot_delete',
                __('Unable to delete the event.', 'artpulse-management'),
                ['status' => 500]
            );
        }

        self::invalidate_overview_cache($user_id);

        return rest_ensure_response([
            'deleted' => true,
            'force'   => (bool) $force,
            'id'      => $post_id,
        ]);
    }

    public static function validate_positive_int($value): bool
    {
        return is_numeric($value) && (int) $value > 0;
    }

    private static function collection_args(): array
    {
        return [
            'status' => [
                'sanitize_callback' => [self::class, 'sanitize_collection_status'],
                'validate_callback' => [self::class, 'validate_status'],
            ],
            'per_page' => [
                'sanitize_callback' => 'absint',
            ],
            'page' => [
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    private static function write_args_schema(): array
    {
        return [
            'title' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'content' => [
                'sanitize_callback' => [self::class, 'sanitize_html'],
            ],
            'excerpt' => [
                'sanitize_callback' => [self::class, 'sanitize_html'],
            ],
            'status' => [
                'sanitize_callback' => [self::class, 'normalize_status'],
                'validate_callback' => [self::class, 'validate_status'],
            ],
            'featured_media' => [
                'sanitize_callback' => 'absint',
            ],
            'artist_id' => [
                'sanitize_callback' => 'absint',
            ],
            'taxonomies' => [
                'sanitize_callback' => [self::class, 'sanitize_tax_input'],
            ],
            'tax_input' => [
                'sanitize_callback' => [self::class, 'sanitize_tax_input'],
            ],
        ];
    }

    private static function event_write_args_schema(): array
    {
        return array_merge(
            self::write_args_schema(),
            [
                'start_datetime' => [
                    'sanitize_callback' => [self::class, 'sanitize_datetime_field'],
                ],
                'end_datetime' => [
                    'sanitize_callback' => [self::class, 'sanitize_datetime_field'],
                ],
                'venue' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'location' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'external_url' => [
                    'sanitize_callback' => 'esc_url_raw',
                ],
            ]
        );
    }

    private static function sanitize_tax_input($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $output = [];
        foreach ($value as $taxonomy => $terms) {
            $taxonomy = sanitize_key($taxonomy);
            $term_ids = array_values(array_filter(array_map('absint', (array) $terms)));
            if (!$taxonomy || empty($term_ids)) {
                continue;
            }
            $output[$taxonomy] = $term_ids;
        }

        return $output;
    }

    private static function sanitize_html($value): string
    {
        return wp_kses_post((string) $value);
    }

    private static function sanitize_datetime_field($value): ?string
    {
        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        $parsed = rest_parse_date($value, true);
        if (false === $parsed) {
            return null;
        }

        return gmdate(DATE_ATOM, $parsed);
    }

    private static function sanitize_collection_status($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));
        if ('' === $value) {
            return null;
        }

        return in_array($value, self::ALLOWED_STATUSES, true) ? $value : null;
    }

    private static function normalize_status($value): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';
        if (!in_array($value, self::ALLOWED_STATUSES, true)) {
            return 'draft';
        }

        return $value;
    }

    private static function validate_status($value): bool
    {
        if (null === $value || '' === $value) {
            return true;
        }

        return in_array(self::normalize_status($value), self::ALLOWED_STATUSES, true);
    }

    private static function sanitize_per_page(int $value): int
    {
        if ($value <= 0) {
            return self::DEFAULT_PER_PAGE;
        }

        return min($value, self::MAX_PER_PAGE);
    }

    private static function sanitize_page(int $value): int
    {
        return max(1, $value ?: 1);
    }

    private static function build_pagination(int $total, int $page, int $per_page): array
    {
        $total_pages = (int) ceil(max(0, $total) / max(1, $per_page));

        return [
            'total'       => $total,
            'total_pages' => max(1, $total_pages),
            'page'        => $page,
            'per_page'    => $per_page,
        ];
    }

    private static function handle_featured_media(int $post_id, array $payload): void
    {
        $featured = isset($payload['featured_media']) ? absint($payload['featured_media']) : 0;
        if ($featured > 0) {
            set_post_thumbnail($post_id, $featured);
            return;
        }

        if (array_key_exists('featured_media', $payload)) {
            delete_post_thumbnail($post_id);
        }
    }

    private static function handle_tax_input(int $post_id, array $payload): void
    {
        $tax_input = [];
        if (isset($payload['taxonomies']) && is_array($payload['taxonomies'])) {
            $tax_input = $payload['taxonomies'];
        } elseif (isset($payload['tax_input']) && is_array($payload['tax_input'])) {
            $tax_input = $payload['tax_input'];
        }

        $tax_input = self::sanitize_tax_input($tax_input);

        foreach ($tax_input as $taxonomy => $terms) {
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            wp_set_post_terms($post_id, $terms, $taxonomy);
        }
    }

    private static function persist_event_meta(int $post_id, array $payload, ?int $preferred_artist_id, array $artist_ids): bool|WP_Error
    {
        if (array_key_exists('start_datetime', $payload)) {
            $raw_start = $payload['start_datetime'];
            $start     = self::sanitize_datetime_field($raw_start);
            if (null === $start && '' !== trim((string) $raw_start)) {
                return new WP_Error(
                    'rest_invalid_param',
                    __('Start datetime must be ISO 8601.', 'artpulse-management'),
                    ['status' => 400]
                );
            }

            if (null === $start) {
                delete_post_meta($post_id, '_ap_event_start');
            } else {
                update_post_meta($post_id, '_ap_event_start', $start);
            }
        }

        if (array_key_exists('end_datetime', $payload)) {
            $raw_end = $payload['end_datetime'];
            $end     = self::sanitize_datetime_field($raw_end);
            if (null === $end && '' !== trim((string) $raw_end)) {
                return new WP_Error(
                    'rest_invalid_param',
                    __('End datetime must be ISO 8601.', 'artpulse-management'),
                    ['status' => 400]
                );
            }

            if (null === $end) {
                delete_post_meta($post_id, '_ap_event_end');
            } else {
                update_post_meta($post_id, '_ap_event_end', $end);
            }
        }

        if (array_key_exists('venue', $payload)) {
            $venue = sanitize_text_field($payload['venue']);
            if ('' === $venue) {
                delete_post_meta($post_id, '_ap_event_venue');
            } else {
                update_post_meta($post_id, '_ap_event_venue', $venue);
            }
        }

        if (array_key_exists('location', $payload)) {
            $location = sanitize_text_field($payload['location']);
            if ('' === $location) {
                delete_post_meta($post_id, '_ap_event_location');
            } else {
                update_post_meta($post_id, '_ap_event_location', $location);
            }
        }

        if (array_key_exists('external_url', $payload)) {
            $url = esc_url_raw($payload['external_url']);
            if ('' === $url) {
                delete_post_meta($post_id, '_ap_event_external_url');
            } else {
                update_post_meta($post_id, '_ap_event_external_url', $url);
            }
        }

        $artist_id = $preferred_artist_id ?? absint($payload['artist_id'] ?? 0);
        if ($artist_id > 0 && in_array($artist_id, $artist_ids, true)) {
            update_post_meta($post_id, '_ap_artist_id', $artist_id);
        }

        return true;
    }

    private static function query_owned_posts(string $post_type, string $status, int $user_id, array $artist_ids, int $per_page, int $page): array
    {
        global $wpdb;

        $offset = ($page - 1) * $per_page;
        $params = [$post_type, $status, $user_id];
        $meta_condition = '';

        if (!empty($artist_ids)) {
            $placeholders   = implode(',', array_fill(0, count($artist_ids), '%d'));
            $meta_condition = " OR (CAST(pm.meta_value AS UNSIGNED) IN ($placeholders))";
            $params         = array_merge($params, $artist_ids);
        }

        $sql = "
            SELECT SQL_CALC_FOUND_ROWS p.ID
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm
                ON pm.post_id = p.ID AND pm.meta_key = '_ap_artist_id'
            WHERE p.post_type = %s
              AND p.post_status = %s
              AND (p.post_author = %d$meta_condition)
            GROUP BY p.ID
            ORDER BY p.post_date DESC
            LIMIT %d OFFSET %d
        ";

        $params[] = $per_page;
        $params[] = $offset;

        $prepared = $wpdb->prepare($sql, ...$params);
        $ids      = array_map('intval', $wpdb->get_col($prepared));
        $total    = (int) $wpdb->get_var('SELECT FOUND_ROWS()');

        return [$ids, $total];
    }

    private static function count_owned_posts(string $post_type, int $user_id, array $artist_ids): int
    {
        global $wpdb;

        $params = [$post_type, $user_id];
        $meta_condition = '';

        if (!empty($artist_ids)) {
            $placeholders   = implode(',', array_fill(0, count($artist_ids), '%d'));
            $meta_condition = " OR CAST(pm.meta_value AS UNSIGNED) IN ($placeholders)";
            $params         = array_merge($params, $artist_ids);
        }

        $sql = "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm
                ON pm.post_id = p.ID AND pm.meta_key = '_ap_artist_id'
            WHERE p.post_type = %s
              AND p.post_status IN ('publish','draft')
              AND (p.post_author = %d$meta_condition)
        ";

        $prepared = $wpdb->prepare($sql, ...$params);

        return (int) $wpdb->get_var($prepared);
    }

    private static function count_favorites(array $artist_ids): int
    {
        if (empty($artist_ids)) {
            return 0;
        }

        $artwork_ids = self::get_content_ids_for_artists('artpulse_artwork', $artist_ids);
        $event_ids   = self::get_content_ids_for_artists('artpulse_event', $artist_ids);

        return self::count_relationship_rows('ap_favorites', 'artpulse_artwork', $artwork_ids)
            + self::count_relationship_rows('ap_favorites', 'artpulse_event', $event_ids);
    }

    private static function count_follows(array $artist_ids): int
    {
        if (empty($artist_ids)) {
            return 0;
        }

        return self::count_relationship_rows('ap_follows', 'artpulse_artist', $artist_ids);
    }

    private static function count_relationship_rows(string $table_suffix, string $object_type, array $object_ids): int
    {
        if (empty($object_ids)) {
            return 0;
        }

        global $wpdb;
        $table        = $wpdb->prefix . $table_suffix;
        $placeholders = implode(',', array_fill(0, count($object_ids), '%d'));
        $params       = array_merge([$object_type], $object_ids);

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE object_type = %s AND object_id IN ({$placeholders})",
            ...$params
        );

        return (int) $wpdb->get_var($sql);
    }

    private static function get_recent_posts(string $post_type, int $user_id, array $artist_ids): array
    {
        global $wpdb;

        $params = [$post_type, $user_id];
        $meta_condition = '';

        if (!empty($artist_ids)) {
            $placeholders   = implode(',', array_fill(0, count($artist_ids), '%d'));
            $meta_condition = " OR CAST(pm.meta_value AS UNSIGNED) IN ($placeholders)";
            $params         = array_merge($params, $artist_ids);
        }

        $sql = "
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm
                ON pm.post_id = p.ID AND pm.meta_key = '_ap_artist_id'
            WHERE p.post_type = %s
              AND p.post_status IN ('publish','draft')
              AND (p.post_author = %d$meta_condition)
            ORDER BY p.post_date DESC
            LIMIT %d
        ";

        $params[] = self::RECENT_LIMIT;
        $prepared = $wpdb->prepare($sql, ...$params);
        $ids      = array_map('intval', $wpdb->get_col($prepared));

        $items = [];
        foreach ($ids as $id) {
            $post = get_post($id);
            if (!$post instanceof WP_Post) {
                continue;
            }

            $items[] = self::format_post_item($post);
        }

        return $items;
    }

    private static function format_post_item($post): array
    {
        if (!$post instanceof WP_Post) {
            return [];
        }

        $edit_link = get_edit_post_link($post->ID, 'raw');

        return [
            'id'         => $post->ID,
            'title'      => get_the_title($post) ?: __('(no title)', 'artpulse-management'),
            'status'     => $post->post_status,
            'permalink'  => get_permalink($post),
            'edit_link'  => $edit_link ?: '',
            'date'       => get_post_time(DATE_ATOM, true, $post),
            'thumbnail'  => get_the_post_thumbnail_url($post, 'thumbnail') ?: '',
            'post_type'  => get_post_type($post),
        ];
    }

    private static function resolve_target_artist(array $artist_ids, array $payload): int
    {
        $requested = absint($payload['artist_id'] ?? 0);
        if ($requested > 0 && in_array($requested, $artist_ids, true)) {
            return $requested;
        }

        return $artist_ids[0] ?? 0;
    }

    private static function get_user_artist_ids(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        $meta_ids = get_user_meta($user_id, '_ap_artist_post_id', false);
        $meta_ids = array_map('absint', (array) $meta_ids);

        $author_owned = get_posts([
            'post_type'      => 'artpulse_artist',
            'post_status'    => self::OWNERSHIP_STATUSES,
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'author'         => $user_id,
        ]);

        $primary_owned = get_posts([
            'post_type'      => 'artpulse_artist',
            'post_status'    => self::OWNERSHIP_STATUSES,
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_key'       => '_ap_owner_user',
            'meta_value'     => $user_id,
        ]);

        $team_owned = get_posts([
            'post_type'      => 'artpulse_artist',
            'post_status'    => self::OWNERSHIP_STATUSES,
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_ap_owner_users',
                    'value'   => sprintf(':%d;', $user_id),
                    'compare' => 'LIKE',
                ],
            ],
        ]);

        $ids = array_map('absint', array_merge($meta_ids, (array) $author_owned, (array) $primary_owned, (array) $team_owned));

        return array_values(array_unique(array_filter($ids)));
    }

    private static function user_can_manage_post(WP_Post $post, int $user_id, array $artist_ids): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        if (current_user_can('ap_is_artist')) {
            return true;
        }

        if ((int) $post->post_author === $user_id) {
            return true;
        }

        $artist_id = (int) get_post_meta($post->ID, '_ap_artist_id', true);
        if ($artist_id > 0 && in_array($artist_id, $artist_ids, true)) {
            return true;
        }

        $owner = (int) get_post_meta($post->ID, '_ap_owner_user', true);
        if ($owner === $user_id) {
            return true;
        }

        $team = get_post_meta($post->ID, '_ap_owner_users', true);
        if (is_array($team)) {
            $team_ids = array_map('absint', $team);
            if (in_array($user_id, $team_ids, true)) {
                return true;
            }
        }

        return false;
    }

    private static function get_content_ids_for_artists(string $post_type, array $artist_ids): array
    {
        if (empty($artist_ids)) {
            return [];
        }

        $posts = get_posts([
            'post_type'      => $post_type,
            'post_status'    => ['publish', 'draft'],
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_ap_artist_id',
                    'value'   => $artist_ids,
                    'compare' => 'IN',
                ],
            ],
        ]);

        return array_values(array_map('absint', (array) $posts));
    }

    private static function verify_request_nonce(WP_REST_Request $request): bool
    {
        $header_nonce = $request->get_header('X-WP-Nonce');
        if (is_string($header_nonce) && '' !== $header_nonce) {
            return (bool) wp_verify_nonce($header_nonce, 'wp_rest');
        }

        $param_nonce = $request->get_param('_wpnonce');
        if (!is_string($param_nonce) || '' === $param_nonce) {
            $param_nonce = (string) $request->get_param('nonce');
        }

        if (!is_string($param_nonce) || '' === $param_nonce) {
            return false;
        }

        $original = $_REQUEST['_wpnonce'] ?? null;
        $_REQUEST['_wpnonce'] = $param_nonce;
        $result = check_ajax_referer('wp_rest', '_wpnonce', false);

        if (null === $original) {
            unset($_REQUEST['_wpnonce']);
        } else {
            $_REQUEST['_wpnonce'] = $original;
        }

        return false !== $result;
    }

}
