<?php
/**
 * Member dashboard upgrade card for artist and organization roles.
 *
 * @var array $org_upgrade
 */

$artist_state = $org_upgrade['artist'] ?? [];
$org_state    = $org_upgrade['organization'] ?? [];

$journeys = [
    'artist'       => [
        'label' => esc_html__('Artist journey', 'artpulse-management'),
        'state' => $artist_state,
        'anchor' => $artist_state['journey']['anchor'] ?? '#ap-journey-artist',
    ],
    'organization' => [
        'label' => esc_html__('Organization journey', 'artpulse-management'),
        'state' => $org_state,
        'anchor' => $org_state['journey']['anchor'] ?? '#ap-journey-organization',
    ],
];
?>
<section class="ap-dashboard-section ap-dashboard-section--journeys">
    <header class="ap-dashboard-section__header">
        <h3><?php esc_html_e('Next steps', 'artpulse-management'); ?></h3>
        <p><?php esc_html_e('Track progress toward unlocking artist and organization tools.', 'artpulse-management'); ?></p>
    </header>
    <div class="ap-dashboard-journeys">
        <?php foreach ($journeys as $slug => $journey) :
            $state            = $journey['state'];
            $status           = $state['status'] ?? 'not_started';
            $cta              = $state['cta'] ?? [];
            $reason           = $state['reason'] ?? '';
            $cta_label        = $cta['label'] ?? '';
            $cta_url          = $cta['url'] ?? '';
            $cta_variant      = $cta['variant'] ?? 'secondary';
            $cta_disabled     = !empty($cta['disabled']);
            $cta_mode         = $cta['mode'] ?? 'link';
            $cta_upgrade_type = $cta['upgrade_type'] ?? '';
            $journey_anchor   = $journey['anchor'] ?? '';
            $journey_id       = is_string($journey_anchor) && strpos($journey_anchor, '#') === 0 ? substr($journey_anchor, 1) : '';
            $journey_meta     = $state['journey']['portfolio'] ?? [];
            $journey_status   = $state['journey']['status_label'] ?? '';
            $progress         = max(0, min(100, (int) ($state['journey']['progress_percent'] ?? 0)));
            $status_notice    = '';
            $notice_variant   = '';

            if ('requested' === $status) {
                $status_notice  = __('Your request is pending review. We will email you when a moderator responds.', 'artpulse-management');
                $notice_variant = 'pending';
            } elseif ('denied' === $status) {
                $status_notice  = __('Your previous request was denied. Update your profile and submit a new request when ready.', 'artpulse-management');
                $notice_variant = 'denied';
            }
            ?>
            <article class="ap-dashboard-journey" data-journey="<?php echo esc_attr($slug); ?>"<?php echo $journey_id !== '' ? ' id="' . esc_attr($journey_id) . '"' : ''; ?>>
                <h4 class="ap-dashboard-journey__title"><?php echo esc_html($journey['label']); ?></h4>
                <?php if ($journey_status !== '') : ?>
                    <p class="ap-dashboard-journey__status"><?php echo esc_html($journey_status); ?></p>
                <?php endif; ?>
                <div class="ap-dashboard-progress" role="progressbar" aria-valuenow="<?php echo esc_attr($progress); ?>" aria-valuemin="0" aria-valuemax="100">
                    <span class="ap-dashboard-progress__bar" style="width: <?php echo esc_attr($progress); ?>%"></span>
                </div>
                <?php if (!empty($journey_meta['title'])) : ?>
                    <p class="ap-dashboard-journey__meta"><?php echo esc_html($journey_meta['title']); ?></p>
                <?php endif; ?>
                <?php if (!empty($state['journey']['description'])) : ?>
                    <p class="ap-dashboard-journey__description"><?php echo esc_html($state['journey']['description']); ?></p>
                <?php endif; ?>
                <?php if ($status_notice !== '') : ?>
                    <div class="ap-dashboard-journey__notice<?php echo $notice_variant !== '' ? ' ap-dashboard-journey__notice--' . esc_attr($notice_variant) : ''; ?>">
                        <p><?php echo esc_html($status_notice); ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($cta_label !== '') : ?>
                    <div class="ap-dashboard-journey__actions">
                        <?php if ('form' === $cta_mode && '' !== $cta_upgrade_type) : ?>
                            <form class="ap-dashboard-journey__form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                                <?php wp_nonce_field('ap-member-upgrade-request', '_ap_nonce', false); ?>
                                <input type="hidden" name="action" value="ap_dashboard_upgrade" />
                                <input type="hidden" name="upgrade_type" value="<?php echo esc_attr($cta_upgrade_type); ?>" />
                                <button type="submit" class="ap-dashboard-button ap-dashboard-button--<?php echo esc_attr($cta_variant); ?>"<?php echo $cta_disabled ? ' disabled="disabled" aria-disabled="true"' : ''; ?>>
                                    <?php echo esc_html($cta_label); ?>
                                </button>
                            </form>
                        <?php elseif ($cta_disabled || $cta_url === '') : ?>
                            <span class="ap-dashboard-button ap-dashboard-button--<?php echo esc_attr($cta_variant); ?> is-disabled" role="link" aria-disabled="true"><?php echo esc_html($cta_label); ?></span>
                        <?php else : ?>
                            <a class="ap-dashboard-button ap-dashboard-button--<?php echo esc_attr($cta_variant); ?>" href="<?php echo esc_url($cta_url); ?>"><?php echo esc_html($cta_label); ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($reason !== '') : ?>
                    <div class="ap-dashboard-journey__feedback">
                        <strong><?php echo esc_html('denied' === $status ? __('Moderator feedback', 'artpulse-management') : __('Feedback', 'artpulse-management')); ?>:</strong>
                        <p><?php echo wp_kses_post($reason); ?></p>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
