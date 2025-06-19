<?php
namespace ArtPulse\Admin;

class AdminColumnsTaxonomies {

    public static function register() {
        // Artist specialties
        add_filter('manage_edit-ead_artist_columns', [self::class, 'artist_columns']);
        add_filter('manage_edit-ead_artist_sortable_columns', [self::class, 'artist_sortable_columns']);

        // Artwork styles
        add_filter('manage_edit-ead_artwork_columns', [self::class, 'artwork_columns']);
        add_filter('manage_edit-ead_artwork_sortable_columns', [self::class, 'artwork_sortable_columns']);

        // Event types
        add_filter('manage_edit-ead_event_columns', [self::class, 'event_columns']);
        add_filter('manage_edit-ead_event_sortable_columns', [self::class, 'event_sortable_columns']);

        // Organization categories
        add_filter('manage_edit-ead_organization_columns', [self::class, 'org_columns']);
        add_filter('manage_edit-ead_organization_sortable_columns', [self::class, 'org_sortable_columns']);

        // Render taxonomy columns
        add_action('manage_ead_artist_posts_custom_column', [self::class, 'render_artist_columns'], 10, 2);
        add_action('manage_ead_artwork_posts_custom_column', [self::class, 'render_artwork_columns'], 10, 2);
        add_action('manage_ead_event_posts_custom_column', [self::class, 'render_event_columns'], 10, 2);
        add_action('manage_ead_organization_posts_custom_column', [self::class, 'render_org_columns'], 10, 2);

        // Sort query modification
        add_action('pre_get_posts', [self::class, 'taxonomy_sort_order']);
    }

    // Columns registration

    public static function artist_columns($columns) {
        $columns['artist_specialty'] = __('Specialties', 'artpulse-management');
        return $columns;
    }
    public static function artwork_columns($columns) {
        $columns['artwork_style'] = __('Styles', 'artpulse-management');
        return $columns;
    }
    public static function event_columns($columns) {
        $columns['event_type'] = __('Event Types', 'artpulse-management');
        return $columns;
    }
    public static function org_columns($columns) {
        $columns['organization_category'] = __('Categories', 'artpulse-management');
        return $columns;
    }

    // Sorting columns

    public static function artist_sortable_columns($columns) {
        $columns['artist_specialty'] = 'artist_specialty';
        return $columns;
    }
    public static function artwork_sortable_columns($columns) {
        $columns['artwork_style'] = 'artwork_style';
        return $columns;
    }
    public static function event_sortable_columns($columns) {
        $columns['event_type'] = 'event_type';
        return $columns;
    }
    public static function org_sortable_columns($columns) {
        $columns['organization_category'] = 'organization_category';
        return $columns;
    }

    // Render columns content

    public static function render_artist_columns($column, $post_id) {
        if ($column === 'artist_specialty') {
            self::render_taxonomy_terms($post_id, 'artist_specialty');
        }
    }
    public static function render_artwork_columns($column, $post_id) {
        if ($column === 'artwork_style') {
            self::render_taxonomy_terms($post_id, 'artwork_style');
        }
    }
    public static function render_event_columns($column, $post_id) {
        if ($column === 'event_type') {
            self::render_taxonomy_terms($post_id, 'event_type');
        }
    }
    public static function render_org_columns($column, $post_id) {
        if ($column === 'organization_category') {
            self::render_taxonomy_terms($post_id, 'organization_category');
        }
    }

    private static function render_taxonomy_terms($post_id, $taxonomy) {
        $terms = get_the_terms($post_id, $taxonomy);
        if (empty($terms) || is_wp_error($terms)) {
            echo __('—', 'artpulse-management');
            return;
        }
        $term_links = [];
        foreach ($terms as $term) {
            $link = get_edit_term_link($term->term_id, $taxonomy);
            $term_links[] = sprintf('<a href="%s">%s</a>', esc_url($link), esc_html($term->name));
        }
        echo implode(', ', $term_links);
    }

    // Modify query to enable sorting by taxonomy

    public static function taxonomy_sort_order($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $orderby = $query->get('orderby');
        $taxonomies = [
            'artist_specialty' => 'ead_artist',
            'artwork_style' => 'ead_artwork',
            'event_type' => 'ead_event',
            'organization_category' => 'ead_organization',
        ];

        if (!isset($taxonomies[$orderby])) {
            return;
        }

        if ($query->get('post_type') !== $taxonomies[$orderby]) {
            return;
        }

        // Join term relationships for sorting by taxonomy name
        $taxonomy = $orderby;

        // Add join and orderby filters
        add_filter('posts_join', function ($join) use ($taxonomy) {
            global $wpdb;
            $join .= " LEFT JOIN {$wpdb->term_relationships} AS tr ON ({$wpdb->posts}.ID = tr.object_id) ";
            $join .= " LEFT JOIN {$wpdb->term_taxonomy} AS tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = '{$taxonomy}') ";
            $join .= " LEFT JOIN {$wpdb->terms} AS t ON (tt.term_id = t.term_id) ";
            return $join;
        });

        add_filter('posts_orderby', function ($orderby_sql) use ($wpdb) {
            return "t.name ASC";
        });
    }
}
