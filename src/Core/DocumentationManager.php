<?php
namespace ArtPulse\Core;

use Parsedown;

class DocumentationManager
{
    public static function register()
    {
        add_action('admin_head', [ self::class, 'addHelpTabs' ]);
    }

    public static function addHelpTabs()
    {
        if ( ! function_exists('get_current_screen') ) {
            return;
        }
        $screen = get_current_screen();
        $docsPath = plugin_dir_path(__DIR__ . '/../../') . 'assets/docs/';
        $parsedown = new Parsedown();

        // Admin Help on Dashboard
        if ($screen->id === 'toplevel_page_artpulse-dashboard') {
            $md = file_get_contents($docsPath . 'Admin_Help.md');
            $content = '<div class="ap-doc">' . $parsedown->text($md) . '</div>';
            $screen->add_help_tab([
                'id'      => 'ap-admin-help',
                'title'   => __('Admin Help', 'artpulse'),
                'content' => $content,
            ]);
        }
        
        // Member Help on Profile and CPT screens
        $memberScreens = [
            'profile',
            'user-edit',
            'artpulse_event',
            'artpulse_artist',
            'artpulse_artwork',
            'artpulse_org',
        ];
        if ( in_array( $screen->id, $memberScreens, true ) ) {
            $md = file_get_contents($docsPath . 'Member_Help.md');
            $content = '<div class="ap-doc">' . $parsedown->text($md) . '</div>';
            $screen->add_help_tab([
                'id'      => 'ap-member-help',
                'title'   => __('Member Help', 'artpulse'),
                'content' => $content,
            ]);
        }
    }
}
