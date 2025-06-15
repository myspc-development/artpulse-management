<?php

namespace EAD\Admin;

use EAD\Admin\SettingsPage;
use EAD\Admin\PendingEvents;
use EAD\Admin\PendingOrganizations;
use EAD\Admin\PendingArtists;
use EAD\Admin\PendingArtworks;
use EAD\Admin\ReviewsModerator;
use EAD\Admin\ManageMembers;
use EAD\Admin\MembershipAnalytics;

class Menu
{
    public static function register_menus()
    {
        add_action('admin_menu', [self::class, 'add_plugin_menus']);
    }

    public static function add_plugin_menus()
    {
        // Pending counts for dashboard badges
        $pending_events_count   = self::get_pending_count('ead_event');
        $pending_orgs_count     = self::get_pending_count('ead_organization');
        $pending_reviews_count  = self::get_pending_count('ead_org_review');
        $pending_artists_count  = self::get_pending_count('ead_artist');
        $pending_artworks_count = self::get_pending_count('ead_artwork');
        $total_pending = $pending_events_count + $pending_orgs_count + $pending_reviews_count + $pending_artists_count + $pending_artworks_count;

        $menu_icon  = $total_pending > 0 ? 'dashicons-bell' : 'dashicons-art';
        $menu_label = __('ArtPulse', 'artpulse-management');
        if ($total_pending > 0) {
            $menu_label .= " <span class=\"awaiting-mod count-$total_pending\"><span class=\"pending-count\">$total_pending</span></span>";
        }

        // Top-level ArtPulse menu (Dashboard)
        add_menu_page(
            __('ArtPulse', 'artpulse-management'),
            $menu_label,
            'manage_options',
            'artpulse-main-menu',
            [self::class, 'render_main_dashboard_page'],
            $menu_icon,
            25
        );
        // Dashboard
        add_submenu_page(
            'artpulse-main-menu',
            __('Dashboard', 'artpulse-management'),
            __('Dashboard', 'artpulse-management'),
            'manage_options',
            'artpulse-dashboard',
            [self::class, 'render_main_dashboard_page']
        );

        // Member Management (only one top-level)
        add_menu_page(
            __('Member Management', 'artpulse-management'),
            __('Member Management', 'artpulse-management'),
            'manage_options',
            'ead-member-menu',
            [ManageMembers::class, 'render_admin_page'],
            'dashicons-groups',
            33
        );
        // Manage Members
        add_submenu_page(
            'ead-member-menu',
            __('Manage Members', 'artpulse-management'),
            __('Manage Members', 'artpulse-management'),
            'manage_options',
            'ead-member-menu',
            [ManageMembers::class, 'render_admin_page']
        );
        // Add New Member (link to WordPress user-new.php)
        add_submenu_page(
            'ead-member-menu',
            __('Add New Member', 'artpulse-management'),
            __('Add New Member', 'artpulse-management'),
            'create_users',
            'user-new.php'
        );
        // Membership Analytics
        add_submenu_page(
            'ead-member-menu',
            __('Membership Analytics', 'artpulse-management'),
            __('Analytics', 'artpulse-management'),
            'manage_options',
            'ead-membership-analytics',
            [MembershipAnalytics::class, 'render_admin_page']
        );
        // Member Settings
        add_submenu_page(
            'ead-member-menu',
            __('Member Settings', 'artpulse-management'),
            __('Member Settings', 'artpulse-management'),
            'manage_options',
            'ead-membership-settings',
            [self::class, 'render_membership_settings_page']
        );

        // Pending items and moderation (under ArtPulse)
        self::add_pending_submenu('artpulse-main-menu', 'Pending Events', $pending_events_count, 'artpulse-pending-events', [PendingEvents::class, 'render_admin_page']);
        self::add_pending_submenu('artpulse-main-menu', 'Pending Organizations', $pending_orgs_count, 'artpulse-pending-organizations', [PendingOrganizations::class, 'render_admin_page']);
        self::add_pending_submenu('artpulse-main-menu', 'Pending Artists', $pending_artists_count, 'artpulse-pending-artists', [PendingArtists::class, 'render_admin_page']);
        self::add_pending_submenu('artpulse-main-menu', 'Pending Artworks', $pending_artworks_count, 'artpulse-pending-artworks', [PendingArtworks::class, 'render_admin_page']);

        // Other submenus (moderation, bookings, notifications, etc.)
        add_submenu_page(
            'artpulse-main-menu',
            __('Moderate Reviews', 'artpulse-management'),
            __('Moderate Reviews', 'artpulse-management'),
            'edit_others_posts',
            'ead-moderate-reviews',
            [ReviewsModerator::class, 'moderate_reviews_page']
        );
        add_submenu_page(
            'artpulse-main-menu',
            __('Comments', 'artpulse-management'),
            __('Comments', 'artpulse-management'),
            'edit_others_posts',
            'artpulse-comments',
            [\EAD\Admin\CommentsAdmin::class, 'render_admin_page']
        );
        add_submenu_page(
            'artpulse-main-menu',
            __('Bookings', 'artpulse-management'),
            __('Bookings', 'artpulse-management'),
            'edit_others_posts',
            'artpulse-bookings',
            [\EAD\Admin\BookingsAdmin::class, 'render_admin_page']
        );
        add_submenu_page(
            'artpulse-main-menu',
            __('Notifications', 'artpulse-management'),
            __('Notifications', 'artpulse-management'),
            'manage_options',
            'artpulse-notifications',
            [\EAD\Admin\NotificationSettingsAdmin::class, 'render_admin_page']
        );
        // ArtPulse settings submenu (assumed registered elsewhere)
        // Add CSS for badges
        add_action('admin_head', [self::class, 'admin_menu_badge_css']);
    }

    private static function add_pending_submenu($parent, $label, $count, $slug, $callback)
    {
        $menu_label = __($label, 'artpulse-management');
        if ($count > 0) {
            $menu_label .= " <span class=\"awaiting-mod count-$count\"><span class=\"pending-count\">$count</span></span>";
        }
        add_submenu_page(
            $parent,
            __($label, 'artpulse-management'),
            $menu_label,
            'edit_others_posts',
            $slug,
            $callback
        );
    }

    public static function admin_menu_badge_css()
    {
        echo '<style>
            #adminmenu .awaiting-mod {
                background-color: #d63638;
                color: #fff;
                font-weight: normal;
                padding: 2px 5px;
                border-radius: 4px;
                font-size: 11px;
                line-height: 1.2;
                margin-left: 6px;
            }
        </style>';
    }

    public static function render_membership_settings_page()
    {
        $_GET['tab'] = 'membership';
        SettingsPage::render_settings_page_with_tabs();
    }

    private static function get_pending_count(string $post_type): int
    {
        $args = [
            'post_type'      => $post_type,
            'post_status'    => 'pending',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ];
        $pending_query = new \WP_Query($args);
        return (int) $pending_query->found_posts;
    }

    public static function render_main_dashboard_page()
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('ArtPulse Management Dashboard', 'artpulse-management') . '</h1>';
        echo '<p>' . esc_html__('Welcome to ArtPulse Management. Use the submenus to manage specific areas of the plugin.', 'artpulse-management') . '</p>';
        echo '<h2>' . esc_html__('Quick Stats:', 'artpulse-management') . '</h2>';
        echo '<ul>';
        echo '<li>' . sprintf(esc_html__('Published Events: %d', 'artpulse-management'), number_format_i18n(wp_count_posts('ead_event')->publish)) . '</li>';
        echo '<li>' . sprintf(esc_html__('Pending Events: %d', 'artpulse-management'), number_format_i18n(self::get_pending_count('ead_event'))) . '</li>';
        echo '<li>' . sprintf(esc_html__('Published Organizations: %d', 'artpulse-management'), number_format_i18n(wp_count_posts('ead_organization')->publish)) . '</li>';
        echo '<li>' . sprintf(esc_html__('Pending Organizations: %d', 'artpulse-management'), number_format_i18n(self::get_pending_count('ead_organization'))) . '</li>';
        echo '<li>' . sprintf(esc_html__('Pending Artists: %d', 'artpulse-management'), number_format_i18n(self::get_pending_count('ead_artist'))) . '</li>';
        echo '<li>' . sprintf(esc_html__('Pending Artworks: %d', 'artpulse-management'), number_format_i18n(self::get_pending_count('ead_artwork'))) . '</li>';
        echo '<li>' . sprintf(esc_html__('Pending Reviews: %d', 'artpulse-management'), number_format_i18n(self::get_pending_count('ead_org_review'))) . '</li>';
        echo '</ul>';
        echo '</div>';
    }
}
