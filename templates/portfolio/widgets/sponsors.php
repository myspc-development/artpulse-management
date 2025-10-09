<?php
if (!isset($portfolio_widget_scope['post_id'])) {
    return;
}

$post_id = (int) $portfolio_widget_scope['post_id'];
$sponsor_ids = get_post_meta($post_id, '_ap_sponsor_ids', true);

if (!is_array($sponsor_ids) || empty($sponsor_ids)) {
    return;
}

$sponsor_ids = array_values(array_filter(array_map('intval', $sponsor_ids)));
if (empty($sponsor_ids)) {
    return;
}
?>
<section class="ap-portfolio-widget ap-portfolio-widget--sponsors" id="ap-widget-sponsors">
    <h2 class="ap-portfolio-widget__title"><?php esc_html_e('Sponsors', 'artpulse-management'); ?></h2>
    <div class="ap-portfolio-sponsors">
        <?php foreach ($sponsor_ids as $attachment_id) : ?>
            <figure class="ap-portfolio-sponsors__item">
                <?php echo wp_get_attachment_image($attachment_id, 'medium', false, ['loading' => 'lazy']); ?>
            </figure>
        <?php endforeach; ?>
    </div>
</section>
