<?php

namespace ArtPulse\Admin;

use ArtPulse\Core\UpgradeAuditLog;
use DateTimeImmutable;
use DateTimeZone;
use WP_List_Table;
use WP_User;

if (!class_exists('\\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class UpgradeActivityPage
{
    private const PAGE_SLUG = 'artpulse-upgrade-activity';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'add_menu']);
    }

    public static function add_menu(): void
    {
        add_management_page(
            __('Upgrade Activity', 'artpulse-management'),
            __('Upgrade Activity', 'artpulse-management'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'artpulse-management'));
        }

        $filters   = self::collect_filters();
        $list_args = array_merge($filters['query'], ['limit' => 500]);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Upgrade Activity', 'artpulse-management') . '</h1>';

        if (!UpgradeAuditLog::table_exists()) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('The audit log table has not been created yet. Activate the plugin to create required tables.', 'artpulse-management') . '</p></div>';
        }

        self::render_filters($filters['inputs']);

        $table = new UpgradeActivityTable($list_args);
        $table->prepare_items();
        $table->display();

        echo '</div>';
    }

    /**
     * @return array{query:array<string,mixed>,inputs:array<string,string>}
     */
    private static function collect_filters(): array
    {
        $user_input = isset($_GET['filter_user']) ? trim((string) wp_unslash($_GET['filter_user'])) : '';
        $type_input = isset($_GET['filter_type']) ? trim((string) wp_unslash($_GET['filter_type'])) : '';
        $status_input = isset($_GET['filter_status']) ? trim((string) wp_unslash($_GET['filter_status'])) : '';
        $from_input = isset($_GET['filter_from']) ? trim((string) wp_unslash($_GET['filter_from'])) : '';
        $to_input = isset($_GET['filter_to']) ? trim((string) wp_unslash($_GET['filter_to'])) : '';

        $filters = [
            'user_id'  => $user_input !== '' ? absint($user_input) : 0,
            'type'     => $type_input !== '' ? sanitize_text_field($type_input) : '',
            'status'   => $status_input !== '' ? sanitize_text_field($status_input) : '',
            'date_from'=> self::normalise_date($from_input, false),
            'date_to'  => self::normalise_date($to_input, true),
        ];

        $query = array_filter($filters, static function ($value) {
            if (is_string($value)) {
                return '' !== $value;
            }

            return null !== $value && 0 !== $value;
        });

        return [
            'query'  => $query,
            'inputs' => [
                'user'   => $user_input,
                'type'   => $type_input,
                'status' => $status_input,
                'from'   => $from_input,
                'to'     => $to_input,
            ],
        ];
    }

    private static function render_filters(array $inputs): void
    {
        $types   = UpgradeAuditLog::get_distinct_values('event_type');
        $statuses = UpgradeAuditLog::get_distinct_values('status');

        echo '<form method="get" class="ap-upgrade-activity__filters">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '" />';

        echo '<fieldset>'; 
        echo '<legend class="screen-reader-text">' . esc_html__('Filter upgrade activity', 'artpulse-management') . '</legend>';

        echo '<label for="ap-filter-user">' . esc_html__('User ID', 'artpulse-management') . '</label> ';
        echo '<input type="number" min="0" id="ap-filter-user" name="filter_user" value="' . esc_attr($inputs['user']) . '" class="small-text" /> ';

        echo '<label for="ap-filter-type">' . esc_html__('Type', 'artpulse-management') . '</label> ';
        echo '<select id="ap-filter-type" name="filter_type">';
        echo '<option value="">' . esc_html__('All types', 'artpulse-management') . '</option>';
        foreach ($types as $type) {
            $selected = selected($inputs['type'], $type, false);
            echo '<option value="' . esc_attr($type) . '" ' . $selected . '>' . esc_html($type) . '</option>';
        }
        echo '</select> ';

        echo '<label for="ap-filter-status">' . esc_html__('Status', 'artpulse-management') . '</label> ';
        echo '<select id="ap-filter-status" name="filter_status">';
        echo '<option value="">' . esc_html__('All statuses', 'artpulse-management') . '</option>';
        foreach ($statuses as $status) {
            $selected = selected($inputs['status'], $status, false);
            echo '<option value="' . esc_attr($status) . '" ' . $selected . '>' . esc_html($status) . '</option>';
        }
        echo '</select> ';

        echo '<label for="ap-filter-from">' . esc_html__('From', 'artpulse-management') . '</label> ';
        echo '<input type="date" id="ap-filter-from" name="filter_from" value="' . esc_attr($inputs['from']) . '" /> ';

        echo '<label for="ap-filter-to">' . esc_html__('To', 'artpulse-management') . '</label> ';
        echo '<input type="date" id="ap-filter-to" name="filter_to" value="' . esc_attr($inputs['to']) . '" /> ';

        submit_button(__('Filter', 'artpulse-management'), 'primary', 'filter_action', false);
        echo ' <a class="button" href="' . esc_url(admin_url('tools.php?page=' . self::PAGE_SLUG)) . '">' . esc_html__('Reset', 'artpulse-management') . '</a>';
        echo '</fieldset>';

        echo '</form>';
    }

    private static function normalise_date(string $value, bool $end_of_day): ?string
    {
        if ('' === $value) {
            return null;
        }

        try {
            $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
            $date = new DateTimeImmutable($value, $timezone);
        } catch (\Exception $exception) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
            return null;
        }

        if ($end_of_day) {
            $date = $date->setTime(23, 59, 59);
        } else {
            $date = $date->setTime(0, 0, 0);
        }

        $utc = $date->setTimezone(new DateTimeZone('UTC'));

        return $utc->format('Y-m-d H:i:s');
    }
}

class UpgradeActivityTable extends WP_List_Table
{
    /**
     * @var array<string,mixed>
     */
    private $filters;

    /**
     * @var array<int,array<string,mixed>>
     */
    public $items = [];

    /**
     * @param array<string,mixed> $filters
     */
    public function __construct(array $filters)
    {
        parent::__construct([
            'plural' => 'upgrade-activity',
            'singular' => 'upgrade-entry',
            'ajax' => false,
        ]);

        $this->filters = $filters;
    }

    public function get_columns(): array
    {
        return [
            'created_at' => __('Date', 'artpulse-management'),
            'event_type' => __('Type', 'artpulse-management'),
            'status'     => __('Status', 'artpulse-management'),
            'user'       => __('User', 'artpulse-management'),
            'related_id' => __('Related ID', 'artpulse-management'),
            'context'    => __('Details', 'artpulse-management'),
        ];
    }

    public function prepare_items(): void
    {
        $entries = UpgradeAuditLog::get_entries($this->filters);
        $per_page = $this->get_items_per_page('ap_upgrade_activity_per_page', 20);
        $total_items = count($entries);
        $current_page = max(1, $this->get_pagenum());
        $offset = ($current_page - 1) * $per_page;

        $this->items = array_slice($entries, $offset, $per_page);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($total_items / $per_page),
        ]);
    }

    public function no_items(): void
    {
        esc_html_e('No upgrade activity found.', 'artpulse-management');
    }

    protected function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'created_at':
                $local = get_date_from_gmt($item['created_at'], 'Y-m-d H:i:s');
                $display = $local ? $local : $item['created_at'];
                return esc_html($display);
            case 'event_type':
            case 'status':
                return esc_html((string) $item[$column_name]);
            case 'related_id':
                $related = (int) $item['related_id'];
                return $related > 0 ? esc_html('#' . $related) : '&mdash;';
            case 'context':
                if (empty($item['context'])) {
                    return '&mdash;';
                }

                $json = wp_json_encode($item['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (false === $json) {
                    $json = '';
                }

                return '<pre class="ap-upgrade-activity__context">' . esc_html($json) . '</pre>';
            case 'user':
                return $this->format_user((int) $item['user_id']);
        }

        return '&mdash;';
    }

    protected function get_table_classes(): array
    {
        $classes = parent::get_table_classes();
        $classes[] = 'widefat';
        $classes[] = 'fixed';

        return $classes;
    }

    private function format_user(int $user_id): string
    {
        if ($user_id <= 0) {
            return '&mdash;';
        }

        $user = get_user_by('id', $user_id);
        if ($user instanceof WP_User) {
            $label = $user->display_name ?: $user->user_login;
            $link  = get_edit_user_link($user_id);

            if ($link) {
                return '<a href="' . esc_url($link) . '">' . esc_html($label) . '</a> <span class="description">#' . esc_html((string) $user_id) . '</span>';
            }

            return esc_html($label) . ' <span class="description">#' . esc_html((string) $user_id) . '</span>';
        }

        return esc_html('#' . $user_id);
    }
}
