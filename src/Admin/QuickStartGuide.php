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
            __('Quick Start', 'artpulse-management'),
            __('Quick Start', 'artpulse-management'),
            'manage_options',
            'artpulse-quickstart',
            [self::class, 'render']
        );
    }

    public static function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'artpulse-management'));
        }

        $admin_doc = plugins_url('assets/docs/Admin_Help.md', ARTPULSE_PLUGIN_FILE);
        $member_doc = plugins_url('assets/docs/Member_Help.md', ARTPULSE_PLUGIN_FILE);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('ArtPulse Quick Start', 'artpulse-management') . '</h1>';
        echo '<p>' . esc_html__('Refer to these guides to get up and running:', 'artpulse-management') . '</p>';
        echo '<ul>';
        echo '<li><a href="' . esc_url($admin_doc) . '" target="_blank">' . esc_html__('Admin Guide', 'artpulse-management') . '</a></li>';
        echo '<li><a href="' . esc_url($member_doc) . '" target="_blank">' . esc_html__('Member Guide', 'artpulse-management') . '</a></li>';
        echo '</ul>';
        echo '</div>';
    }

    public static function welcomeNotice()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (get_user_meta(get_current_user_id(), self::DISMISS_META, true)) {
            return;
        }
        $link = admin_url('admin.php?page=artpulse-quickstart');
        $dismiss_url = wp_nonce_url(add_query_arg('ap_dismiss_quickstart', '1'), 'ap_admin_action', 'ap_admin_nonce');
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>' . sprintf(esc_html__('Welcome to ArtPulse! Visit the %sQuick Start Guide%s.', 'artpulse-management'), '<a href="' . esc_url($link) . '">', '</a>') . ' <a href="' . esc_url($dismiss_url) . '">' . esc_html__('Dismiss', 'artpulse-management') . '</a></p>';
        echo '</div>';
    }

    public static function handleDismiss()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['ap_dismiss_quickstart'])) {
            check_admin_referer('ap_admin_action', 'ap_admin_nonce');
            update_user_meta(get_current_user_id(), self::DISMISS_META, 1);
            wp_safe_redirect(remove_query_arg('ap_dismiss_quickstart'));
            exit;
        }
    }
}
