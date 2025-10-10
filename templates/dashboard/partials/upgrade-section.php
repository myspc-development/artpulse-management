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

            $cta = $upgrade['cta'] ?? __('Upgrade now', 'artpulse');
            ?>
            <article class="ap-dashboard-card ap-upgrade-widget__card">
                <div class="ap-dashboard-card__body ap-upgrade-widget__card-body">
                    <?php if (!empty($upgrade['title'])) : ?>
                        <h4 class="ap-upgrade-widget__card-title"><?php echo esc_html($upgrade['title']); ?></h4>
                    <?php endif; ?>

                    <?php if (!empty($upgrade['description'])) : ?>
                        <p class="ap-upgrade-widget__card-description"><?php echo esc_html($upgrade['description']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="ap-dashboard-card__actions ap-upgrade-widget__card-actions">
                    <a class="ap-dashboard-button ap-dashboard-button--primary ap-upgrade-widget__cta" href="<?php echo esc_url($url); ?>">
                        <?php echo esc_html($cta); ?>
                    </a>

                    <?php if (!empty($upgrade['secondary_actions']) && is_array($upgrade['secondary_actions'])) : ?>
                        <?php foreach ($upgrade['secondary_actions'] as $secondary) :
                            $secondary_url = $secondary['url'] ?? '';

                            if ($secondary_url === '') {
                                continue;
                            }

                            $secondary_label = $secondary['label'] ?? __('Learn more', 'artpulse');
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
