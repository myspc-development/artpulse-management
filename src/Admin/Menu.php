<?php

namespace EAD\Admin;

use EAD\Admin\SettingsPage;
use EAD\Admin\CSVImportExport;
use EAD\Admin\PendingEvents;
use EAD\Admin\PendingOrganizations;
use EAD\Admin\PendingArtists;
use EAD\Admin\PendingArtworks;
use EAD\Admin\ReviewsModerator;
use EAD\Admin\AdminEventForm; // <-- Add this import
use EAD\Admin\ManageMembers;

/**
 * Admin Menu Registration.
 * Registers all admin menu pages for the ArtPulse Management plugin.
 */
class Menu {

    /**
     * Hook into admin_menu to register plugin menus.
     * This is the entry point for menu registration.
     */
    public static function register_menus() {
        add_action('admin_menu', [self::class, 'add_plugin_menus']);
    }

    /**
     * Add all top-level and sub-level plugin menus.
     */
    public static function add_plugin_menus() {
        $pending_events_count   = self::get_pending_count('ead_event');
        $pending_orgs_count     = self::get_pending_count('ead_organization');
        $pending_reviews_count  = self::get_pending_count('ead_org_review');
        $pending_artists_count  = self::get_pending_count('ead_artist');
        $pending_artworks_count = self::get_pending_count('ead_artwork');
        $total_pending          = $pending_events_count + $pending_orgs_count + $pending_reviews_count + $pending_artists_count + $pending_artworks_count;

        $menu_icon  = $total_pending > 0 ? 'dashicons-bell' : 'dashicons-art';
        $menu_label = __('ArtPulse', 'artpulse-management');
        if ($total_pending > 0) {
            $menu_label .= " <span class=\"awaiting-mod count-$total_pending\"><span class=\"pending-count\">$total_pending</span></span>";
        }

        // Top-level ArtPulse menu
        add_menu_page(
            __('ArtPulse', 'artpulse-management'),
            $menu_label,
            'manage_options',
            'artpulse-main-menu',
            [self::class, 'render_main_dashboard_page'],
            $menu_icon,
            25
        );

        // Submenus under ArtPulse menu
        add_submenu_page(
            'artpulse-main-menu',
            __('Dashboard', 'artpulse-management'),
            __('Dashboard', 'artpulse-management'),
            'manage_options',
            'artpulse-dashboard',
            [self::class, 'render_main_dashboard_page']
        );


        $events_label = __('Pending Events', 'artpulse-management');
        if ($pending_events_count > 0) {
            $events_label .= " <span class=\"awaiting-mod count-$pending_events_count\"><span class=\"pending-count\">$pending_events_count</span></span>";
        }

        add_submenu_page(
            'artpulse-main-menu',
            __('Pending Events', 'artpulse-management'),
            $events_label,
            'edit_others_posts',
            'artpulse-pending-events',
            [PendingEvents::class, 'render_admin_page']
        );

        $orgs_label = __('Pending Organizations', 'artpulse-management');
        if ($pending_orgs_count > 0) {
            $orgs_label .= " <span class=\"awaiting-mod count-$pending_orgs_count\"><span class=\"pending-count\">$pending_orgs_count</span></span>";
        }

        add_submenu_page(
            'artpulse-main-menu',
            __('Pending Organizations', 'artpulse-management'),
            $orgs_label,
            'edit_others_posts',
            'artpulse-pending-organizations',
            [PendingOrganizations::class, 'render_admin_page']
        );
        $artists_label = __("Pending Artists", "artpulse-management");
        if ($pending_artists_count > 0) {
            $artists_label .= " <span class=\"awaiting-mod count-$pending_artists_count\"><span class=\"pending-count\">$pending_artists_count</span></span>";
        }
        add_submenu_page(
            "artpulse-main-menu",
            __("Pending Artists", "artpulse-management"),
            $artists_label,
            "edit_others_posts",
            "artpulse-pending-artists",
            [PendingArtists::class, 'render_admin_page']
        );

        $artworks_label = __("Pending Artworks", "artpulse-management");
        if ($pending_artworks_count > 0) {
            $artworks_label .= " <span class=\"awaiting-mod count-$pending_artworks_count\"><span class=\"pending-count\">$pending_artworks_count</span></span>";
        }
        add_submenu_page(
            "artpulse-main-menu",
            __("Pending Artworks", "artpulse-management"),
            $artworks_label,
            "edit_others_posts",
            "artpulse-pending-artworks",
            [PendingArtworks::class, 'render_admin_page']
        );

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
            __('Manage Members', 'artpulse-management'),
            __('Manage Members', 'artpulse-management'),
            'manage_options',
            'artpulse-manage-members',
            [ManageMembers::class, 'render_admin_page']
        );

        add_submenu_page(
            'artpulse-main-menu',
            __('Notifications', 'artpulse-management'),
            __('Notifications', 'artpulse-management'),
            'manage_options',
            'artpulse-notifications',
            [\EAD\Admin\NotificationSettingsAdmin::class, 'render_admin_page']
        );

        add_submenu_page(
            'artpulse-main-menu',
            __('Settings', 'artpulse-management'),
            __('Settings', 'artpulse-management'),
            'manage_options',
            'artpulse-settings',
            [SettingsPage::class, 'render_settings_page_with_tabs']
        );

        // ------------------------------
        // ADD THIS: Admin Event Form
        // ------------------------------
        add_submenu_page(
            'artpulse-main-menu',
            __('Add Event (Admin)', 'artpulse-management'),
            __('Add Event (Admin)', 'artpulse-management'),
            'manage_options',
            'ead-admin-add-event',
            [AdminEventForm::class, 'render_admin_form']
        );
        // ------------------------------

        // Add CSS for pending badges
        add_action('admin_head', [self::class, 'admin_menu_badge_css']);
    }

    /**
     * Adds CSS to style the pending count badges in the admin menu.
     */
    public static function admin_menu_badge_css() {
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

    /**
     * Get the count of pending posts for a given post type.
     *
     * @param string $post_type The post type to count.
     * @return int The number of pending posts.
     */
    private static function get_pending_count(string $post_type): int {
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

    /**
     * Render a generic Main Dashboard Page (Example).
     */
    public static function render_main_dashboard_page() {
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
