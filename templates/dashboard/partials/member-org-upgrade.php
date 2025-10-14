<?php
/**
 * Member dashboard upgrade card for artist and organization roles.
 *
 * @var array $org_upgrade
 */

$artist_state = $org_upgrade['artist'] ?? [];
$org_state    = $org_upgrade['organization'] ?? [];

$artist_status = $artist_state['status'] ?? 'not_started';
$org_status    = $org_state['status'] ?? 'not_started';

$artist_reason = $artist_state['reason'] ?? '';
$org_reason    = $org_state['reason'] ?? '';

$artist_tools_url = !empty($artist_state['profile_url']) ? $artist_state['profile_url'] : add_query_arg('role', 'artist', home_url('/dashboard/'));
$org_tools_url    = !empty($org_state['org_url']) ? $org_state['org_url'] : add_query_arg('role', 'organization', home_url('/dashboard/'));

$admin_post_url = admin_url('admin-post.php');
?>
<div class="ap-dashboard-widget__section ap-dashboard-widget__section--profile-upgrade">
    <h3><?php esc_html_e('Upgrade your profile', 'artpulse-management'); ?></h3>
    <p><?php esc_html_e('Unlock publishing workflows tailored to artists and organizations.', 'artpulse-management'); ?></p>

    <div class="ap-dashboard-upgrade" data-upgrade-target="artist">
        <h4><?php esc_html_e('Become an Artist', 'artpulse-management'); ?></h4>

        <?php if ('approved' === $artist_status) : ?>
            <p class="ap-dashboard-widget__status ap-dashboard-widget__status--approved" data-test="artist-upgrade-status">
                <?php esc_html_e('Your artist tools are ready to use.', 'artpulse-management'); ?>
            </p>
            <a class="ap-dashboard-button ap-dashboard-button--primary" href="<?php echo esc_url($artist_tools_url); ?>">
                <?php esc_html_e('Open Artist Tools', 'artpulse-management'); ?>
            </a>
        <?php elseif ('requested' === $artist_status) : ?>
            <p class="ap-dashboard-widget__status ap-dashboard-widget__status--pending" data-test="artist-upgrade-status">
                <?php esc_html_e('Upgrade request submitted. Awaiting review.', 'artpulse-management'); ?>
            </p>
        <?php elseif ('denied' === $artist_status) : ?>
            <p class="ap-dashboard-widget__status ap-dashboard-widget__status--denied" data-test="artist-upgrade-status">
                <?php esc_html_e('Your previous request was denied.', 'artpulse-management'); ?>
            </p>
            <?php if ($artist_reason !== '') : ?>
                <div class="ap-dashboard-widget__notice">
                    <strong><?php esc_html_e('Reason:', 'artpulse-management'); ?></strong>
                    <p><?php echo wp_kses_post($artist_reason); ?></p>
                </div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url($admin_post_url); ?>" class="ap-dashboard-form">
                <?php wp_nonce_field('ap-member-upgrade-request', '_ap_nonce'); ?>
                <input type="hidden" name="action" value="ap_dashboard_upgrade" />
                <input type="hidden" name="upgrade_type" value="artist" />
                <button type="submit" class="ap-dashboard-button ap-dashboard-button--primary" data-test="artist-upgrade-button">
                    <?php esc_html_e('Resubmit Request', 'artpulse-management'); ?>
                </button>
            </form>
        <?php else : ?>
            <p><?php esc_html_e('Showcase your creative practice and build a public portfolio.', 'artpulse-management'); ?></p>
            <form method="post" action="<?php echo esc_url($admin_post_url); ?>" class="ap-dashboard-form">
                <?php wp_nonce_field('ap-member-upgrade-request', '_ap_nonce'); ?>
                <input type="hidden" name="action" value="ap_dashboard_upgrade" />
                <input type="hidden" name="upgrade_type" value="artist" />
                <button type="submit" class="ap-dashboard-button ap-dashboard-button--primary" data-test="artist-upgrade-button">
                    <?php esc_html_e('Become an Artist', 'artpulse-management'); ?>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="ap-dashboard-upgrade" data-upgrade-target="organization">
        <h4><?php esc_html_e('Register an Organization', 'artpulse-management'); ?></h4>

        <?php if ('approved' === $org_status) : ?>
            <p class="ap-dashboard-widget__status ap-dashboard-widget__status--approved" data-test="org-upgrade-status">
                <?php esc_html_e('Your organization workspace is available.', 'artpulse-management'); ?>
            </p>
            <a class="ap-dashboard-button ap-dashboard-button--primary" href="<?php echo esc_url($org_tools_url); ?>">
                <?php esc_html_e('Open Organization Tools', 'artpulse-management'); ?>
            </a>
        <?php elseif ('requested' === $org_status) : ?>
            <p class="ap-dashboard-widget__status ap-dashboard-widget__status--pending" data-test="org-upgrade-status">
                <?php esc_html_e('Upgrade request submitted. Awaiting admin review.', 'artpulse-management'); ?>
            </p>
        <?php elseif ('denied' === $org_status) : ?>
            <p class="ap-dashboard-widget__status ap-dashboard-widget__status--denied" data-test="org-upgrade-status">
                <?php esc_html_e('Your previous request was denied.', 'artpulse-management'); ?>
            </p>
            <?php if ($org_reason !== '') : ?>
                <div class="ap-dashboard-widget__notice">
                    <strong><?php esc_html_e('Reason:', 'artpulse-management'); ?></strong>
                    <p><?php echo wp_kses_post($org_reason); ?></p>
                </div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url($admin_post_url); ?>" class="ap-dashboard-form">
                <?php wp_nonce_field('ap-member-upgrade-request', '_ap_nonce'); ?>
                <input type="hidden" name="action" value="ap_dashboard_upgrade" />
                <input type="hidden" name="upgrade_type" value="organization" />
                <button type="submit" class="ap-dashboard-button ap-dashboard-button--primary" data-test="org-upgrade-button">
                    <?php esc_html_e('Resubmit Request', 'artpulse-management'); ?>
                </button>
            </form>
        <?php else : ?>
            <p><?php esc_html_e('Promote your events, highlight members, and grow your creative network.', 'artpulse-management'); ?></p>
            <form method="post" action="<?php echo esc_url($admin_post_url); ?>" class="ap-dashboard-form">
                <?php wp_nonce_field('ap-member-upgrade-request', '_ap_nonce'); ?>
                <input type="hidden" name="action" value="ap_dashboard_upgrade" />
                <input type="hidden" name="upgrade_type" value="organization" />
                <button type="submit" class="ap-dashboard-button ap-dashboard-button--primary" data-test="org-upgrade-button">
                    <?php esc_html_e('Register an Organization', 'artpulse-management'); ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
