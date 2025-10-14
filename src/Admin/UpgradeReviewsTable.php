<?php

namespace ArtPulse\Admin;

use ArtPulse\Core\UpgradeReviewRepository;
use WP_List_Table;
use WP_Post;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class UpgradeReviewsTable extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'upgrade-review',
            'plural'   => 'upgrade-reviews',
            'ajax'     => false,
        ]);
    }

    public function get_columns(): array
    {
        return [
            'cb'         => '<input type="checkbox" />',
            'user'       => __('Member', 'artpulse-management'),
            'org'        => __('Organization Draft', 'artpulse-management'),
            'status'     => __('Status', 'artpulse-management'),
            'submitted'  => __('Submitted', 'artpulse-management'),
            'reason'     => __('Reason', 'artpulse-management'),
        ];
    }

    protected function get_sortable_columns(): array
    {
        return [
            'submitted' => ['date', true],
        ];
    }

    protected function column_cb($item): string
    {
        $id = (int) ($item['ID'] ?? 0);
        return sprintf('<input type="checkbox" name="review[]" value="%d" />', $id);
    }

    protected function column_user($item): string
    {
        $user = get_user_by('id', (int) $item['user_id']);
        if (!$user) {
            return esc_html__('Unknown user', 'artpulse-management');
        }

        $profile_url = get_edit_user_link($user->ID);
        $actions = [];

        if ($profile_url) {
            $actions['edit'] = sprintf('<a href="%s">%s</a>', esc_url($profile_url), esc_html__('View profile', 'artpulse-management'));
        }

        $approve_url = wp_nonce_url(
            add_query_arg(
                [
                    'action'    => 'ap_upgrade_review_action',
                    'review'    => $item['ID'],
                    'operation' => 'approve',
                ],
                admin_url('admin-post.php')
            ),
            'ap-upgrade-review-' . $item['ID']
        );

        $actions['approve'] = sprintf('<a href="%s" data-test="approve-upgrade">%s</a>', esc_url($approve_url), esc_html__('Approve', 'artpulse-management'));
        $actions['deny']    = sprintf(
            '<a href="%s" data-test="deny-upgrade">%s</a>',
            esc_url(
                wp_nonce_url(
                    add_query_arg(
                        [
                            'page'   => 'artpulse-upgrade-reviews',
                            'view'   => 'deny',
                            'review' => $item['ID'],
                        ],
                        admin_url('admin.php')
                    ),
                    'ap-upgrade-review-' . $item['ID']
                )
            ),
            esc_html__('Deny', 'artpulse-management')
        );

        $display_name = esc_html($user->display_name ?: $user->user_login);
        $email_markup = '';

        if ($user->user_email) {
            $email_markup = sprintf(
                '<span class="ap-upgrade-review__email"><a href="mailto:%1$s">%1$s</a></span>',
                esc_attr($user->user_email)
            );
        }

        return sprintf('<strong>%1$s</strong><br>%2$s%3$s', $display_name, $email_markup, $this->row_actions($actions));
    }

    protected function column_org($item): string
    {
        $post_id = (int) $item['post_id'];
        if (!$post_id) {
            return esc_html__('—', 'artpulse-management');
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return esc_html__('—', 'artpulse-management');
        }

        $edit_link = get_edit_post_link($post);
        if ($edit_link) {
            return sprintf('<a href="%s">%s</a>', esc_url($edit_link), esc_html($post->post_title));
        }

        return esc_html($post->post_title);
    }

    protected function column_status($item): string
    {
        $status = esc_html($item['status']);
        return sprintf('<span class="status-%1$s">%2$s</span>', sanitize_html_class($item['status']), $status);
    }

    protected function column_reason($item): string
    {
        $reason = $item['reason'] ?? '';
        if ($reason === '') {
            return '—';
        }

        return nl2br(esc_html($reason));
    }

    protected function column_default($item, $column_name)
    {
        return isset($item[$column_name]) ? esc_html((string) $item[$column_name]) : '';
    }

    protected function column_submitted($item): string
    {
        if (empty($item['date_gmt'])) {
            return '—';
        }

        $timestamp_gmt = strtotime($item['date_gmt'] . ' GMT');
        if (!$timestamp_gmt) {
            return '—';
        }

        $relative = human_time_diff($timestamp_gmt, current_time('timestamp', true));
        $absolute = get_date_from_gmt($item['date_gmt'], get_option('date_format') . ' ' . get_option('time_format'));

        return sprintf(
            '<span title="%1$s">%2$s</span>',
            esc_attr($absolute),
            esc_html(sprintf(__('%s ago', 'artpulse-management'), $relative))
        );
    }

    public function prepare_items(): void
    {
        $per_page = 20;
        $paged    = max(1, (int) ($_GET['paged'] ?? 1));
        $offset   = ($paged - 1) * $per_page;

        $args = [
            'post_type'      => UpgradeReviewRepository::POST_TYPE,
            'post_status'    => ['private', 'draft', 'publish'],
            'posts_per_page' => $per_page,
            'offset'         => $offset,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new \WP_Query($args);
        $items = [];

        foreach ($query->posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }

            $items[] = [
                'ID'        => $post->ID,
                'user_id'   => (int) get_post_meta($post->ID, UpgradeReviewRepository::META_USER, true),
                'post_id'   => (int) get_post_meta($post->ID, UpgradeReviewRepository::META_POST, true),
                'status'    => UpgradeReviewRepository::get_status($post),
                'reason'    => UpgradeReviewRepository::get_reason($post),
                'date_gmt'  => $post->post_date_gmt,
            ];
        }

        $this->items = $items;

        $this->set_pagination_args([
            'total_items' => (int) $query->found_posts,
            'per_page'    => $per_page,
        ]);
    }

    protected function get_bulk_actions(): array
    {
        return [];
    }
}
