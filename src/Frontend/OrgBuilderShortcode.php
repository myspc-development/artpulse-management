<?php

namespace ArtPulse\Frontend;

use ArtPulse\Core\ImageTools;
use ArtPulse\Core\UpgradeReviewRepository;
use WP_Post;

class OrgBuilderShortcode
{
    public static function register(): void
    {
        add_shortcode('ap_org_builder', [self::class, 'render']);
        add_action('admin_post_ap_org_builder_save', [self::class, 'handle_save']);
        add_action('admin_post_nopriv_ap_org_builder_save', [self::class, 'handle_save']);
    }

    public static function render($atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to manage your organization.', 'artpulse-management') . '</p>';
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

        if (!current_user_can('edit_post', $org->ID)) {
            return '<p>' . esc_html__('You do not have permission to edit this organization.', 'artpulse-management') . '</p>';
        }

        $step = isset($_GET['step']) ? sanitize_key(wp_unslash($_GET['step'])) : 'profile';
        $step = in_array($step, ['profile', 'images', 'preview', 'publish'], true) ? $step : 'profile';

        $message = '';
        if (!empty($_GET['ap_builder'])) {
            $message_key = sanitize_key(wp_unslash($_GET['ap_builder']));
            if ('saved' === $message_key) {
                $message = esc_html__('Changes saved.', 'artpulse-management');
            } elseif ('published' === $message_key) {
                $message = esc_html__('Organization published successfully.', 'artpulse-management');
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

        include self::get_template_path('wrapper');

        return (string) ob_get_clean();
    }

    public static function handle_save(): void
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/login/'));
            exit;
        }

        check_admin_referer('ap-org-builder');

        $user_id = get_current_user_id();
        $org_id  = isset($_POST['org_id']) ? absint($_POST['org_id']) : 0;
        $step    = isset($_POST['builder_step']) ? sanitize_key(wp_unslash($_POST['builder_step'])) : 'profile';

        $redirect = add_query_arg([
            'step' => $step,
        ], wp_get_referer() ?: add_query_arg(['step' => $step], home_url('/dashboard/')));

        if (!$org_id) {
            wp_safe_redirect(add_query_arg('ap_builder', 'error', $redirect));
            exit;
        }

        $org = get_post($org_id);
        if (!$org instanceof WP_Post) {
            wp_safe_redirect(add_query_arg('ap_builder', 'error', $redirect));
            exit;
        }

        if ((int) get_post_meta($org_id, '_ap_owner_user', true) !== $user_id && (int) $org->post_author !== $user_id) {
            wp_safe_redirect(add_query_arg('ap_builder', 'error', $redirect));
            exit;
        }

        if ('profile' === $step) {
            self::save_profile($org_id);
            $status = 'saved';
        } elseif ('images' === $step) {
            self::save_images($org_id);
            $status = 'saved';
        } elseif ('publish' === $step) {
            self::publish_org($org_id);
            $status = 'published';
        } else {
            $status = 'saved';
        }

        wp_safe_redirect(add_query_arg('ap_builder', $status, $redirect));
        exit;
    }

    private static function save_profile(int $org_id): void
    {
        $fields = [
            '_ap_tagline'   => sanitize_text_field($_POST['ap_tagline'] ?? ''),
            '_ap_about'     => wp_kses_post($_POST['ap_about'] ?? ''),
            '_ap_website'   => esc_url_raw($_POST['ap_website'] ?? ''),
            '_ap_socials'   => sanitize_textarea_field($_POST['ap_socials'] ?? ''),
            '_ap_phone'     => sanitize_text_field($_POST['ap_phone'] ?? ''),
            '_ap_email'     => sanitize_email($_POST['ap_email'] ?? ''),
            '_ap_address'   => sanitize_textarea_field($_POST['ap_address'] ?? ''),
        ];

        foreach ($fields as $key => $value) {
            if ($value === '') {
                delete_post_meta($org_id, $key);
            } else {
                update_post_meta($org_id, $key, $value);
            }
        }
    }

    private static function save_images(int $org_id): void
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        if (!empty($_FILES['ap_logo']['name'])) {
            $logo_id = media_handle_upload('ap_logo', $org_id);
            if (!is_wp_error($logo_id)) {
                update_post_meta($org_id, '_ap_logo_id', (int) $logo_id);
            }
        }

        if (!empty($_FILES['ap_cover']['name'])) {
            $cover_id = media_handle_upload('ap_cover', $org_id);
            if (!is_wp_error($cover_id)) {
                update_post_meta($org_id, '_ap_cover_id', (int) $cover_id);
            }
        }

        $gallery_ids = isset($_POST['existing_gallery_ids']) ? array_map('absint', (array) $_POST['existing_gallery_ids']) : [];

        if (!empty($_FILES['ap_gallery']['name'][0])) {
            $files = self::normalize_files_array($_FILES['ap_gallery']);
            foreach ($files as $file_key => $details) {
                $_FILES['single_gallery_upload'] = $details;
                $attachment_id = media_handle_upload('single_gallery_upload', $org_id);
                if (!is_wp_error($attachment_id)) {
                    $gallery_ids[] = (int) $attachment_id;
                }
            }
        }

        update_post_meta($org_id, '_ap_gallery_ids', array_filter($gallery_ids));

        $featured = isset($_POST['ap_featured_image']) ? absint($_POST['ap_featured_image']) : 0;
        if ($featured > 0) {
            set_post_thumbnail($org_id, $featured);
        }
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
            'gallery_ids' => array_filter((array) get_post_meta($org_id, '_ap_gallery_ids', true)),
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
        foreach ($files['name'] as $index => $name) {
            if (empty($name)) {
                continue;
            }

            $normalized[] = [
                'name'     => $name,
                'type'     => $files['type'][$index],
                'tmp_name' => $files['tmp_name'][$index],
                'error'    => $files['error'][$index],
                'size'     => $files['size'][$index],
            ];
        }

        return $normalized;
    }

    private static function get_template_path(string $view): string
        {
            $base = trailingslashit(ARTPULSE_PLUGIN_DIR) . 'templates/org-builder/' . $view . '.php';
            if (file_exists($base)) {
                return $base;
            }

            return $base;
        }

    private static function build_preview_data(WP_Post $org, array $meta): array
    {
        $logo  = $meta['logo_id'] ? wp_get_attachment_image_url($meta['logo_id'], 'thumbnail') : '';
        $cover = $meta['cover_id'] ? wp_get_attachment_image_url($meta['cover_id'], 'large') : '';

        if (!$cover && $meta['gallery_ids']) {
            $first = (int) $meta['gallery_ids'][0];
            $cover = wp_get_attachment_image_url($first, 'large');
        }

        if (!$cover) {
            $cover = ImageTools::best_image_src($org, 'ap-grid');
        }

        return [
            'title'   => get_the_title($org),
            'tagline' => $meta['tagline'] ?? '',
            'about'   => $meta['about'] ?? '',
            'logo'    => $logo,
            'cover'   => $cover,
            'permalink' => get_permalink($org),
        ];
    }
}
