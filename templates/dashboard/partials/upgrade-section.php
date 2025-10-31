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
            $url = isset($upgrade['url']) ? (string) $upgrade['url'] : '';

            if ($url === '') {
                continue;
            }

            $cta = isset($upgrade['cta']) ? (string) $upgrade['cta'] : __('Upgrade now', 'artpulse-management');
            $slug = isset($upgrade['slug']) ? sanitize_key((string) $upgrade['slug']) : '';

            $review = isset($upgrade['review']) && is_array($upgrade['review']) ? $upgrade['review'] : [];

            $review_id = 0;
            if (isset($upgrade['review_id'])) {
                $review_id = (int) $upgrade['review_id'];
            } elseif (isset($upgrade['request_id'])) {
                $review_id = (int) $upgrade['request_id'];
            } elseif (isset($review['id'])) {
                $review_id = (int) $review['id'];
            } elseif (isset($review['request_id'])) {
                $review_id = (int) $review['request_id'];
            }

            $status_raw = '';
            if (isset($review['status'])) {
                $status_raw = (string) $review['status'];
            } elseif (isset($upgrade['status'])) {
                $status_raw = (string) $upgrade['status'];
            }

            $status = sanitize_key($status_raw);
            if (!in_array($status, ['pending', 'approved', 'denied'], true)) {
                $status = '';
            }

            $role_label = '';
            if (!empty($upgrade['role_label'])) {
                $role_label = (string) $upgrade['role_label'];
            } elseif (!empty($upgrade['role'])) {
                $role_label = (string) $upgrade['role'];
            } elseif ($slug === 'artist') {
                $role_label = __('Artist', 'artpulse-management');
            } elseif ($slug === 'organization') {
                $role_label = __('Organization', 'artpulse-management');
            }

            $status_label = '';
            $status_message = '';

            if ($status !== '') {
                if ('pending' === $status) {
                    $status_label = __('Pending', 'artpulse-management');
                    $status_message = __('Your upgrade request is pending review.', 'artpulse-management');
                } elseif ('approved' === $status) {
                    $status_label = __('Approved', 'artpulse-management');
                    if ($role_label !== '') {
                        $status_message = sprintf(
                            /* translators: %s: role name. */
                            __('Approved — you now have the %s role.', 'artpulse-management'),
                            $role_label
                        );
                    } else {
                        $status_message = __('Approved — you now have the upgraded role.', 'artpulse-management');
                    }
                } elseif ('denied' === $status) {
                    $status_label = __('Denied', 'artpulse-management');
                    $status_message = __('Denied.', 'artpulse-management');
                }
            }

            $denial_reason = '';
            if ('denied' === $status) {
                if (isset($upgrade['denial_reason'])) {
                    $denial_reason = (string) $upgrade['denial_reason'];
                } elseif (isset($upgrade['reason'])) {
                    $denial_reason = (string) $upgrade['reason'];
                } elseif (isset($review['reason'])) {
                    $denial_reason = (string) $review['reason'];
                }

                $denial_reason = trim(wp_strip_all_tags($denial_reason));
            }

            $badge_classes = ['ap-badge'];
            if ($status !== '') {
                $badge_classes[] = 'ap-badge--' . sanitize_html_class($status);
            }

            $primary_aria_label = __('View upgrade details', 'artpulse-management');
            if ('' !== $role_label) {
                $primary_aria_label = sprintf(
                    /* translators: %s is the upgrade role label. */
                    __('View details for the %s upgrade option', 'artpulse-management'),
                    $role_label
                );
            }

            $reopen_aria_label = __('Re-request upgrade review', 'artpulse-management');
            if ('' !== $role_label) {
                $reopen_aria_label = sprintf(
                    /* translators: %s is the upgrade role label. */
                    __('Re-request the %s upgrade review', 'artpulse-management'),
                    $role_label
                );
            }

            $card_attributes = [
                'class'                 => 'ap-dashboard-card ap-upgrade-widget__card',
                'data-ap-upgrade-card' => '1',
            ];

            if ($status !== '') {
                $card_attributes['data-ap-upgrade-status'] = $status;
                $card_attributes['data-ap-upgrade-status-label'] = $status_label;
                $card_attributes['data-ap-upgrade-status-message'] = $status_message;
            }

            if ($role_label !== '') {
                $card_attributes['data-ap-upgrade-role'] = $role_label;
            }

            if ($review_id > 0) {
                $card_attributes['data-ap-upgrade-review'] = (string) $review_id;
            }

            $attribute_parts = [];
            foreach ($card_attributes as $attribute_name => $attribute_value) {
                if ($attribute_value === '') {
                    continue;
                }

                $attribute_parts[] = sprintf(
                    '%s="%s"',
                    esc_attr($attribute_name),
                    esc_attr($attribute_value)
                );
            }

            ?>
            <article <?php echo implode(' ', $attribute_parts); ?>>
                <div class="ap-dashboard-card__body ap-upgrade-widget__card-body">
                    <div class="ap-upgrade-status" data-ap-upgrade-status aria-live="polite" aria-atomic="true" role="status" tabindex="-1">
                        <?php if ($status_label !== '') : ?>
                            <strong class="<?php echo esc_attr(implode(' ', array_filter($badge_classes))); ?>" data-ap-upgrade-badge><?php echo esc_html($status_label); ?></strong>
                        <?php endif; ?>

                        <?php if ($status_message !== '') : ?>
                            <p class="ap-upgrade-status__message" data-ap-upgrade-message><?php echo esc_html($status_message); ?></p>
                        <?php endif; ?>

                        <?php if ('denied' === $status && $denial_reason !== '') : ?>
                            <p class="ap-upgrade-status__reason" data-ap-upgrade-reason>
                                <span data-ap-upgrade-reason-text><?php echo esc_html($denial_reason); ?></span>
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($upgrade['title'])) : ?>
                        <h4 class="ap-upgrade-widget__card-title"><?php echo esc_html((string) $upgrade['title']); ?></h4>
                    <?php endif; ?>

                    <?php if (!empty($upgrade['description'])) : ?>
                        <p class="ap-upgrade-widget__card-description"><?php echo esc_html((string) $upgrade['description']); ?></p>
                    <?php endif; ?>

                </div>

                <div class="ap-dashboard-card__actions ap-upgrade-widget__card-actions" data-ap-upgrade-actions="1">
                    <a class="ap-dashboard-button ap-dashboard-button--primary ap-upgrade-widget__cta" href="<?php echo esc_url($url); ?>" aria-label="<?php echo esc_attr($primary_aria_label); ?>">
                        <?php echo esc_html($cta); ?>
                    </a>

                    <?php if ('denied' === $status && $review_id > 0) : ?>
                        <button type="button"
                                class="button button-secondary ap-upgrade-widget__reopen-button"
                                data-ap-upgrade-reopen
                                data-id="<?php echo esc_attr((string) $review_id); ?>"
                                aria-label="<?php echo esc_attr($reopen_aria_label); ?>">
                            <?php esc_html_e('Re-request review', 'artpulse-management'); ?>
                        </button>
                    <?php endif; ?>

                    <?php if (!empty($upgrade['secondary_actions']) && is_array($upgrade['secondary_actions'])) : ?>
                        <?php foreach ($upgrade['secondary_actions'] as $secondary) :
                            $secondary_url = isset($secondary['url']) ? (string) $secondary['url'] : '';

                            if ($secondary_url === '') {
                                continue;
                            }

                            $secondary_label = isset($secondary['label']) ? (string) $secondary['label'] : __('Learn more', 'artpulse-management');
                            $secondary_aria_label = $role_label !== ''
                                ? sprintf(
                                    /* translators: %s is the upgrade role label. */
                                    __('Learn more about the %s upgrade', 'artpulse-management'),
                                    $role_label
                                )
                                : sprintf(
                                    /* translators: %s is the secondary action label. */
                                    __('Learn more: %s', 'artpulse-management'),
                                    $secondary_label
                                );
                            ?>
                            <div class="ap-upgrade-widget__secondary-action">
                                <?php if (!empty($secondary['title'])) : ?>
                                    <h5 class="ap-upgrade-widget__secondary-title"><?php echo esc_html((string) $secondary['title']); ?></h5>
                                <?php endif; ?>

                                <?php if (!empty($secondary['description'])) : ?>
                                    <p class="ap-upgrade-widget__secondary-description"><?php echo esc_html((string) $secondary['description']); ?></p>
                                <?php endif; ?>

                                <a class="ap-dashboard-button ap-dashboard-button--secondary ap-upgrade-widget__cta ap-upgrade-widget__cta--secondary" href="<?php echo esc_url($secondary_url); ?>" aria-label="<?php echo esc_attr($secondary_aria_label); ?>">
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
