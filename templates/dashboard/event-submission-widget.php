<?php
/**
 * Dashboard event submission widget template.
 *
 * @var string $submission_url URL for submitting a new event.
 */

if (empty($submission_url)) {
    echo '<p>' . esc_html__('Event submissions are currently unavailable.', 'artpulse-management') . '</p>';

    return;
}
?>
<div class="ap-dashboard-widget ap-dashboard-widget--event-submission">
    <div class="ap-dashboard-widget__section ap-dashboard-widget__section--event-submission">
        <h3 class="ap-dashboard-event-widget__title"><?php esc_html_e('Share a New Event', 'artpulse-management'); ?></h3>
        <p class="ap-dashboard-event-widget__description">
            <?php esc_html_e('Bring the community together by sharing details about your upcoming event.', 'artpulse-management'); ?>
        </p>
        <a class="ap-dashboard-button ap-dashboard-button--primary" href="<?php echo esc_url($submission_url); ?>">
            <?php esc_html_e('Submit Event', 'artpulse-management'); ?>
        </a>
    </div>
</div>
