<?php
/**
 * Upgrade section template shared across dashboard widgets.
 *
 * @var string $section_title    Title for the upgrade section.
 * @var string $section_intro    Introductory copy for the upgrade section.
 * @var array  $section_upgrades List of available upgrades.
 */
?>
<div class="ap-dashboard-widget__section ap-dashboard-widget__section--upgrades ap-upgrade-widget ap-upgrade-widget--inline">
    <h3 class="ap-upgrade-widget__heading"><?php echo esc_html($section_title); ?></h3>

    <?php if ($section_intro !== '') : ?>
        <p class="ap-upgrade-widget__intro"><?php echo esc_html($section_intro); ?></p>
    <?php endif; ?>

    <div class="ap-upgrade-widget__list">
        <?php foreach ($section_upgrades as $upgrade) :
            $url = $upgrade['url'] ?? '';

            if ($url === '') {
                continue;
            }

            $cta = $upgrade['cta'] ?? __('Upgrade now', 'artpulse-management');
            $status = isset($upgrade['status']) ? sanitize_key((string) $upgrade['status']) : '';
            $status_label = isset($upgrade['status_label']) ? (string) $upgrade['status_label'] : '';
            $status_variant = isset($upgrade['status_variant']) ? sanitize_key((string) $upgrade['status_variant']) : '';
            $status_message = isset($upgrade['status_message']) ? (string) $upgrade['status_message'] : '';
            $denial_reason = '';

            if (isset($upgrade['denial_reason'])) {
                $denial_reason = (string) $upgrade['denial_reason'];
            } elseif (isset($upgrade['reason'])) {
                $denial_reason = (string) $upgrade['reason'];
            } elseif (isset($upgrade['review']['reason'])) {
                $denial_reason = (string) $upgrade['review']['reason'];
            }

            $review_id = 0;

            if (isset($upgrade['review_id'])) {
                $review_id = (int) $upgrade['review_id'];
            } elseif (isset($upgrade['request_id'])) {
                $review_id = (int) $upgrade['request_id'];
            } elseif (isset($upgrade['review']['id'])) {
                $review_id = (int) $upgrade['review']['id'];
            } elseif (isset($upgrade['review']['request_id'])) {
                $review_id = (int) $upgrade['review']['request_id'];
            }

            if ($status !== '') {
                if ($status_label === '') {
                    switch ($status) {
                        case 'pending':
                            $status_label = __('Pending', 'artpulse-management');
                            break;
                        case 'approved':
                            $status_label = __('Approved', 'artpulse-management');
                            break;
                        case 'denied':
                            $status_label = __('Denied', 'artpulse-management');
                            break;
                        default:
                            $status_label = ucfirst($status);
                            break;
                    }
                }

                if ($status_message === '') {
                    switch ($status) {
                        case 'pending':
                            $status_message = __('Your request is pending review. We will notify you once it has been processed.', 'artpulse-management');
                            break;
                        case 'approved':
                            $status_message = __('Your upgrade request has been approved. Enjoy your new tools.', 'artpulse-management');
                            break;
                        case 'denied':
                            $status_message = __('Your upgrade request was denied. Review the feedback below before resubmitting.', 'artpulse-management');
                            break;
                        default:
                            $status_message = __('Status update available for this request.', 'artpulse-management');
                            break;
                    }
                }
            }

            if ($status_variant === '') {
                switch ($status) {
                    case 'pending':
                        $status_variant = 'pending';
                        break;
                    case 'approved':
                        $status_variant = 'approved';
                        break;
                    case 'denied':
                        $status_variant = 'denied';
                        break;
                    default:
                        $status_variant = '';
                        break;
                }
            }

            $status_classes = ['ap-badge'];

            if ($status_variant !== '') {
                $status_classes[] = 'ap-badge--' . sanitize_html_class($status_variant);
            }

            if ($status !== '' && $status_variant !== $status) {
                $status_classes[] = 'ap-badge--' . sanitize_html_class($status);
            }

            $status_class_attr = implode(' ', array_unique(array_filter($status_classes)));

            $card_attributes = 'class="ap-dashboard-card ap-upgrade-widget__card"';

            if ($status !== '') {
                $card_attributes .= ' data-ap-upgrade-card="1" data-ap-upgrade-status="' . esc_attr($status) . '"';

                if ($status_label !== '') {
                    $card_attributes .= ' data-ap-upgrade-status-label="' . esc_attr($status_label) . '"';
                }

                if ($status_message !== '') {
                    $card_attributes .= ' data-ap-upgrade-status-message="' . esc_attr($status_message) . '"';
                }
            }

            if ($review_id > 0) {
                $card_attributes .= ' data-ap-upgrade-review="' . esc_attr((string) $review_id) . '"';
            }
            ?>
            <article <?php echo $card_attributes; ?>>
                <div class="ap-dashboard-card__body ap-upgrade-widget__card-body">
                    <div class="ap-upgrade-status" data-ap-upgrade-status="<?php echo esc_attr($status); ?>" aria-live="polite" tabindex="-1">
                        <?php if ($status_label !== '') : ?>
                            <strong class="<?php echo esc_attr($status_class_attr); ?>" data-ap-upgrade-badge><?php echo esc_html($status_label); ?></strong>
                        <?php endif; ?>

                        <?php if ($status_message !== '') : ?>
                            <p class="ap-upgrade-status__message" data-ap-upgrade-message><?php echo esc_html($status_message); ?></p>
                        <?php endif; ?>

                        <?php if ('denied' === $status && $denial_reason !== '') : ?>
                            <p class="ap-upgrade-status__reason" data-ap-upgrade-reason>
                                <strong class="ap-upgrade-status__reason-label"><?php esc_html_e('Reason:', 'artpulse-management'); ?></strong>
                                <span data-ap-upgrade-reason-text><?php echo esc_html($denial_reason); ?></span>
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($upgrade['title'])) : ?>
                        <h4 class="ap-upgrade-widget__card-title"><?php echo esc_html($upgrade['title']); ?></h4>
                    <?php endif; ?>

                    <?php if (!empty($upgrade['description'])) : ?>
                        <p class="ap-upgrade-widget__card-description"><?php echo esc_html($upgrade['description']); ?></p>
                    <?php endif; ?>

                </div>

                <div class="ap-dashboard-card__actions ap-upgrade-widget__card-actions" data-ap-upgrade-actions="1">
                    <a class="ap-dashboard-button ap-dashboard-button--primary ap-upgrade-widget__cta" href="<?php echo esc_url($url); ?>">
                        <?php echo esc_html($cta); ?>
                    </a>

                    <?php if ('denied' === $status && $review_id > 0) : ?>
                        <button type="button"
                                class="button button-secondary ap-upgrade-widget__reopen-button"
                                data-ap-upgrade-reopen
                                data-id="<?php echo esc_attr((string) $review_id); ?>">
                            <?php esc_html_e('Re-request review', 'artpulse-management'); ?>
                        </button>
                    <?php endif; ?>

                    <?php if (!empty($upgrade['secondary_actions']) && is_array($upgrade['secondary_actions'])) : ?>
                        <?php foreach ($upgrade['secondary_actions'] as $secondary) :
                            $secondary_url = $secondary['url'] ?? '';

                            if ($secondary_url === '') {
                                continue;
                            }

                            $secondary_label = $secondary['label'] ?? __('Learn more', 'artpulse-management');
                            ?>
                            <div class="ap-upgrade-widget__secondary-action">
                                <?php if (!empty($secondary['title'])) : ?>
                                    <h5 class="ap-upgrade-widget__secondary-title"><?php echo esc_html($secondary['title']); ?></h5>
                                <?php endif; ?>

                                <?php if (!empty($secondary['description'])) : ?>
                                    <p class="ap-upgrade-widget__secondary-description"><?php echo esc_html($secondary['description']); ?></p>
                                <?php endif; ?>

                                <a class="ap-dashboard-button ap-dashboard-button--secondary ap-upgrade-widget__cta ap-upgrade-widget__cta--secondary" href="<?php echo esc_url($secondary_url); ?>">
                                    <?php echo esc_html($secondary_label); ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>
