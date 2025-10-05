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
                        <?php echo esc_html($upgrade['cta'] ?? __('Upgrade now', 'artpulse')); ?>
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>
