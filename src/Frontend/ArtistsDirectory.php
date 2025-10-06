<?php
namespace ArtPulse\Frontend;

use ArtPulse\Core\Rewrites;
use ArtPulse\Core\TitleTools;
use WP_Post;
use WP_Query;

class ArtistsDirectory
{
    private const SHORTCODE = 'ap_artists_directory';
    private const POST_TYPE = 'artpulse_artist';
    private const CACHE_OPTION = 'ap_directory_cache_versions';
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    private static ?string $canonical_url = null;

    public static function register(): void
    {
        add_shortcode(self::SHORTCODE, [self::class, 'render_shortcode']);
        add_action('save_post_' . self::POST_TYPE, [self::class, 'flush_cache_on_save'], 10, 3);
        add_action('transition_post_status', [self::class, 'flush_cache_on_status'], 10, 3);
        add_action('set_object_terms', [self::class, 'flush_cache_on_terms'], 10, 6);
        add_action('updated_post_meta', [self::class, 'flush_cache_on_meta'], 10, 4);
        add_action('deleted_post_meta', [self::class, 'flush_cache_on_meta'], 10, 4);
        add_action('wp_head', [self::class, 'output_canonical'], 1);
    }

    public static function render_shortcode(array $atts = []): string
    {
        TitleTools::backfill_missing_letters(self::POST_TYPE);

        $state = self::parse_state($atts);
        self::set_canonical_url($state);

        $cache_key = apply_filters('ap_directory_cache_key', self::build_cache_key($state), $state, self::POST_TYPE);
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $query = new WP_Query(self::build_query_args($state));
        $post_ids = wp_list_pluck($query->posts, 'ID');
        if (!empty($post_ids)) {
            update_postmeta_cache($post_ids);
            update_object_term_cache($post_ids, [self::POST_TYPE]);
        }

        $html = self::render_directory($state, $query);
        set_transient($cache_key, $html, self::CACHE_TTL);

        return $html;
    }

    public static function output_canonical(): void
    {
        if (empty(self::$canonical_url)) {
            return;
        }

        echo '<link rel="canonical" href="' . esc_url(self::$canonical_url) . '" />' . "\n";
        self::$canonical_url = null;
    }

    private static function parse_state(array $atts): array
    {
        $atts = shortcode_atts([
            'per_page' => 24,
            'letter'   => '',
        ], $atts, self::SHORTCODE);

        $per_page = max(1, (int) $atts['per_page']);

        $requested_letter = get_query_var('ap_letter');
        if ('' === $requested_letter) {
            $requested_letter = get_query_var('letter');
        }
        if ('' === $requested_letter && isset($_GET['ap_letter'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $requested_letter = sanitize_text_field(wp_unslash((string) $_GET['ap_letter']));
        }
        if ('' === $requested_letter && isset($_GET['letter'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $requested_letter = sanitize_text_field(wp_unslash((string) $_GET['letter']));
        }
        if ('' === $requested_letter && !empty($atts['letter'])) {
            $requested_letter = (string) $atts['letter'];
        }

        $search = get_query_var('s');
        if ('' === $search && isset($_GET['s'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $search = sanitize_text_field(wp_unslash((string) $_GET['s']));
        }

        $tax_filters = self::parse_tax_filters();

        $paged = (int) get_query_var('paged');
        if ($paged < 1 && isset($_GET['paged'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $paged = (int) $_GET['paged'];
        }
        if ($paged < 1 && isset($_GET['page'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $paged = (int) $_GET['page'];
        }
        if ($paged < 1) {
            $paged = 1;
        }

        return [
            'letter'       => self::normalize_letter($requested_letter),
            'search'       => is_string($search) ? $search : '',
            'tax_filters'  => $tax_filters,
            'paged'        => $paged,
            'per_page'     => $per_page,
        ];
    }

    private static function build_query_args(array $state): array
    {
        $args = [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'posts_per_page' => $state['per_page'],
            'paged'          => $state['paged'],
        ];

        if ($state['search'] !== '') {
            $args['s'] = $state['search'];
        }

        if (!empty($state['tax_filters'])) {
            $tax_query = [];
            foreach ($state['tax_filters'] as $taxonomy => $terms) {
                $tax_query[] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => $terms,
                ];
            }
            if (!empty($tax_query)) {
                $args['tax_query'] = $tax_query;
            }
        }

        if ('all' !== $state['letter']) {
            $args['meta_query'] = [
                [
                    'key'     => TitleTools::META_KEY,
                    'value'   => $state['letter'],
                    'compare' => '=',
                ],
            ];
        }

        return $args;
    }

    private static function render_directory(array $state, WP_Query $query): string
    {
        $letters = self::get_letters();
        $heading = self::get_heading_label($state['letter']);
        $results_total = (int) $query->found_posts;
        $summary = self::format_summary($results_total);

        ob_start();
        ?>
        <div class="ap-directory" data-letter="<?php echo esc_attr($state['letter']); ?>">
            <a class="ap-directory__skip-link" href="#ap-artists-directory-results">
                <?php esc_html_e('Skip to results', 'artpulse-management'); ?>
            </a>
            <?php echo self::render_letter_nav($letters, $state); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo self::render_search_form($state); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <p class="ap-directory__announce" aria-live="polite" aria-atomic="true">
                <?php echo esc_html($summary); ?>
            </p>
            <div
                id="ap-artists-directory-results"
                class="ap-directory__results"
                tabindex="-1"
                role="region"
                aria-live="polite"
                aria-label="<?php echo esc_attr__('Artists directory results', 'artpulse-management'); ?>"
            >
                <h2 class="ap-directory__heading">
                    <?php echo esc_html($heading); ?>
                </h2>
                <?php
                if (!empty($query->posts)) {
                    echo '<ul class="ap-directory__list">';
                    foreach ($query->posts as $post) {
                        if ($post instanceof WP_Post) {
                            echo self::render_item($post); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                    }
                    echo '</ul>';
                } else {
                    echo '<p class="ap-directory__empty">' . esc_html__('No artists found for this selection.', 'artpulse-management') . '</p>';
                }
                ?>
            </div>
            <?php echo self::render_pagination($state, $query); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php
        wp_reset_postdata();

        return ob_get_clean();
    }

    private static function render_item(WP_Post $post): string
    {
        $data = [
            'thumbnail' => get_the_post_thumbnail($post->ID, 'medium_large', ['loading' => 'lazy']),
            'permalink' => get_permalink($post),
            'title'     => get_the_title($post),
            'excerpt'   => self::get_excerpt($post),
            'meta'      => self::get_item_meta($post),
        ];

        $data = apply_filters('ap_directory_item_data', $data, $post, self::POST_TYPE);

        $template = self::locate_template();

        $item_post = $post; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        $item_data = $data; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        $post_type = self::POST_TYPE; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

        ob_start();
        include $template;

        return ob_get_clean();
    }

    private static function get_excerpt(WP_Post $post): string
    {
        $excerpt = get_the_excerpt($post);
        if (!is_string($excerpt) || $excerpt === '') {
            $content = get_post_field('post_content', $post->ID);
            $excerpt = is_string($content) ? wp_strip_all_tags($content) : '';
        }

        return wp_trim_words($excerpt, 35, '…');
    }

    private static function get_item_meta(WP_Post $post): array
    {
        $meta = [];

        $org = get_post_meta($post->ID, '_ap_artist_org', true);
        if (is_numeric($org)) {
            $title = get_the_title((int) $org);
            if (is_string($title) && $title !== '') {
                $meta[] = $title;
            }
        } elseif (is_string($org) && $org !== '') {
            $meta[] = wp_strip_all_tags($org);
        }

        $role = get_post_meta($post->ID, '_ap_artist_role', true);
        if (is_string($role) && $role !== '') {
            $meta[] = wp_strip_all_tags($role);
        }

        $location = get_post_meta($post->ID, '_ap_artist_location', true);
        if (is_string($location) && $location !== '') {
            $meta[] = wp_strip_all_tags($location);
        }

        $specialties = get_the_terms($post, 'artist_specialty');
        if (is_array($specialties) && !empty($specialties)) {
            $names = array_filter(array_map(static function ($term) {
                return isset($term->name) ? wp_strip_all_tags($term->name) : '';
            }, $specialties));
            if (!empty($names)) {
                $meta[] = implode(', ', $names);
            }
        }

        $meta = array_values(array_filter($meta, static function ($value) {
            return is_string($value) && $value !== '';
        }));

        return apply_filters('ap_artists_directory_meta', $meta, $post);
    }

    private static function render_letter_nav(array $letters, array $state): string
    {
        $items = [];
        foreach ($letters as $letter) {
            $is_active = $letter === $state['letter'];
            $url = self::build_letter_url($letter, $state);
            $classes = ['ap-directory__letter-link'];
            if ($is_active) {
                $classes[] = 'is-active';
            }

            $items[] = sprintf(
                '<li class="ap-directory__letter-item"><a class="%s" href="%s"%s>%s</a></li>',
                esc_attr(implode(' ', $classes)),
                esc_url($url),
                $is_active ? ' aria-current="page"' : '',
                esc_html(self::format_letter_label($letter))
            );
        }

        return sprintf(
            '<nav class="ap-directory__letters" aria-label="%s"><ul class="ap-directory__letters-list">%s</ul></nav>',
            esc_attr__('Browse artists by letter', 'artpulse-management'),
            implode('', $items)
        );
    }

    private static function render_search_form(array $state): string
    {
        $action = Rewrites::get_directory_letter_url('artists', $state['letter']);
        $search_value = $state['search'];
        $permalink_structure = (string) get_option('permalink_structure');

        ob_start();
        ?>
        <form class="ap-directory__search" role="search" method="get" action="<?php echo esc_url($action); ?>">
            <label class="screen-reader-text" for="ap-artists-directory-search">
                <?php esc_html_e('Search artists', 'artpulse-management'); ?>
            </label>
            <input
                type="search"
                id="ap-artists-directory-search"
                name="s"
                value="<?php echo esc_attr($search_value); ?>"
                placeholder="<?php echo esc_attr__('Search artists', 'artpulse-management'); ?>"
                aria-controls="ap-artists-directory-results"
            />
            <?php if ('' === $permalink_structure) : ?>
                <input type="hidden" name="ap_letter" value="<?php echo esc_attr($state['letter']); ?>" />
            <?php endif; ?>
            <?php foreach ($state['tax_filters'] as $taxonomy => $terms) :
                foreach ($terms as $term) : ?>
                    <input type="hidden" name="tax[<?php echo esc_attr($taxonomy); ?>][]" value="<?php echo esc_attr($term); ?>" />
                <?php endforeach;
            endforeach; ?>
            <button type="submit">
                <?php esc_html_e('Search', 'artpulse-management'); ?>
            </button>
        </form>
        <?php

        return ob_get_clean();
    }

    private static function render_pagination(array $state, WP_Query $query): string
    {
        if ($query->max_num_pages <= 1) {
            return '';
        }

        $base_url = Rewrites::get_directory_letter_url('artists', $state['letter'], self::build_canonical_query_args($state, false));
        $base_url = remove_query_arg('paged', $base_url);

        $links = paginate_links([
            'base'      => add_query_arg('paged', '%#%', $base_url),
            'format'    => '',
            'current'   => max(1, (int) $state['paged']),
            'total'     => (int) $query->max_num_pages,
            'prev_text' => esc_html__('Previous', 'artpulse-management'),
            'next_text' => esc_html__('Next', 'artpulse-management'),
            'type'      => 'array',
        ]);

        if (empty($links)) {
            return '';
        }

        $items = array_map(static function ($link) {
            return '<li class="ap-directory__pagination-item">' . $link . '</li>';
        }, $links);

        return sprintf(
            '<nav class="ap-directory__pagination" aria-label="%s"><ul class="ap-directory__pagination-list">%s</ul></nav>',
            esc_attr__('Artists pagination', 'artpulse-management'),
            implode('', $items)
        );
    }

    private static function get_letters(): array
    {
        return array_merge(['all'], range('A', 'Z'), ['#']);
    }

    private static function normalize_letter(string $letter): string
    {
        $letter = trim(rawurldecode($letter));
        if ('' === $letter) {
            return 'A';
        }

        if (strtolower($letter) === 'all') {
            return 'all';
        }

        if ('#' === $letter) {
            return '#';
        }

        $first = strtoupper(substr($letter, 0, 1));
        if (preg_match('/^[A-Z]$/', $first)) {
            return $first;
        }

        return '#';
    }

    private static function format_letter_label(string $letter): string
    {
        if ('all' === $letter) {
            return __('All', 'artpulse-management');
        }

        return $letter;
    }

    private static function get_heading_label(string $letter): string
    {
        if ('all' === $letter) {
            return __('All Artists', 'artpulse-management');
        }

        if ('#' === $letter) {
            return __('Artists starting with #', 'artpulse-management');
        }

        return sprintf(
            /* translators: %s is the active letter. */
            __('Artists starting with %s', 'artpulse-management'),
            $letter
        );
    }

    private static function format_summary(int $total): string
    {
        return sprintf(
            /* translators: %s is the number of artists. */
            _n('%s artist found', '%s artists found', $total, 'artpulse-management'),
            number_format_i18n($total)
        );
    }

    private static function build_letter_url(string $letter, array $state): string
    {
        $query = self::build_canonical_query_args($state, false);

        return Rewrites::get_directory_letter_url('artists', $letter, $query);
    }

    private static function build_canonical_query_args(array $state, bool $include_paged = true): array
    {
        $query = [];
        if ($state['search'] !== '') {
            $query['s'] = $state['search'];
        }

        if (!empty($state['tax_filters'])) {
            $query['tax'] = $state['tax_filters'];
        }

        if ($include_paged && $state['paged'] > 1) {
            $query['paged'] = $state['paged'];
        }

        return $query;
    }

    private static function set_canonical_url(array $state): void
    {
        $url = Rewrites::get_directory_letter_url('artists', $state['letter'], self::build_canonical_query_args($state));
        self::$canonical_url = esc_url_raw($url);
    }

    private static function build_cache_key(array $state): string
    {
        $version = self::get_cache_version();
        $search_hash = $state['search'] !== '' ? md5($state['search']) : '0';
        $tax_hash = !empty($state['tax_filters']) ? md5(wp_json_encode($state['tax_filters'])) : '0';

        return sprintf(
            'ap_dir:%s:v%s:letter=%s:s=%s:tax=%s:page=%d:pp=%d',
            self::POST_TYPE,
            $version,
            rawurlencode($state['letter']),
            $search_hash,
            $tax_hash,
            $state['paged'],
            $state['per_page']
        );
    }

    private static function get_cache_version(): int
    {
        $versions = get_option(self::CACHE_OPTION, []);
        if (!is_array($versions)) {
            $versions = [];
        }

        return isset($versions[self::POST_TYPE]) ? (int) $versions[self::POST_TYPE] : 1;
    }

    private static function bump_cache_version(): void
    {
        $versions = get_option(self::CACHE_OPTION, []);
        if (!is_array($versions)) {
            $versions = [];
        }

        $current = isset($versions[self::POST_TYPE]) ? (int) $versions[self::POST_TYPE] : 1;
        $versions[self::POST_TYPE] = $current + 1;

        update_option(self::CACHE_OPTION, $versions, false);
    }

    public static function flush_cache_on_save(int $post_id, WP_Post $post, bool $update): void
    {
        if ($post->post_type !== self::POST_TYPE) {
            return;
        }

        self::bump_cache_version();
    }

    public static function flush_cache_on_status(string $new_status, string $old_status, WP_Post $post): void
    {
        if ($post->post_type !== self::POST_TYPE) {
            return;
        }

        if ($new_status !== $old_status) {
            self::bump_cache_version();
        }
    }

    public static function flush_cache_on_terms(int $object_id, array $terms, array $tt_ids, string $taxonomy, bool $append, array $old_tt_ids): void
    {
        if (get_post_type($object_id) !== self::POST_TYPE) {
            return;
        }

        self::bump_cache_version();
    }

    public static function flush_cache_on_meta($meta_id, int $object_id, $meta_key = '', $meta_value = ''): void
    {
        if (get_post_type($object_id) !== self::POST_TYPE) {
            return;
        }

        self::bump_cache_version();
    }

    private static function parse_tax_filters(): array
    {
        $filters = [];

        $valid_taxonomies = get_object_taxonomies(self::POST_TYPE, 'names');
        if (!is_array($valid_taxonomies)) {
            $valid_taxonomies = [];
        }
        $valid_taxonomies = array_filter(array_map('sanitize_key', $valid_taxonomies));
        $valid_taxonomies = array_fill_keys($valid_taxonomies, true);
        if (!isset($_GET['tax'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return $filters;
        }

        $raw = wp_unslash($_GET['tax']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!is_array($raw)) {
            return $filters;
        }

        foreach ($raw as $taxonomy => $terms) {
            $taxonomy = sanitize_key($taxonomy);
            if ('' === $taxonomy || !isset($valid_taxonomies[$taxonomy])) {
                continue;
            }

            $terms = is_array($terms) ? $terms : explode(',', (string) $terms);
            $terms = array_filter(array_map(static function ($term) {
                $term = is_string($term) ? sanitize_title($term) : '';
                return $term;
            }, $terms));

            if (!empty($terms)) {
                $filters[$taxonomy] = array_values($terms);
            }
        }

        return $filters;
    }

    private static function locate_template(): string
    {
        $candidates = [
            'artpulse/directory-item-' . self::POST_TYPE . '.php',
            'artpulse/directory-item.php',
        ];

        $template = locate_template($candidates);
        if (!is_string($template) || '' === $template) {
            $template = plugin_dir_path(ARTPULSE_PLUGIN_FILE) . 'templates/partials/directory-item.php';
        }

        return $template;
    }
}
