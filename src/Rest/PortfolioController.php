<?php

namespace ArtPulse\Rest;

use ArtPulse\Core\AuditLogger;
use ArtPulse\Core\ImageTools;
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

        $tmp_name = $file['tmp_name'] ?? '';
        $name     = $file['name'] ?? '';

        $check = wp_check_filetype_and_ext($tmp_name, $name);
        if (!in_array($check['type'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return new WP_Error('invalid_mime', __('Only images are allowed', 'artpulse-management'), ['status' => 415]);
        }

        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($size > 10 * MB_IN_BYTES) {
            return new WP_Error('too_large', __('Images must be smaller than 10MB', 'artpulse-management'), ['status' => 413]);
        }

        $attachment_id = media_handle_sideload($file, (int) $request['id']);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        wp_update_post([
            'ID'          => $attachment_id,
            'post_parent' => (int) $request['id'],
        ]);

        return [
            'attachment' => self::format_image((int) $attachment_id),
        ];
    }

    public static function reorder_media(WP_REST_Request $request)
    {
        $post_id = (int) $request['id'];
        $body    = $request->get_json_params();

        $order = array_map('intval', (array) ($body['gallery_ids'] ?? []));
        update_post_meta($post_id, '_ap_gallery_ids', $order);

        if (!empty($body['featured_id'])) {
            set_post_thumbnail($post_id, (int) $body['featured_id']);
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
}
