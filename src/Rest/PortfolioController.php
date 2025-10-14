<?php

namespace ArtPulse\Rest;

use ArtPulse\Core\AuditLogger;
use ArtPulse\Core\ImageTools;
use ArtPulse\Frontend\Shared\PortfolioWidgetRegistry;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * REST endpoints for retrieving and updating artist/organization portfolios.
 */
final class PortfolioController
{
    public static function register(): void
    {
        register_rest_route('artpulse/v1', '/portfolio/(?P<type>org|artist)/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [self::class, 'get_portfolio'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [self::class, 'update_portfolio'],
                'permission_callback' => [Guards::class, 'own_portfolio_only'],
            ],
        ]);

        register_rest_route('artpulse/v1', '/portfolio/(?P<type>org|artist)/(?P<id>\d+)/media', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [self::class, 'upload_media'],
                'permission_callback' => [Guards::class, 'own_portfolio_only'],
                'args'                => [
                    'file' => [
                        'required' => true,
                    ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [self::class, 'reorder_media'],
                'permission_callback' => [Guards::class, 'own_portfolio_only'],
            ],
        ]);
    }

    public static function get_portfolio(WP_REST_Request $request)
    {
        $post_id = (int) $request['id'];
        $post    = get_post($post_id);

        if (!$post) {
            return new WP_Error('not_found', __('Portfolio not found', 'artpulse-management'), ['status' => 404]);
        }

        $meta = [
            'tagline'  => get_post_meta($post_id, '_ap_tagline', true),
            'about'    => wp_kses_post(get_post_meta($post_id, '_ap_about', true)),
            'website'  => esc_url_raw(get_post_meta($post_id, '_ap_website', true)),
            'socials'  => (array) get_post_meta($post_id, '_ap_socials', true),
            'location' => (array) get_post_meta($post_id, '_ap_location', true),
        ];

        $media = [
            'logo'        => self::format_image((int) get_post_meta($post_id, '_ap_logo_id', true)),
            'cover'       => self::format_image((int) get_post_meta($post_id, '_ap_cover_id', true), 'large'),
            'gallery'     => array_values(
                array_filter(
                    array_map(
                        static fn($attachment_id) => self::format_image((int) $attachment_id),
                        (array) get_post_meta($post_id, '_ap_gallery_ids', true)
                    )
                )
            ),
            'featured_id' => (int) get_post_thumbnail_id($post_id),
        ];

        return [
            'id'    => $post_id,
            'title' => get_the_title($post_id),
            'meta'  => $meta,
            'media' => $media,
            'widgets' => PortfolioWidgetRegistry::for_post($post_id),
        ];
    }

    public static function update_portfolio(WP_REST_Request $request)
    {
        $post_id = (int) $request['id'];
        $body    = $request->get_json_params();

        update_post_meta($post_id, '_ap_tagline', sanitize_text_field($body['tagline'] ?? ''));
        update_post_meta($post_id, '_ap_about', wp_kses_post($body['about'] ?? ''));
        update_post_meta($post_id, '_ap_website', esc_url_raw($body['website'] ?? ''));
        update_post_meta(
            $post_id,
            '_ap_socials',
            array_map('sanitize_text_field', (array) ($body['socials'] ?? []))
        );

        $location = $body['location'] ?? [];
        update_post_meta(
            $post_id,
            '_ap_location',
            [
                'lat'     => isset($location['lat']) ? (float) $location['lat'] : 0.0,
                'lng'     => isset($location['lng']) ? (float) $location['lng'] : 0.0,
                'address' => isset($location['address']) ? sanitize_text_field($location['address']) : '',
            ]
        );

        foreach ([
            'logo_id'  => '_ap_logo_id',
            'cover_id' => '_ap_cover_id',
        ] as $field => $meta_key) {
            if (array_key_exists($field, $body)) {
                self::replace_feature_media($post_id, (int) $body[$field], $meta_key);
            }
        }

        if (array_key_exists('widgets', $body) && is_array($body['widgets'])) {
            PortfolioWidgetRegistry::save($post_id, $body['widgets']);
        }

        AuditLogger::info('portfolio.update', [
            'post_id' => $post_id,
            'user_id' => get_current_user_id(),
        ]);

        return self::get_portfolio($request);
    }

    public static function upload_media(WP_REST_Request $request)
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $files = $request->get_file_params();
        $file  = $files['file'] ?? ($_FILES['file'] ?? null); // phpcs:ignore WordPress.VIP.SuperGlobalInputUsage.AccessDetected

        if (!$file) {
            return new WP_Error('bad_request', __('No file provided', 'artpulse-management'), ['status' => 400]);
        }

        $post_id = (int) $request['id'];

        $validation = self::validate_image_file($file);
        if ($validation instanceof WP_Error) {
            return $validation;
        }

        $attachment_id = media_handle_sideload($file, $post_id);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        wp_update_post([
            'ID'          => $attachment_id,
            'post_parent' => $post_id,
        ]);

        return [
            'attachment' => self::format_image((int) $attachment_id),
        ];
    }

    public static function reorder_media(WP_REST_Request $request)
    {
        $post_id = (int) $request['id'];
        $body    = $request->get_json_params();

        $order = self::filter_portfolio_attachments((array) ($body['gallery_ids'] ?? []), $post_id);
        update_post_meta($post_id, '_ap_gallery_ids', $order);

        if (!empty($body['featured_id'])) {
            $featured = (int) $body['featured_id'];
            $allowed  = $order;
            $cover_id = (int) get_post_meta($post_id, '_ap_cover_id', true);
            if ($cover_id) {
                $allowed[] = $cover_id;
            }

            if (in_array($featured, $allowed, true)) {
                set_post_thumbnail($post_id, $featured);
            }
        }

        AuditLogger::info('portfolio.media.reorder', [
            'post_id' => $post_id,
            'user_id' => get_current_user_id(),
        ]);

        return self::get_portfolio($request);
    }

    private static function format_image(int $attachment_id, string $preferred_size = 'ap-grid'): ?array
    {
        if ($attachment_id <= 0) {
            return null;
        }

        $best = ImageTools::best_image_src($attachment_id, $preferred_size);
        if (!$best) {
            return null;
        }

        $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

        return [
            'id'     => $attachment_id,
            'url'    => $best['src'],
            'size'   => $best['size'],
            'width'  => $best['width'],
            'height' => $best['height'],
            'alt'    => sanitize_text_field($alt),
        ];
    }

    private static function validate_image_file(array $file)
    {
        $tmp_name = $file['tmp_name'] ?? '';
        $name     = $file['name'] ?? '';

        if (!$tmp_name || !file_exists($tmp_name)) {
            return new WP_Error('invalid_file', __('Uploaded file could not be processed.', 'artpulse-management'), ['status' => 400]);
        }

        $check = wp_check_filetype_and_ext($tmp_name, $name);
        $type  = $check['type'] ?? '';
        if (!in_array($type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return new WP_Error('invalid_mime', __('Only images are allowed', 'artpulse-management'), ['status' => 415]);
        }

        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($size > 10 * MB_IN_BYTES) {
            return new WP_Error('too_large', __('Images must be smaller than 10MB', 'artpulse-management'), ['status' => 413]);
        }

        $dimensions = @getimagesize($tmp_name); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if (!is_array($dimensions) || count($dimensions) < 2) {
            return new WP_Error('invalid_image', __('Unable to determine image dimensions.', 'artpulse-management'), ['status' => 415]);
        }

        if ($dimensions[0] < 200 || $dimensions[1] < 200) {
            return new WP_Error(
                'image_too_small',
                __('Images must be at least 200×200 pixels.', 'artpulse-management'),
                ['status' => 415]
            );
        }

        if ('image/jpeg' === $type) {
            $channels = isset($dimensions['channels']) ? (int) $dimensions['channels'] : 0;
            if ($channels >= 4) {
                return new WP_Error(
                    'image_cmyk',
                    __('CMYK JPEGs are not supported. Please upload an RGB image.', 'artpulse-management'),
                    ['status' => 415]
                );
            }
        }

        return null;
    }

    private static function filter_portfolio_attachments(array $attachments, int $post_id): array
    {
        $filtered = [];

        foreach ($attachments as $attachment_id) {
            $attachment_id = (int) $attachment_id;
            if ($attachment_id <= 0) {
                continue;
            }

            $attachment = get_post($attachment_id);
            if (!$attachment || 'attachment' !== $attachment->post_type) {
                continue;
            }

            $parent = (int) $attachment->post_parent;
            if ($parent === $post_id || $parent === 0) {
                if (0 === $parent) {
                    wp_update_post([
                        'ID'          => $attachment_id,
                        'post_parent' => $post_id,
                    ]);
                }

                $filtered[] = $attachment_id;
            }
        }

        return array_values(array_unique($filtered));
    }

    private static function replace_feature_media(int $post_id, int $attachment_id, string $meta_key): void
    {
        $old_id = (int) get_post_meta($post_id, $meta_key, true);

        if ($attachment_id <= 0) {
            delete_post_meta($post_id, $meta_key);
            return;
        }

        $attachment = get_post($attachment_id);
        if (!$attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type) {
            return;
        }

        if ((int) $attachment->post_parent !== $post_id) {
            wp_update_post([
                'ID'          => $attachment_id,
                'post_parent' => $post_id,
            ]);
        }

        if ($old_id && $old_id !== $attachment_id) {
            delete_post_meta($post_id, $meta_key);
        }

        update_post_meta($post_id, $meta_key, $attachment_id);
    }
}
