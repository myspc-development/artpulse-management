<?php
namespace ArtPulse\Taxonomies;

class TaxonomiesRegistrar {
    public static function register() {
        add_action('init', [self::class, 'register_artist_specialties']);
        add_action('init', [self::class, 'register_artwork_styles']);
        add_action('init', [self::class, 'register_event_types']);
        add_action('init', [self::class, 'register_org_categories']);
    }

    public static function register_artist_specialties() {
        $labels = [
            'name' => __('Artist Specialties', 'artpulse-management'),
            'singular_name' => __('Specialty', 'artpulse-management'),
            'search_items' => __('Search Specialties', 'artpulse-management'),
            'all_items' => __('All Specialties', 'artpulse-management'),
            'edit_item' => __('Edit Specialty', 'artpulse-management'),
            'update_item' => __('Update Specialty', 'artpulse-management'),
            'add_new_item' => __('Add New Specialty', 'artpulse-management'),
            'new_item_name' => __('New Specialty Name', 'artpulse-management'),
            'menu_name' => __('Artist Specialties', 'artpulse-management'),
        ];
        register_taxonomy('artist_specialty', 'artpulse_artist', [
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'rewrite' => ['slug' => 'artist-specialty'],
            'show_in_rest' => true,
        ]);
    }

    public static function register_artwork_styles() {
        $labels = [
            'name' => __('Artwork Styles', 'artpulse-management'),
            'singular_name' => __('Style', 'artpulse-management'),
            'search_items' => __('Search Styles', 'artpulse-management'),
            'all_items' => __('All Styles', 'artpulse-management'),
            'edit_item' => __('Edit Style', 'artpulse-management'),
            'update_item' => __('Update Style', 'artpulse-management'),
            'add_new_item' => __('Add New Style', 'artpulse-management'),
            'new_item_name' => __('New Style Name', 'artpulse-management'),
            'menu_name' => __('Artwork Styles', 'artpulse-management'),
        ];
        register_taxonomy('artwork_style', 'artpulse_artwork', [
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'rewrite' => ['slug' => 'artwork-style'],
            'show_in_rest' => true,
        ]);
    }

    public static function register_event_types() {
        $labels = [
            'name' => __('Event Types', 'artpulse-management'),
            'singular_name' => __('Event Type', 'artpulse-management'),
            'search_items' => __('Search Event Types', 'artpulse-management'),
            'all_items' => __('All Event Types', 'artpulse-management'),
            'edit_item' => __('Edit Event Type', 'artpulse-management'),
            'update_item' => __('Update Event Type', 'artpulse-management'),
            'add_new_item' => __('Add New Event Type', 'artpulse-management'),
            'new_item_name' => __('New Event Type Name', 'artpulse-management'),
            'menu_name' => __('Event Types', 'artpulse-management'),
        ];
        register_taxonomy('event_type', 'artpulse_event', [
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'rewrite' => ['slug' => 'event-type'],
            'show_in_rest' => true,
        ]);
    }

    public static function register_org_categories() {
        $labels = [
            'name' => __('Organization Categories', 'artpulse-management'),
            'singular_name' => __('Organization Category', 'artpulse-management'),
            'search_items' => __('Search Organization Categories', 'artpulse-management'),
            'all_items' => __('All Organization Categories', 'artpulse-management'),
            'edit_item' => __('Edit Organization Category', 'artpulse-management'),
            'update_item' => __('Update Organization Category', 'artpulse-management'),
            'add_new_item' => __('Add New Organization Category', 'artpulse-management'),
            'new_item_name' => __('New Organization Category Name', 'artpulse-management'),
            'menu_name' => __('Organization Categories', 'artpulse-management'),
        ];
        register_taxonomy('organization_category', 'artpulse_org', [
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'rewrite' => ['slug' => 'organization-category'],
            'show_in_rest' => true,
        ]);
    }
}
