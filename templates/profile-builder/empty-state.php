<?php
/**
 * Empty state for the unified profile builder.
 *
 * @var string $builder_type
 * @var array  $profile_state
 */
?>
<div class="ap-profile-builder ap-profile-builder--empty">
    <?php if ('artist' === $builder_type) : ?>
        <h2 class="ap-profile-builder__headline"><?php esc_html_e('Create your artist profile', 'artpulse-management'); ?></h2>
        <p><?php esc_html_e('Share your story, showcase your work, and publish your artist presence on ArtPulse.', 'artpulse-management'); ?></p>
        <?php if (!empty($profile_state['builder_url'])) : ?>
            <a class="button button-primary" href="<?php echo esc_url((string) $profile_state['builder_url']); ?>">
                <?php esc_html_e('Start building', 'artpulse-management'); ?>
            </a>
        <?php endif; ?>
    <?php else : ?>
        <h2 class="ap-profile-builder__headline"><?php esc_html_e('Organization profile access', 'artpulse-management'); ?></h2>
        <p><?php esc_html_e('Organization profiles are available once your upgrade request is approved. Check back soon or contact support for help.', 'artpulse-management'); ?></p>
        <?php if (!empty($profile_state['builder_url'])) : ?>
            <a class="button" href="<?php echo esc_url((string) $profile_state['builder_url']); ?>">
                <?php esc_html_e('Return to dashboard', 'artpulse-management'); ?>
            </a>
        <?php endif; ?>
    <?php endif; ?>
</div>
