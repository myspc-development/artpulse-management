<?php
if (!isset($portfolio_widget_scope['meta'])) {
    return;
}

$about = $portfolio_widget_scope['meta']['about'] ?? '';
if ('' === trim((string) $about)) {
    return;
}
?>
<section class="ap-portfolio-widget ap-portfolio-widget--about" id="ap-widget-about">
    <h2 class="ap-portfolio-widget__title"><?php esc_html_e('About', 'artpulse-management'); ?></h2>
    <div class="ap-portfolio-widget__content">
        <?php echo wp_kses_post(wpautop($about)); ?>
    </div>
</section>
