<?php
/**
 * Member dashboard upgrade card for artist and organization roles.
 *
 * @var array $org_upgrade
 */

use ArtPulse\Core\ProfileLinkHelpers;
use function ArtPulse\Core\get_support_url;

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
        <p><?php esc_html_e('Artist and organization access work side by side—unlock both roles whenever you need them.', 'artpulse-management'); ?></p>
    </header>
    <div class="ap-dashboard-journeys">
        <?php foreach ($journeys as $slug => $journey) :
            $state            = is_array($journey['state']) ? $journey['state'] : [];
            $status           = isset($state['status']) ? (string) $state['status'] : 'not_started';
            $cta              = is_array($state['cta'] ?? null) ? $state['cta'] : [];
            $reason           = isset($state['reason']) ? (string) $state['reason'] : '';
            $journey_anchor   = $journey['anchor'] ?? '';
            $journey_id       = is_string($journey_anchor) && strpos($journey_anchor, '#') === 0 ? substr($journey_anchor, 1) : '';
            $journey_meta     = $state['journey']['portfolio'] ?? [];
            $journey_status   = $state['journey']['status_label'] ?? '';
            $progress         = max(0, min(100, (int) ($state['journey']['progress_percent'] ?? 0)));
            $status_notice    = '';
            $notice_variant   = '';
            $exists           = !empty($state['exists']);

            $links        = ProfileLinkHelpers::assemble_links($state);
            $builder_url  = isset($links['edit']) ? (string) $links['edit'] : '';
            $public_url   = isset($links['view']) ? (string) $links['view'] : '';
            $preview_url  = isset($links['preview']) ? (string) $links['preview'] : '';
            $profile_url  = isset($state['profile_url']) ? (string) $state['profile_url'] : '';
            $support_url  = isset($state['support_url']) ? (string) $state['support_url'] : '';
            if ($support_url === '' && isset($state['supportUrl'])) {
                $support_url = (string) $state['supportUrl'];
            }

            $cta_label        = isset($cta['label']) ? (string) $cta['label'] : '';
            $cta_url          = isset($cta['url']) ? (string) $cta['url'] : '';
            $cta_variant      = isset($cta['variant']) ? (string) $cta['variant'] : 'secondary';
            $cta_disabled     = !empty($cta['disabled']);
            $cta_mode         = isset($cta['mode']) ? (string) $cta['mode'] : 'link';
            $cta_upgrade_type = isset($cta['upgrade_type']) ? (string) $cta['upgrade_type'] : '';

            if (!$exists) {
                $cta_label    = __('Start your profile', 'artpulse-management');
                $cta_url      = $builder_url;
                $cta_variant  = 'primary';
                $cta_mode     = 'link';
                $cta_disabled = $builder_url === '';
            } elseif (in_array($status, ['pending', 'pending_review'], true)) {
                $status_notice  = __('Your profile submission is under review. We will email you when a moderator responds.', 'artpulse-management');
                $notice_variant = 'pending';
                $cta_label      = __('Edit submission', 'artpulse-management');
                $cta_url        = $builder_url;
                $cta_variant    = 'secondary';
                $cta_mode       = 'link';
                $cta_disabled   = $builder_url === '';
            } elseif ($exists && 'denied' === $status) {
                $status_notice  = __('Your previous submission was denied. Update your profile and resubmit when you are ready.', 'artpulse-management');
                $notice_variant = 'denied';
                $cta_label      = __('Edit profile', 'artpulse-management');
                $cta_url        = $builder_url;
                $cta_variant    = 'primary';
                $cta_mode       = 'link';
                $cta_disabled   = $builder_url === '';
            } elseif ('pending_request' === $status) {
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
                    <?php
                    $secondary_links   = [];
                    $profile_link      = '';
                    $profile_link_label = '';

                    if ($public_url !== '') {
                        $profile_link       = $public_url;
                        $profile_link_label = __('View profile', 'artpulse-management');
                    } elseif ($preview_url !== '') {
                        $profile_link = $preview_url;
                        if (in_array($status, ['draft', 'in_progress', 'not_started', 'pending_request'], true)) {
                            $profile_link_label = __('Preview your draft profile', 'artpulse-management');
                        } elseif (in_array($status, ['pending', 'pending_review'], true)) {
                            $profile_link_label = __('Preview submitted profile', 'artpulse-management');
                        } else {
                            $profile_link_label = __('Preview profile', 'artpulse-management');
                        }
                    } elseif ($profile_url !== '') {
                        $profile_link       = $profile_url;
                        $profile_link_label = __('View profile', 'artpulse-management');
                    }

                    if ($profile_link !== '') {
                        $secondary_links[] = [
                            'url'   => $profile_link,
                            'label' => $profile_link_label,
                            'class' => 'ap-dashboard-journey__secondary-link',
                        ];
                    }

                    if ($exists && 'denied' === $status) {
                        if ($support_url === '') {
                            $support_url = get_support_url();
                        }
                        if ($support_url !== '') {
                            $secondary_links[] = [
                                'url'   => $support_url,
                                'label' => __('What to fix', 'artpulse-management'),
                                'class' => 'ap-dashboard-journey__secondary-link ap-dashboard-journey__secondary-link--support',
                            ];
                        }
                    }
                    ?>
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
                        <?php if (!empty($secondary_links)) : ?>
                            <?php foreach ($secondary_links as $secondary_link) : ?>
                                <a class="<?php echo esc_attr($secondary_link['class']); ?>" href="<?php echo esc_url($secondary_link['url']); ?>">
                                    <?php echo esc_html($secondary_link['label']); ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ('denied' === $status && $reason !== '') : ?>
                    <div class="ap-dashboard-journey__feedback">
                        <strong><?php esc_html_e('Moderator feedback', 'artpulse-management'); ?>:</strong>
                        <p><?php echo wp_kses_post($reason); ?></p>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
