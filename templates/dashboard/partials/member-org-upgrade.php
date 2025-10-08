<?php
/**
 * Member dashboard organisation upgrade card.
 *
 * @var array $org_upgrade
 */

$status = $org_upgrade['status'] ?? 'none';
$reason = $org_upgrade['reason'] ?? '';
$org_id = (int) ($org_upgrade['org_id'] ?? 0);
$dashboard_url = wp_get_referer() ?: home_url('/dashboard/');
?>
<div class="ap-dashboard-widget__section ap-dashboard-widget__section--org-upgrade">
    <h3><?php esc_html_e('Upgrade to Organization', 'artpulse-management'); ?></h3>

    <?php if ('approved' === $status) : ?>
        <p class="ap-dashboard-widget__status ap-dashboard-widget__status--approved">
            <?php esc_html_e('Your organization tools are now available.', 'artpulse-management'); ?>
        </p>
        <a class="ap-dashboard-button ap-dashboard-button--primary" href="<?php echo esc_url(add_query_arg('role', 'organization', home_url('/dashboard/'))); ?>">
            <?php esc_html_e('Open Organization Tools', 'artpulse-management'); ?>
        </a>
    <?php elseif ('pending' === $status) : ?>
        <p class="ap-dashboard-widget__status ap-dashboard-widget__status--pending">
            <?php esc_html_e('Upgrade request submitted. Awaiting admin review.', 'artpulse-management'); ?>
        </p>
    <?php elseif ('denied' === $status) : ?>
        <p class="ap-dashboard-widget__status ap-dashboard-widget__status--denied">
            <?php esc_html_e('Your previous request was denied.', 'artpulse-management'); ?>
        </p>
        <?php if ($reason !== '') : ?>
            <div class="ap-dashboard-widget__notice">
                <strong><?php esc_html_e('Reason:', 'artpulse-management'); ?></strong>
                <p><?php echo wp_kses_post($reason); ?></p>
            </div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ap-dashboard-form">
            <?php wp_nonce_field('ap-member-upgrade-request'); ?>
            <input type="hidden" name="action" value="ap_org_upgrade_resubmit" />
            <button type="submit" class="ap-dashboard-button ap-dashboard-button--primary">
                <?php esc_html_e('Resubmit Request', 'artpulse-management'); ?>
            </button>
        </form>
    <?php else : ?>
        <p><?php esc_html_e('Unlock the Organization toolkit to publish profiles and events for your collective.', 'artpulse-management'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ap-dashboard-form">
            <?php wp_nonce_field('ap-member-upgrade-request'); ?>
            <input type="hidden" name="action" value="ap_org_upgrade_request" />
            <button type="submit" class="ap-dashboard-button ap-dashboard-button--primary">
                <?php esc_html_e('Upgrade to Organization', 'artpulse-management'); ?>
            </button>
        </form>
    <?php endif; ?>
</div>
