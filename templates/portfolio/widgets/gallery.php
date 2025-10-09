<?php
if (empty($portfolio_widget_scope['media']['gallery_ids'])) {
    return;
}

$gallery_ids = array_values(array_filter(array_map('intval', (array) $portfolio_widget_scope['media']['gallery_ids'])));
if (empty($gallery_ids)) {
    return;
}
?>
<section class="ap-portfolio-widget ap-portfolio-widget--gallery" id="ap-widget-gallery">
    <h2 class="ap-portfolio-widget__title"><?php esc_html_e('Gallery', 'artpulse-management'); ?></h2>
    <div class="ap-portfolio-gallery">
        <?php foreach ($gallery_ids as $attachment_id) : ?>
            <figure class="ap-portfolio-gallery__item">
                <?php echo wp_get_attachment_image($attachment_id, 'ap-grid', false, ['loading' => 'lazy']); ?>
            </figure>
        <?php endforeach; ?>
    </div>
</section>
