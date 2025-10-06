<?php

namespace ArtPulse\Frontend;

use ArtPulse\Rest\EventsController;
use DateTimeInterface;
use WP_Block_Type_Registry;

class EventsCalendar
{
    private const SHORTCODE = 'ap_events';

    public static function register(): void
    {
        add_action('init', [self::class, 'register_shortcode']);
        add_action('init', [self::class, 'register_block']);
        add_action('init', [self::class, 'register_settings']);
        add_action('wp_enqueue_scripts', [self::class, 'register_assets']);
    }

    public static function register_shortcode(): void
    {
        add_shortcode(self::SHORTCODE, [self::class, 'render_shortcode']);
    }

    public static function register_block(): void
    {
        if (!function_exists('register_block_type') || WP_Block_Type_Registry::get_instance()->is_registered('artpulse/events')) {
            return;
        }

        register_block_type('artpulse/events', [
            'render_callback' => [self::class, 'render_block'],
            'attributes'      => self::get_block_attributes(),
        ]);
    }

    public static function register_settings(): void
    {
        register_setting(
            'artpulse_events',
            'ap_events_settings',
            [
                'type'              => 'array',
                'default'           => [
                    'default_layout'   => 'calendar',
                    'default_per_page' => 12,
                    'enable_ics'       => true,
                    'enable_favorites' => true,
                    'enable_recurrence'=> true,
                    'salient_classes'  => true,
                ],
                'show_in_rest'      => true,
                'sanitize_callback' => [self::class, 'sanitize_settings'],
            ]
        );
    }

    public static function register_assets(): void
    {
        $version = defined('ARTPULSE_VERSION') ? ARTPULSE_VERSION : time();
        $base    = plugins_url('', ARTPULSE_PLUGIN_FILE);

        wp_register_style(
            'ap-events-salient',
            $base . '/assets/css/ap-events-salient.css',
            [],
            $version
        );

        wp_register_script(
            'ap-events-calendar',
            $base . '/assets/js/ap-events-calendar.js',
            ['wp-api-fetch'],
            $version,
            true
        );

        wp_register_script(
            'ap-events-grid',
            $base . '/assets/js/ap-events-grid.js',
            ['wp-api-fetch'],
            $version,
            true
        );

        wp_localize_script(
            'ap-events-calendar',
            'APEventsConfig',
            [
                'restUrl'          => esc_url_raw(rest_url('artpulse/v1/events')),
                'icsUrl'           => esc_url_raw(rest_url('artpulse/v1/events.ics')),
                'singleEventUrl'   => esc_url_raw(rest_url('artpulse/v1/event')),
                'nonce'            => wp_create_nonce('wp_rest'),
                'favoritesEnabled' => is_user_logged_in(),
            ]
        );
    }

    public static function render_shortcode(array $atts = [], string $content = '', string $tag = self::SHORTCODE): string
    {
        $atts = shortcode_atts(self::get_shortcode_defaults(), $atts, $tag);
        return self::render($atts);
    }

    public static function render_block(array $attributes): string
    {
        $attributes = shortcode_atts(self::get_shortcode_defaults(), $attributes, self::SHORTCODE);
        return self::render($attributes);
    }

    private static function render(array $atts): string
    {
        $settings = get_option('ap_events_settings', []);
        $layout   = sanitize_key($atts['layout'] ?: ($settings['default_layout'] ?? 'calendar'));

        if (!in_array($layout, ['calendar', 'grid', 'tabs'], true)) {
            $layout = 'calendar';
        }

        $filters = self::resolve_filters($atts);
        $show_filters = filter_var($atts['show_filters'], FILTER_VALIDATE_BOOLEAN);

        $query   = [
            'start'     => $filters['start'],
            'end'       => $filters['end'],
            'category'  => $filters['category'],
            'org'       => $filters['org'],
            'favorites' => $filters['favorites'],
            'per_page'  => (int) $atts['per_page'],
            'page'      => isset($_GET['ap_events_page']) ? absint($_GET['ap_events_page']) : 1,
        ];

        $data = EventsController::fetch_events($query);

        wp_enqueue_style('ap-events-salient');
        wp_enqueue_script('ap-events-calendar');
        wp_enqueue_script('ap-events-grid');

        if ('calendar' === $layout) {
            return self::render_calendar($atts, $filters, $data, $show_filters);
        }

        if ('grid' === $layout) {
            return self::render_grid($atts, $filters, $data, $show_filters);
        }

        return self::render_tabs($atts, $filters, $data, $show_filters);
    }

    private static function render_calendar(array $atts, array $filters, array $data, bool $show_filters): string
    {
        $config = [
            'layout'      => 'calendar',
            'view'        => sanitize_key($atts['view']),
            'initialDate' => $atts['initialDate'],
            'filters'     => $filters,
            'events'      => $data['events'],
            'pagination'  => $data['pagination'],
        ];

        $filters_markup = $show_filters ? self::render_filters($filters) : '';

        return sprintf(
            '<div class="ap-events ap-events--calendar" data-ap-events="%s">%s<div class="ap-events__calendar" data-ap-events-target="calendar"></div></div>',
            esc_attr(wp_json_encode($config)),
            $filters_markup
        );
    }

    private static function render_grid(array $atts, array $filters, array $data, bool $show_filters): string
    {
        $filters_markup = $show_filters ? self::render_filters($filters) : '';
        $items_markup   = self::render_grid_items($data['events']);

        $config = [
            'layout'     => 'grid',
            'filters'    => $filters,
            'pagination' => $data['pagination'],
        ];

        return sprintf(
            '<div class="ap-events ap-events--grid nectar-portfolio" data-ap-events="%s">%s<ul class="portfolio-items ap-events-grid" data-ap-events-target="grid">%s</ul>%s</div>',
            esc_attr(wp_json_encode($config)),
            $filters_markup,
            $items_markup,
            self::render_pagination($data['pagination'])
        );
    }

    private static function render_tabs(array $atts, array $filters, array $data, bool $show_filters): string
    {
        $calendar = self::render_calendar($atts, $filters, $data, $show_filters);
        $grid     = self::render_grid($atts, $filters, $data, $show_filters);

        return '<div class="ap-events ap-events--tabs" data-ap-events-tabs="true">'
            . '<nav class="ap-events__tabs" aria-label="' . esc_attr__('Event view', 'artpulse-management') . '">' .
                '<button type="button" class="ap-events__tab is-active" data-ap-events-tab="calendar">' . esc_html__('Calendar', 'artpulse-management') . '</button>' .
                '<button type="button" class="ap-events__tab" data-ap-events-tab="grid">' . esc_html__('Grid', 'artpulse-management') . '</button>' .
            '</nav>' .
            '<div class="ap-events__tab-content" data-ap-events-pane="calendar">' . $calendar . '</div>' .
            '<div class="ap-events__tab-content is-hidden" data-ap-events-pane="grid">' . $grid . '</div>' .
        '</div>';
    }

    private static function render_filters(array $filters): string
    {
        $start = $filters['start'] ? gmdate('Y-m-d', strtotime($filters['start'])) : '';
        $end   = $filters['end'] ? gmdate('Y-m-d', strtotime($filters['end'])) : '';

        $category_options = self::get_category_options($filters['category']);
        $organizations    = self::get_organization_options($filters['org']);

        ob_start();
        ?>
        <form class="ap-events__filters" method="get" data-ap-events-filters="true">
            <fieldset>
                <legend class="screen-reader-text"><?php esc_html_e('Filter events', 'artpulse-management'); ?></legend>
                <label>
                    <span><?php esc_html_e('Start date', 'artpulse-management'); ?></span>
                    <input type="date" name="ap_event_start" value="<?php echo esc_attr($start); ?>" />
                </label>
                <label>
                    <span><?php esc_html_e('End date', 'artpulse-management'); ?></span>
                    <input type="date" name="ap_event_end" value="<?php echo esc_attr($end); ?>" />
                </label>
                <label>
                    <span><?php esc_html_e('Categories', 'artpulse-management'); ?></span>
                    <select name="ap_event_category[]" multiple>
                        <?php echo $category_options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('Organization', 'artpulse-management'); ?></span>
                    <select name="ap_event_org">
                        <?php echo $organizations; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </select>
                </label>
                <label class="ap-events__favorites">
                    <input type="checkbox" name="ap_event_favorites" value="1" <?php checked($filters['favorites']); ?> />
                    <span><?php esc_html_e('Favorites only', 'artpulse-management'); ?></span>
                </label>
                <button type="submit" class="ap-events__filter-submit"><?php esc_html_e('Apply filters', 'artpulse-management'); ?></button>
            </fieldset>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    private static function render_grid_items(array $events): string
    {
        $output = '';
        foreach ($events as $event) {
            $output .= self::render_grid_item($event);
        }

        return $output;
    }

    private static function render_grid_item(array $event): string
    {
        $template = plugin_dir_path(ARTPULSE_PLUGIN_FILE) . 'templates/events/grid-item.php';
        if (!file_exists($template)) {
            return '';
        }

        ob_start();
        $context = $event;
        include $template;

        return (string) ob_get_clean();
    }

    private static function render_pagination(array $pagination): string
    {
        if ($pagination['total_pages'] <= 1) {
            return '';
        }

        $current = (int) $pagination['current'];
        $total   = (int) $pagination['total_pages'];

        $items = '';
        for ($page = 1; $page <= $total; $page++) {
            $label = sprintf(__('Page %d', 'artpulse-management'), $page);
            $url   = add_query_arg('ap_events_page', $page);
            $items .= sprintf(
                '<li><a href="%s" class="%s" aria-label="%s">%d</a></li>',
                esc_url($url),
                $page === $current ? 'is-active' : '',
                esc_attr($label),
                $page
            );
        }

        return '<nav class="ap-events__pagination" aria-label="' . esc_attr__('Events pagination', 'artpulse-management') . '"><ul>' . $items . '</ul></nav>';
    }

    private static function get_shortcode_defaults(): array
    {
        return [
            'layout'        => 'calendar',
            'start'         => '',
            'end'           => '',
            'category'      => '',
            'org'           => '',
            'favorites'     => 'false',
            'view'          => 'dayGridMonth',
            'initialDate'   => '',
            'show_filters'  => 'true',
            'per_page'      => 12,
        ];
    }

    private static function get_block_attributes(): array
    {
        return [
            'layout'       => ['type' => 'string', 'default' => 'calendar'],
            'start'        => ['type' => 'string', 'default' => ''],
            'end'          => ['type' => 'string', 'default' => ''],
            'category'     => ['type' => 'string', 'default' => ''],
            'org'          => ['type' => 'string', 'default' => ''],
            'favorites'    => ['type' => 'boolean', 'default' => false],
            'view'         => ['type' => 'string', 'default' => 'dayGridMonth'],
            'initialDate'  => ['type' => 'string', 'default' => ''],
            'show_filters' => ['type' => 'boolean', 'default' => true],
            'per_page'     => ['type' => 'number', 'default' => 12],
        ];
    }

    private static function resolve_filters(array $atts): array
    {
        $start = isset($_GET['ap_event_start']) ? sanitize_text_field(wp_unslash($_GET['ap_event_start'])) : ($atts['start'] ?? '');
        $end   = isset($_GET['ap_event_end']) ? sanitize_text_field(wp_unslash($_GET['ap_event_end'])) : ($atts['end'] ?? '');
        $category = isset($_GET['ap_event_category']) ? (array) wp_unslash($_GET['ap_event_category']) : ($atts['category'] ? explode(',', (string) $atts['category']) : []);
        $category = array_filter(array_map('sanitize_text_field', $category));
        $org      = isset($_GET['ap_event_org']) ? absint($_GET['ap_event_org']) : (int) ($atts['org'] ?: 0);
        $favorites = isset($_GET['ap_event_favorites']) ? (bool) absint($_GET['ap_event_favorites']) : filter_var($atts['favorites'], FILTER_VALIDATE_BOOLEAN);

        return [
            'start'     => $start ? gmdate(DateTimeInterface::ATOM, strtotime($start)) : '',
            'end'       => $end ? gmdate(DateTimeInterface::ATOM, strtotime($end)) : '',
            'category'  => $category,
            'org'       => $org,
            'favorites' => $favorites,
        ];
    }

    private static function get_category_options(array $selected): string
    {
        $terms = get_terms([
            'taxonomy'   => 'artpulse_event_type',
            'hide_empty' => false,
        ]);

        $options = '';
        foreach ($terms as $term) {
            if (is_wp_error($term)) {
                continue;
            }

            $options .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr($term->slug),
                selected(in_array($term->slug, $selected, true), true, false),
                esc_html($term->name)
            );
        }

        return $options;
    }

    private static function get_organization_options(int $selected): string
    {
        $posts = get_posts([
            'post_type'      => 'artpulse_org',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ]);

        $options = '<option value="">' . esc_html__('All organizations', 'artpulse-management') . '</option>';
        foreach ($posts as $post_id) {
            $options .= sprintf(
                '<option value="%d" %s>%s</option>',
                (int) $post_id,
                selected($selected === (int) $post_id, true, false),
                esc_html(get_the_title($post_id))
            );
        }

        return $options;
    }

    public static function sanitize_settings($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return [
            'default_layout'   => in_array($value['default_layout'] ?? 'calendar', ['calendar', 'grid', 'tabs'], true) ? $value['default_layout'] : 'calendar',
            'default_per_page' => max(1, (int) ($value['default_per_page'] ?? 12)),
            'enable_ics'       => !empty($value['enable_ics']),
            'enable_favorites' => !empty($value['enable_favorites']),
            'enable_recurrence'=> !empty($value['enable_recurrence']),
            'salient_classes'  => !empty($value['salient_classes']),
        ];
    }
}
