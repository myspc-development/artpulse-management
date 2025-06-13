<?php
namespace EAD\Roles;

/**
 * Class RolesManager
 *
 * Handles custom roles and capabilities for plugin features.
 *
 * @package EAD\Roles
 */
class RolesManager {

    /**
     * Initialize the roles and capabilities.
     */
    public static function init() {
        add_action('after_setup_theme', [self::class, 'register_roles']);
    }

    /**
     * Add custom roles and capabilities.
     */
    public static function add_roles() {
        // Artist
        add_role('artist', __('Artist', 'artpulse-management'), [
            'read'             => true,
            'upload_files'     => true,
            'edit_posts'       => true,
            'publish_posts'    => false,
            'view_dashboard'   => true,
            'submit_reviews'   => true,
            'edit_events'      => true,
        ]);

        // Event Organizer
        add_role('event_organizer', __('Event Organizer', 'artpulse-management'), [
            'read'             => true,
            'edit_posts'       => true,
            'publish_posts'    => true,
            'delete_posts'     => true,
            'upload_files'     => true,
            'view_dashboard'   => true,
            'manage_events'    => true,
            'ead_manage_rsvps' => true,
        ]);

        // Gallery Manager
        add_role('gallery_manager', __('Gallery Manager', 'artpulse-management'), [
            'read'                 => true,
            'edit_posts'           => true,
            'edit_others_posts'    => true,
            'upload_files'         => true,
            'manage_categories'    => true,
            'view_dashboard'       => true,
            'manage_artists'       => true,
        ]);

        // Subscriber Member
        add_role('subscriber_member', __('Subscriber Member', 'artpulse-management'), [
            'read'             => true,
            'comment'          => true,
            'view_dashboard'   => false,
        ]);

        // Also ensure admin has necessary capabilities at activation time.
        self::add_admin_caps();
    }

    /**
     * Remove custom roles.
     */
    public static function remove_roles() {
        self::remove_admin_caps();
        remove_role('artist');
        remove_role('event_organizer');
        remove_role('gallery_manager');
        remove_role('subscriber_member');
    }

    /**
     * Register capabilities to existing roles.
     */
    public static function register_roles() {
        self::add_admin_caps();

        $organizer = get_role('event_organizer');
        if ($organizer) {
            // Event organizers can access the dashboard.
            $organizer->add_cap('ead_manage_rsvps');
            $organizer->add_cap('view_dashboard');
        }

        $artist = get_role('artist');
        if ($artist) {
            $artist->add_cap('view_dashboard');
        }

        $gallery = get_role('gallery_manager');
        if ($gallery) {
            $gallery->add_cap('view_dashboard');
        }
    }

    /**
     * Add capabilities to administrator role.
     */
    private static function add_admin_caps() {
        $admin = get_role('administrator');
        if ($admin) {
            // Administrators also need the dashboard capability.
            $admin->add_cap('view_dashboard');
            $admin->add_cap('edit_events');
            $admin->add_cap('submit_reviews');
            $admin->add_cap('manage_events');
            $admin->add_cap('manage_artists');
            $admin->add_cap('edit_others_posts');
            $admin->add_cap('delete_others_posts');
            $admin->add_cap('ead_manage_rsvps');
        }
    }

    /**
     * Remove capabilities from administrator role.
     */
    private static function remove_admin_caps() {
        $admin = get_role('administrator');
        if ($admin) {
            $admin->remove_cap('view_dashboard');
            $admin->remove_cap('edit_events');
            $admin->remove_cap('submit_reviews');
            $admin->remove_cap('manage_events');
            $admin->remove_cap('manage_artists');
            $admin->remove_cap('edit_others_posts');
            $admin->remove_cap('delete_others_posts');
            $admin->remove_cap('ead_manage_rsvps');
        }
    }
}