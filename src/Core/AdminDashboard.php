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
            __('ArtPulse', 'artpulse'),
            __('ArtPulse', 'artpulse'),
            'manage_options',
            'artpulse-dashboard',
            [ self::class, 'renderDashboard' ],
            'dashicons-art', // choose an appropriate dashicon
            60
        );
        add_submenu_page(
            'artpulse-dashboard',
            __('Events','artpulse'),
            __('Events','artpulse'),
            'edit_artpulse_events',
            'edit.php?post_type=artpulse_event'
        );
        add_submenu_page(
            'artpulse-dashboard',
            __('Artists','artpulse'),
            __('Artists','artpulse'),
            'edit_artpulse_artists',
            'edit.php?post_type=artpulse_artist'
        );
        add_submenu_page(
            'artpulse-dashboard',
            __('Artworks','artpulse'),
            __('Artworks','artpulse'),
            'edit_artpulse_artworks',
            'edit.php?post_type=artpulse_artwork'
        );
        add_submenu_page(
            'artpulse-dashboard',
            __('Organizations','artpulse'),
            __('Organizations','artpulse'),
            'edit_artpulse_orgs',
            'edit.php?post_type=artpulse_org'
        );
    }

    public static function renderDashboard()
    {
        echo '<div class="wrap"><h1>' . __('ArtPulse Dashboard','artpulse') . '</h1>';
        echo '<p>' . __('Quick links to manage Events, Artists, Artworks, and Organizations.','artpulse') . '</p>';
        echo '<ul>';
        echo '<li><a href="' . admin_url('edit.php?post_type=artpulse_event') . '">' . __('Manage Events','artpulse') . '</a></li>';
        echo '<li><a href="' . admin_url('edit.php?post_type=artpulse_artist') . '">' . __('Manage Artists','artpulse') . '</a></li>';
        echo '<li><a href="' . admin_url('edit.php?post_type=artpulse_artwork') . '">' . __('Manage Artworks','artpulse') . '</a></li>';
        echo '<li><a href="' . admin_url('edit.php?post_type=artpulse_org') . '">' . __('Manage Organizations','artpulse') . '</a></li>';
        echo '</ul></div>';
    }
}
