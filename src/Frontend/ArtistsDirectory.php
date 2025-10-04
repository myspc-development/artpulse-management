<?php

namespace ArtPulse\Frontend;

class ArtistsDirectory
{
    private const SHORTCODE = 'ap_artists_directory';
    private const STYLE_HANDLE = 'ap-artists-directory';
    private const SCRIPT_HANDLE = 'ap-artists-directory';
    private const TRANSIENT_PREFIX = 'ap_artists_directory_';
    private const CACHE_KEYS_OPTION = 'ap_artists_directory_cache_keys';

    public static function register(): void
    {
        add_shortcode(self::SHORTCODE, [self::class, 'render_shortcode']);
        add_action('init', [self::class, 'register_assets']);
        add_action('wp_enqueue_scripts', [self::class, 'maybe_enqueue_assets']);

        add_action('save_post_artpulse_artist', [self::class, 'clear_cache'], 10, 0);
        add_action('trashed_post', [self::class, 'maybe_clear_cache']);
        add_action('deleted_post', [self::class, 'maybe_clear_cache']);
    }

    public static function register_assets(): void
    {
        $plugin_url = plugins_url('/', ARTPULSE_PLUGIN_FILE);

        wp_register_style(
            self::STYLE_HANDLE,
            $plugin_url . 'assets/css/ap-artists-directory.css',
            [],
            ARTPULSE_VERSION
        );

        wp_register_script(
            self::SCRIPT_HANDLE,
            $plugin_url . 'assets/js/ap-artists-directory.js',
            [],
            ARTPULSE_VERSION,
            true
        );
    }

    public static function maybe_enqueue_assets(): void
    {
        if (!is_singular()) {
            return;
        }

        $post_id = get_queried_object_id();
        if ($post_id && self::post_has_shortcode($post_id)) {
            wp_enqueue_style(self::STYLE_HANDLE);
            wp_enqueue_script(self::SCRIPT_HANDLE);
        }
    }

    public static function render_shortcode(array $atts = []): string
    {
        self::ensure_assets_enqueued();

        $atts = shortcode_atts(
            [
                'orderby' => 'title',
                'order' => 'ASC',
                'posts_per_page' => -1,
                'letter' => '',
            ],
            $atts,
            self::SHORTCODE
        );

        $requested_letter = strtoupper($atts['letter'] ?: sanitize_text_field(wp_unslash($_GET['letter'] ?? '')));
        $active_letter = $requested_letter ?: 'All';

        $query_args = [
            'post_type' => 'artpulse_artist',
            'post_status' => 'publish',
            'orderby' => sanitize_key($atts['orderby']),
            'order' => strtoupper($atts['order']) === 'DESC' ? 'DESC' : 'ASC',
            'posts_per_page' => (int) $atts['posts_per_page'],
            'fields' => 'ids',
        ];

        $query_args = apply_filters('artpulse_artists_directory_args', $query_args, $atts);

        $cache_key = self::get_cache_key($query_args);
        $artists = get_transient($cache_key);

        if (false === $artists) {
            $artist_posts = get_posts($query_args);
            $artists = [];

            foreach ($artist_posts as $post_id) {
                $post = get_post($post_id);
                if (!$post instanceof \WP_Post) {
                    continue;
                }

                $organization = get_post_meta($post->ID, '_ap_artist_org', true);
                $role = get_post_meta($post->ID, '_ap_artist_role', true);
                $location = get_post_meta($post->ID, '_ap_artist_location', true);

                $fields = [
                    'thumbnail' => get_the_post_thumbnail($post->ID, 'medium'),
                    'name' => sprintf(
                        '<a href="%s">%s</a>',
                        esc_url(get_permalink($post)),
                        esc_html(get_the_title($post))
                    ),
                    'organization' => is_scalar($organization) ? sanitize_text_field((string) $organization) : $organization,
                    'role' => is_scalar($role) ? sanitize_text_field((string) $role) : $role,
                    'location' => is_scalar($location) ? sanitize_text_field((string) $location) : $location,
                ];

                $fields = apply_filters('artpulse_artists_directory_fields', $fields, $post);

                $artists[] = [
                    'id' => $post->ID,
                    'title' => get_the_title($post),
                    'fields' => $fields,
                ];
            }

            set_transient($cache_key, $artists, 12 * HOUR_IN_SECONDS);
            self::remember_cache_key($cache_key);
        }

        if (empty($artists)) {
            return '<p class="ap-artists-directory-empty">' . esc_html__('No artists found at this time.', 'artpulse') . '</p>';
        }

        $groups = self::group_artists_by_letter($artists);
        $letters = array_merge(['All'], range('A', 'Z'), ['#']);
        $active_letter = in_array($active_letter, $letters, true) ? $active_letter : 'All';

        $has_results_for_active = 'All' === $active_letter
            ? !empty($artists)
            : !empty($groups[$active_letter] ?? []);

        ob_start();
        ?>
        <div class="ap-artists-directory" data-active-letter="<?php echo esc_attr($active_letter); ?>" data-default-letter="<?php echo esc_attr($active_letter); ?>">
            <nav class="ap-directory-filter" aria-label="<?php esc_attr_e('Filter artists alphabetically', 'artpulse'); ?>">
                <ul class="ap-directory-filter__list">
                    <?php foreach ($letters as $letter) :
                        if ('#' === $letter && empty($groups['#'] ?? [])) {
                            continue;
                        }
                        $is_active = $letter === $active_letter;
                        $url = 'All' === $letter
                            ? esc_url(remove_query_arg('letter'))
                            : esc_url(add_query_arg('letter', $letter));
                        ?>
                        <li class="ap-directory-filter__item">
                            <a
                                href="<?php echo $url; ?>"
                                class="ap-directory-filter__control<?php echo $is_active ? ' is-active' : ''; ?>"
                                role="button"
                                data-letter="<?php echo esc_attr($letter); ?>"
                                aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>"
                            >
                                <?php echo 'All' === $letter ? esc_html__('All', 'artpulse') : esc_html($letter); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <div class="ap-directory-results" aria-live="polite" aria-busy="false">
                <?php foreach ($groups as $letter => $items) :
                    $is_visible = 'All' === $active_letter || $letter === $active_letter;
                    $hidden_attr = $is_visible ? '' : ' hidden';
                    ?>
                    <section class="ap-directory-section" data-letter="<?php echo esc_attr($letter); ?>"<?php echo $hidden_attr; ?>>
                        <h2 class="ap-directory-heading">
                            <?php echo 'All' === $letter ? esc_html__('All Artists', 'artpulse') : esc_html($letter); ?>
                        </h2>
                        <div class="ap-directory-grid">
                            <?php foreach ($items as $artist) :
                                $fields = $artist['fields'];
                                ?>
                                <article class="ap-artist-card">
                                    <?php if (!empty($fields['thumbnail'])) : ?>
                                        <div class="ap-artist-card__thumb">
                                            <?php echo wp_kses_post($fields['thumbnail']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="ap-artist-card__body">
                                        <h3 class="ap-artist-card__title">
                                            <?php echo isset($fields['name']) ? wp_kses_post($fields['name']) : esc_html($artist['title']); ?>
                                        </h3>
                                        <?php if (!empty($fields['organization']) || !empty($fields['role']) || !empty($fields['location'])) : ?>
                                            <ul class="ap-artist-card__meta">
                                                <?php if (!empty($fields['organization'])) : ?>
                                                    <li class="ap-artist-card__meta-item ap-artist-card__meta-item--organization">
                                                        <?php echo wp_kses_post(is_array($fields['organization']) ? implode(' ', $fields['organization']) : $fields['organization']); ?>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if (!empty($fields['role'])) : ?>
                                                    <li class="ap-artist-card__meta-item ap-artist-card__meta-item--role">
                                                        <?php echo wp_kses_post(is_array($fields['role']) ? implode(' ', $fields['role']) : $fields['role']); ?>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if (!empty($fields['location'])) : ?>
                                                    <li class="ap-artist-card__meta-item ap-artist-card__meta-item--location">
                                                        <?php echo wp_kses_post(is_array($fields['location']) ? implode(' ', $fields['location']) : $fields['location']); ?>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
                <p class="ap-directory-empty"<?php echo $has_results_for_active ? ' hidden' : ''; ?>><?php esc_html_e('No artists match the selected letter.', 'artpulse'); ?></p>
            </div>
        </div>
        <?php
        return trim((string) ob_get_clean());
    }

    public static function clear_cache(?int $post_id = null): void
    {
        $keys = get_option(self::CACHE_KEYS_OPTION, []);
        if (!is_array($keys)) {
            $keys = [];
        }

        foreach ($keys as $key) {
            delete_transient($key);
        }

        update_option(self::CACHE_KEYS_OPTION, []);
    }

    public static function maybe_clear_cache(int $post_id): void
    {
        if ('artpulse_artist' === get_post_type($post_id)) {
            self::clear_cache();
        }
    }

    private static function ensure_assets_enqueued(): void
    {
        if (!wp_style_is(self::STYLE_HANDLE, 'enqueued')) {
            wp_enqueue_style(self::STYLE_HANDLE);
        }
        if (!wp_script_is(self::SCRIPT_HANDLE, 'enqueued')) {
            wp_enqueue_script(self::SCRIPT_HANDLE);
        }
    }

    /**
     * @param array<string, mixed> $query_args
     */
    private static function get_cache_key(array $query_args): string
    {
        return self::TRANSIENT_PREFIX . md5(wp_json_encode($query_args));
    }

    private static function remember_cache_key(string $cache_key): void
    {
        $keys = get_option(self::CACHE_KEYS_OPTION, []);
        if (!is_array($keys)) {
            $keys = [];
        }

        if (!in_array($cache_key, $keys, true)) {
            $keys[] = $cache_key;
            update_option(self::CACHE_KEYS_OPTION, $keys);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $artists
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function group_artists_by_letter(array $artists): array
    {
        $groups = ['All' => []];

        foreach ($artists as $artist) {
            $title = $artist['title'];
            $first_character = function_exists('mb_substr')
                ? mb_substr($title, 0, 1)
                : substr($title, 0, 1);
            $first_letter = strtoupper((string) $first_character);
            if (!preg_match('/[A-Z]/', $first_letter)) {
                $first_letter = '#';
            }

            if (!isset($groups[$first_letter])) {
                $groups[$first_letter] = [];
            }

            $groups['All'][] = $artist;
            $groups[$first_letter][] = $artist;
        }

        ksort($groups);
        if (isset($groups['All'])) {
            $all = $groups['All'];
            unset($groups['All']);
            $groups = array_merge(['All' => $all], $groups);
        }

        return $groups;
    }

    private static function post_has_shortcode(int $post_id): bool
    {
        $post = get_post($post_id);
        if (!$post instanceof \WP_Post) {
            return false;
        }

        if (has_shortcode($post->post_content, self::SHORTCODE)) {
            return true;
        }

        return false;
    }
}
