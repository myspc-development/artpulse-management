<?php
/**
 * Upgrade section template shared across dashboard widgets.
 *
 * @var string $section_title    Title for the upgrade section.
 * @var string $section_intro    Introductory copy for the upgrade section.
 * @var array  $section_upgrades List of available upgrades.
 */
?>
<div class="ap-dashboard-widget__section ap-dashboard-widget__section--upgrades">
    <h3><?php echo esc_html($section_title); ?></h3>

    <?php if ($section_intro !== '') : ?>
        <p class="ap-dashboard-widget__upgrade-intro"><?php echo esc_html($section_intro); ?></p>
    <?php endif; ?>

    <div class="ap-dashboard-widget__upgrades">
        <?php foreach ($section_upgrades as $upgrade) :
            $url = $upgrade['url'] ?? '';

            if ($url === '') {
                continue;
            }
            ?>
            <div class="ap-dashboard-widget__upgrade-card">
                <?php if (!empty($upgrade['title'])) : ?>
                    <h4 class="ap-dashboard-widget__upgrade-title"><?php echo esc_html($upgrade['title']); ?></h4>
                <?php endif; ?>

                <?php if (!empty($upgrade['description'])) : ?>
                    <p class="ap-dashboard-widget__upgrade-description"><?php echo esc_html($upgrade['description']); ?></p>
                <?php endif; ?>

                <a class="ap-dashboard-button ap-dashboard-button--primary" href="<?php echo esc_url($url); ?>">
                    <?php echo esc_html($upgrade['cta'] ?? __('Upgrade now', 'artpulse')); ?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
