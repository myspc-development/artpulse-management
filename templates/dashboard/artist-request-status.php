<?php
/**
 * Template for the artist or organization request status help page.
 */

$journey = get_query_var('journey');
if (!is_string($journey) || $journey === '') {
    $journey = isset($_GET['journey']) ? sanitize_key(wp_unslash($_GET['journey'])) : 'artist';
} else {
    $journey = sanitize_key($journey);
}
if (!in_array($journey, ['artist', 'organization'], true)) {
    $journey = 'artist';
}

$heading = 'artist' === $journey
    ? __('Track your artist access request', 'artpulse-management')
    : __('Track your organization access request', 'artpulse-management');
$dashboard_base = \ArtPulse\Core\get_page_url('dashboard_page_id');
if (!$dashboard_base) {
    $dashboard_base = \ArtPulse\Core\get_missing_page_fallback('dashboard_page_id');
}

$dashboard_url = 'artist' === $journey
    ? \ArtPulse\Core\add_query_args($dashboard_base, ['role' => 'artist'])
    : \ArtPulse\Core\add_query_args($dashboard_base, ['role' => 'organization']);

get_header();
?>
<main class="ap-artist-request-status">
    <div class="ap-artist-request-status__wrap">
        <h1><?php echo esc_html($heading); ?></h1>
        <p><?php esc_html_e('We received your request and a moderator is reviewing the details. You will get an email update as soon as the review is complete.', 'artpulse-management'); ?></p>
        <p><?php esc_html_e('While you wait, you can keep an eye on the Next steps card in your dashboard. It shows the latest review status along with any moderator feedback that needs attention.', 'artpulse-management'); ?></p>
        <p><?php esc_html_e('Need to refresh your portfolio before approval? Reopen the builder, update the missing sections, and resubmit when you are ready.', 'artpulse-management'); ?></p>
        <a class="ap-dashboard-button ap-dashboard-button--secondary" href="<?php echo esc_url($dashboard_url); ?>">
            <?php esc_html_e('Return to dashboard', 'artpulse-management'); ?>
        </a>
    </div>
</main>
<?php
get_footer();
