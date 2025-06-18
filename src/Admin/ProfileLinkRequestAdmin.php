<?php

namespace ArtPulse\Admin;

/**
 * Admin interface for managing profile link requests.
 */
class ProfileLinkRequestAdmin
{
    /**
     * Register the admin submenu page for link requests.
     */
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'add_link_request_menu']);
        add_filter('manage_edit-ap_link_request_columns', [self::class, 'custom_columns']);
        add_action('manage_ap_link_request_posts_custom_column', [self::class, 'render_custom_columns'], 10, 2);
    }

    /**
     * Add a submenu for viewing link requests.
     */
    public static function add_link_request_menu(): void
    {
        add_submenu_page(
            'edit.php?post_type=ap_link_request',
            __('Link Requests', 'artpulse'),
            __('Link Requests', 'artpulse'),
            'edit_users',
            'edit.php?post_type=ap_link_request'
        );
    }

    /**
     * Customize admin columns for link requests.
     */
    public static function custom_columns(array $columns): array
    {
        $columns['linked_user'] = __('User', 'artpulse');
        $columns['target_post'] = __('Requested Org/Artist', 'artpulse');
        return $columns;
    }

    /**
     * Render custom column values.
     */
    public static function render_custom_columns(string $column, int $post_id): void
    {
        if ($column === 'linked_user') {
            $user_id = get_post_field('post_author', $post_id);
            $user = get_user_by('id', $user_id);
            echo $user ? esc_html($user->display_name) : '-';
        }

        if ($column === 'target_post') {
            $target_id = get_post_meta($post_id, '_ap_target_id', true);
            $post = get_post($target_id);
            echo $post ? esc_html($post->post_title) : '-';
        }
    }
}
