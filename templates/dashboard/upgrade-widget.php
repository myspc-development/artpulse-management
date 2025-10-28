<?php
/**
 * Upgrade widget template providing a reusable wrapper for upgrade sections.
 *
 * @var array       $widget_upgrades       List of available upgrades.
 * @var string      $widget_section_intro  Introductory copy for the upgrade section.
 * @var string|null $widget_section_title  Title for the upgrade section.
 * @var string      $widget_title          Optional title displayed above the section wrapper.
 * @var string      $widget_intro          Optional intro displayed above the section wrapper.
 */
?>
<div class="ap-upgrade-widget ap-upgrade-widget--standalone" data-ap-upgrade-widget="1">
    <?php if ($widget_title !== '') : ?>
        <h2 class="ap-upgrade-widget__title"><?php echo esc_html($widget_title); ?></h2>
    <?php endif; ?>

    <?php if ($widget_intro !== '') : ?>
        <p class="ap-upgrade-widget__intro"><?php echo esc_html($widget_intro); ?></p>
    <?php endif; ?>

    <?php echo \ArtPulse\Core\RoleDashboards::renderUpgradeWidgetSection(
        $widget_upgrades,
        $widget_section_intro,
        $widget_section_title
    ); ?>
</div>
