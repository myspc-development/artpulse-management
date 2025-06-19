<?php
namespace ArtPulse\Admin;

class QuickStartGuide
{
    const DISMISS_META = 'ap_dismiss_quickstart';

    public static function register()
    {
        add_action('admin_menu', [self::class, 'addMenu']);
        add_action('admin_notices', [self::class, 'welcomeNotice']);
        add_action('admin_init', [self::class, 'handleDismiss']);
    }

    public static function addMenu()
    {
        add_submenu_page(
            'artpulse-settings',
            __('Quick Start', 'artpulse'),
            __('Quick Start', 'artpulse'),
            'manage_options',
            'artpulse-quickstart',
            [self::class, 'render']
        );
    }

    public static function render()
    {
        $admin_doc = plugins_url('assets/docs/Admin_Help.md', ARTPULSE_PLUGIN_FILE);
        $member_doc = plugins_url('assets/docs/Member_Help.md', ARTPULSE_PLUGIN_FILE);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('ArtPulse Quick Start', 'artpulse') . '</h1>';
        echo '<p>' . esc_html__('Refer to these guides to get up and running:', 'artpulse') . '</p>';
        echo '<ul>';
        echo '<li><a href="' . esc_url($admin_doc) . '" target="_blank">' . esc_html__('Admin Guide', 'artpulse') . '</a></li>';
        echo '<li><a href="' . esc_url($member_doc) . '" target="_blank">' . esc_html__('Member Guide', 'artpulse') . '</a></li>';
        echo '</ul>';
        echo '</div>';
    }

    public static function welcomeNotice()
    {
        if (get_user_meta(get_current_user_id(), self::DISMISS_META, true)) {
            return;
        }
        $link = admin_url('admin.php?page=artpulse-quickstart');
        $dismiss_url = add_query_arg('ap_dismiss_quickstart', '1');
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>' . sprintf(esc_html__('Welcome to ArtPulse! Visit the %sQuick Start Guide%s.', 'artpulse'), '<a href="' . esc_url($link) . '">', '</a>') . ' <a href="' . esc_url($dismiss_url) . '">' . esc_html__('Dismiss', 'artpulse') . '</a></p>';
        echo '</div>';
    }

    public static function handleDismiss()
    {
        if (isset($_GET['ap_dismiss_quickstart'])) {
            update_user_meta(get_current_user_id(), self::DISMISS_META, 1);
            wp_safe_redirect(remove_query_arg('ap_dismiss_quickstart'));
            exit;
        }
    }
}
