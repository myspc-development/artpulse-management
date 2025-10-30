<?php

namespace ArtPulse\Rest;

use ArtPulse\Frontend\Shared\FormRateLimiter;
use ArtPulse\Frontend\Shared\PortfolioAccess;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class OrganizationRestController extends WP_REST_Controller
{
    private const POST_TYPE = 'artpulse_org';
    private const RATE_LIMIT_CONTEXT = 'organization_builder';
    private const MAX_TITLE_LENGTH = 200;
    private const MAX_EXCERPT_LENGTH = 400;
    private const ALLOWED_VISIBILITY = ['public', 'private'];
    private const ALLOWED_STATUS = ['draft', 'pending', 'publish'];

    protected $namespace = 'artpulse/v1';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public static function register(): void
    {
        new self();
    }

    public function register_routes(): void
    {
        register_rest_route(
            $this->namespace,
            '/organizations',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_organizations'],
                    'permission_callback' => '__return_true',
                    'schema'              => [$this, 'get_public_item_schema'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/organizations/(?P<id>\\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_organization'],
                    'permission_callback' => '__return_true',
                    'args'                => [
                        'id' => [
                            'description'       => __('Unique identifier for the organization.', 'artpulse-management'),
                            'type'              => 'integer',
                            'required'          => true,
                            'sanitize_callback' => 'absint',
                            'validate_callback' => [$this, 'validate_post_exists'],
                        ],
                    ],
                    'schema'              => [$this, 'get_public_item_schema'],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'update_organization'],
                    'permission_callback' => [$this, 'permissions_check'],
                    'args'                => array_merge(
                        [
                            'id' => [
                                'description'       => __('Unique identifier for the organization.', 'artpulse-management'),
                                'type'              => 'integer',
                                'required'          => true,
                                'sanitize_callback' => 'absint',
                                'validate_callback' => [$this, 'validate_post_exists'],
                            ],
                        ],
                        $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE)
                    ),
                    'schema'              => [$this, 'get_public_item_schema'],
                ],
            ]
        );
    }

    public function get_organizations(WP_REST_Request $request): WP_REST_Response
    {
        $query = new WP_Query([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        $data = [];
        foreach ($query->posts as $post_id) {
            $post = get_post((int) $post_id);
            if (!$post instanceof WP_Post) {
                continue;
            }

            $response = $this->prepare_item_for_response($post, $request);
            $data[]   = $this->prepare_response_for_collection($response);
        }

        return rest_ensure_response($data);
    }

    public function get_organization(WP_REST_Request $request)
    {
        $post = $this->get_post_from_request($request);
        if ($post instanceof WP_Error) {
            return $post;
        }

        return $this->prepare_item_for_response($post, $request);
    }

    public function update_organization(WP_REST_Request $request)
    {
        $post = $this->get_post_from_request($request);
        if ($post instanceof WP_Error) {
            return $post;
        }

        $user_id    = get_current_user_id();
        $rate_error = FormRateLimiter::enforce($user_id, self::RATE_LIMIT_CONTEXT, 30, 60);
        if ($rate_error instanceof WP_Error) {
            $this->send_rate_limit_headers($rate_error);

            return RestUtils::error(
                'ap_rate_limited',
                __('Too many requests. Try later.', 'artpulse-management'),
                429
            );
        }

        $post_update        = ['ID' => $post->ID];
        $should_update_post = false;

        $title = $request->get_param('title');
        if (null !== $title) {
            $post_update['post_title'] = $title;
            $should_update_post        = true;
        }

        $content = $request->get_param('content');
        if (null !== $content) {
            $post_update['post_content'] = $content;
            $should_update_post          = true;
        }

        $excerpt = $request->get_param('excerpt');
        if (null !== $excerpt) {
            $post_update['post_excerpt'] = $excerpt;
            $should_update_post          = true;
        }

        $status = $request->get_param('status');
        if (null !== $status) {
            $post_update['post_status'] = $status;
            $should_update_post         = true;
        }

        if ($should_update_post) {
            $updated = wp_update_post($post_update, true);
            if ($updated instanceof WP_Error) {
                return RestUtils::error(
                    'ap_update_failed',
                    __('Unable to update organization.', 'artpulse-management'),
                    500
                );
            }

            $post = get_post($post->ID);
            if (!$post instanceof WP_Post) {
                return RestUtils::error('ap_not_found', __('Organization not found.', 'artpulse-management'), 404);
            }
        }

        $website = $request->get_param('website_url');
        if (null !== $website) {
            $this->update_meta_string($post->ID, '_ap_website', $website);
        }

        $socials = $request->get_param('socials');
        if (null !== $socials) {
            update_post_meta($post->ID, '_ap_socials', $socials);
        }

        $location = $request->get_param('location');
        if (null !== $location) {
            $this->update_location_meta($post->ID, $location);
        }

        $visibility = $request->get_param('visibility');
        if (null !== $visibility) {
            $this->update_meta_string($post->ID, '_ap_visibility', $visibility);
        }

        $gallery = $request->get_param('gallery');
        if (null !== $gallery) {
            update_post_meta($post->ID, '_ap_gallery_ids', $gallery);
        }

        $featured = $request->get_param('featured_media');
        if (null !== $featured) {
            if ((int) $featured > 0) {
                set_post_thumbnail($post->ID, (int) $featured);
            } else {
                delete_post_thumbnail($post->ID);
            }
        }

        return $this->prepare_item_for_response(get_post($post->ID), $request);
    }

    public function permissions_check(WP_REST_Request $request)
    {
        $post = $this->get_post_from_request($request);
        if ($post instanceof WP_Error) {
            return $post;
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return RestUtils::error('ap_auth_required', __('Authentication required.', 'artpulse-management'), 401);
        }

        if (!PortfolioAccess::can_manage_portfolio($user_id, $post->ID) || !current_user_can('edit_post', $post->ID)) {
            return RestUtils::error('ap_forbidden', __('You cannot edit this resource.', 'artpulse-management'), 403);
        }

        return true;
    }

    public function get_item_schema(): array
    {
        if ($this->schema) {
            return $this->schema;
        }

        $this->schema = [
            '$schema'    => 'http://json-schema.org/draft-07/schema#',
            'title'      => 'organization',
            'type'       => 'object',
            'properties' => [
                'id' => [
                    'description' => __('Unique identifier for the organization.', 'artpulse-management'),
                    'type'        => 'integer',
                    'context'     => ['view', 'edit'],
                    'readonly'    => true,
                ],
                'title' => [
                    'description' => __('Title of the organization profile.', 'artpulse-management'),
                    'type'        => 'string',
                    'context'     => ['view', 'edit'],
                    'minLength'   => 0,
                    'maxLength'   => self::MAX_TITLE_LENGTH,
                    'arg_options' => [
                        'sanitize_callback' => [self::class, 'sanitize_title_field'],
                        'validate_callback' => [self::class, 'validate_title_field'],
                    ],
                ],
                'content' => [
                    'description' => __('Primary description of the organization.', 'artpulse-management'),
                    'type'        => 'string',
                    'context'     => ['view', 'edit'],
                    'arg_options' => [
                        'sanitize_callback' => [self::class, 'sanitize_content_field'],
                    ],
                ],
                'excerpt' => [
                    'description' => __('Short summary of the organization.', 'artpulse-management'),
                    'type'        => 'string',
                    'context'     => ['view', 'edit'],
                    'minLength'   => 0,
                    'maxLength'   => self::MAX_EXCERPT_LENGTH,
                    'arg_options' => [
                        'sanitize_callback' => [self::class, 'sanitize_excerpt_field'],
                        'validate_callback' => [self::class, 'validate_excerpt_field'],
                    ],
                ],
                'website_url' => [
                    'description' => __('Primary website for the organization.', 'artpulse-management'),
                    'type'        => 'string',
                    'format'      => 'uri',
                    'context'     => ['view', 'edit'],
                    'arg_options' => [
                        'sanitize_callback' => [self::class, 'sanitize_url_field'],
                        'validate_callback' => [self::class, 'validate_url_field'],
                    ],
                ],
                'socials' => [
                    'description' => __('List of social profile URLs.', 'artpulse-management'),
                    'type'        => 'array',
                    'items'       => [
                        'type'   => 'string',
                        'format' => 'uri',
                    ],
                    'context'     => ['view', 'edit'],
                    'arg_options' => [
                        'sanitize_callback' => [self::class, 'sanitize_socials_field'],
                        'validate_callback' => [self::class, 'validate_socials_field'],
                    ],
                ],
                'location' => [
                    'description' => __('Primary location label for the organization.', 'artpulse-management'),
                    'type'        => 'string',
                    'context'     => ['view', 'edit'],
                    'arg_options' => [
                        'sanitize_callback' => [self::class, 'sanitize_location_field'],
                    ],
                ],
                'featured_media' => [
                    'description' => __('Featured media attachment ID.', 'artpulse-management'),
                    'type'        => 'integer',
                    'context'     => ['view', 'edit'],
                    'minimum'     => 0,
                    'arg_options' => [
                        'sanitize_callback' => [self::class, 'sanitize_featured_media_field'],
                        'validate_callback' => [$this, 'validate_featured_media_field'],
                    ],
                ],
                'gallery' => [
                    'description' => __('Gallery attachment IDs.', 'artpulse-management'),
                    'type'        => 'array',
                    'items'       => [
                        'type'    => 'integer',
                        'minimum' => 1,
                    ],
                    'context'     => ['view', 'edit'],
                    'arg_options' => [
                        'sanitize_callback' => [self::class, 'sanitize_gallery_field'],
                        'validate_callback' => [$this, 'validate_gallery_field'],
                    ],
                ],
                'visibility' => [
                    'description' => __('Visibility state for the organization.', 'artpulse-management'),
                    'type'        => 'string',
                    'enum'        => self::ALLOWED_VISIBILITY,
                    'context'     => ['view', 'edit'],
                    'arg_options' => [
                        'sanitize_callback' => [self::class, 'sanitize_visibility_field'],
                        'validate_callback' => [self::class, 'validate_visibility_field'],
                    ],
                ],
                'status' => [
                    'description' => __('Publication status for the organization.', 'artpulse-management'),
                    'type'        => 'string',
                    'enum'        => self::ALLOWED_STATUS,
                    'context'     => ['view', 'edit'],
                    'arg_options' => [
                        'sanitize_callback' => [self::class, 'sanitize_status_field'],
                        'validate_callback' => [self::class, 'validate_status_field'],
                    ],
                ],
            ],
        ];

        return $this->schema;
    }

    public function validate_post_exists($value, WP_REST_Request $request, string $param)
    {
        $post = get_post((int) $value);
        if (!$post instanceof WP_Post || self::POST_TYPE !== $post->post_type) {
            return RestUtils::error('ap_not_found', __('Organization not found.', 'artpulse-management'), 404, $param);
        }

        return true;
    }

    public static function sanitize_title_field($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return null;
        }

        return sanitize_text_field($value);
    }

    public static function validate_title_field($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return true;
        }

        if (!is_string($value)) {
            return RestUtils::error('ap_invalid_param', __('Title must be a string.', 'artpulse-management'), 422, $param);
        }

        if (mb_strlen($value) > self::MAX_TITLE_LENGTH) {
            return RestUtils::error('ap_invalid_param', __('Title must be 200 characters or fewer.', 'artpulse-management'), 422, $param);
        }

        return true;
    }

    public static function sanitize_content_field($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return null;
        }

        return wp_kses_post($value);
    }

    public static function sanitize_excerpt_field($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return null;
        }

        return sanitize_textarea_field($value);
    }

    public static function validate_excerpt_field($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return true;
        }

        if (!is_string($value)) {
            return RestUtils::error('ap_invalid_param', __('Excerpt must be a string.', 'artpulse-management'), 422, $param);
        }

        if (mb_strlen($value) > self::MAX_EXCERPT_LENGTH) {
            return RestUtils::error('ap_invalid_param', __('Excerpt must be 400 characters or fewer.', 'artpulse-management'), 422, $param);
        }

        return true;
    }

    public static function sanitize_url_field($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return null;
        }

        return esc_url_raw((string) $value);
    }

    public static function validate_url_field($value, WP_REST_Request $request, string $param)
    {
        if (null === $value || '' === $value) {
            return true;
        }

        if (!is_string($value)) {
            return RestUtils::error('ap_invalid_param', __('Website must be a valid URL.', 'artpulse-management'), 422, $param);
        }

        if (false === filter_var($value, FILTER_VALIDATE_URL)) {
            return RestUtils::error('ap_invalid_param', __('Website must be a valid URL.', 'artpulse-management'), 422, $param);
        }

        return true;
    }

    public static function sanitize_socials_field($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return null;
        }

        if (!is_array($value)) {
            return [];
        }

        $sanitized = [];
        foreach ($value as $entry) {
            $url = esc_url_raw((string) $entry);
            if ('' !== $url) {
                $sanitized[] = $url;
            }
        }

        return array_values(array_unique($sanitized));
    }

    public static function validate_socials_field($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return true;
        }

        if (!is_array($value)) {
            return RestUtils::error('ap_invalid_param', __('Social profiles must be provided as an array.', 'artpulse-management'), 422, $param);
        }

        foreach ($value as $url) {
            if ('' === $url) {
                continue;
            }

            if (!is_string($url) || false === filter_var($url, FILTER_VALIDATE_URL)) {
                return RestUtils::error('ap_invalid_param', __('Each social profile must be a valid URL.', 'artpulse-management'), 422, $param);
            }
        }

        return true;
    }

    public static function sanitize_location_field($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return null;
        }

        return sanitize_text_field($value);
    }

    public static function sanitize_featured_media_field($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return null;
        }

        return absint($value);
    }

    public function validate_featured_media_field($value, WP_REST_Request $request, string $param)
    {
        if (null === $value || 0 === (int) $value) {
            return true;
        }

        $attachment = get_post((int) $value);
        if (!$attachment instanceof WP_Post || 'attachment' !== $attachment->post_type) {
            return RestUtils::error('ap_invalid_param', __('Featured media must reference an attachment.', 'artpulse-management'), 422, $param);
        }

        $post = $this->get_post_from_request($request);
        if ($post instanceof WP_Error) {
            return $post;
        }

        if (!$this->attachment_accessible($attachment, $post->ID)) {
            return RestUtils::error('ap_invalid_param', __('You do not have access to this attachment.', 'artpulse-management'), 422, $param);
        }

        return true;
    }

    public static function sanitize_gallery_field($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return null;
        }

        if (!is_array($value)) {
            return [];
        }

        $sanitized = array_map('absint', $value);
        $sanitized = array_filter($sanitized, static fn($id) => $id > 0);

        return array_values(array_unique($sanitized));
    }

    public function validate_gallery_field($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return true;
        }

        if (!is_array($value)) {
            return RestUtils::error('ap_invalid_param', __('Gallery must be provided as an array of attachment IDs.', 'artpulse-management'), 422, $param);
        }

        $post = $this->get_post_from_request($request);
        if ($post instanceof WP_Error) {
            return $post;
        }

        foreach ($value as $attachment_id) {
            $attachment = get_post((int) $attachment_id);
            if (!$attachment instanceof WP_Post || 'attachment' !== $attachment->post_type) {
                return RestUtils::error('ap_invalid_param', __('Gallery items must reference attachments.', 'artpulse-management'), 422, $param);
            }

            if (!$this->attachment_accessible($attachment, $post->ID)) {
                return RestUtils::error('ap_invalid_param', __('One or more gallery items are not accessible.', 'artpulse-management'), 422, $param);
            }
        }

        return true;
    }

    public static function sanitize_visibility_field($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return null;
        }

        return sanitize_key($value);
    }

    public static function validate_visibility_field($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return true;
        }

        if (!in_array($value, self::ALLOWED_VISIBILITY, true)) {
            return RestUtils::error('ap_invalid_param', __('Visibility must be public or private.', 'artpulse-management'), 422, $param);
        }

        return true;
    }

    public static function sanitize_status_field($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return null;
        }

        return sanitize_key($value);
    }

    public static function validate_status_field($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return true;
        }

        if (!in_array($value, self::ALLOWED_STATUS, true)) {
            return RestUtils::error('ap_invalid_param', __('Status must be draft, pending, or publish.', 'artpulse-management'), 422, $param);
        }

        return true;
    }

    public function prepare_item_for_response($post, $request)
    {
        if (!$post instanceof WP_Post) {
            $post = get_post($post);
        }

        if (!$post instanceof WP_Post) {
            return rest_ensure_response([]);
        }

        $website  = esc_url_raw((string) get_post_meta($post->ID, '_ap_website', true));
        $socials  = array_map('esc_url_raw', (array) get_post_meta($post->ID, '_ap_socials', true));
        $socials  = array_values(array_filter($socials));
        $location = $this->extract_location($post->ID);
        $gallery  = array_map('intval', (array) get_post_meta($post->ID, '_ap_gallery_ids', true));
        $gallery  = array_values(array_filter($gallery, static fn($id) => $id > 0));
        $visibility = (string) get_post_meta($post->ID, '_ap_visibility', true);
        if ('' === $visibility) {
            $visibility = self::ALLOWED_VISIBILITY[0];
        }

        $data = [
            'id'             => (int) $post->ID,
            'title'          => get_the_title($post),
            'content'        => (string) $post->post_content,
            'excerpt'        => (string) $post->post_excerpt,
            'website_url'    => $website,
            'socials'        => $socials,
            'location'       => $location,
            'featured_media' => (int) get_post_thumbnail_id($post),
            'gallery'        => $gallery,
            'visibility'     => $visibility,
            'status'         => (string) $post->post_status,
        ];

        return rest_ensure_response($data);
    }

    private function get_post_from_request(WP_REST_Request $request)
    {
        $post_id = (int) $request->get_param('id');
        $post    = get_post($post_id);

        if (!$post instanceof WP_Post || self::POST_TYPE !== $post->post_type) {
            return RestUtils::error('ap_not_found', __('Organization not found.', 'artpulse-management'), 404, 'id');
        }

        return $post;
    }

    private function update_meta_string(int $post_id, string $meta_key, ?string $value): void
    {
        if (null === $value) {
            return;
        }

        if ('' === $value) {
            delete_post_meta($post_id, $meta_key);

            return;
        }

        update_post_meta($post_id, $meta_key, $value);
    }

    private function update_location_meta(int $post_id, ?string $location): void
    {
        if (null === $location) {
            return;
        }

        $stored = get_post_meta($post_id, '_ap_location', true);
        if (!is_array($stored)) {
            $stored = [];
        }

        if ('' === $location) {
            unset($stored['address']);
        } else {
            $stored['address'] = $location;
        }

        if (empty($stored)) {
            delete_post_meta($post_id, '_ap_location');
        } else {
            update_post_meta($post_id, '_ap_location', $stored);
        }
    }

    private function extract_location(int $post_id): string
    {
        $stored = get_post_meta($post_id, '_ap_location', true);

        if (is_array($stored)) {
            $address = isset($stored['address']) ? (string) $stored['address'] : '';

            return sanitize_text_field($address);
        }

        return sanitize_text_field((string) $stored);
    }

    private function attachment_accessible(WP_Post $attachment, int $post_id): bool
    {
        $user_id = get_current_user_id();

        if ((int) $attachment->post_author === $user_id) {
            return true;
        }

        if ((int) $attachment->post_parent === $post_id) {
            return true;
        }

        return current_user_can('edit_post', $attachment->ID);
    }

    private function send_rate_limit_headers(WP_Error $error): void
    {
        $data    = (array) $error->get_error_data();
        $headers = isset($data['headers']) && is_array($data['headers']) ? $data['headers'] : [];

        $server = rest_get_server();
        if ($server instanceof WP_REST_Server) {
            foreach ($headers as $name => $value) {
                if ('' === $name) {
                    continue;
                }

                $server->send_header($name, (string) $value);
            }

            return;
        }

        foreach ($headers as $name => $value) {
            if ('' === $name) {
                continue;
            }

            header(trim($name) . ': ' . trim((string) $value));
        }
    }
}
