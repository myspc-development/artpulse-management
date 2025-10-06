<?php
/**
 * Calendar popover template for FullCalendar custom rendering.
 *
 * @var array $event
 */
?>
<div class="ap-events-popover" role="dialog" aria-label="<?php echo esc_attr($event['title'] ?? ''); ?>">
    <h3 class="ap-events-popover__title"><?php echo esc_html($event['title'] ?? ''); ?></h3>
    <?php if (!empty($event['date'])) : ?>
        <p class="ap-events-popover__date"><?php echo esc_html($event['date']); ?></p>
    <?php endif; ?>
    <?php if (!empty($event['location'])) : ?>
        <p class="ap-events-popover__location"><?php echo esc_html($event['location']); ?></p>
    <?php endif; ?>
    <?php if (!empty($event['cost'])) : ?>
        <p class="ap-events-popover__cost"><?php echo esc_html($event['cost']); ?></p>
    <?php endif; ?>
    <p class="ap-events-popover__actions">
        <a href="<?php echo esc_url($event['url'] ?? '#'); ?>" class="ap-events-popover__link"><?php esc_html_e('View event', 'artpulse-management'); ?></a>
        <?php if (!empty($event['ics'])) : ?>
            <a href="<?php echo esc_url($event['ics']); ?>" class="ap-events-popover__ics"><?php esc_html_e('Add to calendar', 'artpulse-management'); ?></a>
        <?php endif; ?>
    </p>
</div>
