<?php

namespace ArtPulse\Frontend;

use ArtPulse\Community\FavoritesManager;
use WP_Query;
use WP_Post;

class OrgsDirectory
{
    private const TRANSIENT_PREFIX = 'ap_orgs_dir_';
    private const TRANSIENT_ALL_PREFIX = 'ap_orgs_dir_all_';

    public static function register(): void
    {
        add_shortcode('ap_orgs_directory', [self::class, 'render_shortcode']);
        add_action('init', [self::class, 'register_cache_busters']);
        add_action('wp_enqueue_scripts', [self::class, 'register_assets']);
    }

    public static function register_assets(): void
    {
        if (!wp_style_is('ap-orgs-directory', 'registered')) {
            wp_register_style(
                'ap-orgs-directory',
                plugins_url('assets/css/ap-orgs-directory.css', ARTPULSE_PLUGIN_FILE),
                [],
                defined('ARTPULSE_VERSION') ? ARTPULSE_VERSION : null
            );
        }

        if (!wp_script_is('ap-orgs-directory', 'registered')) {
            wp_register_script(
                'ap-orgs-directory',
                plugins_url('assets/js/ap-orgs-directory.js', ARTPULSE_PLUGIN_FILE),
                [],
                defined('ARTPULSE_VERSION') ? ARTPULSE_VERSION : null,
                true
            );
        }
    }

    public static function register_cache_busters(): void
    {
        $post_type = self::get_post_type_slug();

        add_action('save_post_' . $post_type, [self::class, 'flush_cache']);
        add_action('deleted_post', [self::class, 'maybe_flush_on_delete']);
    }

    public static function flush_cache(): void
    {
        global $wpdb;

        $like_patterns = [
            '_transient_' . self::TRANSIENT_PREFIX . '%',
            '_transient_timeout_' . self::TRANSIENT_PREFIX . '%',
            '_transient_' . self::TRANSIENT_ALL_PREFIX . '%',
            '_transient_timeout_' . self::TRANSIENT_ALL_PREFIX . '%',
        ];

        foreach ($like_patterns as $pattern) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $pattern
                )
            );
        }
    }

    public static function maybe_flush_on_delete(int $post_id): void
    {
        $post = get_post($post_id);
        if ($post instanceof WP_Post && $post->post_type === self::get_post_type_slug()) {
            self::flush_cache();
        }
    }

    public static function render_shortcode($atts = []): string
    {
        if (!post_type_exists(self::get_post_type_slug())) {
            return '';
        }

        $atts = shortcode_atts([
            'letters'        => 'A-Z|All',
            'per_page'       => 24,
            'taxonomy'       => '',
            'show_search'    => 'true',
            'show_favorites' => 'true',
        ], $atts, 'ap_orgs_directory');

        $letters = apply_filters('artpulse_orgs_directory_letters', self::parse_letters($atts['letters']));

        $per_page = (int) $atts['per_page'];
        if ($per_page <= 0) {
            $per_page = 24;
        }

        $taxonomy_filter = self::parse_taxonomy_attribute($atts['taxonomy']);

        $show_search = self::is_truthy($atts['show_search']);
        $show_favorites = self::is_truthy($atts['show_favorites']);

        $active_letter = isset($_GET['letter']) ? sanitize_text_field(wp_unslash($_GET['letter'])) : '';
        $active_letter = self::normalize_letter_query($active_letter, $letters);

        $search_term = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';

        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

        $post_type = self::get_post_type_slug();

        $all_items = self::get_all_items($post_type, $taxonomy_filter, $search_term);

        $pagination = self::get_paginated_ids(
            $all_items,
            $active_letter,
            $per_page,
            $page,
            $taxonomy_filter,
            $search_term
        );

        $favorites_lookup = [];
        if ($show_favorites && function_exists('is_user_logged_in') && is_user_logged_in() && class_exists(FavoritesManager::class)) {
            $user_favorites = FavoritesManager::get_user_favorites(get_current_user_id(), $post_type);
            foreach ($user_favorites as $favorite) {
                if ((int) $favorite->object_id > 0) {
                    $favorites_lookup[(int) $favorite->object_id] = true;
                }
            }
        }

        $cards_by_id = [];
        foreach ($all_items as $item) {
            $item_id = (int) $item['id'];
            $item['is_favorited'] = isset($favorites_lookup[$item_id]);
            $cards_by_id[$item_id] = self::prepare_card_markup($item);
        }

        $visible_ids = $pagination['ids'];
        $visible_markup = array_map(
            static function ($id) use ($cards_by_id) {
                return $cards_by_id[$id] ?? '';
            },
            $visible_ids
        );

        wp_enqueue_style('ap-orgs-directory');
        wp_localize_script(
            'ap-orgs-directory',
            'apOrgsDirectoryL10n',
            [
                'one'  => esc_html__('%s organization found', 'artpulse'),
                'many' => esc_html__('%s organizations found', 'artpulse'),
            ]
        );
        wp_enqueue_script('ap-orgs-directory');

        $current_url = self::get_current_url();
        $base_url = remove_query_arg(['letter', 'page'], $current_url);

        $total_results = (int) $pagination['total'];
        $total_pages = (int) $pagination['total_pages'];

        $json_data = [
            'items'      => array_values(array_map(
                static function ($item) use ($favorites_lookup) {
                    $item_id = (int) $item['id'];
                    $item['is_favorited'] = isset($favorites_lookup[$item_id]);
                    $item['html'] = self::prepare_card_markup($item);
                    return $item;
                },
                $all_items
            )),
            'activeLetter' => $active_letter,
            'searchTerm'   => $search_term,
        ];

        $output = '<div class="ap-orgs-dir" data-per-page="' . esc_attr((string) $per_page) . '" data-total-pages="' . esc_attr((string) $total_pages) . '">';
        $output .= self::render_alphabet_bar($letters, $active_letter, $base_url);

        if ($show_search) {
            $output .= self::render_search_form($search_term, $active_letter);
        }

        $output .= '<div class="ap-orgs-dir__summary">' . sprintf(
            /* translators: %s is number of organizations */
            esc_html(_n('%s organization found', '%s organizations found', $total_results, 'artpulse')),
            esc_html(number_format_i18n($total_results))
        ) . '</div>';

        $output .= '<div class="ap-grid" role="region" aria-live="polite" aria-busy="false">' . implode('', $visible_markup) . '</div>';
        $output .= '<div class="ap-orgs-dir__empty" role="note"' . ($total_results > 0 ? ' hidden' : '') . '>' . esc_html__('No organizations found.', 'artpulse') . '</div>';

        if ($total_pages > 1) {
            $output .= self::render_pagination($page, $total_pages, $base_url, $active_letter, $search_term);
        }

        $output .= '<script type="application/json" class="ap-orgs-dir__data">' . wp_json_encode($json_data) . '</script>';
        $output .= '</div>';

        return $output;
    }

    public static function get_normalized_letter(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '#';
        }

        $name = preg_replace('/^(?:the|a|an)\s+/i', '', $name) ?? $name;
        $name = trim($name);

        if ($name === '') {
            return '#';
        }

        if (function_exists('remove_accents')) {
            $name = remove_accents($name);
        }

        $first_char = strtoupper(substr($name, 0, 1));

        if (preg_match('/[A-Z]/', $first_char)) {
            return $first_char;
        }

        return '#';
    }

    private static function normalize_letter_query(string $letter, array $letters): string
    {
        if ($letter === '') {
            return in_array('All', $letters, true) ? 'All' : ($letters[0] ?? 'All');
        }

        $upper = strtoupper($letter);
        if ('ALL' === $upper) {
            return 'All';
        }

        if ('#' === $letter) {
            return '#';
        }

        foreach ($letters as $candidate) {
            if (strtoupper($candidate) === $upper) {
                return $candidate;
            }
        }

        return in_array('All', $letters, true) ? 'All' : ($letters[0] ?? 'All');
    }

    private static function parse_letters(string $letters): array
    {
        $letters = strtoupper($letters);
        $parts = preg_split('/[|,]/', $letters) ?: [];
        $output = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if ('A-Z' === $part) {
                $output = array_merge($output, range('A', 'Z'));
                continue;
            }

            if ('ALL' === $part) {
                $output[] = 'All';
                continue;
            }

            if ('#' === $part) {
                $output[] = '#';
                continue;
            }

            if (strlen($part) === 1 && ctype_alpha($part)) {
                $output[] = strtoupper($part);
            }
        }

        if (empty($output)) {
            $output = array_merge(range('A', 'Z'), ['All']);
        }

        $output = array_values(array_unique($output));

        if (!in_array('All', $output, true)) {
            array_unshift($output, 'All');
        }

        return $output;
    }

    private static function parse_taxonomy_attribute(string $taxonomy): array
    {
        $taxonomy = trim($taxonomy);
        if ($taxonomy === '') {
            return [];
        }

        $parts = array_map('trim', explode(':', $taxonomy));
        if (count($parts) < 2) {
            return [];
        }

        [$tax_name, $term_value] = $parts;
        if ($tax_name === '' || $term_value === '') {
            return [];
        }

        return [
            'taxonomy' => sanitize_key($tax_name),
            'field'    => is_numeric($term_value) ? 'term_id' : 'slug',
            'terms'    => is_numeric($term_value) ? [(int) $term_value] : [sanitize_title($term_value)],
        ];
    }

    private static function is_truthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower((string) $value);
        return !in_array($value, ['false', '0', 'no', 'off'], true);
    }

    private static function get_post_type_slug(): string
    {
        static $slug = null;

        if (null !== $slug) {
            return $slug;
        }

        $candidates = apply_filters('artpulse_orgs_directory_post_types', [
            'artpulse_org',
            'organisation',
            'organization',
        ]);

        foreach ($candidates as $candidate) {
            if (post_type_exists($candidate)) {
                $slug = $candidate;
                return $slug;
            }
        }

        $slug = 'artpulse_org';
        return $slug;
    }

    private static function get_all_items(string $post_type, array $taxonomy_filter, string $search_term): array
    {
        $cache_key = self::TRANSIENT_ALL_PREFIX . md5($post_type . '|' . self::serialize_tax_filter($taxonomy_filter) . '|' . $search_term);
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $query_args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ];

        if ($search_term !== '') {
            $query_args['s'] = $search_term;
        }

        if (!empty($taxonomy_filter)) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => $taxonomy_filter['taxonomy'],
                    'field'    => $taxonomy_filter['field'],
                    'terms'    => $taxonomy_filter['terms'],
                ],
            ];
        }

        $query_args = apply_filters('artpulse_orgs_directory_query_args', $query_args, [
            'taxonomy'   => $taxonomy_filter,
            'search'     => $search_term,
            'post_type'  => $post_type,
        ]);

        $query = new WP_Query($query_args);

        $items = [];

        foreach ($query->posts as $post_id) {
            $post_id = (int) $post_id;
            $display_name = self::get_display_name($post_id);
            $letter = self::get_normalized_letter($display_name);
            $permalink = get_permalink($post_id);
            $thumbnail = get_the_post_thumbnail_url($post_id, 'medium');
            $excerpt = self::get_excerpt($post_id);

            $badges = self::get_badges($post_id, $taxonomy_filter);

            $fields = [
                'id'           => $post_id,
                'name'         => $display_name,
                'letter'       => $letter,
                'permalink'    => $permalink,
                'thumbnail'    => $thumbnail,
                'excerpt'      => $excerpt,
                'badges'       => $badges,
                'search_index' => strtolower($display_name . ' ' . implode(' ', $badges) . ' ' . $excerpt),
                'sort_key'     => strtolower($display_name),
            ];

            $fields = apply_filters('artpulse_orgs_directory_fields', $fields, $post_id);

            $items[] = $fields;
        }

        usort(
            $items,
            static function ($a, $b) {
                return strcmp($a['sort_key'], $b['sort_key']);
            }
        );

        set_transient($cache_key, $items, HOUR_IN_SECONDS);

        return $items;
    }

    private static function get_paginated_ids(array $items, string $letter, int $per_page, int $page, array $taxonomy_filter, string $search_term): array
    {
        $cache_key = self::TRANSIENT_PREFIX . md5(
            $letter . '|' . self::serialize_tax_filter($taxonomy_filter) . '|' . $page . '|' . $per_page . '|' . $search_term
        );

        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $filtered = array_values(array_filter(
            $items,
            static function ($item) use ($letter) {
                if ('All' === $letter) {
                    return true;
                }

                return $item['letter'] === $letter;
            }
        ));

        $total = count($filtered);
        $total_pages = max(1, (int) ceil($total / $per_page));
        $page = max(1, min($page, $total_pages));

        $offset = ($page - 1) * $per_page;
        $paged_items = array_slice($filtered, $offset, $per_page);

        $result = [
            'ids'         => array_map(
                static function ($item) {
                    return (int) $item['id'];
                },
                $paged_items
            ),
            'total'       => $total,
            'total_pages' => $total_pages,
        ];

        set_transient($cache_key, $result, HOUR_IN_SECONDS);

        return $result;
    }

    private static function render_alphabet_bar(array $letters, string $active_letter, string $base_url): string
    {
        $output = '<ul class="ap-az" role="list">';
        foreach ($letters as $letter) {
            $is_active = ($letter === $active_letter);
            $url = add_query_arg(
                ['letter' => 'All' === $letter ? null : $letter, 'page' => null],
                $base_url
            );
            $output .= '<li>';
            $output .= '<a class="ap-az__link' . ($is_active ? ' is-active' : '') . '" data-letter="' . esc_attr($letter) . '" href="' . esc_url($url) . '" role="button"';
            if ($is_active) {
                $output .= ' aria-current="true"';
            }
            $output .= '>' . esc_html($letter) . '</a>';
            $output .= '</li>';
        }
        $output .= '</ul>';

        return $output;
    }

    private static function render_search_form(string $search_term, string $active_letter): string
    {
        $output = '<form class="ap-orgs-dir__search" method="get" role="search">';
        $output .= '<label class="screen-reader-text" for="ap-orgs-dir-search">' . esc_html__('Search organizations', 'artpulse') . '</label>';
        $output .= '<input type="search" id="ap-orgs-dir-search" name="search" value="' . esc_attr($search_term) . '" placeholder="' . esc_attr__('Search organizations…', 'artpulse') . '" />';
        $output .= '<input type="hidden" name="letter" value="' . esc_attr($active_letter) . '" />';
        $output .= '<button type="submit" class="ap-orgs-dir__submit">' . esc_html__('Search', 'artpulse') . '</button>';
        $output .= '</form>';

        return $output;
    }

    private static function render_pagination(int $current_page, int $total_pages, string $base_url, string $active_letter, string $search_term): string
    {
        $output = '<nav class="ap-orgs-dir__pagination" aria-label="' . esc_attr__('Organizations pagination', 'artpulse') . '">';
        $output .= '<ul class="ap-orgs-dir__pagination-list">';

        for ($page = 1; $page <= $total_pages; $page++) {
            $url = add_query_arg(
                [
                    'page'   => $page,
                    'letter' => 'All' === $active_letter ? null : $active_letter,
                    'search' => $search_term !== '' ? $search_term : null,
                ],
                $base_url
            );

            $output .= '<li>';
            $output .= '<a class="ap-orgs-dir__page-link' . ($page === $current_page ? ' is-active' : '') . '" href="' . esc_url($url) . '"' . ($page === $current_page ? ' aria-current="true"' : '') . '>' . esc_html((string) $page) . '</a>';
            $output .= '</li>';
        }

        $output .= '</ul>';
        $output .= '</nav>';

        return $output;
    }

    private static function get_current_url(): string
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '';
        if ($request_uri === '') {
            return '';
        }

        $scheme = is_ssl() ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_HOST'])) : '';
        return esc_url_raw($scheme . '://' . $host . $request_uri);
    }

    private static function get_display_name(int $post_id): string
    {
        $candidates = [
            'org_display_name',
            '_org_display_name',
            'ap_org_display_name',
            '_ap_org_display_name',
        ];

        foreach ($candidates as $meta_key) {
            $value = get_post_meta($post_id, $meta_key, true);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $title = get_post_field('post_title', $post_id);
        return is_string($title) ? $title : '';
    }

    private static function get_excerpt(int $post_id): string
    {
        $excerpt = get_post_field('post_excerpt', $post_id);
        if (!is_string($excerpt) || $excerpt === '') {
            $content = get_post_field('post_content', $post_id);
            $excerpt = is_string($content) ? wp_strip_all_tags($content) : '';
        }

        return wp_html_excerpt($excerpt, 140, '&hellip;');
    }

    private static function get_badges(int $post_id, array $taxonomy_filter): array
    {
        $badges = [];

        $address = get_post_meta($post_id, '_ap_org_address', true);
        if (is_string($address) && $address !== '') {
            $badges[] = $address;
        }

        $taxonomies = ['organization_category'];
        if (!empty($taxonomy_filter)) {
            $taxonomies[] = $taxonomy_filter['taxonomy'];
        }

        $taxonomies = array_unique(array_filter($taxonomies));

        foreach ($taxonomies as $taxonomy) {
            $terms = get_the_terms($post_id, $taxonomy);
            if (is_array($terms)) {
                foreach ($terms as $term) {
                    $badges[] = $term->name;
                }
            }
        }

        return array_values(array_unique(array_filter($badges, static function ($badge) {
            return is_string($badge) && $badge !== '';
        })));
    }

    private static function prepare_card_markup(array $item): string
    {
        $classes = ['ap-card'];
        $classes[] = 'ap-card--letter-' . sanitize_html_class(strtolower($item['letter']));

        $attributes = [
            'class'       => implode(' ', array_map('sanitize_html_class', $classes)),
            'data-letter' => $item['letter'],
            'data-search' => $item['search_index'],
        ];

        $html = '<article';
        foreach ($attributes as $key => $value) {
            $html .= ' ' . $key . '="' . esc_attr($value) . '"';
        }
        $html .= '>';

        if (!empty($item['thumbnail'])) {
            $html .= '<div class="ap-card__thumb"><img src="' . esc_url($item['thumbnail']) . '" alt="" loading="lazy" /></div>';
        }

        $html .= '<div class="ap-card__body">';
        $html .= '<h3 class="ap-card__title"><a href="' . esc_url($item['permalink']) . '">' . esc_html($item['name']) . '</a></h3>';

        if (!empty($item['excerpt'])) {
            $html .= '<p class="ap-card__excerpt">' . esc_html($item['excerpt']) . '</p>';
        }

        if (!empty($item['badges'])) {
            $html .= '<div class="ap-card__badges">';
            foreach ($item['badges'] as $badge) {
                $html .= '<span class="ap-badge">' . esc_html($badge) . '</span>';
            }
            $html .= '</div>';
        }

        if (!empty($item['is_favorited'])) {
            $html .= '<div class="ap-card__favorite">' . esc_html__('★ Favorited', 'artpulse') . '</div>';
        }

        $html .= '</div>';
        $html .= '</article>';

        return $html;
    }

    private static function serialize_tax_filter(array $taxonomy_filter): string
    {
        if (empty($taxonomy_filter)) {
            return '';
        }

        return $taxonomy_filter['taxonomy'] . ':' . implode(',', $taxonomy_filter['terms']);
    }
}
