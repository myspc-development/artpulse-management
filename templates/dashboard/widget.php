<?php
/**
 * Dashboard widget template for ArtPulse role dashboards.
 *
 * @var array  $dashboard Prepared dashboard payload.
 * @var string $role      Role slug for the dashboard.
 */

use ArtPulse\Core\RoleDashboards;

$profile       = $dashboard['profile'] ?? [];
$metrics       = $dashboard['metrics'] ?? [];
$quick_actions = $dashboard['quick_actions'] ?? [];
$journeys      = $dashboard['journeys'] ?? [];
$notifications = $dashboard['notifications'] ?? [];
$favorites     = array_slice($dashboard['favorites'] ?? [], 0, 5);
$follows       = array_slice($dashboard['follows'] ?? [], 0, 5);
$submissions   = $dashboard['submissions'] ?? [];
$membership    = $profile['membership'] ?? [];
$upgrades      = $dashboard['upgrades'] ?? [];
$upgrade_intro = $dashboard['upgrade_intro'] ?? '';
$layout        = $dashboard['layout'] ?? [];

$metric_labels = [
    'favorites'             => esc_html__('Favorites', 'artpulse-management'),
    'follows'               => esc_html__('Follows', 'artpulse-management'),
    'submissions'           => esc_html__('Submissions', 'artpulse-management'),
    'pending_submissions'   => esc_html__('Pending', 'artpulse-management'),
    'published_submissions' => esc_html__('Published', 'artpulse-management'),
];
?>
<div class="ap-dashboard-widget ap-dashboard-widget--modern" data-ap-dashboard-role="<?php echo esc_attr($role); ?>">
    <?php if (!empty($profile)) : ?>
        <section class="ap-dashboard-hero">
            <div class="ap-dashboard-hero__media">
                <?php if (!empty($profile['avatar'])) : ?>
                    <img class="ap-dashboard-hero__avatar" src="<?php echo esc_url($profile['avatar']); ?>" alt="" loading="lazy" decoding="async" />
                <?php else : ?>
                    <div class="ap-dashboard-hero__avatar ap-dashboard-hero__avatar--placeholder" aria-hidden="true"></div>
                <?php endif; ?>
            </div>
            <div class="ap-dashboard-hero__content">
                <h2 class="ap-dashboard-hero__name"><?php echo esc_html($profile['display_name'] ?? ''); ?></h2>
                <?php if (!empty($profile['email'])) : ?>
                    <p class="ap-dashboard-hero__email">
                        <a href="mailto:<?php echo esc_attr($profile['email']); ?>"><?php echo esc_html($profile['email']); ?></a>
                    </p>
                <?php endif; ?>
                <?php if (!empty($membership['level']) || !empty($membership['expires_display'])) : ?>
                    <p class="ap-dashboard-hero__membership">
                        <?php if (!empty($membership['level'])) : ?>
                            <span class="ap-dashboard-badge ap-dashboard-badge--tier"><?php echo esc_html($membership['level']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($membership['expires_display'])) : ?>
                            <span class="ap-dashboard-hero__meta"><?php echo esc_html(sprintf(esc_html__('Renews %s', 'artpulse-management'), $membership['expires_display'])); ?></span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($profile['bio'])) : ?>
                    <p class="ap-dashboard-hero__bio"><?php echo wp_kses_post(wp_trim_words($profile['bio'], 40)); ?></p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($notifications)) : ?>
        <div class="ap-dashboard-notifications" role="status" aria-live="polite">
            <?php foreach ($notifications as $notice) :
                $notice_type = $notice['type'] ?? 'info';
                $notice_anchor = $notice['anchor'] ?? '';
                $notice_message = $notice['message'] ?? '';
                ?>
                <div class="ap-dashboard-notice ap-dashboard-notice--<?php echo esc_attr($notice_type); ?>">
                    <p>
                        <?php echo wp_kses_post($notice_message); ?>
                        <?php if ($notice_anchor) : ?>
                            <a class="ap-dashboard-notice__link" href="<?php echo esc_url($notice_anchor); ?>"><?php esc_html_e('View details', 'artpulse-management'); ?></a>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($quick_actions)) : ?>
        <section class="ap-dashboard-quick-actions">
            <div class="ap-dashboard-section__header">
                <h3><?php esc_html_e('Quick actions', 'artpulse-management'); ?></h3>
                <p><?php esc_html_e('Stay on top of profile tasks and publishing steps.', 'artpulse-management'); ?></p>
            </div>
            <div class="ap-dashboard-quick-actions__grid">
                <?php foreach ($quick_actions as $action) :
                    $action_anchor = $action['anchor'] ?? '';
                    $action_id = '';
                    if (is_string($action_anchor) && strpos($action_anchor, '#') === 0) {
                        $action_id = substr($action_anchor, 1);
                    }
                    $action_context = $action;
                    include __DIR__ . '/partials/quick-action-card.php';
                endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="ap-dashboard-grid">
        <section class="ap-dashboard-section ap-dashboard-section--metrics">
            <header class="ap-dashboard-section__header">
                <h3><?php esc_html_e('Insights', 'artpulse-management'); ?></h3>
                <p><?php esc_html_e('Key activity across your roles.', 'artpulse-management'); ?></p>
            </header>
            <?php if (!empty($metrics)) : ?>
                <ul class="ap-dashboard-metrics" role="list">
                    <?php foreach ($metrics as $key => $value) :
                        if (!array_key_exists($key, $metric_labels)) {
                            continue;
                        }
                        ?>
                        <li class="ap-dashboard-metric">
                            <span class="ap-dashboard-metric__label"><?php echo esc_html($metric_labels[$key]); ?></span>
                            <span class="ap-dashboard-metric__value"><?php echo esc_html(number_format_i18n((int) $value)); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="ap-dashboard-empty"><?php esc_html_e('Metrics will appear here once you start engaging with the community.', 'artpulse-management'); ?></p>
            <?php endif; ?>
        </section>

        <section class="ap-dashboard-section ap-dashboard-section--activity">
            <header class="ap-dashboard-section__header">
                <h3><?php esc_html_e('Recent activity', 'artpulse-management'); ?></h3>
                <p><?php esc_html_e('A quick look at your favorites, follows, and submissions.', 'artpulse-management'); ?></p>
            </header>
            <div class="ap-dashboard-activity">
                <?php if (!empty($favorites)) : ?>
                    <div class="ap-dashboard-activity__group">
                        <h4><?php esc_html_e('Favorites', 'artpulse-management'); ?></h4>
                        <ul>
                            <?php foreach ($favorites as $favorite) : ?>
                                <li>
                                    <a href="<?php echo esc_url($favorite['permalink'] ?? '#'); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($favorite['title'] ?? ''); ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($follows)) : ?>
                    <div class="ap-dashboard-activity__group">
                        <h4><?php esc_html_e('Follows', 'artpulse-management'); ?></h4>
                        <ul>
                            <?php foreach ($follows as $follow) : ?>
                                <li>
                                    <a href="<?php echo esc_url($follow['permalink'] ?? '#'); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($follow['title'] ?? ''); ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($submissions)) : ?>
                    <div class="ap-dashboard-activity__group ap-dashboard-activity__group--submissions">
                        <h4><?php esc_html_e('Submissions', 'artpulse-management'); ?></h4>
                        <?php foreach ($submissions as $post_type => $details) :
                            $label  = $details['label'] ?? $post_type;
                            $counts = $details['counts'] ?? [];
                            $items  = $details['items'] ?? [];
                            ?>
                            <div class="ap-dashboard-submission">
                                <strong><?php echo esc_html($label); ?></strong>
                                <?php if (!empty($counts)) : ?>
                                    <ul class="ap-dashboard-submission__counts">
                                        <?php foreach ($counts as $status => $count) : ?>
                                            <li>
                                                <span class="ap-dashboard-submission__status"><?php echo esc_html(ucfirst($status)); ?></span>
                                                <span class="ap-dashboard-submission__value"><?php echo esc_html(number_format_i18n((int) $count)); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <?php if (!empty($items)) : ?>
                                    <ul class="ap-dashboard-submission__list">
                                        <?php foreach (array_slice($items, 0, 3) as $item) : ?>
                                            <li>
                                                <a href="<?php echo esc_url($item['permalink'] ?? '#'); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($item['title'] ?? ''); ?></a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($favorites) && empty($follows) && empty($submissions)) : ?>
                    <p class="ap-dashboard-empty"><?php esc_html_e('Your activity will appear here as you start engaging.', 'artpulse-management'); ?></p>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!empty($dashboard['org_upgrade'])) : ?>
            <?php
            $org_upgrade = $dashboard['org_upgrade'];
            $org_upgrade_template = dirname(__DIR__) . '/org-builder/member-upgrade-card.php';
            if (!file_exists($org_upgrade_template)) {
                $org_upgrade_template = __DIR__ . '/partials/member-org-upgrade.php';
            }
            if (file_exists($org_upgrade_template)) {
                include $org_upgrade_template;
            }
            ?>
        <?php endif; ?>

        <?php if (!empty($upgrades)) : ?>
            <section class="ap-dashboard-section ap-dashboard-section--upgrades">
                <?php echo RoleDashboards::renderUpgradeWidget($upgrades, $upgrade_intro); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </section>
        <?php endif; ?>
    </div>
</div>
