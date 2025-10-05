<?php
/**
 * Dashboard widget template for ArtPulse role dashboards.
 *
 * @var array  $dashboard Prepared dashboard payload.
 * @var string $role      Role slug for the dashboard.
 */

$profile     = $dashboard['profile'] ?? [];
$metrics     = $dashboard['metrics'] ?? [];
$favorites   = array_slice($dashboard['favorites'] ?? [], 0, 5);
$follows     = array_slice($dashboard['follows'] ?? [], 0, 5);
$submissions   = $dashboard['submissions'] ?? [];
$membership    = $profile['membership'] ?? [];
$upgrades      = $dashboard['upgrades'] ?? [];
$upgrade_intro = $dashboard['upgrade_intro'] ?? '';

$metric_labels = [
    'favorites'             => esc_html__('Favorites', 'artpulse'),
    'follows'               => esc_html__('Follows', 'artpulse'),
    'submissions'           => esc_html__('Submissions', 'artpulse'),
    'pending_submissions'   => esc_html__('Pending', 'artpulse'),
    'published_submissions' => esc_html__('Published', 'artpulse'),
];
?>
<div class="ap-dashboard-widget" data-ap-dashboard-role="<?php echo esc_attr($role); ?>">
    <?php if (!empty($profile)) : ?>
        <div class="ap-dashboard-widget__section ap-dashboard-widget__section--profile">
            <h3><?php esc_html_e('Profile Summary', 'artpulse'); ?></h3>
            <p class="ap-dashboard-widget__name">
                <strong><?php echo esc_html($profile['display_name'] ?? ''); ?></strong>
            </p>

            <?php if (!empty($profile['email'])) : ?>
                <p class="ap-dashboard-widget__email">
                    <a href="mailto:<?php echo esc_attr($profile['email']); ?>"><?php echo esc_html($profile['email']); ?></a>
                </p>
            <?php endif; ?>

            <?php if (!empty($profile['profile_url'])) : ?>
                <p class="ap-dashboard-widget__profile-url">
                    <a href="<?php echo esc_url($profile['profile_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View public profile', 'artpulse'); ?></a>
                </p>
            <?php endif; ?>

            <?php if (!empty($membership['level']) || !empty($membership['expires_display'])) : ?>
                <p class="ap-dashboard-widget__membership">
                    <?php if (!empty($membership['level'])) : ?>
                        <span class="ap-dashboard-widget__membership-level"><?php echo esc_html($membership['level']); ?></span>
                    <?php endif; ?>

                    <?php if (!empty($membership['expires_display'])) : ?>
                        <span class="ap-dashboard-widget__membership-expiration"><?php echo esc_html(sprintf(esc_html__('Expires %s', 'artpulse'), $membership['expires_display'])); ?></span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($profile['bio'])) : ?>
                <p class="ap-dashboard-widget__bio"><?php echo wp_kses_post(wp_trim_words($profile['bio'], 40)); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($metrics)) : ?>
        <div class="ap-dashboard-widget__section ap-dashboard-widget__section--metrics">
            <h3><?php esc_html_e('Metrics', 'artpulse'); ?></h3>
            <ul>
                <?php foreach ($metrics as $key => $value) : ?>
                    <?php if (!array_key_exists($key, $metric_labels)) { continue; } ?>
                    <li>
                        <span class="ap-dashboard-widget__metric-label"><?php echo esc_html($metric_labels[$key]); ?>:</span>
                        <span class="ap-dashboard-widget__metric-value"><?php echo esc_html(number_format_i18n((int) $value)); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($favorites)) : ?>
        <div class="ap-dashboard-widget__section ap-dashboard-widget__section--favorites">
            <h3><?php esc_html_e('Recent Favorites', 'artpulse'); ?></h3>
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
        <div class="ap-dashboard-widget__section ap-dashboard-widget__section--follows">
            <h3><?php esc_html_e('Recent Follows', 'artpulse'); ?></h3>
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
        <div class="ap-dashboard-widget__section ap-dashboard-widget__section--submissions">
            <h3><?php esc_html_e('Submissions', 'artpulse'); ?></h3>
            <?php foreach ($submissions as $post_type => $details) : ?>
                <?php
                $label  = $details['label'] ?? $post_type;
                $counts = $details['counts'] ?? [];
                ?>
                <div class="ap-dashboard-widget__submission-group">
                    <strong><?php echo esc_html($label); ?></strong>
                    <?php if (!empty($counts)) : ?>
                        <ul>
                            <?php foreach ($counts as $status => $count) : ?>
                                <li>
                                    <span class="ap-dashboard-widget__submission-status"><?php echo esc_html(ucfirst($status)); ?>:</span>
                                    <span class="ap-dashboard-widget__submission-count"><?php echo esc_html(number_format_i18n((int) $count)); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p><?php esc_html_e('No submissions yet.', 'artpulse'); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php echo \ArtPulse\Core\RoleDashboards::renderUpgradeWidgetSection($upgrades, $upgrade_intro); ?>
</div>
