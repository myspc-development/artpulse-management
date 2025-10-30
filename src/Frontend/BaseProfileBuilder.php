<?php

namespace ArtPulse\Frontend;

use ArtPulse\Core\ProfileProgress;
use ArtPulse\Core\ProfileState;
use ArtPulse\Frontend\Shared\PortfolioAccess;
use WP_Post;
use function ArtPulse\Core\get_page_url;
use function absint;
use function array_filter;
use function array_map;
use function esc_html;
use function esc_html__;
use function esc_url_raw;
use function get_current_user_id;
use function get_option;
use function get_post;
use function get_post_meta;
use function get_post_thumbnail_id;
use function get_posts;
use function get_the_title;
use function get_permalink;
use function in_array;
use function is_array;
use function is_user_logged_in;
use function ob_get_clean;
use function ob_start;
use function plugins_url;
use function preg_split;
use function rest_url;
use function sanitize_key;
use function status_header;
use function sprintf;
use function wp_create_nonce;
use function wp_enqueue_media;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function auth_redirect;
use function current_user_can;

final class BaseProfileBuilder
{
    /**
     * Render the shared profile builder UI.
     */
    public static function render(string $type): string
    {
        $type = sanitize_key($type);
        if (!in_array($type, ['artist', 'org'], true)) {
            return '';
        }

        if (!self::is_enabled($type)) {
            status_header(404);
            return '';
        }

        if (!is_user_logged_in()) {
            auth_redirect();
            exit;
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return '';
        }

        $capability = 'artist' === $type ? 'edit_artpulse_artist' : 'edit_artpulse_org';
        if (!current_user_can($capability)) {
            return '<div class="ap-profile-builder__notice">' . esc_html__('You do not have permission to edit this profile.', 'artpulse-management') . '</div>';
        }

        $config = ProfileBuilderConfig::for($type);
        $state  = ProfileState::for_user($type, $user_id);

        $post_id = $state['post_id'] ?? null;
        if (!$post_id) {
            $post_id = self::resolve_owned_post_id($config['post_type']);
        }

        if (!$post_id) {
            ob_start();
            $builder_type = $type;
            $profile_state = $state;
            include ARTPULSE_PLUGIN_DIR . 'templates/profile-builder/empty-state.php';

            return (string) ob_get_clean();
        }

        $post_id = (int) $post_id;
        if (!current_user_can('edit_post', $post_id)) {
            return '<div class="ap-profile-builder__notice">' . esc_html__('You do not have permission to edit this profile.', 'artpulse-management') . '</div>';
        }

        $payload  = self::get_payload($post_id, $config['post_type']);
        if (!empty($state['status'])) {
            $payload['status'] = (string) $state['status'];
        }

        if (!empty($state['visibility'])) {
            $payload['visibility'] = (string) $state['visibility'];
        }

        $progress = ProfileProgress::compute($payload, $config['required_fields'], $config['steps']);
        $public_url    = ('publish' === ($payload['status'] ?? '') && 'public' === ($payload['visibility'] ?? ''))
            ? $payload['public_url']
            : null;

        wp_enqueue_script('wp-api-fetch');
        wp_enqueue_media();

        wp_enqueue_style(
            'ap-profile-builder',
            plugins_url('assets/css/ap-profile-builder.css', ARTPULSE_PLUGIN_FILE),
            [],
            ARTPULSE_VERSION
        );

        wp_enqueue_script(
            'ap-autosave',
            plugins_url('assets/js/ap-autosave.js', ARTPULSE_PLUGIN_FILE),
            ['wp-api-fetch'],
            ARTPULSE_VERSION,
            true
        );

        wp_localize_script('ap-autosave', 'APAutosave', [
            'nonce'    => wp_create_nonce('wp_rest'),
            'postId'   => $post_id,
            'type'     => $type,
            'endpoint' => esc_url_raw(rest_url(sprintf('artpulse/v1/portfolio/%s/%d', $type, $post_id)) . '?context=edit'),
            'strings'  => [
                'saving'         => esc_html__('Saving…', 'artpulse-management'),
                'savedJustNow'   => esc_html__('Saved just now', 'artpulse-management'),
                'savedAgo'       => esc_html__('Saved %s ago', 'artpulse-management'),
                'failed'         => esc_html__('Failed to save. Retry?', 'artpulse-management'),
                'sessionExpired' => esc_html__('Your session expired. Please refresh.', 'artpulse-management'),
                'retryingIn'     => esc_html__('Retrying in %d seconds…', 'artpulse-management'),
                'savingError'    => esc_html__('Please fix the highlighted field.', 'artpulse-management'),
            ],
        ]);

        $builder_type     = $type;
        $builder_payload  = $payload;
        $builder_config   = $config;
        $builder_progress = $progress;
        $profile_state    = array_merge($state, [
            'post_id'    => $post_id,
            'public_url' => $public_url,
            'exists'     => true,
            'status'     => $payload['status'] ?? 'draft',
            'visibility' => $payload['visibility'] ?? null,
            'complete'   => (int) ($progress['percent'] ?? 0),
        ]);

        ob_start();
        include ARTPULSE_PLUGIN_DIR . 'templates/profile-builder/wrapper.php';

        return (string) ob_get_clean();
    }

    private static function is_enabled(string $type): bool
    {
        if ('artist' === $type) {
            return (bool) get_option('ap_enable_artist_builder', true);
        }

        if ('org' === $type) {
            return (bool) get_option('ap_enable_org_builder', true);
        }

        return false;
    }

    /**
     * Resolve the latest owned post identifier for the current user.
     */
    private static function resolve_owned_post_id(string $post_type): ?int
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return null;
        }

        $type = 'artpulse_org' === $post_type ? 'org' : 'artist';
        $state = ProfileState::for_user($type, $user_id);
        if (!empty($state['post_id'])) {
            return (int) $state['post_id'];
        }

        $ids = PortfolioAccess::get_owned_portfolio_ids($user_id, $post_type);
        if (empty($ids)) {
            return null;
        }

        $posts = get_posts([
            'post_type'      => $post_type,
            'post_status'    => ['draft', 'pending', 'publish', 'future'],
            'post__in'       => $ids,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'numberposts'    => 1,
            'fields'         => 'ids',
        ]);

        if (empty($posts)) {
            return null;
        }

        return (int) $posts[0];
    }

    /**
     * Prefetch payload for the builder UI.
     *
     * @return array<string, mixed>
     */
    private static function get_payload(int $post_id, string $post_type): array
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return [];
        }

        $tagline = (string) get_post_meta($post_id, '_ap_tagline', true);
        $bio     = (string) get_post_meta($post_id, '_ap_about', true);
        $website = (string) get_post_meta($post_id, '_ap_website', true);
        $social_meta = get_post_meta($post_id, '_ap_socials', true);
        if (is_array($social_meta)) {
            $socials = array_filter(array_map('trim', array_map('strval', $social_meta)));
        } else {
            $socials = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $social_meta) ?: []));
        }

        $gallery = get_post_meta($post_id, '_ap_gallery_ids', true);
        if (!is_array($gallery)) {
            $gallery = [];
        }

        $visibility = (string) get_post_meta($post_id, '_ap_visibility', true);
        $status     = $post->post_status;

        $public_url    = get_permalink($post_id);

        return [
            'post_id'        => $post_id,
            'type'           => 'artpulse_org' === $post_type ? 'org' : 'artist',
            'title'          => get_the_title($post_id),
            'tagline'        => $tagline,
            'bio'            => $bio,
            'website_url'    => $website,
            'socials'        => array_values($socials),
            'featured_media' => (int) get_post_thumbnail_id($post_id),
            'gallery'        => array_values(array_map('absint', $gallery)),
            'visibility'     => $visibility,
            'status'         => $status,
            'dashboard_url'  => get_page_url('dashboard_page_id'),
            'public_url'     => $public_url ? $public_url : null,
        ];
    }
}
