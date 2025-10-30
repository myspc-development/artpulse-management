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
use function absint;
use function array_filter;
use function array_map;
use function current_user_can;
use function esc_html__;
use function esc_url_raw;
use function get_current_user_id;
use function get_post;
use function get_post_meta;
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
use function wp_update_post;
use function delete_post_meta;
use function delete_post_thumbnail;
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
                return self::error('ap_forbidden', esc_html__('You do not have permission to edit this profile.', 'artpulse-management'), 403);
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
            return self::error('ap_forbidden', esc_html__('You do not have permission to edit this profile.', 'artpulse-management'), 403);
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
            if ($featured > 0 && !self::user_owns_attachment($featured, $user_id)) {
                return self::error('ap_invalid_param', esc_html__('Select an image you uploaded.', 'artpulse-management'), 422, ['field' => 'featured_media']);
            }
            $data['featured_media'] = $featured;
        }

        if (array_key_exists('gallery', $payload)) {
            if (!is_array($payload['gallery'])) {
                return self::error('ap_invalid_param', esc_html__('Gallery must be an array of media identifiers.', 'artpulse-management'), 422, ['field' => 'gallery']);
            }

            $gallery = [];
            foreach ($payload['gallery'] as $item) {
                $attachment_id = absint($item);
                if ($attachment_id <= 0) {
                    continue;
                }
                if (!self::user_owns_attachment($attachment_id, $user_id)) {
                    return self::error('ap_invalid_param', esc_html__('One of the selected gallery items is not available.', 'artpulse-management'), 422, ['field' => 'gallery']);
                }
                $gallery[] = $attachment_id;
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

    private static function user_owns_attachment(int $attachment_id, int $user_id): bool
    {
        if ($attachment_id <= 0) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $attachment = get_post($attachment_id);
        if (!$attachment instanceof WP_Post) {
            return false;
        }

        if ('attachment' !== $attachment->post_type) {
            return false;
        }

        return (int) $attachment->post_author === $user_id;
    }

    private static function error(string $code, string $message, int $status, array $extra = []): WP_Error
    {
        $data = array_merge(['status' => $status], $extra);
        return new WP_Error($code, $message, $data);
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
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'tagline' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'bio' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'wp_kses_post',
            ],
            'website_url' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'socials' => [
                'type'     => 'array',
                'required' => false,
                'items'    => [
                    'type' => 'string',
                ],
            ],
            'featured_media' => [
                'type'     => 'integer',
                'required' => false,
            ],
            'gallery' => [
                'type'     => 'array',
                'required' => false,
                'items'    => [
                    'type' => 'integer',
                ],
            ],
            'visibility' => [
                'type'     => 'string',
                'required' => false,
            ],
            'status' => [
                'type'     => 'string',
                'required' => false,
            ],
        ];
    }
}
