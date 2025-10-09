<?php

namespace ArtPulse\Frontend;

use ArtPulse\Core\ImageTools;
use ArtPulse\Core\UpgradeReviewRepository;
use ArtPulse\Frontend\Shared\FormRateLimiter;
use ArtPulse\Frontend\Shared\PortfolioAccess;
use WP_Error;
use WP_Post;
use WP_User;

class OrgBuilderShortcode
{
    private const ERROR_TRANSIENT_KEY = 'ap_org_builder_errors_';
    private const MAX_UPLOAD_BYTES = 10 * MB_IN_BYTES;
    private const MIN_IMAGE_DIMENSION = 200;
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public static function register(): void
    {
        add_shortcode('ap_org_builder', [self::class, 'render']);
        add_action('admin_post_ap_org_builder_save', [self::class, 'handle_save']);
        add_action('admin_post_nopriv_ap_org_builder_save', [self::class, 'handle_save']);
    }

    public static function render($atts = []): string
    {
        if (!get_option('ap_enable_org_builder', true)) {
            status_header(404);

            return '';
        }

        if (!is_user_logged_in()) {
            return wp_login_form(['echo' => false]);
        }

        $user_id = get_current_user_id();
        $org     = self::get_owned_org($user_id);

        if (!$org instanceof WP_Post) {
            $request = UpgradeReviewRepository::get_latest_for_user($user_id);
            if ($request instanceof WP_Post && UpgradeReviewRepository::STATUS_PENDING === UpgradeReviewRepository::get_status($request)) {
                return '<div class="ap-org-builder__notice ap-org-builder__notice--pending">' . esc_html__('Your upgrade request is still pending. Once approved you can build your organization profile here.', 'artpulse-management') . '</div>';
            }

            return '<div class="ap-org-builder__notice ap-org-builder__notice--missing">' . esc_html__('No approved organization profile found for your account.', 'artpulse-management') . '</div>';
        }

        if (!PortfolioAccess::can_manage_portfolio($user_id, (int) $org->ID)) {
            return '<p>' . esc_html__('You do not have permission to edit this organization.', 'artpulse-management') . '</p>';
        }

        if (!current_user_can('edit_post', $org->ID)) {
            return '<p>' . esc_html__('You do not have permission to edit this organization.', 'artpulse-management') . '</p>';
        }

        $step = isset($_GET['step']) ? sanitize_key(wp_unslash($_GET['step'])) : 'profile';
        $step = in_array($step, ['profile', 'images', 'preview', 'publish'], true) ? $step : 'profile';

        $message = '';
        $errors  = self::pull_errors($user_id);
        if (!empty($_GET['ap_builder'])) {
            $message_key = sanitize_key(wp_unslash($_GET['ap_builder']));
            if ('saved' === $message_key) {
                $message = esc_html__('Changes saved.', 'artpulse-management');
            } elseif ('published' === $message_key) {
                $message = esc_html__('Organization published successfully.', 'artpulse-management');
            } elseif ('error' === $message_key && empty($errors)) {
                $message = esc_html__('We could not save your changes. Please review the messages below.', 'artpulse-management');
            }
        }

        $meta = self::get_org_meta($org->ID);
        $preview = self::build_preview_data($org, $meta);
        $event_url = apply_filters('artpulse/org_builder/event_url', add_query_arg('org_id', $org->ID, home_url('/submit-event/')), $org->ID, $org);

        ob_start();

        wp_enqueue_style('ap-org-builder', plugins_url('assets/css/ap-org-builder.css', ARTPULSE_PLUGIN_FILE), [], ARTPULSE_VERSION);

        $org_post = $org;
        $builder_meta = $meta;
        $builder_preview = $preview;
        $builder_message = $message;
        $builder_step = $step;
        $builder_event_url = $event_url;
        $builder_errors = $errors;

        include self::get_template_path('wrapper');

        return (string) ob_get_clean();
    }

    public static function handle_save(): void
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/login/'));
            exit;
        }

        $user_id = get_current_user_id();
        $org_id  = isset($_POST['org_id']) ? absint($_POST['org_id']) : 0;
        $step    = isset($_POST['builder_step']) ? sanitize_key(wp_unslash($_POST['builder_step'])) : 'profile';

        if (!$org_id) {
            wp_safe_redirect(add_query_arg('ap_builder', 'error', wp_get_referer() ?: home_url('/dashboard/')));
            exit;
        }

        if (!check_admin_referer('ap_portfolio_update', '_ap_portfolio_nonce', false)) {
            self::remember_errors($user_id, [__('Security check failed. Please try again.', 'artpulse-management')]);
            wp_safe_redirect(add_query_arg('ap_builder', 'error', wp_get_referer() ?: home_url('/dashboard/')));
            exit;
        }

        $nonce = isset($_POST['ap_org_builder_nonce']) ? wp_unslash($_POST['ap_org_builder_nonce']) : '';
        if (!is_string($nonce) || !wp_verify_nonce($nonce, 'ap-org-builder-' . $org_id)) {
            wp_safe_redirect(add_query_arg('ap_builder', 'error', wp_get_referer() ?: home_url('/dashboard/')));
            exit;
        }

        $redirect = add_query_arg([
            'step' => $step,
        ], wp_get_referer() ?: add_query_arg(['step' => $step], home_url('/dashboard/')));

        $rate_error = FormRateLimiter::enforce('portfolio', $user_id);
        if ($rate_error instanceof WP_Error) {
            $data = $rate_error->get_error_data();
            if (is_array($data)) {
                if (isset($data['retry_after'])) {
                    header('Retry-After: ' . (int) $data['retry_after']);
                }
                if (isset($data['limit'])) {
                    header('X-RateLimit-Limit: ' . (int) $data['limit']);
                }
                header('X-RateLimit-Remaining: 0');
                if (isset($data['reset'])) {
                    header('X-RateLimit-Reset: ' . (int) $data['reset']);
                }
            }
            self::remember_errors($user_id, [$rate_error->get_error_message()]);
            wp_safe_redirect(add_query_arg('ap_builder', 'error', $redirect));
            exit;
        }

        $org = get_post($org_id);
        if (!$org instanceof WP_Post) {
            wp_safe_redirect(add_query_arg('ap_builder', 'error', $redirect));
            exit;
        }

        if (!PortfolioAccess::can_manage_portfolio($user_id, $org_id)) {
            self::remember_errors($user_id, [__('You do not have permission to update this organization.', 'artpulse-management')]);
            wp_safe_redirect(add_query_arg('ap_builder', 'error', $redirect));
            exit;
        }

        if (!self::user_can_manage_org($org)) {
            self::remember_errors($user_id, [__('You do not have permission to update this organization.', 'artpulse-management')]);
            wp_safe_redirect(add_query_arg('ap_builder', 'error', $redirect));
            exit;
        }

        $errors = [];

        if ('profile' === $step) {
            self::save_profile($org_id);
            $status = 'saved';
        } elseif ('images' === $step) {
            if (!current_user_can('upload_files')) {
                $errors[] = __('You do not have permission to upload images.', 'artpulse-management');
            } else {
                $errors = self::save_images($org_id);
            }
            $status = empty($errors) ? 'saved' : 'error';
        } elseif ('publish' === $step) {
            if (!current_user_can('publish_post', $org_id)) {
                $errors[] = __('You do not have permission to publish this organization.', 'artpulse-management');
                $status = 'error';
            } else {
                self::publish_org($org_id);
                $status = 'published';
            }
        } else {
            $status = 'saved';
        }

        if (!empty($errors)) {
            self::remember_errors($user_id, $errors);
        }

        wp_safe_redirect(add_query_arg('ap_builder', $status, $redirect));
        exit;
    }

    private static function save_profile(int $org_id): void
    {
        $fields = [
            '_ap_tagline'   => sanitize_text_field(wp_unslash($_POST['ap_tagline'] ?? '')),
            '_ap_about'     => wp_kses_post(wp_unslash($_POST['ap_about'] ?? '')),
            '_ap_website'   => esc_url_raw(wp_unslash($_POST['ap_website'] ?? '')),
            '_ap_socials'   => sanitize_textarea_field(wp_unslash($_POST['ap_socials'] ?? '')),
            '_ap_phone'     => sanitize_text_field(wp_unslash($_POST['ap_phone'] ?? '')),
            '_ap_email'     => sanitize_email(wp_unslash($_POST['ap_email'] ?? '')),
            '_ap_address'   => sanitize_textarea_field(wp_unslash($_POST['ap_address'] ?? '')),
        ];

        foreach ($fields as $key => $value) {
            if ($value === '') {
                delete_post_meta($org_id, $key);
            } else {
                update_post_meta($org_id, $key, $value);
            }
        }
    }

    private static function save_images(int $org_id): array
    {
        self::ensure_media_dependencies();

        $errors = [];
        $logo_id  = (int) get_post_meta($org_id, '_ap_logo_id', true);
        $cover_id = (int) get_post_meta($org_id, '_ap_cover_id', true);

        if ($logo_id) {
            self::assign_attachment_to_portfolio($logo_id, $org_id);
        }

        if ($cover_id) {
            self::assign_attachment_to_portfolio($cover_id, $org_id);
        }

        if (!empty($_FILES['ap_logo']['name'])) {
            $logo_file = self::prepare_file_array($_FILES['ap_logo']);
            $validation = self::validate_image_upload($logo_file, __('Logo', 'artpulse-management'));
            if ($validation) {
                $errors[] = $validation;
            } else {
                $upload = self::process_upload_field('ap_logo', $org_id);
                if ($upload instanceof WP_Error) {
                    $errors[] = $upload->get_error_message();
                } else {
                    $logo_id = (int) $upload;
                    if (self::assign_attachment_to_portfolio($logo_id, $org_id)) {
                        update_post_meta($org_id, '_ap_logo_id', $logo_id);
                    }
                }
            }
        }

        if (!empty($_FILES['ap_cover']['name'])) {
            $cover_file = self::prepare_file_array($_FILES['ap_cover']);
            $validation = self::validate_image_upload($cover_file, __('Cover image', 'artpulse-management'));
            if ($validation) {
                $errors[] = $validation;
            } else {
                $upload = self::process_upload_field('ap_cover', $org_id);
                if ($upload instanceof WP_Error) {
                    $errors[] = $upload->get_error_message();
                } else {
                    $cover_id = (int) $upload;
                    if (self::assign_attachment_to_portfolio($cover_id, $org_id)) {
                        update_post_meta($org_id, '_ap_cover_id', $cover_id);
                    }
                }
            }
        }

        $gallery_ids = isset($_POST['existing_gallery_ids'])
            ? self::filter_owned_attachments((array) wp_unslash($_POST['existing_gallery_ids']), $org_id)
            : [];

        if (!empty($_FILES['ap_gallery']['name'][0])) {
            $files = self::normalize_files_array($_FILES['ap_gallery']);
            foreach ($files as $details) {
                $validation = self::validate_image_upload($details, __('Gallery image', 'artpulse-management'));
                if ($validation) {
                    $errors[] = $validation;
                    continue;
                }

                $_FILES['ap_single_gallery'] = $details;
                $upload = self::process_upload_field('ap_single_gallery', $org_id);

                if ($upload instanceof WP_Error) {
                    $errors[] = $upload->get_error_message();
                    continue;
                }

                if (self::assign_attachment_to_portfolio((int) $upload, $org_id)) {
                    $gallery_ids[] = (int) $upload;
                }
            }

            unset($_FILES['ap_single_gallery']);
        }

        $gallery_ids = array_values(array_filter(array_unique(self::filter_owned_attachments($gallery_ids, $org_id))));

        $order_input = isset($_POST['gallery_order']) ? (array) wp_unslash($_POST['gallery_order']) : [];
        $order = [];
        foreach ($order_input as $attachment_id => $position) {
            $attachment_id = absint($attachment_id);
            if ($attachment_id <= 0) {
                continue;
            }

            $order[$attachment_id] = absint($position);
        }

        if (!empty($order)) {
            $gallery_ids = self::apply_gallery_order($gallery_ids, $order);
        }

        update_post_meta($org_id, '_ap_gallery_ids', $gallery_ids);

        $eligible_featured = $gallery_ids;
        if ($cover_id) {
            $eligible_featured[] = $cover_id;
        }
        $eligible_featured = array_values(array_unique(array_filter($eligible_featured)));

        $featured = isset($_POST['ap_featured_image']) ? absint(wp_unslash($_POST['ap_featured_image'])) : 0;
        $current_featured = (int) get_post_thumbnail_id($org_id);

        if ($featured > 0 && in_array($featured, $eligible_featured, true)) {
            set_post_thumbnail($org_id, $featured);
        } elseif (!in_array($current_featured, $eligible_featured, true)) {
            if (!empty($eligible_featured)) {
                set_post_thumbnail($org_id, (int) $eligible_featured[0]);
            } else {
                delete_post_thumbnail($org_id);
            }
        }

        return $errors;
    }

    private static function publish_org(int $org_id): void
    {
        $post = get_post($org_id);
        if ($post instanceof WP_Post && 'publish' !== $post->post_status) {
            wp_update_post([
                'ID'          => $org_id,
                'post_status' => 'publish',
            ]);
        }
    }

    private static function get_org_meta(int $org_id): array
    {
        return [
            'tagline' => get_post_meta($org_id, '_ap_tagline', true),
            'about'   => get_post_meta($org_id, '_ap_about', true),
            'website' => get_post_meta($org_id, '_ap_website', true),
            'socials' => get_post_meta($org_id, '_ap_socials', true),
            'phone'   => get_post_meta($org_id, '_ap_phone', true),
            'email'   => get_post_meta($org_id, '_ap_email', true),
            'address' => get_post_meta($org_id, '_ap_address', true),
            'logo_id' => (int) get_post_meta($org_id, '_ap_logo_id', true),
            'cover_id'=> (int) get_post_meta($org_id, '_ap_cover_id', true),
            'gallery_ids' => array_values(array_filter(array_map('absint', (array) get_post_meta($org_id, '_ap_gallery_ids', true)))),
            'featured_id' => (int) get_post_thumbnail_id($org_id),
        ];
    }

    private static function get_owned_org(int $user_id): ?WP_Post
    {
        $posts = get_posts([
            'post_type'      => 'artpulse_org',
            'post_status'    => ['publish', 'draft', 'pending'],
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_ap_owner_user',
                    'value' => $user_id,
                ],
            ],
        ]);

        if (!empty($posts) && $posts[0] instanceof WP_Post) {
            return $posts[0];
        }

        $posts = get_posts([
            'post_type'      => 'artpulse_org',
            'post_status'    => ['publish', 'draft', 'pending'],
            'posts_per_page' => 1,
            'author'         => $user_id,
        ]);

        if (!empty($posts) && $posts[0] instanceof WP_Post) {
            return $posts[0];
        }

        return null;
    }

    private static function normalize_files_array(array $files): array
    {
        $normalized = [];
        $names = isset($files['name']) ? (array) wp_unslash($files['name']) : [];

        foreach ($names as $index => $name) {
            if ('' === trim((string) $name)) {
                continue;
            }

            $normalized[] = self::prepare_file_array([
                'name'     => $name,
                'type'     => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error'    => $files['error'][$index] ?? 0,
                'size'     => $files['size'][$index] ?? 0,
            ]);
        }

        return $normalized;
    }

    private static function filter_owned_attachments(array $attachments, int $post_id): array
    {
        $filtered = [];

        foreach ($attachments as $attachment_id) {
            $attachment_id = (int) $attachment_id;
            if ($attachment_id <= 0) {
                continue;
            }

            if (self::assign_attachment_to_portfolio($attachment_id, $post_id)) {
                $filtered[] = $attachment_id;
            }
        }

        return array_values(array_unique($filtered));
    }

    private static function get_template_path(string $view): string
    {
        $base = trailingslashit(ARTPULSE_PLUGIN_DIR) . 'templates/org-builder/' . $view . '.php';
        if (file_exists($base)) {
            return $base;
        }

        return $base;
    }

    private static function prepare_file_array(array $file): array
    {
        return [
            'name'     => isset($file['name']) ? (string) $file['name'] : '',
            'type'     => isset($file['type']) ? (string) $file['type'] : '',
            'tmp_name' => isset($file['tmp_name']) ? (string) $file['tmp_name'] : '',
            'error'    => isset($file['error']) ? (int) $file['error'] : 0,
            'size'     => isset($file['size']) ? (int) $file['size'] : 0,
        ];
    }

    private static function ensure_media_dependencies(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $loaded = true;
    }

    private static function process_upload_field(string $field, int $post_id)
    {
        $result = media_handle_upload($field, $post_id);

        if (is_wp_error($result)) {
            return new WP_Error($result->get_error_code(), $result->get_error_message());
        }

        $attachment_id = (int) $result;
        self::assign_attachment_to_portfolio($attachment_id, $post_id);

        return $attachment_id;
    }

    private static function assign_attachment_to_portfolio(int $attachment_id, int $post_id): bool
    {
        if ($attachment_id <= 0 || $post_id <= 0) {
            return false;
        }

        $attachment = get_post($attachment_id);
        if (!$attachment instanceof WP_Post || 'attachment' !== $attachment->post_type) {
            return false;
        }

        if ((int) $attachment->post_parent === $post_id) {
            return true;
        }

        if ((int) $attachment->post_parent !== 0) {
            return false;
        }

        wp_update_post([
            'ID'          => $attachment_id,
            'post_parent' => $post_id,
        ]);

        return true;
    }

    private static function validate_image_upload(array $file, string $context_label): ?string
    {
        if (!empty($file['error']) && UPLOAD_ERR_OK !== $file['error']) {
            $message = match ((int) $file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __('The file exceeds the maximum allowed size.', 'artpulse-management'),
                UPLOAD_ERR_PARTIAL                       => __('The file upload was incomplete.', 'artpulse-management'),
                UPLOAD_ERR_NO_FILE                       => __('No file was uploaded.', 'artpulse-management'),
                default                                  => __('The file could not be uploaded.', 'artpulse-management'),
            };

            return sprintf('%s: %s', $context_label, $message);
        }

        if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return sprintf(__('Unable to read the %s upload.', 'artpulse-management'), strtolower($context_label));
        }

        if ($file['size'] > self::MAX_UPLOAD_BYTES) {
            return sprintf(
                /* translators: 1: Field label, 2: size in megabytes. */
                __('%1$s must be smaller than %2$dMB.', 'artpulse-management'),
                $context_label,
                10
            );
        }

        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        $type = $check['type'] ?? '';
        if ('' === $type || !in_array($type, self::ALLOWED_MIME_TYPES, true)) {
            return sprintf(
                /* translators: %s field label. */
                __('%s must be a JPG, PNG, or WebP image.', 'artpulse-management'),
                $context_label
            );
        }

        $dimensions = @getimagesize($file['tmp_name']); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if (!is_array($dimensions) || count($dimensions) < 2) {
            return sprintf(__('We could not determine the size of the %s.', 'artpulse-management'), strtolower($context_label));
        }

        [$width, $height] = $dimensions;
        if ($width < self::MIN_IMAGE_DIMENSION || $height < self::MIN_IMAGE_DIMENSION) {
            return sprintf(
                /* translators: 1: Field label, 2: minimum dimension. */
                __('%1$s must be at least %2$d×%2$d pixels.', 'artpulse-management'),
                $context_label,
                self::MIN_IMAGE_DIMENSION
            );
        }

        if ('image/jpeg' === $type) {
            $channels = isset($dimensions['channels']) ? (int) $dimensions['channels'] : 0;
            if ($channels >= 4) {
                return sprintf(
                    /* translators: %s field label. */
                    __('%s must use an RGB color profile. Please upload a non-CMYK JPG.', 'artpulse-management'),
                    $context_label
                );
            }
        }

        return null;
    }

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

    private static function remember_errors(int $user_id, array $messages): void
    {
        $messages = array_filter(array_map(static function ($message) {
            return sanitize_text_field((string) $message);
        }, $messages));

        if (empty($messages)) {
            return;
        }

        set_transient(self::ERROR_TRANSIENT_KEY . $user_id, $messages, 5 * MINUTE_IN_SECONDS);
    }

    private static function pull_errors(int $user_id): array
    {
        $key = self::ERROR_TRANSIENT_KEY . $user_id;
        $messages = get_transient($key);

        if (!is_array($messages)) {
            return [];
        }

        delete_transient($key);

        return array_map('sanitize_text_field', $messages);
    }

    private static function user_can_manage_org(WP_Post $org): bool
    {
        $user = wp_get_current_user();

        if (!$user instanceof WP_User) {
            return false;
        }

        $user_id = (int) $user->ID;

        if ($user_id <= 0) {
            return false;
        }

        $owner_id = (int) get_post_meta($org->ID, '_ap_owner_user', true);
        $is_owner = $owner_id === $user_id || (int) $org->post_author === $user_id;

        if ($is_owner) {
            return current_user_can('edit_post', $org->ID);
        }

        if (current_user_can('edit_post', $org->ID)) {
            return true;
        }

        if (in_array('organization', (array) $user->roles, true)) {
            $owned = self::get_owned_org($user_id);
            if ($owned instanceof WP_Post && (int) $owned->ID === (int) $org->ID) {
                return true;
            }
        }

        return current_user_can('manage_options');
    }

    private static function build_preview_data(WP_Post $org, array $meta): array
    {
        $logo_id   = (int) ($meta['logo_id'] ?? 0);
        $cover_id  = (int) ($meta['cover_id'] ?? 0);
        $gallery   = $meta['gallery_ids'] ?? [];

        if (!$cover_id && !empty($gallery)) {
            $cover_id = (int) $gallery[0];
        }

        if (!$cover_id) {
            $featured = (int) get_post_thumbnail_id($org);
            if ($featured) {
                $cover_id = $featured;
            }
        }

        $cover_src = '';
        if ($cover_id) {
            $best = ImageTools::best_image_src($cover_id, ['ap-grid', 'large', 'medium_large', 'medium']);
            if ($best) {
                $cover_src = $best['url'];
            }
        }

        return [
            'title'        => get_the_title($org),
            'tagline'      => $meta['tagline'] ?? '',
            'about'        => $meta['about'] ?? '',
            'logo_id'      => $logo_id,
            'cover_id'     => $cover_id,
            'cover_src'    => $cover_src,
            'gallery_ids'  => $gallery,
            'permalink'    => get_permalink($org),
        ];
    }
}
