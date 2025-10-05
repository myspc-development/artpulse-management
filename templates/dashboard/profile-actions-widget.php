<?php
/**
 * Dashboard profile actions widget template.
 *
 * @var array[] $profile_actions_data An array of profile action data indexed numerically.
 */

if (empty($profile_actions_data)) {
    echo '<p>' . esc_html__('Profile actions are currently unavailable.', 'artpulse') . '</p>';

    return;
}
?>
<div class="ap-dashboard-widget ap-dashboard-widget--profile-actions">
    <?php foreach ($profile_actions_data as $profile_action) :
        $label      = $profile_action['label'] ?? '';
        $create_url = $profile_action['create_url'] ?? '';
        $edit_url   = $profile_action['edit_url'] ?? '';
    ?>
        <div class="ap-dashboard-widget__section ap-dashboard-widget__section--profile-actions">
            <?php if ($label !== '') : ?>
                <h3 class="ap-dashboard-widget__section-title">
                    <?php echo esc_html(sprintf(__('Manage %s profile', 'artpulse'), $label)); ?>
                </h3>
            <?php endif; ?>

            <div class="ap-dashboard-widget__actions">
                <?php if (!empty($create_url)) : ?>
                    <a class="ap-dashboard-button ap-dashboard-button--primary" href="<?php echo esc_url($create_url); ?>">
                        <?php esc_html_e('Create profile', 'artpulse'); ?>
                    </a>
                <?php else : ?>
                    <p class="ap-dashboard-widget__action-description">
                        <?php esc_html_e('Profile creation is currently unavailable.', 'artpulse'); ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($edit_url)) : ?>
                    <a class="ap-dashboard-button ap-dashboard-button--secondary" href="<?php echo esc_url($edit_url); ?>">
                        <?php esc_html_e('Edit profile', 'artpulse'); ?>
                    </a>
                <?php else : ?>
                    <p class="ap-dashboard-widget__action-description">
                        <?php esc_html_e('We could not find a profile to edit yet.', 'artpulse'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
