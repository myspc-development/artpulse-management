<?php
namespace ArtPulse\Core;

class AdminDashboard
{
    public static function register()
    {
        add_action('admin_menu', [ self::class, 'addMenus' ]);
    }

    public static function addMenus()
    {
        add_menu_page(
            __('ArtPulse', 'artpulse-management'),
            __('ArtPulse', 'artpulse-management'),
            'manage_options',
            'artpulse-dashboard',
            [ self::class, 'renderDashboard' ],
            'dashicons-art', // choose an appropriate dashicon
            60
        );
        add_submenu_page(
            'artpulse-dashboard',
            __('Events', 'artpulse-management'),
            __('Events', 'artpulse-management'),
            'edit_artpulse_events',
            'edit.php?post_type=artpulse_event'
        );
        add_submenu_page(
            'artpulse-dashboard',
            __('Artists', 'artpulse-management'),
            __('Artists', 'artpulse-management'),
            'edit_artpulse_artists',
            'edit.php?post_type=artpulse_artist'
        );
        add_submenu_page(
            'artpulse-dashboard',
            __('Artworks', 'artpulse-management'),
            __('Artworks', 'artpulse-management'),
            'edit_artpulse_artworks',
            'edit.php?post_type=artpulse_artwork'
        );
        add_submenu_page(
            'artpulse-dashboard',
            __('Organizations', 'artpulse-management'),
            __('Organizations', 'artpulse-management'),
            'edit_artpulse_orgs',
            'edit.php?post_type=artpulse_org'
        );
    }

    public static function renderDashboard()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'artpulse-management'));
        }

        echo '<div class="wrap"><h1>' . esc_html__('ArtPulse Dashboard', 'artpulse-management') . '</h1>';
        echo '<p>' . esc_html__('Quick links to manage Events, Artists, Artworks, and Organizations.', 'artpulse-management') . '</p>';
        echo '<ul>';
        echo '<li><a href="' . admin_url('edit.php?post_type=artpulse_event') . '">' . esc_html__('Manage Events', 'artpulse-management') . '</a></li>';
        echo '<li><a href="' . admin_url('edit.php?post_type=artpulse_artist') . '">' . esc_html__('Manage Artists', 'artpulse-management') . '</a></li>';
        echo '<li><a href="' . admin_url('edit.php?post_type=artpulse_artwork') . '">' . esc_html__('Manage Artworks', 'artpulse-management') . '</a></li>';
        echo '<li><a href="' . admin_url('edit.php?post_type=artpulse_org') . '">' . esc_html__('Manage Organizations', 'artpulse-management') . '</a></li>';
        echo '</ul></div>';
    }
}
