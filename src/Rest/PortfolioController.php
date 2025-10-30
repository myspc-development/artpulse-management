<?php

namespace ArtPulse\Rest;

use ArtPulse\Core\AuditLogger;
use ArtPulse\Core\ImageTools;
use ArtPulse\Frontend\Shared\FormRateLimiter;
use ArtPulse\Frontend\Shared\PortfolioWidgetRegistry;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;
use function absint;
use function esc_url_raw;
use function is_email;
use function sanitize_email;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function wp_http_validate_url;

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
        $post    = get_post($post_id);

        if (!$post instanceof \WP_Post) {
            return new WP_Error('not_found', __('Portfolio not found', 'artpulse-management'), ['status' => 404]);
        }

        if (!in_array($post->post_type, ['artpulse_org', 'artpulse_artist'], true)) {
            return new WP_Error('invalid_portfolio', __('Unsupported portfolio type.', 'artpulse-management'), ['status' => 400]);
        }

        $rate_error = FormRateLimiter::enforce(get_current_user_id(), 'builder_write', 30, 60);
        if ($rate_error instanceof WP_Error) {
            return self::prepare_rate_limit_error($rate_error);
        }

        $validated = self::validate_payload($request, $post);
        if ($validated instanceof WP_Error) {
            return $validated;
        }

        self::persist_portfolio_update($post, $validated);

        AuditLogger::info('portfolio.update', [
            'post_id' => $post_id,
            'user_id' => get_current_user_id(),
        ]);

        return self::get_portfolio($request);
    }

    /**
     * Normalize and validate a portfolio payload.
     */
    private static function validate_payload(WP_REST_Request $request, \WP_Post $post)
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }

        $errors        = [];
        $meta_updates  = [];
        $post_updates  = [];
        $data          = [];
        $post_id       = (int) $post->ID;
        $current_gallery = self::filter_portfolio_attachments((array) get_post_meta($post_id, '_ap_gallery_ids', true), $post_id);
        $sanitized_gallery = null;
        $gallery_order    = [];

        if (array_key_exists('title', $body)) {
            $title = sanitize_text_field((string) $body['title']);
            if ('' === $title) {
                $errors['title'] = __('Title cannot be empty.', 'artpulse-management');
            } else {
                $post_updates['post_title'] = $title;
            }
        }

        if (array_key_exists('tagline', $body)) {
            $tagline = sanitize_text_field((string) $body['tagline']);
            if (self::string_length($tagline) > 160) {
                $errors['tagline'] = __('Tagline must be 160 characters or fewer.', 'artpulse-management');
            } else {
                $meta_updates['_ap_tagline'] = $tagline;
            }
        }

        if (array_key_exists('about', $body)) {
            $meta_updates['_ap_about'] = wp_kses_post((string) $body['about']);
        }

        if (array_key_exists('website', $body)) {
            $website = trim((string) $body['website']);
            if ('' !== $website && !wp_http_validate_url($website)) {
                $errors['website'] = __('Enter a valid website URL (including https://).', 'artpulse-management');
            } else {
                $meta_updates['_ap_website'] = esc_url_raw($website);
            }
        }

        if (array_key_exists('phone', $body)) {
            $phone = sanitize_text_field((string) $body['phone']);
            if (self::string_length($phone) > 40) {
                $errors['phone'] = __('Phone numbers must be 40 characters or fewer.', 'artpulse-management');
            } else {
                $meta_updates['_ap_phone'] = $phone;
            }
        }

        if (array_key_exists('email', $body)) {
            $email = sanitize_email((string) $body['email']);
            if ('' !== $email && !is_email($email)) {
                $errors['email'] = __('Enter a valid email address or leave the field blank.', 'artpulse-management');
            } else {
                $meta_updates['_ap_email'] = $email;
            }
        }

        if (array_key_exists('address', $body)) {
            $meta_updates['_ap_address'] = sanitize_textarea_field((string) $body['address']);
        }

        if (array_key_exists('socials', $body)) {
            $social_input = $body['socials'];
            if (is_string($social_input)) {
                $social_input = preg_split('/\r?\n/', $social_input) ?: [];
            }

            $socials = [];
            if (is_array($social_input)) {
                foreach ($social_input as $social) {
                    $social = trim((string) $social);
                    if ('' === $social) {
                        continue;
                    }

                    $url = esc_url_raw($social);
                    if ('' === $url || !wp_http_validate_url($url)) {
                        $errors['socials'] = __('Enter full URLs (including https://) for your social links.', 'artpulse-management');
                        break;
                    }

                    $socials[] = $url;
                }
            }

            if (!isset($errors['socials'])) {
                $meta_updates['_ap_socials'] = implode("\n", $socials);
            }
        }

        if (array_key_exists('location', $body)) {
            $location = (array) $body['location'];
            $lat      = isset($location['lat']) ? (float) $location['lat'] : 0.0;
            $lng      = isset($location['lng']) ? (float) $location['lng'] : 0.0;
            $address  = isset($location['address']) ? sanitize_text_field((string) $location['address']) : '';

            if (0.0 !== $lat || 0.0 !== $lng || '' !== $address) {
                if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                    $errors['location'] = __('Location coordinates are outside the supported range.', 'artpulse-management');
                } else {
                    $meta_updates['_ap_location'] = [
                        'lat'     => $lat,
                        'lng'     => $lng,
                        'address' => $address,
                    ];
                }
            } else {
                $meta_updates['_ap_location'] = [];
            }
        }

        if (array_key_exists('visibility', $body)) {
            $visibility = sanitize_key((string) $body['visibility']);
            if ('' === $visibility) {
                $data['visibility'] = '';
            } elseif (!in_array($visibility, ['public', 'private'], true)) {
                $errors['visibility'] = __('Choose a valid visibility option.', 'artpulse-management');
            } else {
                $data['visibility'] = $visibility;
            }
        }

        if (array_key_exists('gallery_ids', $body)) {
            $sanitized_gallery = self::filter_portfolio_attachments((array) $body['gallery_ids'], $post_id);
        }

        if (array_key_exists('gallery_order', $body) && is_array($body['gallery_order'])) {
            foreach ($body['gallery_order'] as $attachment_id => $position) {
                $attachment_id = absint($attachment_id);
                $position      = absint($position);
                if ($attachment_id > 0 && $position > 0) {
                    $gallery_order[$attachment_id] = $position;
                }
            }
        }

        if (null !== $sanitized_gallery) {
            if (!empty($gallery_order)) {
                $sanitized_gallery = self::apply_gallery_order($sanitized_gallery, $gallery_order);
            }
            $data['gallery_ids'] = $sanitized_gallery;
        }

        $allowed_featured = $sanitized_gallery ?? $current_gallery;
        $cover_id         = (int) get_post_meta($post_id, '_ap_cover_id', true);
        if ($cover_id) {
            $allowed_featured[] = $cover_id;
        }
        $allowed_featured = array_values(array_unique(array_filter(array_map('intval', $allowed_featured))));

        if (array_key_exists('featured_id', $body)) {
            $featured_id = absint($body['featured_id']);
            if ($featured_id > 0 && !in_array($featured_id, $allowed_featured, true)) {
                $errors['featured'] = __('Choose a featured image from your gallery or cover.', 'artpulse-management');
            } else {
                $data['featured_id'] = $featured_id;
            }
        }

        if (array_key_exists('widgets', $body)) {
            $data['widgets'] = is_array($body['widgets']) ? $body['widgets'] : [];
        }

        if (!empty($errors)) {
            return new WP_Error(
                'invalid_portfolio_payload',
                __('Some fields need attention before we can save your changes.', 'artpulse-management'),
                [
                    'status' => 422,
                    'errors' => $errors,
                ]
            );
        }

        $data['meta'] = $meta_updates;
        $data['post'] = $post_updates;

        return $data;
    }

    /**
     * Persist sanitized portfolio data.
     *
     * @param array<string, mixed> $data
     */
    private static function persist_portfolio_update(\WP_Post $post, array $data): void
    {
        $post_id = (int) $post->ID;

        if (!empty($data['post'])) {
            $payload = array_merge(['ID' => $post_id], $data['post']);
            wp_update_post($payload);
        }

        if (!empty($data['meta']) && is_array($data['meta'])) {
            foreach ($data['meta'] as $meta_key => $value) {
                if (is_array($value)) {
                    $filtered = array_filter($value, static function ($item) {
                        return $item !== '' && null !== $item && 0.0 !== $item;
                    });
                    if (empty($filtered)) {
                        delete_post_meta($post_id, $meta_key);
                        continue;
                    }
                }

                if (is_string($value)) {
                    $value = trim($value);
                }

                if ('' === $value || (is_array($value) && empty($value))) {
                    delete_post_meta($post_id, $meta_key);
                } else {
                    update_post_meta($post_id, $meta_key, $value);
                }
            }
        }

        if (array_key_exists('visibility', $data)) {
            $visibility = (string) $data['visibility'];
            if ('' === $visibility) {
                delete_post_meta($post_id, 'portfolio_visibility');
            } else {
                update_post_meta($post_id, 'portfolio_visibility', $visibility);
            }
        }

        if (array_key_exists('gallery_ids', $data)) {
            $gallery_ids = array_map('intval', (array) $data['gallery_ids']);
            update_post_meta($post_id, '_ap_gallery_ids', $gallery_ids);
        }

        if (array_key_exists('featured_id', $data)) {
            $featured_id = (int) $data['featured_id'];
            if ($featured_id > 0) {
                set_post_thumbnail($post_id, $featured_id);
            } else {
                delete_post_thumbnail($post_id);
            }
        }

        if (array_key_exists('widgets', $data)) {
            PortfolioWidgetRegistry::save($post_id, is_array($data['widgets']) ? $data['widgets'] : []);
        }
    }

    /**
     * Normalize rate limit errors for REST responses.
     */
    private static function prepare_rate_limit_error(WP_Error $error): WP_Error
    {
        $data = (array) $error->get_error_data();

        if (!empty($data['headers']) && is_array($data['headers'])) {
            foreach ($data['headers'] as $name => $value) {
                header(trim((string) $name) . ': ' . trim((string) $value));
            }
        }

        $payload = [
            'status'      => isset($data['status']) ? (int) $data['status'] : 429,
            'retry_after' => isset($data['retry_after']) ? (int) $data['retry_after'] : null,
            'limit'       => isset($data['limit']) ? (int) $data['limit'] : null,
            'reset'       => isset($data['reset']) ? (int) $data['reset'] : null,
        ];

        return new WP_Error($error->get_error_code(), $error->get_error_message(), $payload);
    }

    /**
     * Apply display order to a gallery collection.
     *
     * @param int[] $gallery_ids
     * @param array<int, int> $order
     *
     * @return int[]
     */
    private static function apply_gallery_order(array $gallery_ids, array $order): array
    {
        if (empty($gallery_ids)) {
            return $gallery_ids;
        }

        usort($gallery_ids, static function ($a, $b) use ($order) {
            $position_a = $order[$a] ?? PHP_INT_MAX;
            $position_b = $order[$b] ?? PHP_INT_MAX;

            if ($position_a === $position_b) {
                return $a <=> $b;
            }

            return $position_a <=> $position_b;
        });

        return $gallery_ids;
    }

    private static function string_length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
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
        if (!is_array($file) || empty($file)) {
            return new WP_Error(
                'invalid_file',
                __('Uploaded file could not be processed.', 'artpulse-management'),
                ['status' => 400]
            );
        }

        $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_OK;
        if (UPLOAD_ERR_OK !== $error) {
            switch ($error) {
                case UPLOAD_ERR_NO_FILE:
                    return new WP_Error(
                        'bad_request',
                        __('No file provided', 'artpulse-management'),
                        ['status' => 400]
                    );
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    return new WP_Error(
                        'too_large',
                        __('Images must be smaller than 10MB', 'artpulse-management'),
                        ['status' => 413]
                    );
                default:
                    return new WP_Error(
                        'upload_error',
                        __('The upload could not be completed. Please try again.', 'artpulse-management'),
                        ['status' => 500]
                    );
            }
        }

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
