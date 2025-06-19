<?php

namespace ArtPulse\Admin;

class EngagementDashboard
{
    public static function register()
    {
        add_action('admin_menu', [self::class, 'addMenu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function enqueueAssets()
    {
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
    }

    public static function addMenu()
    {
        add_submenu_page(
            'artpulse-settings',
            __('Engagement Dashboard', 'artpulse'),
            __('Engagement', 'artpulse'),
            'manage_options',
            'artpulse-engagement',
            [self::class, 'render']
        );
    }

    public static function render()
    {
        $activity_filter = sanitize_text_field($_GET['activity'] ?? '');
        $paged = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 20;

        $all_users = get_users([
            'role__in' => ['subscriber', 'artpulse_member', 'artpulse_pro', 'artpulse_org'],
            'orderby'  => 'registered',
            'order'    => 'DESC',
            'number'   => 9999,
        ]);

        $filtered = [];
        foreach ($all_users as $user) {
            $last_login = get_user_meta($user->ID, 'wp_last_login', true);
            $artworks = count_user_posts($user->ID, 'artwork');
            $events = count_user_posts($user->ID, 'event');
            $activity_score = $artworks + $events;

            $is_active = strtotime($last_login) > strtotime('-30 days') || $activity_score > 0;

            if ($activity_filter === 'active' && !$is_active) continue;
            if ($activity_filter === 'inactive' && $is_active) continue;

            $user->ap_last_login = $last_login;
            $user->ap_artworks = $artworks;
            $user->ap_events = $events;
            $user->ap_score = $activity_score;
            $user->ap_followers = get_user_meta($user->ID, 'ap_follower_count', true);
            $user->ap_following = get_user_meta($user->ID, 'ap_following_count', true);

            $filtered[] = $user;
        }

        usort($filtered, fn($a, $b) => $b->ap_score <=> $a->ap_score);

        $total = count($filtered);
        $paged_users = array_slice($filtered, ($paged - 1) * $per_page, $per_page);

        $weekly_logins = array_fill(0, 7, 0);
        $weekly_uploads = array_fill(0, 7, 0);

        foreach ($filtered as $user) {
            $login_time = strtotime($user->ap_last_login);
            if ($login_time >= strtotime('-7 days')) {
                $days_ago = 6 - floor((time() - $login_time) / 86400);
                if (isset($weekly_logins[$days_ago])) {
                    $weekly_logins[$days_ago]++;
                }
            }

            $upload_total = $user->ap_artworks + $user->ap_events;
            if ($upload_total > 0) {
                $weekly_uploads[6] += $upload_total;
            }
        }

        if (isset($_GET['ap_export_csv'])) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="artpulse-engagement.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Name', 'Email', 'Logins', 'Artworks', 'Events', 'Followers', 'Following', 'Score']);

            foreach ($filtered as $user) {
                fputcsv($output, [
                    $user->display_name ?: $user->user_login,
                    $user->user_email,
                    $user->ap_last_login,
                    $user->ap_artworks,
                    $user->ap_events,
                    $user->ap_followers,
                    $user->ap_following,
                    $user->ap_score,
                ]);
            }

            fclose($output);
            exit;
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Member Engagement Dashboard', 'artpulse'); ?></h1>

            <form method="get" style="margin-bottom: 20px;">
                <input type="hidden" name="page" value="artpulse-engagement" />
                <select name="activity">
                    <option value="" <?php selected($activity_filter, ''); ?>>All Users</option>
                    <option value="active" <?php selected($activity_filter, 'active'); ?>><?php esc_html_e('Active Only', 'artpulse'); ?></option>
                    <option value="inactive" <?php selected($activity_filter, 'inactive'); ?>><?php esc_html_e('Inactive Only', 'artpulse'); ?></option>
                </select>
                <button class="button">Filter</button>
                <a href="<?php echo esc_url(add_query_arg(['activity' => $activity_filter, 'ap_export_csv' => 1])); ?>" class="button button-secondary">Export CSV</a>
            </form>

            <!-- Inline Data Passing -->
            <script>
                window.apWeeklyLogins = <?php echo json_encode($weekly_logins); ?>;
                window.apWeeklyUploads = <?php echo json_encode($weekly_uploads); ?>;
            </script>

            <canvas id="apEngagementChart" height="120"></canvas>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const ctx = document.getElementById('apEngagementChart').getContext('2d');

                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: ["6d ago", "5d", "4d", "3d", "2d", "1d", "Today"],
                            datasets: [
                                {
                                    label: 'Logins',
                                    data: window.apWeeklyLogins, // Access data from window
                                    backgroundColor: 'rgba(54, 162, 235, 0.6)'
                                },
                                {
                                    label: 'Uploads',
                                    data: window.apWeeklyUploads, // Access data from window
                                    backgroundColor: 'rgba(255, 206, 86, 0.6)'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'top'
                                }
                            }
                        }
                    });
                });
            </script>

            <table class="widefat fixed striped">
                <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'artpulse'); ?></th>
                    <th><?php esc_html_e('Last Login', 'artpulse'); ?></th>
                    <th><?php esc_html_e('Artworks', 'artpulse'); ?></th>
                    <th><?php esc_html_e('Events', 'artpulse'); ?></th>
                    <th><?php esc_html_e('Followers', 'artpulse'); ?></th>
                    <th><?php esc_html_e('Following', 'artpulse'); ?></th>
                    <th><?php esc_html_e('Score', 'artpulse'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($paged_users as $user): ?>
                    <tr>
                        <td><a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>"><?php echo esc_html($user->display_name ?: $user->user_login); ?></a></td>
                        <td><?php echo esc_html($user->ap_last_login ? date_i18n(get_option('date_format'), strtotime($user->ap_last_login)) : '—'); ?></td>
                        <td><?php echo intval($user->ap_artworks); ?></td>
                        <td><?php echo intval($user->ap_events); ?></td>
                        <td><?php echo intval($user->ap_followers); ?></td>
                        <td><?php echo intval($user->ap_following); ?></td>
                        <td>
                            <?php echo esc_html($user->ap_score); ?>
                            <?php if ($user->ap_score >= 5): ?>
                                <span style="color:green">↑</span>
                            <?php elseif ($user->ap_score == 0): ?>
                                <span style="color:red">↓</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($paged_users)): ?>
                    <tr>
                        <td colspan="7">No members found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>

            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    $total_pages = ceil($total / $per_page);
                    $base = add_query_arg('paged', '%#%');

                    echo paginate_links([
                        'base' => $base,
                        'format' => '',
                        'current' => $paged,
                        'total' => $total_pages,
                    ]);
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
}