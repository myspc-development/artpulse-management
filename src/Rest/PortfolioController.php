<?php

namespace ArtPulse\Rest;

use ArtPulse\Core\ProfileProgress;
use ArtPulse\Frontend\ProfileBuilderConfig;
use ArtPulse\Frontend\Shared\FormRateLimiter;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Server;
use function ArtPulse\Core\get_page_url;
use function __;
use function absint;
use function array_filter;
use function array_map;
use function array_values;
use function array_unique;
use function current_user_can;
use function esc_html__;
use function esc_url_raw;
use function get_current_user_id;
use function get_post;
use function get_post_meta;
use function get_posts;
use function get_permalink;
use function header;
use function in_array;
use function is_array;
use function preg_split;
use function set_post_thumbnail;
use function trim;
use function update_post_meta;
use function wp_http_validate_url;
use function wp_kses_post;
use function wp_strip_all_tags;
use function wp_update_post;
use function delete_post_meta;
use function delete_post_thumbnail;
use function sanitize_key;
use function sanitize_text_field;

/**
 * REST endpoints for managing artist and organization profiles.
 */
final class PortfolioController
{
    private const RATE_CONTEXT = 'builder_write';

    public static function register(): void
    {
        register_rest_route('artpulse/v1', '/portfolio/(?P<type>org|artist)/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [self::class, 'get_portfolio'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'context' => [
                        'default'           => 'view',
                        'sanitize_callback' => [self::class, 'sanitize_context'],
                        'validate_callback' => [self::class, 'validate_context'],
                    ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [self::class, 'update_portfolio'],
                'permission_callback' => '__return_true',
                'args'                => self::request_args_schema(),
            ],
        ]);
    }

    public static function sanitize_context($value): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : 'view';
        return in_array($value, ['view', 'edit'], true) ? $value : 'view';
    }

    public static function validate_context($value): bool
    {
        return in_array($value, ['view', 'edit'], true);
    }

    public static function get_portfolio(WP_REST_Request $request)
    {
        $type    = (string) $request['type'];
        $post_id = (int) $request['id'];
        $context = self::sanitize_context($request->get_param('context'));

        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return self::error('ap_not_found', esc_html__('Portfolio not found.', 'artpulse-management'), 404);
        }

        $expected_post_type = self::post_type_from_type($type);
        if ($post->post_type !== $expected_post_type) {
            return self::error('ap_not_found', esc_html__('Portfolio not found.', 'artpulse-management'), 404);
        }

        if ('edit' === $context) {
            $cap = 'artist' === $type ? 'edit_artpulse_artist' : 'edit_artpulse_org';
            if (!current_user_can('edit_post', $post_id) || !current_user_can($cap)) {
                return self::error('ap_forbidden', __('You cannot edit this profile.', 'artpulse-management'), 403);
            }
        } else {
            $visibility = (string) get_post_meta($post_id, '_ap_visibility', true);
            if ('publish' !== $post->post_status || 'public' !== $visibility) {
                return self::error('ap_not_found', esc_html__('Portfolio not found.', 'artpulse-management'), 404);
            }
        }

        return self::prepare_response($post, $type, $context);
    }

    public static function update_portfolio(WP_REST_Request $request)
    {
        $type    = (string) $request['type'];
        $post_id = (int) $request['id'];
        $post    = get_post($post_id);

        if (!$post instanceof WP_Post) {
            return self::error('ap_not_found', esc_html__('Portfolio not found.', 'artpulse-management'), 404);
        }

        $expected_post_type = self::post_type_from_type($type);
        if ($post->post_type !== $expected_post_type) {
            return self::error('ap_not_found', esc_html__('Portfolio not found.', 'artpulse-management'), 404);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return self::error('ap_forbidden', esc_html__('You must be logged in to update this profile.', 'artpulse-management'), 403);
        }

        $cap = 'artist' === $type ? 'edit_artpulse_artist' : 'edit_artpulse_org';
        if (!current_user_can('edit_post', $post_id) || !current_user_can($cap)) {
            return self::error('ap_forbidden', __('You cannot edit this profile.', 'artpulse-management'), 403);
        }

        $rate = FormRateLimiter::enforce($user_id, self::RATE_CONTEXT, 30, MINUTE_IN_SECONDS);
        if ($rate instanceof WP_Error) {
            return self::rate_limit_error($rate);
        }

        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = [];
        }

        $validation = self::validate_update_payload($payload, $type, $post_id, $user_id);
        if ($validation instanceof WP_Error) {
            return $validation;
        }

        $post_updates = $validation['post'];
        $meta_updates = $validation['meta'];

        $requested_status = strtolower(trim((string) ($payload['status'] ?? '')));
        if ('publish' === $requested_status) {
            $final_state = [
                'title'          => $post_updates['post_title'] ?? $post->post_title,
                'bio'            => $meta_updates['_ap_about'] ?? (string) get_post_meta($post_id, '_ap_about', true),
                'featured_media' => array_key_exists('featured_media', $validation)
                    ? (int) $validation['featured_media']
                    : (int) get_post_thumbnail_id($post_id),
            ];

            if (!self::meets_minimum_requirements($final_state)) {
                return self::error(
                    'ap_invalid_state',
                    esc_html__('Complete required fields before publishing.', 'artpulse-management'),
                    422
                );
            }
        }

        if (!empty($post_updates)) {
            $post_updates['ID'] = $post_id;
            $result = wp_update_post($post_updates, true);
            if ($result instanceof WP_Error) {
                return self::error('ap_invalid_param', esc_html__('Unable to update the profile.', 'artpulse-management'), 500);
            }
        }

        foreach ($meta_updates as $meta_key => $value) {
            if (null === $value) {
                delete_post_meta($post_id, $meta_key);
                continue;
            }

            update_post_meta($post_id, $meta_key, $value);
        }

        if (array_key_exists('featured_media', $validation)) {
            $featured = (int) $validation['featured_media'];
            if ($featured > 0) {
                set_post_thumbnail($post_id, $featured);
            } else {
                delete_post_thumbnail($post_id);
            }
        }

        if (array_key_exists('gallery', $validation)) {
            update_post_meta($post_id, '_ap_gallery_ids', $validation['gallery']);
        }

        $updated = get_post($post_id);
        if (!$updated instanceof WP_Post) {
            return self::error('ap_not_found', esc_html__('Portfolio not found.', 'artpulse-management'), 404);
        }

        return self::prepare_response($updated, $type, 'edit');
    }

    /**
     * Validate and normalize incoming payload values.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    private static function validate_update_payload(array $payload, string $type, int $post_id, int $user_id)
    {
        $data = [
            'post'           => [],
            'meta'           => [],
        ];

        if (array_key_exists('title', $payload)) {
            $title = (string) $payload['title'];
            $title = sanitize_text_field($title);
            if ('' === trim($title)) {
                return self::error('ap_invalid_param', esc_html__('Title cannot be empty.', 'artpulse-management'), 422, ['field' => 'title']);
            }
            if (mb_strlen($title) > 200) {
                return self::error('ap_invalid_param', esc_html__('Title must be 200 characters or fewer.', 'artpulse-management'), 422, ['field' => 'title']);
            }
            $data['post']['post_title'] = $title;
        }

        if (array_key_exists('tagline', $payload)) {
            $tagline = sanitize_text_field((string) $payload['tagline']);
            if (mb_strlen($tagline) > 160) {
                return self::error('ap_invalid_param', esc_html__('Tagline must be 160 characters or fewer.', 'artpulse-management'), 422, ['field' => 'tagline']);
            }
            $data['meta']['_ap_tagline'] = $tagline;
        }

        if (array_key_exists('bio', $payload)) {
            $bio = wp_kses_post((string) $payload['bio']);
            $data['meta']['_ap_about'] = $bio;
        }

        if (array_key_exists('website_url', $payload)) {
            $website = trim((string) $payload['website_url']);
            if ('' !== $website && !wp_http_validate_url($website)) {
                return self::error('ap_invalid_param', esc_html__('Enter a valid website URL.', 'artpulse-management'), 422, ['field' => 'website_url']);
            }
            $data['meta']['_ap_website'] = esc_url_raw($website);
        }

        if (array_key_exists('socials', $payload)) {
            $socials_raw = $payload['socials'];
            if (!is_array($socials_raw)) {
                return self::error('ap_invalid_param', esc_html__('Social links must be an array.', 'artpulse-management'), 422, ['field' => 'socials']);
            }

            $socials = [];
            foreach ($socials_raw as $url) {
                $url = trim((string) $url);
                if ('' === $url) {
                    continue;
                }
                if (!wp_http_validate_url($url)) {
                    return self::error('ap_invalid_param', esc_html__('Enter valid URLs for your social profiles.', 'artpulse-management'), 422, ['field' => 'socials']);
                }
                $socials[] = esc_url_raw($url);
            }

            $data['meta']['_ap_socials'] = implode("\n", $socials);
        }

        if (array_key_exists('visibility', $payload)) {
            $visibility = strtolower(trim((string) $payload['visibility']));
            if (!in_array($visibility, ['public', 'private'], true)) {
                return self::error('ap_invalid_param', esc_html__('Visibility must be public or private.', 'artpulse-management'), 422, ['field' => 'visibility']);
            }
            $data['meta']['_ap_visibility'] = $visibility;
        }

        if (array_key_exists('status', $payload)) {
            $status = strtolower(trim((string) $payload['status']));
            if (!in_array($status, ['draft', 'pending', 'publish'], true)) {
                return self::error('ap_invalid_param', esc_html__('Status must be draft, pending, or publish.', 'artpulse-management'), 422, ['field' => 'status']);
            }
            $data['post']['post_status'] = $status;
        }

        if (array_key_exists('featured_media', $payload)) {
            $featured = absint($payload['featured_media']);
            if ($featured > 0 && !self::user_owns_attachments([$featured], $user_id)) {
                return self::error('ap_invalid_param', esc_html__('Select an image you uploaded.', 'artpulse-management'), 422, ['field' => 'featured_media']);
            }
            $data['featured_media'] = $featured;
        }

        if (array_key_exists('gallery', $payload)) {
            if (!is_array($payload['gallery'])) {
                return self::error('ap_invalid_param', esc_html__('Gallery must be an array of media identifiers.', 'artpulse-management'), 422, ['field' => 'gallery']);
            }

            $gallery = array_values(array_filter(array_map('absint', $payload['gallery'])));
            if (!empty($gallery) && !self::user_owns_attachments($gallery, $user_id)) {
                return self::error('ap_invalid_param', esc_html__('One of the selected gallery items is not available.', 'artpulse-management'), 422, ['field' => 'gallery']);
            }

            $data['gallery'] = $gallery;
        }

        return $data;
    }

    private static function prepare_response(WP_Post $post, string $type, string $context): array
    {
        $post_id    = (int) $post->ID;
        $visibility = (string) get_post_meta($post_id, '_ap_visibility', true);
        $tagline    = (string) get_post_meta($post_id, '_ap_tagline', true);
        $bio        = (string) get_post_meta($post_id, '_ap_about', true);
        $website    = (string) get_post_meta($post_id, '_ap_website', true);
        $socials_raw = (string) get_post_meta($post_id, '_ap_socials', true);
        $socials = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $socials_raw) ?: []));
        $gallery_meta = get_post_meta($post_id, '_ap_gallery_ids', true);
        $gallery_ids  = array_values(array_filter(array_map('absint', is_array($gallery_meta) ? $gallery_meta : (array) $gallery_meta)));

        $payload = [
            'id'             => $post_id,
            'type'           => $type,
            'title'          => $post->post_title,
            'tagline'        => $tagline,
            'bio'            => $bio,
            'website_url'    => $website,
            'socials'        => $socials,
            'featured_media' => (int) get_post_thumbnail_id($post_id),
            'gallery'        => $gallery_ids,
            'visibility'     => $visibility,
            'status'         => $post->post_status,
            'dashboard_url'  => get_page_url('dashboard_page_id'),
            'public_url'     => get_permalink($post_id) ?: '',
        ];

        $config    = ProfileBuilderConfig::for($type);
        $progress  = ProfileProgress::compute($payload, $config['required_fields'], $config['steps']);
        $payload['progress'] = $progress;

        if ('view' === $context) {
            unset($payload['dashboard_url']);
        }

        if ('publish' !== $post->post_status || 'public' !== $visibility) {
            $payload['public_url'] = '';
        }

        return $payload;
    }

    private static function post_type_from_type(string $type): string
    {
        return 'org' === $type ? 'artpulse_org' : 'artpulse_artist';
    }

    private static function rate_limit_error(WP_Error $error): WP_Error
    {
        $data = $error->get_error_data();
        if (is_array($data) && isset($data['headers']) && is_array($data['headers'])) {
            foreach ($data['headers'] as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        $retry = 0;
        if (is_array($data) && isset($data['retry_after'])) {
            $retry = (int) $data['retry_after'];
        }

        return self::error('ap_rate_limited', $error->get_error_message(), 429, ['retry_after' => $retry]);
    }

    private static function user_owns_attachments(array $ids, int $uid): bool
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));
        if (!$ids) {
            return true;
        }

        $found = get_posts([
            'post_type'       => 'attachment',
            'post__in'        => $ids,
            'author'          => $uid,
            'fields'          => 'ids',
            'posts_per_page'  => count($ids),
        ]);

        return count($found) === count($ids);
    }

    private static function error(string $code, string $message, int $status, array $extra = []): WP_Error
    {
        $data = array_merge(['status' => $status], $extra);
        return new WP_Error($code, $message, $data);
    }

    public static function validate_title_arg($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return true;
        }

        $value = (string) $value;
        if ('' === trim($value)) {
            return self::error('ap_invalid_param', esc_html__('Title cannot be empty.', 'artpulse-management'), 422, ['field' => 'title']);
        }

        if (mb_strlen($value) > 200) {
            return self::error('ap_invalid_param', esc_html__('Title must be 200 characters or fewer.', 'artpulse-management'), 422, ['field' => 'title']);
        }

        return true;
    }

    public static function validate_tagline_arg($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return true;
        }

        $value = (string) $value;
        if (mb_strlen($value) > 160) {
            return self::error('ap_invalid_param', esc_html__('Tagline must be 160 characters or fewer.', 'artpulse-management'), 422, ['field' => 'tagline']);
        }

        return true;
    }

    public static function validate_website_arg($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return true;
        }

        $value = trim((string) $value);
        if ('' === $value) {
            return true;
        }

        if (!wp_http_validate_url($value)) {
            return self::error('ap_invalid_param', esc_html__('Enter a valid website URL.', 'artpulse-management'), 422, ['field' => 'website_url']);
        }

        return true;
    }

    public static function sanitize_socials_arg($value)
    {
        if (!is_array($value)) {
            return [];
        }

        return array_map(static fn($item) => sanitize_text_field((string) $item), $value);
    }

    public static function validate_socials_arg($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return true;
        }

        if (!is_array($value)) {
            return self::error('ap_invalid_param', esc_html__('Social links must be an array.', 'artpulse-management'), 422, ['field' => 'socials']);
        }

        foreach ($value as $url) {
            $url = trim((string) $url);
            if ('' === $url) {
                continue;
            }

            if (!wp_http_validate_url($url)) {
                return self::error('ap_invalid_param', esc_html__('Enter valid URLs for your social profiles.', 'artpulse-management'), 422, ['field' => 'socials']);
            }
        }

        return true;
    }

    public static function validate_featured_media_arg($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return true;
        }

        $value = (int) $value;
        if ($value < 0) {
            return self::error('ap_invalid_param', esc_html__('Select an image you uploaded.', 'artpulse-management'), 422, ['field' => 'featured_media']);
        }

        return true;
    }

    public static function sanitize_gallery_arg($value)
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map('absint', $value));
    }

    public static function validate_gallery_arg($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return true;
        }

        if (!is_array($value)) {
            return self::error('ap_invalid_param', esc_html__('Gallery must be an array of media identifiers.', 'artpulse-management'), 422, ['field' => 'gallery']);
        }

        foreach ($value as $item) {
            if ((int) $item < 0) {
                return self::error('ap_invalid_param', esc_html__('Gallery must be an array of media identifiers.', 'artpulse-management'), 422, ['field' => 'gallery']);
            }
        }

        return true;
    }

    public static function validate_visibility_arg($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return true;
        }

        $value = strtolower((string) $value);
        if (!in_array($value, ['public', 'private'], true)) {
            return self::error('ap_invalid_param', esc_html__('Visibility must be public or private.', 'artpulse-management'), 422, ['field' => 'visibility']);
        }

        return true;
    }

    public static function validate_status_arg($value, WP_REST_Request $request, string $param)
    {
        if (null === $value) {
            return true;
        }

        $value = strtolower((string) $value);
        if (!in_array($value, ['draft', 'pending', 'publish'], true)) {
            return self::error('ap_invalid_param', esc_html__('Status must be draft, pending, or publish.', 'artpulse-management'), 422, ['field' => 'status']);
        }

        return true;
    }

    /**
     * Determine if the portfolio meets minimum publish requirements.
     *
     * @param array{title?:string,bio?:string,featured_media?:int} $state
     */
    private static function meets_minimum_requirements(array $state): bool
    {
        $title = isset($state['title']) ? trim((string) $state['title']) : '';
        if ('' === $title) {
            return false;
        }

        $bio_raw = isset($state['bio']) ? (string) $state['bio'] : '';
        $bio     = trim(wp_strip_all_tags($bio_raw));
        if ('' === $bio) {
            return false;
        }

        $featured = isset($state['featured_media']) ? (int) $state['featured_media'] : 0;

        return $featured > 0;
    }

    /**
     * Schema for writable parameters.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function request_args_schema(): array
    {
        return [
            'title' => [
                'type'              => 'string',
                'minLength'         => 1,
                'maxLength'         => 200,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => [self::class, 'validate_title_arg'],
            ],
            'tagline' => [
                'type'              => 'string',
                'maxLength'         => 160,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => [self::class, 'validate_tagline_arg'],
            ],
            'bio' => [
                'type'              => 'string',
                'maxLength'         => 5000,
                'sanitize_callback' => 'wp_kses_post',
            ],
            'website_url' => [
                'type'              => 'string',
                'format'            => 'uri',
                'sanitize_callback' => 'esc_url_raw',
                'validate_callback' => [self::class, 'validate_website_arg'],
            ],
            'socials' => [
                'type'     => 'array',
                'items'    => [
                    'type'   => 'string',
                    'format' => 'uri',
                ],
                'sanitize_callback' => [self::class, 'sanitize_socials_arg'],
                'validate_callback' => [self::class, 'validate_socials_arg'],
            ],
            'featured_media' => [
                'type'     => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => [self::class, 'validate_featured_media_arg'],
            ],
            'gallery' => [
                'type'     => 'array',
                'items'    => [
                    'type' => 'integer',
                ],
                'sanitize_callback' => [self::class, 'sanitize_gallery_arg'],
                'validate_callback' => [self::class, 'validate_gallery_arg'],
            ],
            'visibility' => [
                'type'     => 'string',
                'enum'     => ['public', 'private'],
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => [self::class, 'validate_visibility_arg'],
            ],
            'status' => [
                'type'     => 'string',
                'enum'     => ['draft', 'pending', 'publish'],
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => [self::class, 'validate_status_arg'],
            ],
        ];
    }
}
