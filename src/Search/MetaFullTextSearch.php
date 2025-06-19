<?php
namespace ArtPulse\Search;

class MetaFullTextSearch {
    public static function register() {
        add_filter('posts_search', [self::class, 'posts_search_meta'], 10, 2);

        // REST API filters for meta search on all CPTs
        foreach (['ead_artist', 'ead_artwork', 'ead_event', 'ead_organization'] as $post_type) {
            add_filter("rest_{$post_type}_query", function($args, $request) use ($post_type) {
                return self::rest_meta_search_filter($args, $request, $post_type);
            }, 10, 2);
        }
    }

    // Extend WP search to meta fields
    public static function posts_search_meta($search, $query) {
        global $wpdb;

        if (is_admin() || !$query->is_main_query()) {
            return $search;
        }

        $post_type = $query->get('post_type');
        if (!in_array($post_type, ['ead_artist', 'ead_artwork', 'ead_event', 'ead_organization'], true)) {
            return $search;
        }

        $search_term = $query->get('s');
        if (!$search_term) {
            return $search;
        }

        $meta_keys = self::get_meta_keys_for_post_type($post_type);
        if (empty($meta_keys)) {
            return $search;
        }

        $like = '%' . $wpdb->esc_like($search_term) . '%';

        $meta_search_sql = [];
        foreach ($meta_keys as $key) {
            $meta_search_sql[] = $wpdb->prepare("
                EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm
                    WHERE pm.post_id = {$wpdb->posts}.ID
                      AND pm.meta_key = %s
                      AND pm.meta_value LIKE %s
                )", $key, $like);
        }

        if ($meta_search_sql) {
            $search .= ' OR (' . implode(' OR ', $meta_search_sql) . ')';
        }

        return $search;
    }

    // REST API meta query filter for meta search
    public static function rest_meta_search_filter($args, $request, $post_type) {
        $meta_key = $request->get_param('meta_key');
        $meta_value = $request->get_param('meta_value');

        if ($meta_key && $meta_value && in_array($meta_key, self::get_meta_keys_for_post_type($post_type), true)) {
            $args['meta_query'] = [
                [
                    'key'     => $meta_key,
                    'value'   => $meta_value,
                    'compare' => 'LIKE',
                ]
            ];
        }

        return $args;
    }

    // Define searchable meta keys per post type
    private static function get_meta_keys_for_post_type(string $post_type): array {
        switch ($post_type) {
            case 'ead_artist':
                return [
                    'artist_name',
                    'artist_bio',
                    'artist_email',
                    'artist_specialties',
                ];
            case 'ead_artwork':
                return [
                    'artwork_title',
                    'artwork_artist',
                    'artwork_medium',
                    'artwork_description',
                    'artwork_tags',
                ];
            case 'ead_event':
                return [
                    'venue_name',
                    'event_organizer_name',
                    'event_street_address',
                    'event_city',
                ];
            case 'ead_organization':
                return [
                    'ead_org_type',
                    'ead_org_description',
                    'ead_org_city',
                    'ead_org_website',
                ];
            default:
                return [];
        }
    }
}

// Register on plugin load
add_action('init', [MetaFullTextSearch::class, 'register']);
