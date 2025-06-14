<?php
get_header();
?>
<div class="ead-confirmation">
    <h1><?php _e('Thank You!', 'artpulse-management'); ?></h1>
    <p><?php _e('Your event has been submitted and is pending review.', 'artpulse-management'); ?></p>
    <a href="<?php echo esc_url(home_url('/')); ?>" class="button">
        <?php _e('Return to Home', 'artpulse-management'); ?>
    </a>
</div>
<?php
get_footer();
?>
